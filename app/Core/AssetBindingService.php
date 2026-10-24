<?php
namespace App\Core;
use PDO;use InvalidArgumentException;use RuntimeException;
final class AssetBindingService
{
    public function __construct(private PDO $pdo){}
    private function installationId(): int { $s=$this->pdo->prepare('SELECT id FROM bh_installations WHERE public_uuid=? LIMIT 1');$s->execute([(string)config('deployment.installation_uuid')]);$id=(int)$s->fetchColumn();if($id<1)throw new RuntimeException('Instalación local no encontrada.');return $id; }
    public function dashboard(): array
    {
        $iid=$this->installationId();$assets=[];
        $q=$this->pdo->prepare("SELECT 'access_point' logical_type,ap.id,ap.code,ap.name,ap.logical_state,a.name area,p.name property_name FROM bh_access_points ap LEFT JOIN bh_areas a ON a.id=ap.area_id LEFT JOIN bh_properties p ON p.id=COALESCE(ap.property_id,a.property_id) WHERE ap.installation_id=? ORDER BY ap.name");$q->execute([$iid]);$assets=array_merge($assets,$q->fetchAll()?:[]);
        $q=$this->pdo->prepare("SELECT 'camera' logical_type,c.id,CONCAT('CAM-',c.id) code,c.name,c.logical_state,a.name area,p.name property_name FROM bh_cameras c LEFT JOIN bh_areas a ON a.id=c.area_id LEFT JOIN bh_properties p ON p.id=COALESCE(c.property_id,a.property_id) WHERE c.installation_id=? AND c.source_deleted_at IS NULL ORDER BY c.name");$q->execute([$iid]);$assets=array_merge($assets,$q->fetchAll()?:[]);
        $b=$this->pdo->prepare('SELECT b.*,ci.name connector_name FROM bh_asset_bindings b LEFT JOIN bh_connector_instances ci ON ci.id=b.connector_instance_id WHERE b.installation_id=? ORDER BY b.logical_type,b.logical_id,b.priority,b.id');$b->execute([$iid]);$bindings=$b->fetchAll()?:[];$by=[];foreach($bindings as $row)$by[$row['logical_type'].':'.$row['logical_id']][]=$row;
        foreach($assets as &$a)$a['bindings']=$by[$a['logical_type'].':'.$a['id']]??[];unset($a);
        $paths=$this->executionPaths();$pathBy=[];foreach($paths as $row)$pathBy[(int)$row['access_point_id']][]=$row;foreach($assets as &$a){if($a['logical_type']==='access_point')$a['execution_paths']=$pathBy[(int)$a['id']]??[];}unset($a);
        $q=$this->pdo->prepare("SELECT eo.*,ci.name connector_name,cd.code connector_code,cd.domain connector_domain FROM bh_external_objects eo JOIN bh_connector_instances ci ON ci.id=eo.connector_instance_id JOIN bh_connector_definitions cd ON cd.id=ci.connector_definition_id WHERE eo.installation_id=? AND eo.adoption_status<>'ignored' ORDER BY eo.object_type,ci.name,eo.name,eo.external_code LIMIT 1500");$q->execute([$iid]);$external=$q->fetchAll()?:[];
        return ['assets'=>$assets,'external'=>$external,'connectors'=>$this->connectors(),'summary'=>$this->summary($assets,$external),'recent_access_events'=>$this->recentAccessEvents()];
    }
    private function connectors(): array {$q=$this->pdo->prepare("SELECT ci.id,ci.name,cd.code connector_code,ci.status FROM bh_connector_instances ci LEFT JOIN bh_connector_definitions cd ON cd.id=ci.connector_definition_id WHERE ci.installation_id=? ORDER BY ci.name");$q->execute([$this->installationId()]);return $q->fetchAll()?:[];}
    private function summary(array $assets,array $external): array {$s=['logical'=>count($assets),'mapped'=>0,'unmapped'=>0,'discovered'=>count($external)];foreach($assets as $a){if(!empty($a['bindings'])||($a['logical_state']??'')==='mapped')$s['mapped']++;else$s['unmapped']++;}return $s;}
    public function saveBinding(array $data): int
    {
        $iid=$this->installationId();$type=(string)($data['logical_type']??'');$id=(int)($data['logical_id']??0);if(!in_array($type,['access_point','camera','sensor','light','intercom','elevator','other'],true)||$id<1)throw new InvalidArgumentException('Activo lógico inválido.');
        $role=(string)($data['binding_role']??'primary');if(!in_array($role,['primary','secondary','authenticator','actuator','stream','telemetry'],true))$role='primary';$externalId=trim((string)($data['external_id']??''));$connectorId=(int)($data['connector_instance_id']??0)?:null;$externalType=trim((string)($data['external_object_type']??''));$externalName=trim((string)($data['external_name']??''));if($externalId===''&&empty($data['local_device_id']))throw new InvalidArgumentException('Seleccione un recurso físico descubierto o un equipo local.');
        $mode=(string)($data['execution_mode']??'realtime');if(!in_array($mode,['provisioned','realtime','hybrid','passive'],true))$mode='realtime';
        if($connectorId&&$externalId!=='')$this->assertBindingCompatibility($type,$role,$connectorId,$externalType,$externalId);
        $sql="INSERT INTO bh_asset_bindings (installation_id,logical_type,logical_id,binding_role,connector_instance_id,external_object_type,external_id,external_name,local_device_id,execution_mode,status,last_seen_at) VALUES (?,?,?,?,?,?,?,?,?,?,'mapped',NOW())";$this->pdo->prepare($sql)->execute([$iid,$type,$id,$role,$connectorId,$externalType?:null,$externalId?:null,$externalName?:null,(int)($data['local_device_id']??0)?:null,$mode]);
        $bid=(int)$this->pdo->lastInsertId();$table=$type==='camera'?'bh_cameras':($type==='access_point'?'bh_access_points':null);if($table)$this->pdo->prepare("UPDATE {$table} SET logical_state='mapped' WHERE id=? AND installation_id=?")->execute([$id,$iid]);
        if($connectorId&&$externalId!=='')$this->pdo->prepare("UPDATE bh_external_objects SET adoption_status='adopted',adopted_at=COALESCE(adopted_at,NOW()) WHERE installation_id=? AND connector_instance_id=? AND object_type=? AND external_id=?")->execute([$iid,$connectorId,$externalType,$externalId]);
        return $bid;
    }
    private function assertBindingCompatibility(string $logicalType,string $role,int $connectorId,string $externalType,string $externalId): void
    {
        $q=$this->pdo->prepare("SELECT eo.object_type,cd.code connector_code,cd.domain connector_domain FROM bh_external_objects eo JOIN bh_connector_instances ci ON ci.id=eo.connector_instance_id JOIN bh_connector_definitions cd ON cd.id=ci.connector_definition_id WHERE eo.installation_id=? AND eo.connector_instance_id=? AND eo.object_type=? AND eo.external_id=? LIMIT 1");
        $q->execute([$this->installationId(),$connectorId,$externalType,$externalId]);$x=$q->fetch();if(!is_array($x))throw new InvalidArgumentException('El recurso físico descubierto ya no existe o no pertenece a esta instalación.');
        $objectType=(string)$x['object_type'];$code=strtolower((string)($x['connector_code']??''));$domain=strtolower((string)($x['connector_domain']??''));$isZk=in_array($code,['zkteco','zkteco_biosecurity'],true);
        if($logicalType==='camera'){
            if($isZk||in_array($objectType,['door','reader'],true))throw new InvalidArgumentException('Una cámara Property no puede vincularse a una controladora, puerta o lector ZKBio. Seleccione una fuente de video, canal, cámara o stream compatible.');
            $videoTypes=['camera','channel','video_channel','stream','nvr_channel','device'];$looksVideo=$domain==='video'||str_contains($code,'onvif')||str_contains($code,'rtsp')||str_contains($code,'video')||str_contains($code,'hik')||str_contains($code,'dahua');
            if(!in_array($objectType,$videoTypes,true)&&!$looksVideo)throw new InvalidArgumentException('El objeto seleccionado no es una fuente de video compatible con una cámara Property.');
        }
        if($logicalType==='access_point'){
            if($objectType==='reader'&&!in_array($role,['authenticator','secondary','telemetry'],true))throw new InvalidArgumentException('Un lector debe vincularse como autenticador, secundario o telemetría; no como actuador principal de la puerta.');
            if($objectType==='device'&&!in_array($role,['secondary','telemetry'],true))throw new InvalidArgumentException('Una controladora física se relaciona como topología/telemetría. Para operar la puerta seleccione la puerta ZKBio correspondiente.');
            if(!in_array($objectType,['door','reader','device','relay','lock','barrier','turnstile'],true))throw new InvalidArgumentException('El objeto seleccionado no es compatible con un punto de acceso Property.');
        }
    }

