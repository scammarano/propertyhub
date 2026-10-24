<?php
declare(strict_types=1);

/**
 * Registro persistente de topología y credenciales operativas.
 *
 * .env queda como compatibilidad/bootstrap. Una vez existe 0022, las URLs y
 * credenciales Node/Central usadas en operación se resuelven desde BD.
 */
final class ConnectionRegistry
{
    public function __construct(private ?PDO $pdo=null) { $this->pdo ??= Connection::pdo(); }

    public function available(): bool
    {
        try{
            $stmt=$this->pdo->prepare("SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='bh_remote_connections' LIMIT 1");
            $stmt->execute();
            return $stmt->fetchColumn()!==false;
        }catch(Throwable){ return false; }
    }

    public function get(string $type): array
    {
        $type=$this->type($type);
        if($this->available()){
            $stmt=$this->pdo->prepare('SELECT * FROM bh_remote_connections WHERE installation_id=? AND connection_type=? LIMIT 1');
            $stmt->execute([$this->installationId(),$type]);
            $row=$stmt->fetch();
            if(is_array($row)){
                $secret=EdgeSecretStore::decrypt((string)($row['credential_encrypted']??''));
                return $row+['credential'=>(string)($secret['credential']??'')];
            }
        }
        return $this->legacy($type);
    }

    public function central(): array { return $this->get('central'); }
    public function parentNode(): array { return $this->get('parent_node'); }

    public function save(string $type,string $endpoint,string $credential='',string $remoteCode='',string $provisionedBy='self',string $status='configured',array $metadata=[]): array
    {
        $type=$this->type($type);$endpoint=$this->normalize($endpoint);
        if($endpoint==='')throw new InvalidArgumentException('El endpoint remoto no puede estar vacío.');
        if(!$this->available())return ['connection_type'=>$type,'endpoint_url'=>$endpoint,'credential'=>$credential,'remote_code'=>$remoteCode,'status'=>$status,'legacy'=>true];
        $allowedProvision=['installer','self','property_node','legacy_env','manual_recovery'];
        if(!in_array($provisionedBy,$allowedProvision,true))$provisionedBy='self';
        $allowedStatus=['configured','online','offline','error','disabled'];if(!in_array($status,$allowedStatus,true))$status='configured';
        $existing=$this->getRaw($type);$storedCredential=$credential;
        if($storedCredential===''&&is_array($existing)){
            $old=EdgeSecretStore::decrypt((string)($existing['credential_encrypted']??''));$storedCredential=(string)($old['credential']??'');
        }
        $encrypted=$storedCredential!==''?EdgeSecretStore::encrypt(['credential'=>$storedCredential]):null;
        $sql="INSERT INTO bh_remote_connections (installation_id,connection_type,remote_code,endpoint_url,credential_encrypted,status,provisioned_by,last_ok_at,last_error,metadata_json) VALUES (?,?,?,?,?,?,?,?,?,?) "
            ."ON DUPLICATE KEY UPDATE remote_code=VALUES(remote_code),endpoint_url=VALUES(endpoint_url),credential_encrypted=COALESCE(VALUES(credential_encrypted),credential_encrypted),status=VALUES(status),provisioned_by=VALUES(provisioned_by),last_ok_at=VALUES(last_ok_at),last_error=VALUES(last_error),metadata_json=VALUES(metadata_json)";
        $this->pdo->prepare($sql)->execute([
            $this->installationId(),$type,$remoteCode!==''?$remoteCode:null,$endpoint,$encrypted,$status,$provisionedBy,
            $status==='online'?date('Y-m-d H:i:s'):null,null,json_encode($metadata,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),
        ]);
        return $this->get($type);
    }

