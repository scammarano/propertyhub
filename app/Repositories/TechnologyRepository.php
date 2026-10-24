<?php
declare(strict_types=1);

final class TechnologyRepository
{
    public function __construct(private ?PDO $pdo=null) { $this->pdo ??= Connection::pdo(); }

    public function installationId(): int
    {
        static $id=null;if(is_int($id)&&$id>0)return $id;
        $stmt=$this->pdo->prepare('SELECT id FROM bh_installations WHERE public_uuid=? LIMIT 1');
        $stmt->execute([(string)config('deployment.installation_uuid')]);$id=(int)$stmt->fetchColumn();
        if($id<1)throw new RuntimeException('No se encontró la instalación local.');return $id;
    }

    public function profile(): array
    {
        $stmt=$this->pdo->prepare('SELECT * FROM bh_technology_profiles WHERE installation_id=? LIMIT 1');$stmt->execute([$this->installationId()]);
        return $stmt->fetch() ?: ['data_scope'=>'aggregates','share_aggregate_metrics'=>1,'share_connector_inventory'=>1,'remote_support_enabled'=>0,'remote_support_requires_local_approval'=>1,'notes'=>''];
    }

    /** Catálogo: conectores disponibles en el producto, no necesariamente usados en este inmueble. */
    public function connectorDefinitions(): array
    {
        return $this->pdo->query('SELECT * FROM bh_connector_definitions ORDER BY domain,name')->fetchAll();
    }

    /** Configurados: instancias reales declaradas dentro de esta instalación. */
    public function connectorInstances(): array
    {
        $stmt=$this->pdo->prepare(
            'SELECT ci.*,cd.code connector_code,cd.name connector_name,cd.vendor,cd.domain,cd.version connector_version,cd.status connector_lifecycle, '
            .'(SELECT COUNT(*) FROM bh_devices d WHERE d.connector_instance_id=ci.id) actual_device_count, '
            ."(SELECT COUNT(*) FROM bh_external_objects eo WHERE eo.connector_instance_id=ci.id AND eo.object_type='device') discovered_device_count, "
            ."(SELECT COUNT(*) FROM bh_external_objects eo WHERE eo.connector_instance_id=ci.id AND eo.object_type='door') discovered_door_count, "
            ."(SELECT COUNT(*) FROM bh_external_objects eo WHERE eo.connector_instance_id=ci.id AND eo.object_type='reader') discovered_reader_count, "
            ."(SELECT COUNT(*) FROM bh_external_mappings em WHERE em.connector_instance_id=ci.id AND em.local_entity_type='access_point' AND em.external_object_type='door') adopted_access_count "
            .'FROM bh_connector_instances ci JOIN bh_connector_definitions cd ON cd.id=ci.connector_definition_id '
            .'WHERE ci.installation_id=? ORDER BY cd.domain,ci.name'
        );
        $stmt->execute([$this->installationId()]);
        return $stmt->fetchAll();
    }

    public function connectorInstance(int $id): ?array
    {
        $stmt=$this->pdo->prepare('SELECT * FROM bh_connector_instances WHERE id=? AND installation_id=?');$stmt->execute([$id,$this->installationId()]);return $stmt->fetch()?:null;
    }

