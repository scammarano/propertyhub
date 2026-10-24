<?php
declare(strict_types=1);

/**
 * Disaster recovery for Property Hub Central when the database and .env survive
 * but storage/installed.lock and repository signing keys were lost.
 *
 * It NEVER installs a new Central and NEVER recreates the database. It validates
 * the existing Central identity and an existing local admin, rebuilds runtime
 * storage, recreates installed.lock and rotates repository signing keys only when
 * the old keypair is missing.
 */
final class CentralRecoveryService
{
    public function __construct(private string $root) {}

    /** @return array<string,mixed> */
    public function inspect(): array
    {
        if (!is_file($this->root.'/.env')) {
            return ['candidate'=>false,'reason'=>'No existe .env.'];
        }
        if (is_file($this->root.'/storage/installed.lock')) {
            return ['candidate'=>false,'reason'=>'La instalación ya tiene installed.lock.'];
        }

        $role=strtolower(trim((string)env('INSTANCE_ROLE','')));
        if (!in_array($role,['central_repository','central'],true)) {
            return ['candidate'=>false,'reason'=>'El .env no corresponde a Property Hub Central.'];
        }

        try {
            $pdo=$this->pdo();
            $uuid=trim((string)env('INSTANCE_UUID',''));
            $code=strtoupper(trim((string)env('INSTANCE_CODE','')));
            $row=$this->findCentral($pdo,$uuid,$code);
            if(!$row) return ['candidate'=>false,'reason'=>'La BD existe, pero no se encontró la identidad Central registrada.'];

            return [
                'candidate'=>true,
                'installation_id'=>(int)$row['id'],
                'installation_uuid'=>(string)$row['public_uuid'],
                'installation_code'=>(string)$row['code'],
                'installation_name'=>(string)$row['name'],
                'database'=>(string)env('DB_DATABASE',''),
                'repository_public_url'=>(string)env('REPOSITORY_PUBLIC_URL',(string)env('APP_URL','')),
                'keys_present'=>$this->keysPresent(),
                'registered_installations'=>$this->countTable($pdo,'bh_repo_installations'),
                'published_releases'=>$this->countTable($pdo,'bh_repo_releases','status=\'published\''),
                'local_users'=>$this->countTable($pdo,'bh_local_users'),
            ];
        } catch(Throwable $e) {
            return ['candidate'=>false,'reason'=>'No se pudo validar la BD existente: '.$e->getMessage()];
        }
    }