    public function retireDirectCentralForEdge(string $reason='distributed_via_property_node'): void
    {
        if(!InstallationMode::isEdge()||!$this->available())return;
        try{
            $meta=['retired_at'=>date(DATE_ATOM),'reason'=>$reason,'distribution_chain'=>'central>node>edge'];
            $this->pdo->prepare("UPDATE bh_remote_connections SET credential_encrypted=NULL,status='disabled',last_error=NULL,metadata_json=?,updated_at=NOW() WHERE installation_id=? AND connection_type='central'")
                ->execute([json_encode($meta,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),$this->installationId()]);
            $this->pdo->prepare("UPDATE bh_deployment_settings SET repository_url=NULL,repository_status='registered',repository_installation_key_hash=NULL,last_repository_check_at=NOW() WHERE installation_uuid=?")
                ->execute([(string)config('deployment.installation_uuid','')]);
        }catch(Throwable $e){ErrorReporter::report($e,['operation'=>'edge_retire_direct_central']);}
    }

    public function mark(string $type,bool $ok,string $error=''): void
    {
        if(!$this->available())return;$type=$this->type($type);
        $this->pdo->prepare("UPDATE bh_remote_connections SET status=?,last_ok_at=CASE WHEN ?=1 THEN NOW() ELSE last_ok_at END,last_error=NULLIF(?,''),updated_at=NOW() WHERE installation_id=? AND connection_type=?")
            ->execute([$ok?'online':'error',$ok?1:0,$error,$this->installationId(),$type]);
    }

    /** Importa valores históricos solo cuando todavía no hay registro en BD. */
    public function importLegacyIfNeeded(): void
    {
        if(!$this->available())return;
        if(!$this->getRaw('central')){
            $url=trim((string)config('deployment.central_repository_url',''));$key=trim((string)config('deployment.installation_api_key',''));
            if($url!=='')$this->save('central',$url,$key,'','legacy_env','configured',['imported_from'=>'.env']);
        }
        if(InstallationMode::isEdge()&&!$this->getRaw('parent_node')){
            $url=trim((string)config('deployment.property_node_url',''));$key=trim((string)config('deployment.property_node_agent_key',''));$code=trim((string)config('deployment.parent_property_code',''));
            if($url!=='')$this->save('parent_node',$url,$key,$code,'legacy_env','configured',['imported_from'=>'.env']);
        }
    }

    private function legacy(string $type): array
    {
        if($type==='central')return ['connection_type'=>'central','endpoint_url'=>(string)config('deployment.central_repository_url',''),'credential'=>(string)config('deployment.installation_api_key',''),'remote_code'=>'','status'=>'configured','legacy'=>true];
        return ['connection_type'=>'parent_node','endpoint_url'=>(string)config('deployment.property_node_url',''),'credential'=>(string)config('deployment.property_node_agent_key',''),'remote_code'=>(string)config('deployment.parent_property_code',''),'status'=>'configured','legacy'=>true];
    }

    private function getRaw(string $type): ?array
    {
        if(!$this->available())return null;$stmt=$this->pdo->prepare('SELECT * FROM bh_remote_connections WHERE installation_id=? AND connection_type=? LIMIT 1');$stmt->execute([$this->installationId(),$this->type($type)]);$row=$stmt->fetch();return is_array($row)?$row:null;
    }

    private function installationId(): int
    {
        $uuid=(string)config('deployment.installation_uuid','');$stmt=$this->pdo->prepare('SELECT id FROM bh_installations WHERE public_uuid=? LIMIT 1');$stmt->execute([$uuid]);$id=(int)$stmt->fetchColumn();
        if($id<1){$stmt=$this->pdo->prepare('SELECT id FROM bh_installations WHERE code=? LIMIT 1');$stmt->execute([(string)config('app.instance_code','')]);$id=(int)$stmt->fetchColumn();}
        if($id<1)throw new RuntimeException('No se encontró la instalación local para registrar conexiones.');return $id;
    }

    private function type(string $type): string { if(!in_array($type,['central','parent_node'],true))throw new InvalidArgumentException('Tipo de conexión no válido.');return $type; }
    private function normalize(string $url): string { $url=trim($url);if($url==='')return '';return rtrim($url,'/'); }
}