    public function saveConnectorInstance(array $data, ?int $id=null): int
    {
        $definitionId=(int)($data['connector_definition_id']??0);
        $name=trim((string)($data['name']??''));
        if($definitionId<1||$name==='')throw new InvalidArgumentException('Seleccione un conector e indique un nombre para la instancia.');

        $definition=$this->pdo->prepare('SELECT code,name FROM bh_connector_definitions WHERE id=?');
        $definition->execute([$definitionId]);
        $definitionRow=$definition->fetch();
        if(!$definitionRow)throw new InvalidArgumentException('El conector seleccionado no existe en el catálogo.');

        $targetTypes=['platform_server','cloud_platform','protocol_service','direct_device','technology_gateway','other'];
        $targetType=in_array((string)($data['target_type']??''),$targetTypes,true)?(string)$data['target_type']:'platform_server';
        $directPolicies=['not_applicable','forbidden','optional','required'];
        $directPolicy=in_array((string)($data['direct_device_policy']??''),$directPolicies,true)?(string)$data['direct_device_policy']:'not_applicable';
        $managesAssets=!empty($data['manages_downstream_assets'])?1:0;

        if(in_array((string)$definitionRow['code'],['zkteco','zkteco_biosecurity'],true)){
            $targetType='platform_server';
            $directPolicy='forbidden';
            $managesAssets=1;
        }

        $status=in_array((string)($data['status']??''),['configured','online','offline','warning','error','disabled','planned'],true)?(string)$data['status']:'configured';
        $environment=in_array((string)($data['environment']??''),['production','test','sandbox'],true)?(string)$data['environment']:'production';
        $values=[
            $definitionId,$name,$targetType,$managesAssets,$directPolicy,
            trim((string)($data['platform_product']??''))?:null,
            trim((string)($data['platform_version']??''))?:null,
            trim((string)($data['integration_mode']??''))?:null,
            trim((string)($data['api_family']??''))?:null,
            trim((string)($data['api_version']??''))?:null,
            trim((string)($data['authentication_mode']??''))?:null,
            trim((string)($data['endpoint_url']??''))?:null,
            json_encode([],JSON_UNESCAPED_SLASHES),$environment,$status
        ];

        if($id){
            $this->assertOwned('bh_connector_instances',$id);
            $stmt=$this->pdo->prepare('UPDATE bh_connector_instances SET connector_definition_id=?,name=?,target_type=?,manages_downstream_assets=?,direct_device_policy=?,platform_product=?,platform_version=?,integration_mode=?,api_family=?,api_version=?,authentication_mode=?,endpoint_url=?,capabilities_json=?,environment=?,status=? WHERE id=? AND installation_id=?');
            $stmt->execute([...$values,$id,$this->installationId()]);
            $this->refreshConnectorDeviceCount($id);
            $this->audit('connector.update','connector_instance',$id,['name'=>$name,'target_type'=>$targetType]);
            return $id;
        }

        $stmt=$this->pdo->prepare('INSERT INTO bh_connector_instances (installation_id,connector_definition_id,name,target_type,manages_downstream_assets,direct_device_policy,platform_product,platform_version,integration_mode,api_family,api_version,authentication_mode,endpoint_url,device_count,capabilities_json,environment,status,last_health_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,0,?,?,?,NULL)');
        $stmt->execute([$this->installationId(),...$values]);
        $newId=(int)$this->pdo->lastInsertId();
        $this->audit('connector.create','connector_instance',$newId,['name'=>$name,'target_type'=>$targetType]);
        return $newId;
    }

    /** Compatibilidad con rutas anteriores. */
    public function createConnectorInstance(array $data): void { $this->saveConnectorInstance($data); }

    public function deleteConnectorInstance(int $id): string
    {
        $this->assertOwned('bh_connector_instances',$id);
        $s=$this->pdo->prepare('SELECT COUNT(*) FROM bh_devices WHERE connector_instance_id=?');$s->execute([$id]);
        if((int)$s->fetchColumn()>0){$this->pdo->prepare("UPDATE bh_connector_instances SET status='disabled' WHERE id=?")->execute([$id]);$this->audit('connector.disable','connector_instance',$id,[]);return 'disabled';}
        $this->pdo->prepare('DELETE FROM bh_connector_instances WHERE id=?')->execute([$id]);$this->audit('connector.delete','connector_instance',$id,[]);return 'deleted';
    }