    /** @return array<string,mixed> */
    public function recover(string $identifier,string $password): array
    {
        $info=$this->inspect();
        if(empty($info['candidate'])) throw new RuntimeException((string)($info['reason']??'No se detectó una Central recuperable.'));
        if(trim($identifier)===''||$password==='') throw new InvalidArgumentException('Indique el usuario/correo y contraseña de un administrador existente de Central.');

        $pdo=$this->pdo();
        $installationId=(int)$info['installation_id'];
        $this->verifyAdmin($pdo,$installationId,$identifier,$password);
        $this->ensureRuntimeDirectories();

        // Keep the surviving .env as the source of server/DB/application secrets.
        $envChanges=[
            'INSTANCE_ROLE'=>'central_repository',
            'REPOSITORY_ENABLED'=>'true',
            'REPOSITORY_PRIVATE_KEY_PATH'=>'storage/keys/repository-private.pem',
            'REPOSITORY_PUBLIC_KEY_PATH'=>'storage/keys/repository-public.pem',
        ];
        $publicUrl=trim((string)env('REPOSITORY_PUBLIC_URL',''));
        if($publicUrl==='') $envChanges['REPOSITORY_PUBLIC_URL']=rtrim((string)env('APP_URL',''),'/');
        EnvironmentEditor::setFile($this->root.'/.env',$envChanges);
        foreach($envChanges as $k=>$v){putenv($k.'='.$v);$_ENV[$k]=$v;}

        $rotation=false;
        if(!$this->keysPresent()) {
            $this->generateRepositoryKeys();
            $rotation=true;
        }
        $public=(string)@file_get_contents($this->root.'/storage/keys/repository-public.pem');
        if(trim($public)==='') throw new RuntimeException('No fue posible recuperar/generar la clave pública del repositorio.');

        $versionData=json_decode((string)@file_get_contents($this->root.'/VERSION.json'),true);
        $version=is_array($versionData)?trim((string)($versionData['version']??'')):'';
        if($version==='')$version='0.14.60';
        $lock=[
            'installed_at'=>date(DATE_ATOM),
            'recovered_at'=>date(DATE_ATOM),
            'recovery_mode'=>'existing_central_database',
            'installer_version'=>$version,
            'instance_role'=>'central_repository',
            'installation_uuid'=>(string)$info['installation_uuid'],
            'installation_code'=>(string)$info['installation_code'],
            'database'=>(string)$info['database'],
            'app_version'=>$version,
            'ui_version'=>'1.2.0',
            'repository_key_rotated'=>$rotation,
        ];
        if(file_put_contents($this->root.'/storage/installed.lock',json_encode($lock,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),LOCK_EX)===false){
            throw new RuntimeException('No fue posible reconstruir storage/installed.lock.');
        }
        @chmod($this->root.'/storage/installed.lock',0600);

        // Reconcile local metadata to the code that was just restored. No data is recreated.
        try{
            $pdo->prepare('UPDATE bh_installations SET app_version=?,updated_at=NOW() WHERE id=?')->execute([$version,$installationId]);
            $pdo->prepare("UPDATE bh_installed_components SET installed_version=?,updated_at=NOW() WHERE installation_id=? AND component_code IN ('property-hub-core','property-hub-central-console')")->execute([$version,$installationId]);
        }catch(Throwable $e){/* non-fatal for old schemas */}

        $sync=null;$syncError=null;
        try{$sync=(new RepositorySynchronizer($pdo))->syncOfficialCore();}
        catch(Throwable $e){$syncError=$e->getMessage();}

        $fingerprint=strtoupper(implode(':',str_split(hash('sha256',$public),2)));
        $this->auditRecovery($pdo,$installationId,$rotation,$fingerprint,$syncError);
        return $info+[
            'ok'=>true,
            'version'=>$version,
            'keys_rotated'=>$rotation,
            'public_key_fingerprint'=>$fingerprint,
            'repository_sync'=>$sync,
            'repository_sync_error'=>$syncError,
        ];
    }

