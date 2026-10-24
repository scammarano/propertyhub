<?php
declare(strict_types=1);

/**
 * Canonical identity/lifecycle service for repository components.
 *
 * component_code identifies the product permanently. Releases/versions point to
 * the component row; connector aliases (for example `vimar`) are explicit and no
 * longer depend on string heuristics such as stripping `connector-` at runtime.
 */
final class ComponentRegistryService
{
    private const TYPES=['core','ui','module','connector','api','bridge','video_gateway','language','report','other'];
    private const CHANNELS=['development','beta','stable','pinned'];

    public function __construct(private ?PDO $pdo=null)
    {
        $this->pdo ??= Connection::pdo();
    }

    public function available(): bool
    {
        try {
            $stmt=$this->pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='bh_repo_components'");
            return (int)$stmt->fetchColumn()>0;
        } catch (Throwable) {
            return false;
        }
    }

    /** @return array<string,mixed> */
    public function ensureFromPackage(array $meta): array
    {
        if(!$this->available())throw new RuntimeException('El registro maestro de componentes todavía no está instalado. Ejecute las migraciones de Central.');
        $code=self::normalizeCode((string)($meta['component_code']??''));
        $type=strtolower(trim((string)($meta['component_type']??'other')));
        $display=trim((string)($meta['display_name']??$code));
        if($code===''||!preg_match('/^[a-z0-9][a-z0-9._-]{1,119}$/',$code))throw new RuntimeException('update-package.json contiene un component_code inválido.');
        if(!in_array($type,self::TYPES,true))throw new RuntimeException('update-package.json contiene un component_type no soportado: '.$type.'.');
        if($display==='')$display=$code;
        $connectorCode=$type==='connector'?DesiredConnectorPolicy::canonical((string)($meta['connector_code']??self::derivedConnectorCode($code))):'';
        if($connectorCode!==''&&!preg_match('/^[a-z0-9_-]{2,80}$/',$connectorCode))throw new RuntimeException('El connector_code canónico del paquete no es válido.');
        $vendor=trim((string)($meta['vendor_code']??''));

        $existing=$this->byCode($code);
        if(!is_array($existing)){
            $aliasOwner=$this->resolve($code);
            if(is_array($aliasOwner))throw new RuntimeException('El paquete usa '.$code.' como component_code, pero ese valor ya es un alias de '.(string)$aliasOwner['component_code'].'. Use siempre el component_code canónico del registro maestro.');
            if($connectorCode!==''){
                $connectorOwner=$this->resolve($connectorCode);
                if(is_array($connectorOwner))throw new RuntimeException('El connector_code '.$connectorCode.' ya pertenece al componente '.(string)$connectorOwner['component_code'].'. No se creará una segunda identidad para la misma integración.');
            }
        }
        if(is_array($existing)){
            if((string)$existing['component_type']!==$type)throw new RuntimeException('El componente '.$code.' ya existe como '.$existing['component_type'].' y el paquete intenta cambiarlo a '.$type.'. La identidad de un componente es inmutable.');
            $existingConnector=DesiredConnectorPolicy::canonical((string)($existing['connector_code']??''));
            if($existingConnector!==''&&$connectorCode!==''&&!hash_equals($existingConnector,$connectorCode))throw new RuntimeException('El componente '.$code.' ya está vinculado al connector_code '.$existingConnector.' y el paquete intenta cambiarlo a '.$connectorCode.'. Ese vínculo forma parte de su identidad y no puede mutarse.');
            $this->pdo->prepare('UPDATE bh_repo_components SET display_name=?,connector_code=COALESCE(NULLIF(?,\'\'),connector_code),vendor_code=COALESCE(NULLIF(?,\'\'),vendor_code),lifecycle_status=\'active\',updated_at=NOW() WHERE id=?')
                ->execute([$display,$connectorCode,$vendor,(int)$existing['id']]);
            $id=(int)$existing['id'];
        }else{
            $stmt=$this->pdo->prepare("INSERT INTO bh_repo_components (component_code,component_type,display_name,connector_code,vendor_code,lifecycle_status,metadata_json) VALUES (?,?,?,?,?,'active',?)");
            $stmt->execute([$code,$type,$display,$connectorCode?:null,$vendor?:null,json_encode(['source'=>'package_manifest'],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]);
            $id=(int)$this->pdo->lastInsertId();
        }
        $this->ensureAlias($id,$code,'component_code');
        if($connectorCode!=='')$this->ensureAlias($id,$connectorCode,'connector_code');
        $row=$this->byId($id);
        if(!is_array($row))throw new RuntimeException('No fue posible materializar el componente '.$code.' en el registro maestro.');
        return $row;
    }

    /** Reconciles connector definitions into stable repository component identities. */
    public function reconcileConnectorCatalog(): int
    {
        if(!$this->available())return 0;
        $count=0;
        try{
            foreach($this->pdo->query('SELECT code,name,vendor,version,status FROM bh_connector_definitions ORDER BY id')->fetchAll()?:[] as $def){
                $connectorCode=DesiredConnectorPolicy::canonical((string)$def['code']);
                if($connectorCode==='')continue;
                $componentCode='connector-'.$connectorCode;
                $existing=$this->byCode($componentCode);
                if(is_array($existing)){
                    if((string)$existing['component_type']!=='connector')throw new RuntimeException('El catálogo '.$connectorCode.' colisiona con un componente no-connector: '.$componentCode.'.');
                    $bound=DesiredConnectorPolicy::canonical((string)($existing['connector_code']??''));
                    if($bound!==''&&!hash_equals($bound,$connectorCode))throw new RuntimeException('El componente '.$componentCode.' ya está vinculado al connector_code '.$bound.'.');
                    if($bound==='')$this->pdo->prepare('UPDATE bh_repo_components SET connector_code=?,updated_at=NOW() WHERE id=?')->execute([$connectorCode,(int)$existing['id']]);
                    $this->ensureAlias((int)$existing['id'],$componentCode,'component_code');$this->ensureAlias((int)$existing['id'],$connectorCode,'connector_code');
                }else{
                    $meta=[
                        'component_code'=>$componentCode,'component_type'=>'connector','display_name'=>(string)$def['name'],
                        'connector_code'=>$connectorCode,'vendor_code'=>(string)($def['vendor']??''),
                    ];
                    $this->ensureFromPackage($meta);
                }
                $count++;
            }
        }catch(Throwable $e){ErrorReporter::report($e,['operation'=>'component_registry_reconcile_connector_catalog']);}
        return $count;
    }

    /** @return array<string,mixed>|null */
    public function resolve(string $code): ?array
    {
        if(!$this->available())return null;
        $code=self::normalizeCode($code);if($code==='')return null;
        $row=$this->byCode($code);if(is_array($row))return $row;
        $stmt=$this->pdo->prepare('SELECT c.* FROM bh_repo_component_aliases a JOIN bh_repo_components c ON c.id=a.component_id WHERE a.alias_code=? LIMIT 1');
        $stmt->execute([$code]);$row=$stmt->fetch();return is_array($row)?$row:null;
    }

    /** @return array<string,mixed>|null */
    public function byCode(string $code): ?array
    {
        if(!$this->available())return null;
        $stmt=$this->pdo->prepare('SELECT * FROM bh_repo_components WHERE component_code=? LIMIT 1');$stmt->execute([self::normalizeCode($code)]);$row=$stmt->fetch();return is_array($row)?$row:null;
    }

    /** @return array<string,mixed>|null */
    public function byId(int $id): ?array
    {
        if(!$this->available()||$id<=0)return null;
        $stmt=$this->pdo->prepare('SELECT * FROM bh_repo_components WHERE id=? LIMIT 1');$stmt->execute([$id]);$row=$stmt->fetch();return is_array($row)?$row:null;
    }

    public function attachRelease(int $releaseId,int $componentId): void
    {
        if(!$this->available()||$releaseId<=0||$componentId<=0)return;
        $this->pdo->prepare('UPDATE bh_repo_releases SET component_id=COALESCE(component_id,?),artifact_locked_at=COALESCE(artifact_locked_at,NOW()) WHERE id=?')->execute([$componentId,$releaseId]);
    }

    public function promoteRelease(int $releaseId,string $channel,?int $userId=null): void
    {
        if(!$this->available())throw new RuntimeException('El registro maestro de componentes no está disponible.');
        $channel=strtolower(trim($channel));if(!in_array($channel,self::CHANNELS,true))throw new InvalidArgumentException('Canal de publicación no válido.');
        $stmt=$this->pdo->prepare("SELECT r.id,r.status,r.package_hash_sha256,r.component_id,r.version FROM bh_repo_releases r WHERE r.id=? LIMIT 1");$stmt->execute([$releaseId]);$release=$stmt->fetch();
        if(!is_array($release)||($release['status']??'')!=='published')throw new RuntimeException('Solo se pueden promover releases publicadas.');
        if(trim((string)($release['package_hash_sha256']??''))==='')throw new RuntimeException('El release no tiene un artefacto SHA-256 bloqueado.');
        $sql="INSERT INTO bh_repo_release_channels (release_id,channel,active,promoted_at,promoted_by) VALUES (?,?,1,NOW(),?) ON DUPLICATE KEY UPDATE active=1,promoted_at=NOW(),promoted_by=VALUES(promoted_by)";
        $this->pdo->prepare($sql)->execute([$releaseId,$channel,$userId]);
    }

    /** @return array<int,list<string>> */
    public function channelsForReleaseIds(array $ids): array
    {
        $ids=array_values(array_unique(array_filter(array_map('intval',$ids),static fn(int $v):bool=>$v>0)));if($ids===[]||!$this->available())return [];
        $marks=implode(',',array_fill(0,count($ids),'?'));$stmt=$this->pdo->prepare("SELECT release_id,channel FROM bh_repo_release_channels WHERE active=1 AND release_id IN ($marks) ORDER BY FIELD(channel,'stable','beta','development','pinned'),channel");$stmt->execute($ids);$out=[];
        foreach($stmt->fetchAll()?:[] as $row)$out[(int)$row['release_id']][]=(string)$row['channel'];
        return $out;
    }

    /** @return list<array<string,mixed>> */
    public function components(): array
    {
        if(!$this->available())return [];
        $sql="SELECT c.*,COUNT(r.id) release_count,MAX(r.published_at) last_release_at FROM bh_repo_components c LEFT JOIN bh_repo_releases r ON r.component_id=c.id GROUP BY c.id ORDER BY c.component_type,c.display_name";
        return $this->pdo->query($sql)->fetchAll()?:[];
    }

    public static function normalizeCode(string $code): string
    {
        $code=strtolower(trim($code));$code=preg_replace('/[^a-z0-9._-]+/','-',$code)??'';return trim($code,'-');
    }

    public static function derivedConnectorCode(string $componentCode): string
    {
        $code=self::normalizeCode($componentCode);
        if(str_starts_with($code,'connector-'))$code=substr($code,10);
        return DesiredConnectorPolicy::canonical($code);
    }

    private function ensureAlias(int $componentId,string $alias,string $type): void
    {
        $alias=self::normalizeCode($alias);if($alias==='')return;
        $stmt=$this->pdo->prepare('SELECT component_id FROM bh_repo_component_aliases WHERE alias_code=? LIMIT 1');$stmt->execute([$alias]);$owner=$stmt->fetchColumn();
        if($owner!==false&&(int)$owner!==$componentId)throw new RuntimeException('El alias '.$alias.' ya pertenece a otro componente.');
        $this->pdo->prepare("INSERT INTO bh_repo_component_aliases (component_id,alias_code,alias_type) VALUES (?,?,?) ON DUPLICATE KEY UPDATE component_id=VALUES(component_id),alias_type=VALUES(alias_type)")->execute([$componentId,$alias,$type]);
    }
}