    public function deleteBinding(int $id): void
    {
        $iid=$this->installationId();$q=$this->pdo->prepare('SELECT logical_type,logical_id FROM bh_asset_bindings WHERE id=? AND installation_id=? LIMIT 1');$q->execute([$id,$iid]);$row=$q->fetch();if(!$row)return;$this->pdo->prepare('DELETE FROM bh_asset_bindings WHERE id=? AND installation_id=?')->execute([$id,$iid]);$c=$this->pdo->prepare('SELECT COUNT(*) FROM bh_asset_bindings WHERE installation_id=? AND logical_type=? AND logical_id=? AND status<>\'disabled\'');$c->execute([$iid,$row['logical_type'],$row['logical_id']]);if((int)$c->fetchColumn()===0){$table=$row['logical_type']==='camera'?'bh_cameras':($row['logical_type']==='access_point'?'bh_access_points':null);if($table)$this->pdo->prepare("UPDATE {$table} SET logical_state='unmapped' WHERE id=? AND installation_id=?")->execute([(int)$row['logical_id'],$iid]);}
    }
    public function saveAuthenticator(array $data): int
    {
        $iid=$this->installationId();$ap=(int)($data['access_point_id']??0);$name=trim((string)($data['name']??''));$type=(string)($data['authenticator_type']??'property_app');if($ap<1||$name==='')throw new InvalidArgumentException('Acceso y nombre del autenticador son obligatorios.');
        $owner=(string)($data['validation_owner']??'property_hub');if(!in_array($owner,['property_hub','vendor','shared'],true))$owner='property_hub';$status=(string)($data['status']??'planned');if(!in_array($status,['planned','unmapped','online','offline','disabled'],true))$status='planned';
        $this->pdo->prepare('INSERT INTO bh_access_authenticators (installation_id,access_point_id,name,authenticator_type,validation_owner,connector_instance_id,external_object_type,external_id,allow_residents,allow_visitors,allow_temporary_credentials,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)')->execute([$iid,$ap,$name,$type,$owner,(int)($data['connector_instance_id']??0)?:null,trim((string)($data['external_object_type']??''))?:null,trim((string)($data['external_id']??''))?:null,!empty($data['allow_residents'])?1:0,!empty($data['allow_visitors'])?1:0,!empty($data['allow_temporary_credentials'])?1:0,$status]);return (int)$this->pdo->lastInsertId();
    }
    public function authenticators(): array {$q=$this->pdo->prepare('SELECT au.*,ap.name access_name,ci.name connector_name FROM bh_access_authenticators au JOIN bh_access_points ap ON ap.id=au.access_point_id LEFT JOIN bh_connector_instances ci ON ci.id=au.connector_instance_id WHERE au.installation_id=? ORDER BY ap.name,au.name');$q->execute([$this->installationId()]);return $q->fetchAll()?:[];}
    public function executionPaths(): array
    {
        $q=$this->pdo->prepare("SELECT ep.*,ap.name access_name,b.external_name binding_name,ci.name connector_name FROM bh_access_execution_paths ep JOIN bh_access_points ap ON ap.id=ep.access_point_id LEFT JOIN bh_asset_bindings b ON b.id=ep.binding_id LEFT JOIN bh_connector_instances ci ON ci.id=ep.connector_instance_id WHERE ep.installation_id=? ORDER BY ap.name,ep.is_primary DESC,ep.priority,ep.id");
        $q->execute([$this->installationId()]);
        return $q->fetchAll()?:[];
    }
    public function saveExecutionPath(array $data): int
    {
        $iid=$this->installationId();$ap=(int)($data['access_point_id']??0);$name=trim((string)($data['name']??''));if($ap<1||$name==='')throw new InvalidArgumentException('Acceso y nombre de ruta son obligatorios.');
        $type=(string)($data['path_type']??'vendor_api');$allowed=['vendor_api','relay_to_controller_input','parallel_relay','local_io','automation','manual','other'];if(!in_array($type,$allowed,true))$type='other';
        $target=(string)($data['topology_target']??'controller');if(!in_array($target,['controller','rex_input','lock_parallel','lock_direct','other'],true))$target='other';
        $bindingId=(int)($data['binding_id']??0)?:null;$connector=(int)($data['connector_instance_id']??0)?:null;$externalType=trim((string)($data['external_object_type']??''))?:null;$externalId=trim((string)($data['external_id']??''))?:null;
        $this->pdo->prepare('INSERT INTO bh_access_execution_paths (installation_id,access_point_id,name,path_type,binding_id,connector_instance_id,external_object_type,external_id,topology_target,depends_on_controller,vendor_event_expected,priority,is_primary,enabled,pulse_ms,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([$iid,$ap,$name,$type,$bindingId,$connector,$externalType,$externalId,$target,!empty($data['depends_on_controller'])?1:0,!empty($data['vendor_event_expected'])?1:0,max(1,(int)($data['priority']??100)),!empty($data['is_primary'])?1:0,!empty($data['enabled'])?1:0,(int)($data['pulse_ms']??0)?:null,trim((string)($data['notes']??''))?:null]);
        $id=(int)$this->pdo->lastInsertId();
        if(!empty($data['is_primary']))$this->pdo->prepare('UPDATE bh_access_execution_paths SET is_primary=0 WHERE installation_id=? AND access_point_id=? AND id<>?')->execute([$iid,$ap,$id]);
        return $id;
    }
    public function deleteExecutionPath(int $id): void
    {
        $this->pdo->prepare('DELETE FROM bh_access_execution_paths WHERE id=? AND installation_id=?')->execute([$id,$this->installationId()]);
    }
    public function recentAccessEvents(int $limit=40): array
    {
        $limit=max(1,min(200,$limit));
        $sql="SELECT e.*,ap.name access_name,p.display_name person_name,ci.name connector_name FROM bh_access_events e LEFT JOIN bh_access_points ap ON ap.id=e.access_point_id LEFT JOIN bh_people p ON p.id=e.person_id LEFT JOIN bh_connector_instances ci ON ci.id=e.connector_instance_id WHERE e.installation_id=? ORDER BY e.event_time DESC,e.id DESC LIMIT {$limit}";
        $q=$this->pdo->prepare($sql);$q->execute([$this->installationId()]);return $q->fetchAll()?:[];
    }

}