    private function pdo(): PDO
    {
        $host=trim((string)env('DB_HOST','127.0.0.1'));
        $port=(int)env('DB_PORT',3306);
        $db=trim((string)env('DB_DATABASE',''));
        $user=(string)env('DB_USERNAME','');
        $pass=(string)env('DB_PASSWORD','');
        if($db===''||$user==='')throw new RuntimeException('El .env no contiene DB_DATABASE/DB_USERNAME válidos.');
        return new PDO("mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4",$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false,PDO::MYSQL_ATTR_USE_BUFFERED_QUERY=>true]);
    }

    private function findCentral(PDO $pdo,string $uuid,string $code): ?array
    {
        $sql="SELECT i.*,d.instance_role,d.installation_uuid AS deployment_uuid FROM bh_deployment_settings d JOIN bh_installations i ON i.id=d.installation_id WHERE d.instance_role='central_repository'";
        if($uuid!==''){$s=$pdo->prepare($sql.' AND (d.installation_uuid=? OR i.public_uuid=?) LIMIT 1');$s->execute([$uuid,$uuid]);$r=$s->fetch();if(is_array($r))return $r;}
        if($code!==''){$s=$pdo->prepare($sql.' AND i.code=? LIMIT 1');$s->execute([$code]);$r=$s->fetch();if(is_array($r))return $r;}
        $s=$pdo->query($sql.' ORDER BY i.id ASC LIMIT 1');$r=$s->fetch();return is_array($r)?$r:null;
    }

    private function verifyAdmin(PDO $pdo,int $installationId,string $identifier,string $password): void
    {
        $s=$pdo->prepare("SELECT username,email,password_hash,user_type,status FROM bh_local_users WHERE installation_id=? AND status='active' AND user_type IN ('superadmin','admin') AND (LOWER(email)=LOWER(?) OR LOWER(username)=LOWER(?)) LIMIT 1");
        $s->execute([$installationId,trim($identifier),trim($identifier)]);$user=$s->fetch();
        if(!is_array($user)||trim((string)($user['password_hash']??''))===''||!password_verify($password,(string)$user['password_hash'])){
            throw new RuntimeException('Las credenciales no corresponden a un administrador activo de esta Central.');
        }
    }

    private function ensureRuntimeDirectories(): void
    {
        foreach(['storage/keys','storage/repository/packages','storage/repository/manifests','storage/repository/signatures','storage/backups','storage/backups/tmp','storage/backups/incoming','storage/backups/agents','storage/updates/downloads','storage/updates/staging','storage/updates/rollback','storage/logs','storage/uploads','storage/tmp','storage/cache','storage/sessions'] as $relative){
            $dir=$this->root.'/'.$relative;
            if(!is_dir($dir)&&!@mkdir($dir,0775,true)&&!is_dir($dir))throw new RuntimeException('No se pudo crear '.$relative.'.');
            if(!is_writable($dir))throw new RuntimeException($relative.' no tiene permisos de escritura.');
        }
        $ht=$this->root.'/storage/.htaccess';if(!is_file($ht))@file_put_contents($ht,"Deny from all\n");
    }

    private function keysPresent(): bool
    {
        return is_file($this->root.'/storage/keys/repository-private.pem')&&is_file($this->root.'/storage/keys/repository-public.pem')&&filesize($this->root.'/storage/keys/repository-private.pem')>100&&filesize($this->root.'/storage/keys/repository-public.pem')>100;
    }

    private function generateRepositoryKeys(): void
    {
        $resource=openssl_pkey_new(['private_key_bits'=>3072,'private_key_type'=>OPENSSL_KEYTYPE_RSA]);
        if($resource===false)throw new RuntimeException('OpenSSL no pudo generar la nueva clave RSA del repositorio.');
        if(!openssl_pkey_export($resource,$private))throw new RuntimeException('No fue posible exportar la nueva clave privada del repositorio.');
        $details=openssl_pkey_get_details($resource);
        if(!is_array($details)||empty($details['key']))throw new RuntimeException('No fue posible obtener la nueva clave pública del repositorio.');
        if(file_put_contents($this->root.'/storage/keys/repository-private.pem',$private,LOCK_EX)===false||file_put_contents($this->root.'/storage/keys/repository-public.pem',(string)$details['key'],LOCK_EX)===false)throw new RuntimeException('No fue posible guardar el nuevo par de claves del repositorio.');
        @chmod($this->root.'/storage/keys/repository-private.pem',0600);@chmod($this->root.'/storage/keys/repository-public.pem',0644);
    }

    private function countTable(PDO $pdo,string $table,string $where='1=1'): int
    {
        if(!preg_match('/^[a-z0-9_]+$/i',$table))return 0;
        try{return (int)$pdo->query('SELECT COUNT(*) FROM '.$table.' WHERE '.$where)->fetchColumn();}catch(Throwable){return 0;}
    }

    private function auditRecovery(PDO $pdo,int $installationId,bool $rotation,string $fingerprint,?string $syncError): void
    {
        try{
            $pdo->prepare("INSERT INTO bh_audit_logs (installation_id,actor_local_user_id,action,entity_type,entity_id,result,reason,ip_address,user_agent,payload_json) VALUES (?,NULL,'central_recovery','system',NULL,'success',?,?,?,?)")
                ->execute([$installationId,'Central recuperada desde .env + BD existente',(string)($_SERVER['REMOTE_ADDR']??''),substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,500),json_encode(['repository_key_rotated'=>$rotation,'new_public_key_sha256'=>$fingerprint,'repository_sync_error'=>$syncError],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]);
        }catch(Throwable){/* optional audit schema */}
    }
}