    public function devices(array $filters=[]): array
    {
        $search=trim((string)($filters['search']??''));$type=trim((string)($filters['type']??''));$status=trim((string)($filters['status']??''));
        $category=trim((string)($filters['category']??''));$connectorId=max(0,(int)($filters['connector_id']??0));
        $page=max(1,(int)($filters['page']??1));$perPage=(int)($filters['per_page']??25);if(!in_array($perPage,[10,25,50,100],true))$perPage=25;$offset=($page-1)*$perPage;
        $where=['d.installation_id=?'];$args=[$this->installationId()];
        if($search!==''){$where[]='(d.name LIKE ? OR d.vendor LIKE ? OR d.model LIKE ? OR d.serial_number LIKE ? OR d.ip_address LIKE ? OR ci.name LIKE ? OR d.managed_by LIKE ? OR l.name LIKE ? OR p.name LIKE ? OR a.name LIKE ?)';$like='%'.$search.'%';array_push($args,$like,$like,$like,$like,$like,$like,$like,$like,$like,$like);}
        if($type!==''){$where[]='d.device_type=?';$args[]=$type;}if($status!==''){$where[]='d.status=?';$args[]=$status;}if($connectorId>0){$where[]='d.connector_instance_id=?';$args[]=$connectorId;}
        $categoryMap=[
            'access'=>['access_controller','reader','lock','relay','turnstile','barrier','door_operator'],
            'video'=>['camera','nvr'],
            'network'=>['network_controller','network_switch','poe_switch','access_point','patch_panel','fiber_distributor','rack','ups'],
            'iot'=>['gateway','sensor','iot_actuator','meter','pump','fire_panel'],
            'vertical'=>['elevator_controller'],
            'communications'=>['intercom','phone','speaker'],
        ];
        if($category!==''&&isset($categoryMap[$category])){$marks=implode(',',array_fill(0,count($categoryMap[$category]),'?'));$where[]="d.device_type IN ({$marks})";array_push($args,...$categoryMap[$category]);}
        $sqlWhere=implode(' AND ',$where);
        $count=$this->pdo->prepare("SELECT COUNT(*) FROM bh_devices d LEFT JOIN bh_connector_instances ci ON ci.id=d.connector_instance_id LEFT JOIN bh_site_locations l ON l.id=d.site_location_id LEFT JOIN bh_properties p ON p.id=d.property_id LEFT JOIN bh_areas a ON a.id=d.area_id WHERE {$sqlWhere}");$count->execute($args);$total=(int)$count->fetchColumn();
        $stmt=$this->pdo->prepare(
            "SELECT d.*,ci.name connector_instance,ci.target_type connector_target_type,ci.platform_product,cd.code connector_code,cd.name connector_name,cd.domain connector_domain,l.name site_location,p.name structure_location,a.name area_location FROM bh_devices d "
            ."LEFT JOIN bh_connector_instances ci ON ci.id=d.connector_instance_id LEFT JOIN bh_connector_definitions cd ON cd.id=ci.connector_definition_id "
            ."LEFT JOIN bh_site_locations l ON l.id=d.site_location_id LEFT JOIN bh_properties p ON p.id=d.property_id LEFT JOIN bh_areas a ON a.id=d.area_id WHERE {$sqlWhere} ORDER BY d.asset_stage,d.asset_role,d.device_type,d.name LIMIT {$perPage} OFFSET {$offset}"
        );$stmt->execute($args);
        return ['items'=>$stmt->fetchAll(),'total'=>$total,'page'=>$page,'per_page'=>$perPage,'pages'=>max(1,(int)ceil($total/$perPage)),'search'=>$search,'filters'=>['type'=>$type,'status'=>$status,'category'=>$category,'connector_id'=>$connectorId]];
    }

    /**
     * Materializa equipos físicos descubiertos por plataformas administradoras (hoy ZKBio)
     * dentro del inventario técnico único. Puertas permanecen como recursos lógicos; aquí
     * sólo se adoptan controladoras/dispositivos y lectores físicos.
     */
    public function reconcileManagedExternalEquipment(): array
    {
        $iid=$this->installationId();
        $q=$this->pdo->prepare("SELECT eo.*,ci.name connector_name,cd.code connector_code,cd.domain connector_domain FROM bh_external_objects eo JOIN bh_connector_instances ci ON ci.id=eo.connector_instance_id JOIN bh_connector_definitions cd ON cd.id=ci.connector_definition_id WHERE eo.installation_id=? AND eo.object_type IN ('device','reader') AND eo.adoption_status<>'ignored' AND cd.code IN ('zkteco','zkteco_biosecurity') ORDER BY eo.id");
        $q->execute([$iid]);$rows=$q->fetchAll()?:[];$created=0;$updated=0;$mapped=0;
        $findMap=$this->pdo->prepare("SELECT local_entity_id FROM bh_external_mappings WHERE installation_id=? AND connector_instance_id=? AND local_entity_type='device' AND external_object_type=? AND external_id=? LIMIT 1");
        $findDevice=$this->pdo->prepare('SELECT id,name,property_id,area_id,site_location_id FROM bh_devices WHERE installation_id=? AND connector_instance_id=? AND external_id=? ORDER BY id LIMIT 1');
        $insert=$this->pdo->prepare("INSERT INTO bh_devices (installation_id,site_location_id,property_id,area_id,connector_instance_id,asset_role,asset_stage,connection_path,connection_notes,device_type,name,vendor,model,firmware_version,serial_number,ip_address,network_zone,managed_by,connectivity_mode,direct_to_core,external_id,capabilities_json,status,last_seen_at) VALUES (?,NULL,NULL,NULL,?,'managed_device','installed','via_platform','Sincronizado desde ZKBio',?,?,'ZKTeco',?,NULL,?,?,NULL,?,'lan',0,?,?,?,NOW())");
        $update=$this->pdo->prepare("UPDATE bh_devices SET asset_role='managed_device',connection_path='via_platform',device_type=?,vendor='ZKTeco',model=COALESCE(NULLIF(?,''),model),serial_number=COALESCE(NULLIF(?,''),serial_number),ip_address=COALESCE(NULLIF(?,''),ip_address),managed_by=?,external_id=?,capabilities_json=?,status=?,last_seen_at=NOW() WHERE id=? AND installation_id=?");
        $map=$this->pdo->prepare("INSERT INTO bh_external_mappings (installation_id,connector_instance_id,local_entity_type,local_entity_id,external_object_type,external_id,external_name,authority,sync_status,last_synced_at) VALUES (?,?,'device',?,?,?,?,'shared','synced',NOW()) ON DUPLICATE KEY UPDATE local_entity_type='device',local_entity_id=VALUES(local_entity_id),external_name=VALUES(external_name),authority='shared',sync_status='synced',last_synced_at=NOW()");
        $adopt=$this->pdo->prepare("UPDATE bh_external_objects SET adoption_status='adopted',adopted_at=COALESCE(adopted_at,NOW()) WHERE id=? AND installation_id=?");
        foreach($rows as $row){
            $raw=json_decode((string)($row['payload_json']??''),true);if(!is_array($raw))$raw=[];
            $objectType=(string)$row['object_type'];$externalId=(string)$row['external_id'];$cid=(int)$row['connector_instance_id'];
            $deviceType=$objectType==='reader'?'reader':'access_controller';
            $name=trim((string)($row['name']??''));if($name==='')$name=$deviceType==='reader'?'Lector ZKBio '.($row['external_code']?:$externalId):'Controladora ZKBio '.($row['external_code']?:$externalId);
            $model=trim((string)($raw['type']??$raw['model']??$raw['deviceType']??''));
            $serial=trim((string)($raw['sn']??($objectType==='device'?($row['external_code']??''):'')));
            $ip=trim((string)($raw['ip']??$raw['ipAddress']??$raw['deviceIp']??''));if($ip===''&&filter_var($name,FILTER_VALIDATE_IP))$ip=$name;
            $state=(string)($raw['status']??($objectType==='device'?($raw['state']??$row['status']??'') : ''));
            $status=match(strtolower(trim($state))){'1','online','enabled','active'=>'online','0','offline'=>'offline','2','disabled'=>'disabled',default=>'unknown'};
            $cap=json_encode(['source'=>'zkbio','external_object_type'=>$objectType,'external_object_id'=>$externalId,'payload'=>$raw],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            $findMap->execute([$iid,$cid,$objectType,$externalId]);$deviceId=(int)$findMap->fetchColumn();
            if($deviceId<1){$findDevice->execute([$iid,$cid,$externalId]);$existing=$findDevice->fetch();$deviceId=(int)($existing['id']??0);}
            if($deviceId>0){$update->execute([$deviceType,$model,$serial,$ip,(string)$row['connector_name'],$externalId,$cap,$status,$deviceId,$iid]);$updated++;}
            else{$insert->execute([$iid,$cid,$deviceType,$name,$model?:null,$serial?:null,$ip?:null,(string)$row['connector_name'],$externalId,$cap,$status]);$deviceId=(int)$this->pdo->lastInsertId();$created++;}
            $map->execute([$iid,$cid,$deviceId,$objectType,$externalId,$name]);if($map->rowCount()>0)$mapped++;
            $adopt->execute([(int)$row['id'],$iid]);$this->refreshConnectorDeviceCount($cid);
        }
        return ['created'=>$created,'updated'=>$updated,'mapped'=>$mapped,'scanned'=>count($rows)];
    }

    public function saveDevice(array $data, ?int $id=null): int
    {
        $name=trim((string)($data['name']??''));$type=(string)($data['device_type']??'other');
        $allowed=['access_controller','reader','lock','relay','camera','nvr','intercom','phone','speaker','network_controller','network_switch','poe_switch','access_point','patch_panel','fiber_distributor','rack','ups','gateway','elevator_controller','turnstile','barrier','door_operator','fire_panel','pump','meter','sensor','iot_actuator','other'];
        if($name===''||!in_array($type,$allowed,true))throw new InvalidArgumentException('Nombre y tipo de equipo son obligatorios.');
        $status=in_array((string)($data['status']??''),['online','offline','maintenance','disabled','unknown'],true)?(string)$data['status']:'unknown';
        $roles=['managed_device','direct_device','platform_server','technology_gateway','reference_asset'];
        $assetRole=in_array((string)($data['asset_role']??''),$roles,true)?(string)$data['asset_role']:'managed_device';
        $assetStage=in_array((string)($data['asset_stage']??''),['planned','installed','commissioned','retired'],true)?(string)$data['asset_stage']:'installed';
        $paths=['via_platform','direct','vendor_cloud','technology_gateway','manual_reference'];
        $connectionPath=in_array((string)($data['connection_path']??''),$paths,true)?(string)$data['connection_path']:'via_platform';
        $connectorId=(int)($data['connector_instance_id']??0)?:null;$locationId=(int)($data['site_location_id']??0)?:null;
        $propertyId=(int)($data['property_id']??0)?:null;$areaId=(int)($data['area_id']??0)?:null;
        if($connectorId)$this->assertOwned('bh_connector_instances',$connectorId);if($locationId)$this->assertOwned('bh_site_locations',$locationId);
        if($propertyId)$this->assertOwned('bh_properties',$propertyId);if($areaId)$this->assertOwned('bh_areas',$areaId);
        if($areaId){$q=$this->pdo->prepare('SELECT property_id FROM bh_areas WHERE id=? AND installation_id=?');$q->execute([$areaId,$this->installationId()]);$areaProperty=(int)$q->fetchColumn()?:null;if($propertyId&&$areaProperty&&$propertyId!==$areaProperty)throw new InvalidArgumentException('El área seleccionada no pertenece a la ubicación estructural indicada.');if(!$propertyId)$propertyId=$areaProperty;}
        if($propertyId&&!$areaId){$q=$this->pdo->prepare('SELECT metadata_json FROM bh_properties WHERE id=? AND installation_id=?');$q->execute([$propertyId,$this->installationId()]);$m=json_decode((string)$q->fetchColumn(),true);if(!is_array($m))$m=[];$accepts=array_key_exists('accepts_resources',$m)?!empty($m['accepts_resources']):(($m['structure_role']??'physical')!=='organizer');if(!$accepts)throw new InvalidArgumentException('La ubicación seleccionada es un contenedor organizativo y no admite recursos directos. Seleccione una ubicación física dentro de él.');}

        $connectorCode='';$connectorName='';
        if($connectorId){
            $q=$this->pdo->prepare('SELECT cd.code,ci.name FROM bh_connector_instances ci JOIN bh_connector_definitions cd ON cd.id=ci.connector_definition_id WHERE ci.id=? AND ci.installation_id=?');
            $q->execute([$connectorId,$this->installationId()]);$r=$q->fetch()?:[];$connectorCode=(string)($r['code']??'');$connectorName=(string)($r['name']??'');
        }
        if(in_array($connectorCode,['zkteco','zkteco_biosecurity'],true)){$assetRole='managed_device';$connectionPath='via_platform';}

        $managedBy=trim((string)($data['managed_by']??''))?:($connectionPath==='via_platform'?$connectorName:null);
        $values=[$locationId,$propertyId,$areaId,$connectorId,$assetRole,$assetStage,$connectionPath,trim((string)($data['connection_notes']??''))?:null,$type,$name,trim((string)($data['vendor']??''))?:null,trim((string)($data['model']??''))?:null,trim((string)($data['firmware_version']??''))?:null,trim((string)($data['serial_number']??''))?:null,trim((string)($data['ip_address']??''))?:null,trim((string)($data['network_zone']??''))?:null,$managedBy,trim((string)($data['connectivity_mode']??''))?:null,$connectionPath==='direct'?1:0,trim((string)($data['external_id']??''))?:null,json_encode([],JSON_UNESCAPED_SLASHES),$status];

        $oldConnector=null;
        if($id){
            $this->assertOwned('bh_devices',$id);$q=$this->pdo->prepare('SELECT connector_instance_id FROM bh_devices WHERE id=?');$q->execute([$id]);$oldConnector=(int)$q->fetchColumn()?:null;
            $stmt=$this->pdo->prepare('UPDATE bh_devices SET site_location_id=?,property_id=?,area_id=?,connector_instance_id=?,asset_role=?,asset_stage=?,connection_path=?,connection_notes=?,device_type=?,name=?,vendor=?,model=?,firmware_version=?,serial_number=?,ip_address=?,network_zone=?,managed_by=?,connectivity_mode=?,direct_to_core=?,external_id=?,capabilities_json=?,status=? WHERE id=? AND installation_id=?');
            $stmt->execute([...$values,$id,$this->installationId()]);if($oldConnector)$this->refreshConnectorDeviceCount($oldConnector);if($connectorId)$this->refreshConnectorDeviceCount($connectorId);$this->audit('device.update','device',$id,['name'=>$name,'connection_path'=>$connectionPath,'property_id'=>$propertyId,'area_id'=>$areaId]);return$id;
        }
        $stmt=$this->pdo->prepare('INSERT INTO bh_devices (installation_id,site_location_id,property_id,area_id,connector_instance_id,asset_role,asset_stage,connection_path,connection_notes,device_type,name,vendor,model,firmware_version,serial_number,ip_address,network_zone,managed_by,connectivity_mode,direct_to_core,external_id,capabilities_json,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([$this->installationId(),...$values]);$newId=(int)$this->pdo->lastInsertId();if($connectorId)$this->refreshConnectorDeviceCount($connectorId);$this->audit('device.create','device',$newId,['name'=>$name,'connection_path'=>$connectionPath,'property_id'=>$propertyId,'area_id'=>$areaId]);return$newId;
    }

    public function deleteDevice(int $id): string
    {
        $this->assertOwned('bh_devices',$id);
        $q=$this->pdo->prepare('SELECT connector_instance_id FROM bh_devices WHERE id=?');$q->execute([$id]);$connectorId=(int)$q->fetchColumn()?:null;
        foreach(['SELECT COUNT(*) FROM bh_cameras WHERE device_id=?','SELECT COUNT(*) FROM bh_access_points WHERE device_id=?'] as $sql){$s=$this->pdo->prepare($sql);$s->execute([$id]);if((int)$s->fetchColumn()>0){$this->pdo->prepare("UPDATE bh_devices SET status='disabled' WHERE id=?")->execute([$id]);if($connectorId)$this->refreshConnectorDeviceCount($connectorId);$this->audit('device.disable','device',$id,[]);return 'disabled';}}
        $this->pdo->prepare('DELETE FROM bh_devices WHERE id=?')->execute([$id]);if($connectorId)$this->refreshConnectorDeviceCount($connectorId);$this->audit('device.delete','device',$id,[]);return 'deleted';
    }

    public function updatePrivacyProfile(array $data): void
    {
        $scope=in_array((string)($data['data_scope']??''),['metadata_only','aggregates','support_snapshot'],true)?(string)$data['data_scope']:'aggregates';
        $stmt=$this->pdo->prepare('INSERT INTO bh_technology_profiles (installation_id,profile_name,data_scope,share_aggregate_metrics,share_connector_inventory,remote_support_enabled,remote_support_requires_local_approval,notes) VALUES (?,\'Perfil tecnológico principal\',?,?,?,?,?,?) ON DUPLICATE KEY UPDATE data_scope=VALUES(data_scope),share_aggregate_metrics=VALUES(share_aggregate_metrics),share_connector_inventory=VALUES(share_connector_inventory),remote_support_enabled=VALUES(remote_support_enabled),remote_support_requires_local_approval=VALUES(remote_support_requires_local_approval),notes=VALUES(notes)');
        $stmt->execute([$this->installationId(),$scope,!empty($data['share_aggregate_metrics'])?1:0,!empty($data['share_connector_inventory'])?1:0,!empty($data['remote_support_enabled'])?1:0,!empty($data['remote_support_requires_local_approval'])?1:0,trim((string)($data['notes']??''))?:null]);
    }

    public function siteLocations(): array
    {
        $stmt=$this->pdo->prepare('SELECT l.*,p.name parent_name FROM bh_site_locations l LEFT JOIN bh_site_locations p ON p.id=l.parent_id WHERE l.installation_id=? ORDER BY l.is_operational_center DESC,l.location_type,l.name');$stmt->execute([$this->installationId()]);return $stmt->fetchAll();
    }

    public function saveSiteLocation(array $data, ?int $id=null): int
    {
        $code=$this->code((string)($data['code']??''));$name=trim((string)($data['name']??''));if($code===''||$name==='')throw new InvalidArgumentException('Código y nombre son obligatorios.');
        $types=['main_site','headquarters','sector','street','block','parcel','building','tower','floor','unit','guard_post','park','common_area','technical_area','parking','perimeter','remote_point','other'];$type=(string)($data['location_type']??'other');if(!in_array($type,$types,true))throw new InvalidArgumentException('Tipo de ubicación no válido.');
        $modes=['lan','fiber','utp','wifi','radio','vpn','internet','cellular','lorawan','mixed','none'];$mode=(string)($data['connectivity_mode']??'lan');if(!in_array($mode,$modes,true))$mode='lan';
        $status=in_array((string)($data['status']??''),['online','warning','offline','planned','maintenance','unknown'],true)?(string)$data['status']:'unknown';$parent=(int)($data['parent_id']??0)?:null;if($id&&$parent===$id)throw new InvalidArgumentException('Una ubicación no puede depender de sí misma.');if($parent)$this->assertOwned('bh_site_locations',$parent);
        $values=[$parent,$code,$name,$type,!empty($data['is_operational_center'])?1:0,$mode,trim((string)($data['latitude']??''))?:null,trim((string)($data['longitude']??''))?:null,$status,trim((string)($data['notes']??''))?:null,json_encode([],JSON_UNESCAPED_SLASHES)];
        if($id){$this->assertOwned('bh_site_locations',$id);$stmt=$this->pdo->prepare('UPDATE bh_site_locations SET parent_id=?,code=?,name=?,location_type=?,is_operational_center=?,connectivity_mode=?,latitude=?,longitude=?,status=?,notes=?,metadata_json=? WHERE id=? AND installation_id=?');$stmt->execute([...$values,$id,$this->installationId()]);$this->audit('site_location.update','site_location',$id,['name'=>$name]);return $id;}
        $stmt=$this->pdo->prepare('INSERT INTO bh_site_locations (installation_id,parent_id,code,name,location_type,is_operational_center,connectivity_mode,latitude,longitude,status,notes,metadata_json) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');$stmt->execute([$this->installationId(),...$values]);$newId=(int)$this->pdo->lastInsertId();$this->audit('site_location.create','site_location',$newId,['name'=>$name]);return $newId;
    }

    public function deleteSiteLocation(int $id): string
    {
        $this->assertOwned('bh_site_locations',$id);
        foreach(['SELECT COUNT(*) FROM bh_site_locations WHERE parent_id=?','SELECT COUNT(*) FROM bh_connectivity_links WHERE from_location_id=? OR to_location_id=?','SELECT COUNT(*) FROM bh_devices WHERE site_location_id=?','SELECT COUNT(*) FROM bh_lorawan_gateways WHERE site_location_id=?','SELECT COUNT(*) FROM bh_lorawan_devices WHERE site_location_id=?'] as $i=>$sql){$s=$this->pdo->prepare($sql);$i===1?$s->execute([$id,$id]):$s->execute([$id]);if((int)$s->fetchColumn()>0){$this->pdo->prepare("UPDATE bh_site_locations SET status='planned' WHERE id=?")->execute([$id]);$this->audit('site_location.archive','site_location',$id,[]);return 'archived';}}
        $this->pdo->prepare('DELETE FROM bh_site_locations WHERE id=?')->execute([$id]);$this->audit('site_location.delete','site_location',$id,[]);return 'deleted';
    }

    public function connectivityLinks(): array
    {
        $stmt=$this->pdo->prepare('SELECT c.*,f.name from_name,t.name to_name FROM bh_connectivity_links c LEFT JOIN bh_site_locations f ON f.id=c.from_location_id LEFT JOIN bh_site_locations t ON t.id=c.to_location_id WHERE c.installation_id=? ORDER BY FIELD(c.link_role,\'primary\',\'backup\',\'independent\',\'iot\'),c.name');$stmt->execute([$this->installationId()]);return $stmt->fetchAll();
    }

    public function saveConnectivityLink(array $data, ?int $id=null): int
    {
        $code=$this->code((string)($data['code']??''));$name=trim((string)($data['name']??''));if($code===''||$name==='')throw new InvalidArgumentException('Código y nombre son obligatorios.');
        $types=['fiber','utp','wifi','radio','vpn','internet','cellular','lorawan','mixed','other'];$type=(string)($data['link_type']??'utp');if(!in_array($type,$types,true))throw new InvalidArgumentException('Tecnología de enlace no válida.');
        $role=in_array((string)($data['link_role']??''),['primary','backup','independent','iot'],true)?(string)$data['link_role']:'primary';$status=in_array((string)($data['status']??''),['online','warning','offline','planned','maintenance','unknown'],true)?(string)$data['status']:'unknown';
        $from=(int)($data['from_location_id']??0)?:null;$to=(int)($data['to_location_id']??0)?:null;if($from)$this->assertOwned('bh_site_locations',$from);if($to)$this->assertOwned('bh_site_locations',$to);
        $values=[$from,$to,$code,$name,$type,$role,trim((string)($data['provider']??''))?:null,trim((string)($data['capacity_label']??''))?:null,$status,!empty($data['monitored'])?1:0,trim((string)($data['notes']??''))?:null];
        if($id){$this->assertOwned('bh_connectivity_links',$id);$stmt=$this->pdo->prepare('UPDATE bh_connectivity_links SET from_location_id=?,to_location_id=?,code=?,name=?,link_type=?,link_role=?,provider=?,capacity_label=?,status=?,monitored=?,notes=? WHERE id=? AND installation_id=?');$stmt->execute([...$values,$id,$this->installationId()]);$this->audit('connectivity.update','connectivity_link',$id,['name'=>$name]);return $id;}
        $stmt=$this->pdo->prepare('INSERT INTO bh_connectivity_links (installation_id,from_location_id,to_location_id,code,name,link_type,link_role,provider,capacity_label,status,monitored,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');$stmt->execute([$this->installationId(),...$values]);$newId=(int)$this->pdo->lastInsertId();$this->audit('connectivity.create','connectivity_link',$newId,['name'=>$name]);return $newId;
    }

    public function deleteConnectivityLink(int $id): void{$this->assertOwned('bh_connectivity_links',$id);$this->pdo->prepare('DELETE FROM bh_connectivity_links WHERE id=?')->execute([$id]);$this->audit('connectivity.delete','connectivity_link',$id,[]);}

    public function lorawanNetworks(): array{$stmt=$this->pdo->prepare('SELECT * FROM bh_lorawan_networks WHERE installation_id=? ORDER BY name');$stmt->execute([$this->installationId()]);return $stmt->fetchAll();}
    public function lorawanGateways(): array{$stmt=$this->pdo->prepare('SELECT g.*,n.name network_name,l.name location_name FROM bh_lorawan_gateways g JOIN bh_lorawan_networks n ON n.id=g.network_id LEFT JOIN bh_site_locations l ON l.id=g.site_location_id WHERE g.installation_id=? ORDER BY g.name');$stmt->execute([$this->installationId()]);return $stmt->fetchAll();}
    public function lorawanDevices(): array{$stmt=$this->pdo->prepare('SELECT d.*,n.name network_name,l.name location_name FROM bh_lorawan_devices d JOIN bh_lorawan_networks n ON n.id=d.network_id LEFT JOIN bh_site_locations l ON l.id=d.site_location_id WHERE d.installation_id=? ORDER BY FIELD(d.status,\'warning\',\'offline\',\'unknown\',\'online\'),d.device_type,d.name');$stmt->execute([$this->installationId()]);return $stmt->fetchAll();}
    public function iotAutomationRules(): array{$stmt=$this->pdo->prepare('SELECT * FROM bh_iot_automation_rules WHERE installation_id=? ORDER BY FIELD(priority,\'critical\',\'high\',\'normal\',\'low\'),name');$stmt->execute([$this->installationId()]);return $stmt->fetchAll();}

    public function summary(): array
    {
        $id=$this->installationId();
        $s=$this->pdo->prepare("SELECT COUNT(*) total,SUM(status='online') online_count FROM bh_connector_instances WHERE installation_id=?");$s->execute([$id]);$connectors=$s->fetch()?:[];
        $s=$this->pdo->prepare("SELECT COUNT(*) total,SUM(status='online') online_count FROM bh_devices WHERE installation_id=?");$s->execute([$id]);$devices=$s->fetch()?:[];
        $s=$this->pdo->prepare('SELECT COUNT(DISTINCT cd.domain) FROM bh_connector_instances ci JOIN bh_connector_definitions cd ON cd.id=ci.connector_definition_id WHERE ci.installation_id=?');$s->execute([$id]);
        return ['connectors'=>(int)($connectors['total']??0),'online_connectors'=>(int)($connectors['online_count']??0),'devices'=>(int)($devices['total']??0),'online_devices'=>(int)($devices['online_count']??0),'domains'=>(int)$s->fetchColumn()];
    }

    private function refreshConnectorDeviceCount(int $connectorId): void
    {
        $stmt=$this->pdo->prepare('UPDATE bh_connector_instances SET device_count=(SELECT COUNT(*) FROM bh_devices WHERE connector_instance_id=?) WHERE id=? AND installation_id=?');
        $stmt->execute([$connectorId,$connectorId,$this->installationId()]);
    }

    private function assertOwned(string $table,int $id): void{if(!preg_match('/^bh_[a-z_]+$/',$table))throw new InvalidArgumentException('Tabla no válida.');$s=$this->pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE id=? AND installation_id=?");$s->execute([$id,$this->installationId()]);if((int)$s->fetchColumn()!==1)throw new RuntimeException('El registro no pertenece a esta instalación.');}
    private function code(string $value): string{$value=strtoupper(trim($value));$value=preg_replace('/[^A-Z0-9._-]+/','-',$value)??'';return trim($value,'-');}
    private function audit(string $action,string $type,int $id,array $payload): void{try{$u=current_user()??[];$local=is_numeric($u['id']??null)?(int)$u['id']:null;$s=$this->pdo->prepare('INSERT INTO bh_audit_logs (installation_id,actor_local_user_id,action,entity_type,entity_id,result,ip_address,user_agent,payload_json) VALUES (?,?,?,?,?,\'success\',?,?,?)');$s->execute([$this->installationId(),$local,$action,$type,$id,(string)($_SERVER['REMOTE_ADDR']??''),(string)($_SERVER['HTTP_USER_AGENT']??''),json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);}catch(Throwable){}}
}
