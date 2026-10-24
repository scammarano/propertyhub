<?php
declare(strict_types=1);

/**
 * Capa operativa del proveedor ZKBio en el Property Node.
 * Property conserva el modelo de negocio; Edge ejecuta localmente contra ZKBio.
 */
final class ZkBioOperationalService
{
    public function __construct(private PDO $pdo){}
    private function assertNode(): void{if(!InstallationMode::isNode())throw new RuntimeException('La operación ZKBio de negocio corresponde al Property Node.');}
    private function installationId(): int{$q=$this->pdo->prepare('SELECT id FROM bh_installations WHERE public_uuid=? LIMIT 1');$q->execute([(string)config('deployment.installation_uuid')]);$id=(int)$q->fetchColumn();if($id<1)throw new RuntimeException('Instalación Node no encontrada.');return $id;}
    private function connectorId(): int{$this->assertNode();$all=LocalIntegrationProvisioning::nodeIntegrations($this->pdo);$id=(int)($all['zkteco']['connector_instance_id']??0);if($id<1||empty($all['zkteco']['enabled']))throw new RuntimeException('ZKBio no está habilitado en Sistemas conectados del Node.');return $id;}

    public function preferredAgent(): array
    {
        $this->assertNode();$agents=(new EdgeNodeService($this->pdo))->agents();$best=null;$bestScore=-1;
        foreach($agents as $a){$meta=json_decode((string)($a['metadata_json']??''),true);$zk=(array)($meta['connectors']['zkbio']??[]);$score=0;if(($a['status']??'')==='active')$score+=100;if(!empty($zk['configured']))$score+=50;if(!empty($zk['token_present']))$score+=50;if(($zk['status']??'')==='online')$score+=50;$seen=strtotime((string)($a['last_seen_at']??''))?:0;$score+=min(49,max(0,(int)(($seen-(time()-3600))/75)));
            if($score>$bestScore){$best=$a;$bestScore=$score;}}
        if(!is_array($best))throw new RuntimeException('No hay Property Edge Agent disponible para ZKBio.');return $best;
    }

    public function queueInventory(?int $userId=null): array
    {
        $agent=$this->preferredAgent();return (new EdgeNodeService($this->pdo))->queueZkBioCommand((int)$agent['id'],'zkbio.inventory',['requested_at'=>date(DATE_ATOM)],$userId);
    }

    public function inventory(): array
    {
        $cid=$this->connectorId();$iid=$this->installationId();$q=$this->pdo->prepare("SELECT eo.*,m.local_entity_id mapped_local_id,m.sync_status mapping_sync,ap.name mapped_access_name,ap.property_id mapped_property_id,ap.area_id mapped_area_id,pr.name mapped_property_name,ar.name mapped_area_name,(SELECT s.area_id FROM bh_access_point_sides s WHERE s.access_point_id=ap.id AND s.side_key='A' LIMIT 1) mapped_side_a_area_id,(SELECT s.area_id FROM bh_access_point_sides s WHERE s.access_point_id=ap.id AND s.side_key='B' LIMIT 1) mapped_side_b_area_id FROM bh_external_objects eo LEFT JOIN bh_external_mappings m ON m.connector_instance_id=eo.connector_instance_id AND m.external_object_type=eo.object_type AND m.external_id=eo.external_id LEFT JOIN bh_access_points ap ON m.local_entity_type='access_point' AND ap.id=m.local_entity_id LEFT JOIN bh_properties pr ON pr.id=ap.property_id LEFT JOIN bh_areas ar ON ar.id=ap.area_id WHERE eo.installation_id=? AND eo.connector_instance_id=? ORDER BY FIELD(eo.object_type,'device','door','reader','department','access_level','person','vehicle','card'),eo.name,eo.external_code");$q->execute([$iid,$cid]);$by=[];foreach($q->fetchAll()?:[] as $r)$by[(string)$r['object_type']][]=$r;
        $counts=[];foreach($by as $k=>$rows)$counts[$k]=count($rows);return ['connector_id'=>$cid,'counts'=>$counts,'objects'=>$by,'doors'=>$by['door']??[],'departments'=>$by['department']??[],'access_levels'=>$by['access_level']??[],'persons'=>$by['person']??[]];
    }

    /** @param array<string,array<int,array<string,mixed>>> $sets */
    public function persistInventory(array $sets): array
    {
        $this->assertNode();$iid=$this->installationId();$cid=$this->connectorId();$allowed=['device','door','reader','department','access_level','person','card','vehicle'];$counts=[];
        $sql="INSERT INTO bh_external_objects (installation_id,connector_instance_id,object_type,external_id,external_code,name,status,payload_json,discovered_at,last_seen_at) VALUES (?,?,?,?,?,?,?,?,NOW(),NOW()) ON DUPLICATE KEY UPDATE external_code=VALUES(external_code),name=VALUES(name),status=VALUES(status),payload_json=VALUES(payload_json),last_seen_at=NOW()";$ins=$this->pdo->prepare($sql);
        foreach($sets as $type=>$rows){if(!in_array($type,$allowed,true)||!is_array($rows))continue;$count=0;foreach($rows as $row){if(!is_array($row))continue;$externalId=trim((string)($row['id']??$row['pin']??$row['code']??$row['cardNo']??$row['carPlate']??$row['sn']??''));if($externalId==='')continue;$externalCode=trim((string)($row['pin']??$row['code']??$row['cardNo']??$row['carPlate']??$row['sn']??$row['readerNo']??''));$name=trim((string)($row['name']??$row['deptName']??$row['doorName']??$row['readerName']??''));$status=trim((string)($row['status']??$row['state']??''));$ins->execute([$iid,$cid,$type,$externalId,$externalCode?:null,$name?:null,$status?:null,json_encode($row,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);$count++;}$counts[$type]=$count;}
        $deptMapped=$this->autoMapDepartments();$personMapped=$this->autoMapPersons();$equipment=(new \TechnologyRepository($this->pdo))->reconcileManagedExternalEquipment();return ['counts'=>$counts,'departments_auto_mapped'=>$deptMapped,'persons_auto_mapped'=>$personMapped,'equipment_reconciled'=>$equipment];
    }

    private function autoMapDepartments(): int
    {
        $iid=$this->installationId();$cid=$this->connectorId();$q=$this->pdo->prepare("SELECT p.id,p.code,p.name,eo.external_code,eo.name FROM bh_properties p JOIN bh_external_objects eo ON eo.installation_id=p.installation_id AND eo.connector_instance_id=? AND eo.object_type='department' AND (UPPER(eo.external_code)=UPPER(p.code) OR UPPER(eo.name)=UPPER(p.code) OR UPPER(eo.name)=UPPER(p.name)) LEFT JOIN bh_external_mappings m ON m.connector_instance_id=eo.connector_instance_id AND m.local_entity_type='property' AND m.local_entity_id=p.id WHERE p.installation_id=? AND m.id IS NULL ORDER BY CASE WHEN UPPER(eo.external_code)=UPPER(p.code) THEN 0 ELSE 1 END,eo.id");$q->execute([$cid,$iid]);$seen=[];$count=0;$ins=$this->pdo->prepare("INSERT IGNORE INTO bh_external_mappings (installation_id,connector_instance_id,local_entity_type,local_entity_id,external_object_type,external_id,external_name,authority,sync_status,last_synced_at) VALUES (?,?,'property',?,'department',?,?,'shared','synced',NOW())");
        foreach($q->fetchAll()?:[] as $r){$pid=(int)$r['id'];if(isset($seen[$pid]))continue;$code=trim((string)$r['external_code']);if($code==='')continue;$ins->execute([$iid,$cid,$pid,$code,(string)($r['name']??$code)]);if($ins->rowCount()>0)$count++;$seen[$pid]=true;}return $count;
    }

    private static function norm(string $v): string{$v=mb_strtoupper(trim($v));$v=preg_replace('/\s+/u',' ',$v)??$v;return $v;}

    /** @return array<int,string> */
    private static function departmentCandidates(string $code,string $name): array
    {
        $raw=[$code,$name];$out=[];
        foreach($raw as $value){
            $v=self::norm($value);if($v==='')continue;$out[]=$v;
            $v=preg_replace('/^(APARTAMENTO|APTO|UNIDAD)\s*/u','',$v)??$v;
            $compact=preg_replace('/[^A-Z0-9]+/u','',$v)??'';if($compact==='')continue;$out[]=$compact;
            if(preg_match('/^0*(\d+)([A-Z]+)$/',$compact,$m))$out[]='P'.(int)$m[1].$m[2];
            if(preg_match('/^P0*(\d+)([A-Z]+)$/',$compact,$m))$out[]='P'.(int)$m[1].$m[2];
            if(preg_match('/^PH([A-Z]+)$/',$compact,$m))$out[]='PH'.$m[1];
        }
        return array_values(array_unique(array_filter($out)));
    }

    private static function isVehicleIdentity(array $levelNames,array $raw): bool
    {
        $levels=array_values(array_filter(array_map(static fn($v)=>mb_strtoupper(trim((string)$v)),$levelNames)));
        if(!$levels)return false;
        $vehicleLevels=array_values(array_filter($levels,static fn(string $v):bool=>str_contains($v,'VEHIC')));
        if(!$vehicleLevels)return false;
        // Premier tiene personas humanas con niveles vehiculares adicionales. Solo tratamos como
        // identidad vehicular los registros cuyo conjunto de niveles es exclusivamente VEHICULOS*.
        foreach($levels as $level)if(!str_contains($level,'VEHIC'))return false;
        return true;
    }
    private function autoMapPersons(): int
    {
        $iid=$this->installationId();$cid=$this->connectorId();$persons=$this->pdo->prepare("SELECT p.id,p.display_name,p.first_name,p.last_name,m.property_id,pm.external_id dept_code FROM bh_people p JOIN bh_property_memberships m ON m.person_id=p.id AND m.status='active' LEFT JOIN bh_external_mappings pm ON pm.connector_instance_id=? AND pm.local_entity_type='property' AND pm.local_entity_id=m.property_id WHERE p.installation_id=? ORDER BY p.id,m.is_primary DESC,m.id");$persons->execute([$cid,$iid]);$ext=$this->pdo->prepare("SELECT external_id,external_code,name,payload_json FROM bh_external_objects WHERE connector_instance_id=? AND object_type='person'");$ext->execute([$cid]);$index=[];foreach($ext->fetchAll()?:[] as $e){$raw=json_decode((string)($e['payload_json']??''),true);if(!is_array($raw))$raw=[];$full=self::norm(trim((string)($raw['name']??'').' '.(string)($raw['lastName']??'')));$dept=self::norm((string)($raw['deptCode']??''));$pin=trim((string)($raw['pin']??$e['external_code']??''));if($full!==''&&$pin!=='')$index[$dept.'|'.$full][]=['pin'=>$pin,'name'=>trim((string)($raw['name']??'').' '.(string)($raw['lastName']??''))];}
        $already=[];$count=0;$ins=$this->pdo->prepare("INSERT IGNORE INTO bh_external_mappings (installation_id,connector_instance_id,local_entity_type,local_entity_id,external_object_type,external_id,external_name,authority,sync_status,last_synced_at) VALUES (?,?,'person',?,'person',?,?,'shared','synced',NOW())");foreach($persons->fetchAll()?:[] as $p){$pid=(int)$p['id'];if(isset($already[$pid]))continue;$already[$pid]=true;$dept=self::norm((string)($p['dept_code']??''));if($dept==='')continue;$names=array_unique(array_filter([self::norm((string)$p['display_name']),self::norm(trim((string)$p['first_name'].' '.(string)$p['last_name']))]));$matches=[];foreach($names as $name)foreach($index[$dept.'|'.$name]??[] as $m)$matches[$m['pin']]=$m;if(count($matches)!==1)continue;$m=array_values($matches)[0];$ins->execute([$iid,$cid,$pid,$m['pin'],$m['name']]);if($ins->rowCount()>0)$count++;}return $count;
    }

    public function adoptDoor(int $externalObjectId,array $placement=[]): int
    {
        $this->assertNode();$iid=$this->installationId();$cid=$this->connectorId();
        $q=$this->pdo->prepare("SELECT * FROM bh_external_objects WHERE id=? AND installation_id=? AND connector_instance_id=? AND object_type='door' LIMIT 1");$q->execute([$externalObjectId,$iid,$cid]);$door=$q->fetch();if(!is_array($door))throw new InvalidArgumentException('Puerta ZKBio no encontrada en el inventario del Node.');
        $propertyId=(int)($placement['property_id']??0)?:null;$areaId=(int)($placement['area_id']??0)?:null;
        $newAreaName=trim((string)($placement['new_area_name']??''));$newAreaScope=(string)($placement['new_area_scope']??'common');$newAreaUsage=(string)($placement['new_area_usage']??($placement['new_area_type']??'circulation'));
        $sideAAreaId=(int)($placement['side_a_area_id']??0)?:null;$sideBAreaId=(int)($placement['side_b_area_id']??0)?:null;
        $sideANew=trim((string)($placement['side_a_new_area']??''));$sideBNew=trim((string)($placement['side_b_new_area']??''));
        if($propertyId!==null){$p=$this->pdo->prepare('SELECT id,property_type,code,name FROM bh_properties WHERE id=? AND installation_id=? AND status<>\'inactive\' LIMIT 1');$p->execute([$propertyId,$iid]);$property=$p->fetch();if(!is_array($property))throw new InvalidArgumentException('Ubicación estructural Property no válida.');}else $property=null;
        $repo=new NodeRepository($this->pdo);
        if($newAreaName!==''){$propertyId??=null;if(!$propertyId)throw new InvalidArgumentException('Seleccione primero un piso, sótano, torre u otro elemento estructural para crear el área.');$areaId=$this->createLocatedArea($repo,$propertyId,$newAreaName,$newAreaScope,$newAreaUsage);}
        if($areaId!==null){$area=$this->areaForPlacement($areaId,$iid);if($propertyId!==null&&(int)($area['property_id']??0)!==$propertyId)throw new InvalidArgumentException('El área seleccionada no pertenece a la ubicación estructural indicada.');if($propertyId===null)$propertyId=(int)($area['property_id']??0)?:null;}
        if($sideANew!==''){$propertyId??=null;if(!$propertyId)throw new InvalidArgumentException('Seleccione una ubicación estructural para crear el espacio del lado A.');$sideAAreaId=$this->createLocatedArea($repo,$propertyId,$sideANew,'common','circulation');}
        if($sideBNew!==''){$propertyId??=null;if(!$propertyId)throw new InvalidArgumentException('Seleccione una ubicación estructural para crear el espacio del lado B.');$sideBAreaId=$this->createLocatedArea($repo,$propertyId,$sideBNew,'common','circulation');}
        foreach([$sideAAreaId,$sideBAreaId] as $sid)if($sid!==null){$side=$this->areaForPlacement($sid,$iid);if($propertyId!==null&&(int)($side['property_id']??0)!==$propertyId)throw new InvalidArgumentException('Los lados A/B deben pertenecer a la misma ubicación estructural de la puerta.');}
        $existing=$this->pdo->prepare("SELECT local_entity_id FROM bh_external_mappings WHERE connector_instance_id=? AND local_entity_type='access_point' AND external_object_type='door' AND external_id=? LIMIT 1");$existing->execute([$cid,(string)$door['external_id']]);$apId=(int)$existing->fetchColumn();
        if($apId>0){$this->applyAccessPlacement($apId,$propertyId,$areaId,$sideAAreaId,$sideBAreaId);return $apId;}
        $base=preg_replace('/[^A-Z0-9]+/','_',mb_strtoupper((string)($door['name']?:'ZK_DOOR')))?:'ZK_DOOR';$base=trim(substr($base,0,48),'_');$code=$base;$n=1;while(true){$c=$this->pdo->prepare('SELECT COUNT(*) FROM bh_access_points WHERE installation_id=? AND code=?');$c->execute([$iid,$code]);if((int)$c->fetchColumn()===0)break;$n++;$code=substr($base,0,52).'_'.$n;}
        $scope='common';$scopePropertyId=null;if($propertyId){$sq=$this->pdo->prepare("SELECT id,property_type FROM bh_properties WHERE id=? AND installation_id=? LIMIT 1");$sq->execute([$propertyId,$iid]);$sr=$sq->fetch();if(is_array($sr)){$scopePropertyId=(int)$sr['id'];$scope=match((string)$sr['property_type']){'apartment','office','commercial_unit','house','townhouse','warehouse','storage'=>'unit','floor','ground_floor','basement','mezzanine','terrace','roof','technical_floor'=>'floor','tower'=>'tower','building','site','urbanization'=>'property',default=>'property'};}}
        $this->pdo->beginTransaction();try{
            $this->pdo->prepare("INSERT INTO bh_access_points (installation_id,property_id,area_id,device_id,code,name,access_type,operation_mode,remote_policy,status,logical_state,capabilities_json,access_scope,access_scope_property_id) VALUES (?,?,?,NULL,?,?,'door','hybrid','allowed','active','mapped',?,?,?)")->execute([$iid,$propertyId,$areaId,$code,(string)($door['name']?:$code),json_encode(['remote_open'=>true,'provider'=>'zkbio'],JSON_UNESCAPED_SLASHES),$scope,$scopePropertyId]);$apId=(int)$this->pdo->lastInsertId();
            $this->pdo->prepare("INSERT INTO bh_external_mappings (installation_id,connector_instance_id,local_entity_type,local_entity_id,external_object_type,external_id,external_name,authority,sync_status,last_synced_at) VALUES (?,?,'access_point',?,'door',?,?,'external','synced',NOW())")->execute([$iid,$cid,$apId,(string)$door['external_id'],(string)$door['name']]);
            $this->pdo->prepare("INSERT INTO bh_asset_bindings (installation_id,logical_type,logical_id,binding_role,connector_instance_id,external_object_type,external_id,external_name,execution_mode,priority,status,last_seen_at) VALUES (?,'access_point',?,'actuator',?,'door',?,?, 'realtime',100,'online',NOW())")->execute([$iid,$apId,$cid,(string)$door['external_id'],(string)$door['name']]);$bindingId=(int)$this->pdo->lastInsertId();
            $this->pdo->prepare("INSERT INTO bh_access_execution_paths (installation_id,access_point_id,name,path_type,binding_id,connector_instance_id,external_object_type,external_id,topology_target,depends_on_controller,vendor_event_expected,priority,is_primary,enabled,pulse_ms,capabilities_json) VALUES (?,?,?,'vendor_api',?,?,'door',?,'controller',1,1,100,1,1,3000,?)")->execute([$iid,$apId,'ZKBio · '.(string)$door['name'],$bindingId,$cid,(string)$door['external_id'],json_encode(['remote_open'=>true,'command'=>'door/remoteOpenById'],JSON_UNESCAPED_SLASHES)]);
            $this->pdo->prepare("INSERT IGNORE INTO bh_access_point_bindings (installation_id,access_point_id,connector_instance_id,driver_key,actuator_type,external_id,external_name,execution_mode,pulse_seconds,status) VALUES (?,?,?,'zkbio','door',?,?,'realtime',3.00,'active')")->execute([$iid,$apId,$cid,(string)$door['external_id'],(string)$door['name']]);
            $this->pdo->prepare("INSERT INTO bh_access_authenticators (installation_id,access_point_id,name,authenticator_type,validation_owner,capabilities_json,allow_residents,allow_visitors,allow_temporary_credentials,status) VALUES (?,?,'Property App','property_app','property_hub',?,1,1,1,'online')")->execute([$iid,$apId,json_encode(['remote_request'=>true],JSON_UNESCAPED_SLASHES)]);
            $this->saveAccessSides($apId,$propertyId,$sideAAreaId,$sideBAreaId);
            $this->pdo->prepare("UPDATE bh_external_objects SET adoption_status='adopted',adopted_at=NOW() WHERE id=?")->execute([$externalObjectId]);$this->pdo->commit();return $apId;
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    private function areaForPlacement(int $areaId,int $installationId): array
    {
        $q=$this->pdo->prepare('SELECT id,property_id,name,area_type FROM bh_areas WHERE id=? AND installation_id=? AND status<>\'inactive\' LIMIT 1');$q->execute([$areaId,$installationId]);$r=$q->fetch();if(!is_array($r))throw new InvalidArgumentException('Área/espacio Property no válido.');return $r;
    }

    private function createLocatedArea(NodeRepository $repo,int $propertyId,string $name,string $scope='common',string $usage='circulation'): int
    {
        if(!in_array($scope,['common','private','technical','service','restricted','public'],true))$scope='common';
        // Compatibilidad: callers antiguos podían enviar el area_type histórico como uso.
        $legacyUsage=['common'=>'general','reservable'=>'reservable','security'=>'security','technical'=>'technical','commercial'=>'commercial','office'=>'office','parking'=>'parking','circulation'=>'circulation','other'=>'other'];
        $usage=$legacyUsage[$usage]??$usage;if(!in_array($usage,['general','lobby','circulation','parking','office','commercial','technical','security','recreation','amenity','reservable','storage','residential','other'],true))$usage='circulation';
        $q=$this->pdo->prepare('SELECT code FROM bh_properties WHERE id=? AND installation_id=? LIMIT 1');$q->execute([$propertyId,$this->installationId()]);$parentCode=(string)$q->fetchColumn();if($parentCode==='')throw new InvalidArgumentException('Ubicación estructural no encontrada.');
        $slug=preg_replace('/[^A-Z0-9]+/','-',mb_strtoupper($name))?:'AREA';$slug=trim(substr($slug,0,36),'-');$base=substr($parentCode.'-'.$slug,0,54);$code=$base;$n=1;while(true){$c=$this->pdo->prepare('SELECT COUNT(*) FROM bh_areas WHERE installation_id=? AND code=?');$c->execute([$this->installationId(),$code]);if((int)$c->fetchColumn()===0)break;$code=substr($base,0,54).'-'.(++$n);}
        return $repo->saveArea(['property_id'=>$propertyId,'code'=>$code,'name'=>$name,'scope_type'=>$scope,'usage_type'=>$usage,'requires_approval'=>0,'status'=>'active']);
    }

    private function applyAccessPlacement(int $accessPointId,?int $propertyId,?int $areaId,?int $sideAAreaId,?int $sideBAreaId): void
    {
        $scope='common';$scopePropertyId=null;if($propertyId){$q=$this->pdo->prepare('SELECT property_type FROM bh_properties WHERE id=? AND installation_id=? LIMIT 1');$q->execute([$propertyId,$this->installationId()]);$type=(string)$q->fetchColumn();$scopePropertyId=$propertyId;$scope=match($type){'apartment','office','commercial_unit','house','townhouse','warehouse','storage'=>'unit','floor','ground_floor','basement','mezzanine','terrace','roof','technical_floor'=>'floor','tower'=>'tower','building','site','urbanization'=>'property',default=>'property'};}
        $this->pdo->prepare('UPDATE bh_access_points SET property_id=?,area_id=?,access_scope=?,access_scope_property_id=? WHERE id=? AND installation_id=?')->execute([$propertyId,$areaId,$scope,$scopePropertyId,$accessPointId,$this->installationId()]);$this->saveAccessSides($accessPointId,$propertyId,$sideAAreaId,$sideBAreaId);
    }

    private function saveAccessSides(int $accessPointId,?int $propertyId,?int $sideAAreaId,?int $sideBAreaId): void
    {
        $this->pdo->prepare('DELETE FROM bh_access_point_sides WHERE access_point_id=? AND installation_id=?')->execute([$accessPointId,$this->installationId()]);
        $ins=$this->pdo->prepare("INSERT INTO bh_access_point_sides (installation_id,access_point_id,side_key,property_id,area_id,label,direction_hint) VALUES (?,?,?,?,?,?,'unknown')");
        foreach([['A',$sideAAreaId],['B',$sideBAreaId]] as [$key,$areaId]){if(!$areaId)continue;$a=$this->areaForPlacement((int)$areaId,$this->installationId());$ins->execute([$this->installationId(),$accessPointId,$key,$propertyId,(int)$a['id'],(string)$a['name']]);}
    }

    public function accessExecutionPath(int $accessPointId): array
    {
        $q=$this->pdo->prepare("SELECT ep.*,ap.name access_name FROM bh_access_execution_paths ep JOIN bh_access_points ap ON ap.id=ep.access_point_id WHERE ep.installation_id=? AND ep.access_point_id=? AND ep.enabled=1 AND ep.connector_instance_id=? AND ep.external_object_type='door' ORDER BY ep.is_primary DESC,ep.priority,ep.id LIMIT 1");$q->execute([$this->installationId(),$accessPointId,$this->connectorId()]);$r=$q->fetch();if(!is_array($r))throw new RuntimeException('Este acceso Property todavía no tiene una ruta ZKBio operativa.');return $r;
    }

    public function requestRemoteOpen(int $accessPointId,?int $localUserId,?int $personId,?int $propertyId,string $source='property_app'): array
    {
        $path=$this->accessExecutionPath($accessPointId);$agent=$this->preferredAgent();$correlation=self::uuid();$iid=$this->installationId();$this->pdo->prepare("INSERT INTO bh_access_commands (installation_id,correlation_uuid,access_point_id,execution_path_id,actor_local_user_id,actor_person_id,property_id,credential_type,source,decision,status,details_json) VALUES (?,?,?,?,?,?,?,'mobile',?,'allowed','queued',?)")->execute([$iid,$correlation,$accessPointId,(int)$path['id'],$localUserId,$personId,$propertyId,$source,json_encode(['provider'=>'zkbio','door_id'=>$path['external_id']],JSON_UNESCAPED_SLASHES)]);$accessCommandId=(int)$this->pdo->lastInsertId();
        try{$queued=(new EdgeNodeService($this->pdo))->queueZkBioCommand((int)$agent['id'],'zkbio.remote_open',['door_id'=>(string)$path['external_id'],'interval'=>max(1,min(30,(int)ceil(((int)($path['pulse_ms']??3000))/1000))),'context'=>['access_command_id'=>$accessCommandId,'correlation_uuid'=>$correlation,'access_point_id'=>$accessPointId]],$localUserId);$details=['provider'=>'zkbio','door_id'=>$path['external_id'],'edge_command_uuid'=>$queued['command_uuid']??null];$this->pdo->prepare("UPDATE bh_access_commands SET details_json=? WHERE id=?")->execute([json_encode($details,JSON_UNESCAPED_SLASHES),$accessCommandId]);return ['ok'=>true,'status'=>'queued','correlation_uuid'=>$correlation,'access_command_id'=>$accessCommandId,'edge_command_uuid'=>$queued['command_uuid']??null,'access_name'=>$path['access_name']];}
        catch(Throwable $e){$this->pdo->prepare("UPDATE bh_access_commands SET status='failed',error_text=? WHERE id=?")->execute([$e->getMessage(),$accessCommandId]);throw $e;}
    }

    public function accessCommandStatus(string $correlation,?int $localUserId=null,?int $personId=null): array
    {
        $q=$this->pdo->prepare("SELECT c.*,ap.name access_name FROM bh_access_commands c JOIN bh_access_points ap ON ap.id=c.access_point_id WHERE c.installation_id=? AND c.correlation_uuid=? LIMIT 1");$q->execute([$this->installationId(),$correlation]);$r=$q->fetch();if(!is_array($r))throw new RuntimeException('Solicitud de apertura no encontrada.');if($localUserId!==null&&(int)($r['actor_local_user_id']??0)!==$localUserId)throw new RuntimeException('La solicitud no pertenece a esta sesión.');if($personId!==null&&(int)($r['actor_person_id']??0)!==$personId)throw new RuntimeException('La solicitud no pertenece a esta persona.');return ['correlation_uuid'=>$correlation,'status'=>$r['status'],'access_name'=>$r['access_name'],'error'=>$r['error_text'],'requested_at'=>$r['requested_at'],'confirmed_at'=>$r['confirmed_at']];
    }

    private function propertyDepartmentCode(int $propertyId,string $override=''): string
    {
        if(trim($override)!=='')return trim($override);$cid=$this->connectorId();$q=$this->pdo->prepare("SELECT external_id FROM bh_external_mappings WHERE connector_instance_id=? AND local_entity_type='property' AND local_entity_id=? AND external_object_type='department' LIMIT 1");$q->execute([$cid,$propertyId]);$code=trim((string)$q->fetchColumn());if($code!=='')return $code;$this->autoMapDepartments();$q->execute([$cid,$propertyId]);$code=trim((string)$q->fetchColumn());if($code==='')throw new RuntimeException('La unidad Property todavía no está conciliada con un departamento ZKBio. Actualice el inventario o indique el código de departamento ZK.');return $code;
    }

    public function personPin(int $personId,bool $allowAutoMatch=true): string
    {
        $cid=$this->connectorId();$q=$this->pdo->prepare("SELECT external_id FROM bh_external_mappings WHERE connector_instance_id=? AND local_entity_type='person' AND local_entity_id=? AND external_object_type='person' LIMIT 1");$q->execute([$cid,$personId]);$pin=trim((string)$q->fetchColumn());if($pin===''&&$allowAutoMatch){$this->autoMapPersons();$q->execute([$cid,$personId]);$pin=trim((string)$q->fetchColumn());}return $pin;
    }

    private function generatePin(): string
    {
        $cid=$this->connectorId();for($i=0;$i<2000;$i++){$pin=(string)(90000000+random_int(0,9999999));$q=$this->pdo->prepare("SELECT COUNT(*) FROM bh_external_objects WHERE connector_instance_id=? AND object_type='person' AND external_code=?");$q->execute([$cid,$pin]);if((int)$q->fetchColumn()===0){$m=$this->pdo->prepare("SELECT COUNT(*) FROM bh_external_mappings WHERE connector_instance_id=? AND local_entity_type='person' AND external_id=?");$m->execute([$cid,$pin]);if((int)$m->fetchColumn()===0)return $pin;}}throw new RuntimeException('No se pudo generar un PIN ZKBio libre.');
    }

    public function queuePersonProvision(int $personId,int $propertyId,array $options=[],?int $userId=null): array
    {
        $this->assertNode();$iid=$this->installationId();$cid=$this->connectorId();$q=$this->pdo->prepare("SELECT p.*,m.starts_at,m.ends_at FROM bh_people p LEFT JOIN bh_property_memberships m ON m.person_id=p.id AND m.property_id=? AND m.status='active' WHERE p.id=? AND p.installation_id=? LIMIT 1");$q->execute([$propertyId,$personId,$iid]);$p=$q->fetch();if(!is_array($p))throw new InvalidArgumentException('Persona Property no encontrada en esa unidad.');$pin=$this->personPin($personId,false);if($pin==='')$pin=trim((string)($options['zk_pin']??''));if($pin==='')$pin=$this->generatePin();if(!preg_match('/^[A-Za-z0-9_-]{1,30}$/',$pin))throw new InvalidArgumentException('El PIN ZKBio contiene caracteres no admitidos por el laboratorio.');$dept=$this->propertyDepartmentCode($propertyId,(string)($options['zk_dept_code']??''));$levels=$options['zk_access_level_ids']??[];if(!is_array($levels))$levels=array_filter(array_map('trim',explode(',',(string)$levels)));$levels=array_values(array_unique(array_filter(array_map('strval',$levels))));$body=['pin'=>$pin,'deptCode'=>$dept,'name'=>(string)($p['first_name']?:$p['display_name']),'lastName'=>(string)($p['last_name']??''),'gender'=>'','cardNo'=>'','personPhoto'=>'','accLevelIds'=>implode(',',$levels),'accStartTime'=>$p['starts_at']?date('Y-m-d H:i:s',strtotime((string)$p['starts_at'])):'','accEndTime'=>$p['ends_at']?date('Y-m-d H:i:s',strtotime((string)$p['ends_at'])):''];
        $this->pdo->prepare("INSERT INTO bh_external_mappings (installation_id,connector_instance_id,local_entity_type,local_entity_id,external_object_type,external_id,external_name,authority,sync_status,last_error) VALUES (?,?,'person',?,'person',?,?,'property_hub','pending',NULL) ON DUPLICATE KEY UPDATE external_id=VALUES(external_id),external_name=VALUES(external_name),authority='property_hub',sync_status='pending',last_error=NULL")->execute([$iid,$cid,$personId,$pin,(string)$p['display_name']]);$agent=$this->preferredAgent();return (new EdgeNodeService($this->pdo))->queueZkBioCommand((int)$agent['id'],'zkbio.create_person',['person'=>$body,'context'=>['person_id'=>$personId,'property_id'=>$propertyId,'pin'=>$pin,'dept_code'=>$dept]],$userId);
    }

    public function queueVisitorProvision(int $invitationId,?int $userId=null): array
    {
        $iid=$this->installationId();$q=$this->pdo->prepare("SELECT i.*,p.display_name host_name FROM bh_invitations i JOIN bh_people p ON p.id=i.host_person_id WHERE i.id=? AND i.installation_id=? LIMIT 1");$q->execute([$invitationId,$iid]);$i=$q->fetch();if(!is_array($i))throw new InvalidArgumentException('Invitación Property no encontrada.');$hostPin=$this->personPin((int)$i['host_person_id'],true);if($hostPin===''){$msg='El anfitrión todavía no está vinculado a una persona ZKBio.';$this->pdo->prepare("UPDATE bh_invitations SET external_provider='zkbio',external_sync_status='warning',external_error=? WHERE id=?")->execute([$msg,$invitationId]);throw new RuntimeException($msg);}$cert=trim((string)($i['visitor_document']??''));if($cert==='')$cert=(string)$i['access_code'];$body=['persPersonPin'=>$hostPin,'certType'=>'8','certNum'=>$cert,'visEmpName'=>(string)$i['visitor_name'],'visitEmpPhone'=>(string)($i['visitor_phone']??''),'company'=>'','visitReason'=>'Property Hub invitation','visitorCount'=>'1','startTime'=>date('Y-m-d H:i:s',strtotime((string)$i['starts_at'])),'endTime'=>date('Y-m-d H:i:s',strtotime((string)$i['ends_at'])),'cardNo'=>'','visLevels'=>''];$agent=$this->preferredAgent();$queued=(new EdgeNodeService($this->pdo))->queueZkBioCommand((int)$agent['id'],'zkbio.create_visitor',['visitor'=>$body,'context'=>['invitation_id'=>$invitationId,'cert_num'=>$cert,'host_pin'=>$hostPin]],$userId);$this->pdo->prepare("UPDATE bh_invitations SET external_provider='zkbio',external_sync_status='pending',external_command_uuid=?,external_error=NULL WHERE id=?")->execute([(string)$queued['command_uuid'],$invitationId]);return $queued;
    }

    public function saveVehicle(array $data,?int $residentPersonId=null,?int $residentPropertyId=null): int
    {
        $iid=$this->installationId();$propertyId=$residentPropertyId?:max(0,(int)($data['property_id']??0));$personId=$residentPersonId?:max(0,(int)($data['person_id']??0));if($propertyId<1)throw new InvalidArgumentException('Seleccione la unidad del vehículo.');$p=$this->pdo->prepare('SELECT COUNT(*) FROM bh_properties WHERE id=? AND installation_id=?');$p->execute([$propertyId,$iid]);if((int)$p->fetchColumn()!==1)throw new InvalidArgumentException('Unidad no válida.');if($personId>0){$p=$this->pdo->prepare('SELECT COUNT(*) FROM bh_people WHERE id=? AND installation_id=?');$p->execute([$personId,$iid]);if((int)$p->fetchColumn()!==1)throw new InvalidArgumentException('Persona no válida.');if($residentPersonId){$m=$this->pdo->prepare("SELECT COUNT(*) FROM bh_property_memberships WHERE person_id=? AND property_id=? AND status='active'");$m->execute([$personId,$propertyId]);if((int)$m->fetchColumn()<1)throw new RuntimeException('No puede registrar vehículos para otra unidad.');}}
        $plate=mb_strtoupper(trim((string)($data['plate']??'')));$id=max(0,(int)($data['id']??0));$levels=$data['zk_access_level_ids']??[];if(!is_array($levels))$levels=array_filter(array_map('trim',explode(',',(string)$levels)));$levelText=implode(',',array_values(array_unique(array_filter(array_map('strval',$levels)))));$make=trim((string)($data['make']??''));$model=trim((string)($data['model']??''));$display=trim((string)($data['display_name']??''));if($display==='')$display=trim($make.' '.$model);if($display==='')$display=$plate!==''?$plate:'Vehículo';$vals=[$propertyId,$personId?:null,$display,$plate!==''?$plate:null,$make?:null,$model?:null,trim((string)($data['color']??''))?:null,trim((string)($data['notes']??''))?:null,$levelText?:null];if($id>0){$this->pdo->prepare("UPDATE bh_vehicles SET property_id=?,person_id=?,display_name=?,plate=?,make=?,model=?,color=?,notes=?,zk_access_level_ids=?,status='active' WHERE id=? AND installation_id=?")->execute([...$vals,$id,$iid]);}else{$this->pdo->prepare("INSERT INTO bh_vehicles (public_uuid,installation_id,property_id,person_id,display_name,plate,make,model,color,notes,zk_access_level_ids,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,'active')")->execute([self::uuid(),$iid,...$vals]);$id=(int)$this->pdo->lastInsertId();}
        $rfid=trim((string)($data['rfid_card_no']??''));if($rfid!==''){$this->saveVehicleCredential($id,'rfid',$rfid,'zkbio',true);}return $id;
    }

    private function saveVehicleCredential(int $vehicleId,string $type,string $value,?string $provider,bool $primary=false): int
    {
        $iid=$this->installationId();$type=strtolower(trim($type));$value=trim($value);if($value==='')throw new InvalidArgumentException('La credencial del vehículo está vacía.');if(!in_array($type,['rfid','card','plate_lpr','qr','mobile','other'],true))throw new InvalidArgumentException('Tipo de credencial vehicular no válido.');
        $q=$this->pdo->prepare("SELECT id FROM bh_vehicle_credentials WHERE installation_id=? AND provider<=>? AND credential_type=? AND credential_value=? LIMIT 1");$q->execute([$iid,$provider,$type,$value]);$id=(int)$q->fetchColumn();if($id>0){$this->pdo->prepare("UPDATE bh_vehicle_credentials SET vehicle_id=?,is_primary=?,status='active',external_error=NULL WHERE id=?")->execute([$vehicleId,$primary?1:0,$id]);return $id;}
        $this->pdo->prepare("INSERT INTO bh_vehicle_credentials (public_uuid,installation_id,vehicle_id,credential_type,credential_value,provider,is_primary,status) VALUES (?,?,?,?,?,?,?,'active')")->execute([self::uuid(),$iid,$vehicleId,$type,$value,$provider,$primary?1:0]);return (int)$this->pdo->lastInsertId();
    }

    public function vehicles(?int $personId=null,?int $propertyId=null): array
    {
        $sql="SELECT v.*,p.display_name person_name,pr.name property_name,(SELECT vc.credential_value FROM bh_vehicle_credentials vc WHERE vc.vehicle_id=v.id AND vc.status='active' AND vc.credential_type IN ('rfid','card') ORDER BY vc.is_primary DESC,vc.id LIMIT 1) rfid_card_no FROM bh_vehicles v LEFT JOIN bh_people p ON p.id=v.person_id JOIN bh_properties pr ON pr.id=v.property_id WHERE v.installation_id=? AND v.status<>'inactive'";$args=[$this->installationId()];if($personId){$sql.=' AND v.person_id=?';$args[]=$personId;}if($propertyId){$sql.=' AND v.property_id=?';$args[]=$propertyId;}$sql.=' ORDER BY COALESCE(v.display_name,v.plate),v.id';$q=$this->pdo->prepare($sql);$q->execute($args);return $q->fetchAll()?:[];
    }

    private function generateVehiclePin(): string
    {
        $cid=$this->connectorId();for($i=0;$i<2000;$i++){$pin=(string)(70000000+random_int(0,9999999));$q=$this->pdo->prepare("SELECT COUNT(*) FROM bh_external_objects WHERE connector_instance_id=? AND object_type='person' AND external_code=?");$q->execute([$cid,$pin]);if((int)$q->fetchColumn()===0){$m=$this->pdo->prepare("SELECT COUNT(*) FROM bh_external_mappings WHERE connector_instance_id=? AND external_id=?");$m->execute([$cid,$pin]);if((int)$m->fetchColumn()===0)return $pin;}}throw new RuntimeException('No se pudo generar un PIN ZKBio libre para el vehículo.');
    }

    public function queueVehicleProvision(int $vehicleId,?int $userId=null): array
    {
        $q=$this->pdo->prepare("SELECT v.*,(SELECT vc.credential_value FROM bh_vehicle_credentials vc WHERE vc.vehicle_id=v.id AND vc.status='active' AND vc.credential_type IN ('rfid','card') ORDER BY vc.is_primary DESC,vc.id LIMIT 1) rfid_card_no FROM bh_vehicles v WHERE v.id=? AND v.installation_id=? LIMIT 1");$q->execute([$vehicleId,$this->installationId()]);$v=$q->fetch();if(!is_array($v))throw new InvalidArgumentException('Vehículo Property no encontrado.');$rfid=trim((string)($v['rfid_card_no']??''));if($rfid===''){$msg='Premier autentica vehículos por etiqueta RFID/cardNo. Registre la etiqueta RFID del parabrisas antes de provisionar ZKBio.';$this->pdo->prepare("UPDATE bh_vehicles SET external_provider='zkbio',external_sync_status='warning',external_error=? WHERE id=?")->execute([$msg,$vehicleId]);throw new RuntimeException($msg);}
        $existing=$this->pdo->prepare("SELECT external_id FROM bh_external_mappings WHERE connector_instance_id=? AND local_entity_type='vehicle' AND local_entity_id=? AND external_object_type='person' LIMIT 1");$existing->execute([$this->connectorId(),$vehicleId]);$pin=trim((string)$existing->fetchColumn());if($pin==='')$pin=$this->generateVehiclePin();$dept=$this->propertyDepartmentCode((int)$v['property_id']);$levels=array_values(array_filter(array_map('trim',explode(',',(string)($v['zk_access_level_ids']??'')))));
        $make=trim((string)($v['make']??''));$model=trim((string)($v['model']??''));$body=['mode'=>'rfid_person','pin'=>$pin,'deptCode'=>$dept,'name'=>$make!==''?$make:'VEHICULO','lastName'=>$model!==''?$model:(string)$v['plate'],'gender'=>'','cardNo'=>$rfid,'personPhoto'=>'','accLevelIds'=>implode(',',$levels),'accStartTime'=>'','accEndTime'=>'','plate'=>(string)$v['plate']];$agent=$this->preferredAgent();$queued=(new EdgeNodeService($this->pdo))->queueZkBioCommand((int)$agent['id'],'zkbio.create_vehicle',['vehicle'=>$body,'context'=>['vehicle_id'=>$vehicleId,'person_id'=>(int)($v['person_id']??0),'pin'=>$pin,'plate'=>$v['plate'],'display_name'=>(string)($v['display_name']??''),'rfid_card_no'=>$rfid,'dept_code'=>$dept,'mode'=>'rfid_person']],$userId);$this->pdo->prepare("UPDATE bh_vehicles SET external_provider='zkbio',external_sync_status='pending',external_command_uuid=?,external_error=NULL WHERE id=?")->execute([(string)$queued['command_uuid'],$vehicleId]);$this->pdo->prepare("UPDATE bh_vehicle_credentials SET external_sync_status='pending',external_error=NULL WHERE vehicle_id=? AND credential_value=? AND provider='zkbio'")->execute([$vehicleId,$rfid]);return $queued;
    }

    public function handleCommandCompletion(array $command,bool $ok,mixed $result,string $error): array
    {
        $type=(string)($command['command_type']??'');$payload=json_decode((string)($command['payload_json']??''),true);if(!is_array($payload))$payload=[];$ctx=(array)($payload['context']??[]);$safeResult=is_array($result)?$result:[];$extra=[];
        if($type==='zkbio.inventory'&&$ok){$sets=(array)($safeResult['inventory']??[]);$extra['inventory']=$this->persistInventory($sets);}
        if($type==='zkbio.remote_open'){$id=(int)($ctx['access_command_id']??0);if($id>0){$this->pdo->prepare("UPDATE bh_access_commands SET status=?,sent_at=COALESCE(sent_at,NOW()),confirmed_at=?,error_text=? WHERE id=? AND installation_id=?")->execute([$ok?'confirmed':'failed',$ok?date('Y-m-d H:i:s'):null,$ok?null:($error?:'ZKBio rechazó la apertura'),$id,$this->installationId()]);if($ok){$q=$this->pdo->prepare('SELECT * FROM bh_access_commands WHERE id=?');$q->execute([$id]);$c=$q->fetch();if(is_array($c)){$this->pdo->prepare("INSERT INTO bh_access_events (installation_id,access_point_id,command_id,connector_instance_id,external_event_id,person_id,property_id,event_time,event_type,credential_type,source_system,result,correlation_status,raw_payload_json) VALUES (?,?,?,?,?,?,?,?,?,'mobile','zkbio','success','property_only',?)")->execute([$this->installationId(),$c['access_point_id'],$id,$this->connectorId(),'property-command-'.(string)$c['correlation_uuid'],$c['actor_person_id'],$c['property_id'],date('Y-m-d H:i:s'),'REMOTE_OPEN_CONFIRMED',json_encode($safeResult,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);}}}}
        if($type==='zkbio.create_person'){$personId=(int)($ctx['person_id']??0);if($personId>0)$this->pdo->prepare("UPDATE bh_external_mappings SET sync_status=?,last_synced_at=?,last_error=? WHERE connector_instance_id=? AND local_entity_type='person' AND local_entity_id=?")->execute([$ok?'synced':'error',$ok?date('Y-m-d H:i:s'):null,$ok?null:($error?:'Error ZKBio'),$this->connectorId(),$personId]);}
        if($type==='zkbio.create_visitor'){$id=(int)($ctx['invitation_id']??0);if($id>0){$extId='';$data=$safeResult['response']['data']??$safeResult['data']??null;if(is_array($data))$extId=trim((string)($data['id']??$data['pin']??''));$this->pdo->prepare("UPDATE bh_invitations SET external_sync_status=?,external_id=?,external_error=?,external_response_json=? WHERE id=? AND installation_id=?")->execute([$ok?'synced':'error',$extId?:null,$ok?null:($error?:'Error ZKBio'),json_encode($safeResult,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$id,$this->installationId()]);}}
        if($type==='zkbio.create_vehicle'){$id=(int)($ctx['vehicle_id']??0);if($id>0){$pin=(string)($ctx['pin']??'');$rfid=(string)($ctx['rfid_card_no']??'');$this->pdo->prepare("UPDATE bh_vehicles SET external_sync_status=?,external_id=?,external_error=?,external_response_json=? WHERE id=? AND installation_id=?")->execute([$ok?'synced':'error',$ok?$pin:null,$ok?null:($error?:'Error ZKBio'),json_encode($safeResult,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$id,$this->installationId()]);$this->pdo->prepare("UPDATE bh_vehicle_credentials SET external_sync_status=?,external_id=?,external_error=? WHERE vehicle_id=? AND provider='zkbio' AND credential_value=?")->execute([$ok?'synced':'error',$ok?$rfid:null,$ok?null:($error?:'Error ZKBio'),$id,$rfid]);if($ok){$this->pdo->prepare("INSERT INTO bh_external_mappings (installation_id,connector_instance_id,local_entity_type,local_entity_id,external_object_type,external_id,external_name,authority,sync_status,last_synced_at) VALUES (?,?,'vehicle',?,'person',?,?,'property_hub','synced',NOW()) ON DUPLICATE KEY UPDATE external_object_type='person',external_id=VALUES(external_id),external_name=VALUES(external_name),sync_status='synced',last_synced_at=NOW(),last_error=NULL")->execute([$this->installationId(),$this->connectorId(),$id,$pin,trim((string)($ctx['display_name']??''))?:('VEHICULO '.(string)($ctx['plate']??''))]);}}}
        return $extra;
    }

    public function departmentReconciliation(): array
    {
        $this->assertNode();$iid=$this->installationId();$cid=$this->connectorId();
        $q=$this->pdo->prepare("SELECT id,code,name,property_type,parent_id FROM bh_properties WHERE installation_id=? AND property_type IN ('apartment','office','commercial_unit','house','townhouse','warehouse','storage') AND status<>'inactive' ORDER BY code,name");$q->execute([$iid]);$units=$q->fetchAll()?:[];
        $q=$this->pdo->prepare("SELECT external_id,external_code,name,payload_json FROM bh_external_objects WHERE installation_id=? AND connector_instance_id=? AND object_type='department' ORDER BY name,external_code");$q->execute([$iid,$cid]);$departments=$q->fetchAll()?:[];
        $q=$this->pdo->prepare("SELECT local_entity_id,external_id FROM bh_external_mappings WHERE connector_instance_id=? AND local_entity_type='property' AND external_object_type='department'");$q->execute([$cid]);$mapped=[];$used=[];foreach($q->fetchAll()?:[] as $r){$mapped[(int)$r['local_entity_id']]=(string)$r['external_id'];$used[(string)$r['external_id']]=(int)$r['local_entity_id'];}
        $q=$this->pdo->prepare("SELECT payload_json FROM bh_external_objects WHERE installation_id=? AND connector_instance_id=? AND object_type='person'");$q->execute([$iid,$cid]);$counts=[];foreach($q->fetchAll()?:[] as $r){$raw=json_decode((string)($r['payload_json']??''),true);if(!is_array($raw))continue;$d=trim((string)($raw['deptCode']??''));if($d!=='')$counts[$d]=($counts[$d]??0)+1;}
        $deptByKey=[];foreach($departments as $d){$code=trim((string)($d['external_code']?:$d['external_id']));foreach([$code,(string)($d['name']??'')] as $candidate){$k=self::norm($candidate);if($k!==''&&!isset($deptByKey[$k]))$deptByKey[$k]=$d;}}
        $suggestedCount=0;foreach($units as &$u){$current=$mapped[(int)$u['id']]??'';$u['zk_department_code']=$current;$u['zk_people_count']=$current!==''?(int)($counts[$current]??0):0;$u['suggested_department_code']='';$u['suggestion_reason']='';if($current===''){foreach(self::departmentCandidates((string)$u['code'],(string)$u['name']) as $candidate){$k=self::norm($candidate);if(!isset($deptByKey[$k]))continue;$d=$deptByKey[$k];$external=(string)($d['external_code']?:$d['external_id']);if($external===''||isset($used[$external]))continue;$u['suggested_department_code']=$external;$u['suggestion_reason']=$candidate;$used[$external]=(int)$u['id'];$suggestedCount++;break;}}}
        unset($u);return ['units'=>$units,'departments'=>$departments,'mapped_count'=>count($mapped),'suggested_count'=>$suggestedCount];
    }

    public function bulkMapSuggestedDepartments(): array
    {
        $this->assertNode();$recon=$this->departmentReconciliation();$iid=$this->installationId();$cid=$this->connectorId();$result=['linked'=>0,'skipped'=>0];
        $deptNames=[];foreach($recon['departments']??[] as $d){$external=(string)($d['external_code']?:$d['external_id']);if($external!=='')$deptNames[$external]=(string)($d['name']?:$external);}
        $ins=$this->pdo->prepare("INSERT INTO bh_external_mappings (installation_id,connector_instance_id,local_entity_type,local_entity_id,external_object_type,external_id,external_name,authority,sync_status,last_synced_at) VALUES (?,?,'property',?,'department',?,?,'shared','synced',NOW())");
        $this->pdo->beginTransaction();try{foreach($recon['units']??[] as $u){if(trim((string)($u['zk_department_code']??''))!==''){$result['skipped']++;continue;}$external=trim((string)($u['suggested_department_code']??''));if($external===''||!isset($deptNames[$external])){$result['skipped']++;continue;}$q=$this->pdo->prepare("SELECT COUNT(*) FROM bh_external_mappings WHERE connector_instance_id=? AND local_entity_type='property' AND external_object_type='department' AND external_id=?");$q->execute([$cid,$external]);if((int)$q->fetchColumn()>0){$result['skipped']++;continue;}$ins->execute([$iid,$cid,(int)$u['id'],$external,$deptNames[$external]]);$result['linked']++;}$this->pdo->commit();}catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
        $result['persons_auto_mapped']=$this->autoMapPersons();return $result;
    }

    /**
     * Adopta de una sola vez todo el contenido de los departamentos ZKBio vinculados.
     * Conserva la adopción por unidad; esta es la ruta masiva global solicitada para
     * instalaciones grandes. Opcionalmente vincula primero las coincidencias inequívocas.
     */
    public function adoptAllFoundSubjects(bool $mapSuggested=true,string $relationshipMode='infer',?int $profileId=null,bool $normalizeNames=true): array
    {
        $this->assertNode();
        $result=['units_processed'=>0,'units_with_content'=>0,'departments_linked'=>0,'persons_created'=>0,'persons_linked'=>0,'vehicles_created'=>0,'reclassified'=>0,'already'=>0,'skipped'=>0,'names_normalized'=>0,'names_unchanged'=>0,'errors'=>[]];
        if($mapSuggested){$mapped=$this->bulkMapSuggestedDepartments();$result['departments_linked']=(int)($mapped['linked']??0);}
        $recon=$this->departmentReconciliation();
        foreach($recon['units']??[] as $unit){
            $propertyId=(int)($unit['id']??0);$department=trim((string)($unit['zk_department_code']??''));$count=(int)($unit['zk_people_count']??0);
            if($propertyId<1||$department===''||$count<1)continue;
            $result['units_with_content']++;
            try{
                $one=$this->adoptDepartmentSubjects($propertyId,$relationshipMode,$profileId,$normalizeNames);$result['units_processed']++;
                foreach(['persons_created','persons_linked','vehicles_created','reclassified','already','skipped','names_normalized','names_unchanged'] as $key)$result[$key]+=(int)($one[$key]??0);
                foreach((array)($one['errors']??[]) as $error)$result['errors'][]=(string)($unit['code']??$unit['name']??('#'.$propertyId)).': '.$error;
            }catch(Throwable $e){$result['skipped']+=$count;$result['errors'][]=(string)($unit['code']??$unit['name']??('#'.$propertyId)).': '.$e->getMessage();}
        }
        return $result;
    }

    public function mapDepartment(int $propertyId,string $departmentCode): void
    {
        $this->assertNode();$departmentCode=trim($departmentCode);if($propertyId<1||$departmentCode==='')throw new InvalidArgumentException('Seleccione la unidad Property y el departamento ZKBio.');$iid=$this->installationId();$cid=$this->connectorId();
        $q=$this->pdo->prepare("SELECT id,name FROM bh_properties WHERE id=? AND installation_id=? AND property_type IN ('apartment','office','commercial_unit','house','townhouse','warehouse','storage') LIMIT 1");$q->execute([$propertyId,$iid]);$property=$q->fetch();if(!is_array($property))throw new InvalidArgumentException('La unidad Property no es válida.');
        $q=$this->pdo->prepare("SELECT external_id,name FROM bh_external_objects WHERE installation_id=? AND connector_instance_id=? AND object_type='department' AND (external_id=? OR external_code=?) LIMIT 1");$q->execute([$iid,$cid,$departmentCode,$departmentCode]);$dept=$q->fetch();if(!is_array($dept))throw new InvalidArgumentException('Departamento ZKBio no encontrado en el inventario. Actualice el inventario primero.');$external=(string)$dept['external_id'];
        $q=$this->pdo->prepare("SELECT local_entity_id FROM bh_external_mappings WHERE connector_instance_id=? AND local_entity_type='property' AND external_object_type='department' AND external_id=? AND local_entity_id<>? LIMIT 1");$q->execute([$cid,$external,$propertyId]);$other=(int)$q->fetchColumn();if($other>0)throw new RuntimeException('Ese departamento ZKBio ya está vinculado a otra unidad Property. Desvincúlelo primero.');
        $this->pdo->prepare("DELETE FROM bh_external_mappings WHERE connector_instance_id=? AND local_entity_type='property' AND local_entity_id=? AND external_object_type='department'")->execute([$cid,$propertyId]);
        $this->pdo->prepare("INSERT INTO bh_external_mappings (installation_id,connector_instance_id,local_entity_type,local_entity_id,external_object_type,external_id,external_name,authority,sync_status,last_synced_at) VALUES (?,?,'property',?,'department',?,?,'shared','synced',NOW())")->execute([$iid,$cid,$propertyId,$external,(string)($dept['name']?:$external)]);
        $this->autoMapPersons();
    }

    public function unmapDepartment(int $propertyId): void
    {
        $this->assertNode();$this->pdo->prepare("DELETE FROM bh_external_mappings WHERE connector_instance_id=? AND local_entity_type='property' AND local_entity_id=? AND external_object_type='department'")->execute([$this->connectorId(),$propertyId]);
    }

    public function peopleByMappedProperty(): array
    {
        $this->assertNode();$iid=$this->installationId();$cid=$this->connectorId();
        $q=$this->pdo->prepare("SELECT local_entity_id,external_id FROM bh_external_mappings WHERE connector_instance_id=? AND local_entity_type='property' AND external_object_type='department'");$q->execute([$cid]);$deptToProperty=[];foreach($q->fetchAll()?:[] as $r)$deptToProperty[(string)$r['external_id']]=(int)$r['local_entity_id'];
        // En esta integración external_id del mapping de persona/vehículo es el PIN ZK.
        $mappedPeople=[];$q=$this->pdo->prepare("SELECT local_entity_id,external_id FROM bh_external_mappings WHERE connector_instance_id=? AND local_entity_type='person' AND external_object_type='person'");$q->execute([$cid]);foreach($q->fetchAll()?:[] as $r)$mappedPeople[(string)$r['external_id']]=(int)$r['local_entity_id'];
        $mappedVehicles=[];$q=$this->pdo->prepare("SELECT local_entity_id,external_id FROM bh_external_mappings WHERE connector_instance_id=? AND local_entity_type='vehicle' AND external_object_type='person'");$q->execute([$cid]);foreach($q->fetchAll()?:[] as $r)$mappedVehicles[(string)$r['external_id']]=(int)$r['local_entity_id'];
        $levels=[];$q=$this->pdo->prepare("SELECT external_id,name FROM bh_external_objects WHERE connector_instance_id=? AND object_type='access_level'");$q->execute([$cid]);foreach($q->fetchAll()?:[] as $r)$levels[(string)$r['external_id']]=(string)($r['name']?:$r['external_id']);
        $q=$this->pdo->prepare("SELECT external_id,external_code,name,payload_json FROM bh_external_objects WHERE installation_id=? AND connector_instance_id=? AND object_type='person' ORDER BY name,external_code");$q->execute([$iid,$cid]);$out=[];
        foreach($q->fetchAll()?:[] as $r){$raw=json_decode((string)($r['payload_json']??''),true);if(!is_array($raw))$raw=[];$dept=trim((string)($raw['deptCode']??''));$propertyId=$deptToProperty[$dept]??0;if($propertyId<1)continue;$ids=array_filter(array_map('trim',explode(',',(string)($raw['accLevelIds']??''))));$levelNames=[];foreach($ids as $id)if(isset($levels[$id]))$levelNames[]=$levels[$id];$pin=trim((string)($raw['pin']??$r['external_code']??''));$fullName=trim((string)($raw['name']??'').' '.(string)($raw['lastName']??''));$isVehicle=self::isVehicleIdentity($levelNames,$raw);$card=trim((string)($raw['cardNo']??''));$plate=trim((string)($raw['carPlate']??''));
            $out[$propertyId][]=['external_object_id'=>(string)$r['external_id'],'external_id'=>(string)$r['external_id'],'pin'=>$pin,'first_name'=>(string)($raw['name']??''),'last_name'=>(string)($raw['lastName']??''),'name'=>$fullName?:($isVehicle?'Vehículo ZK':'Persona ZK'),'dept_code'=>$dept,'card_no'=>$card,'car_plate'=>$plate,'email'=>(string)($raw['email']??''),'phone'=>(string)($raw['mobilePhone']??''),'cert_type'=>(string)($raw['certType']??''),'cert_number'=>(string)($raw['certNumber']??''),'access_level_ids'=>$ids,'access_level_names'=>$levelNames,'subject_type'=>$isVehicle?'vehicle':'person','mapped_person_id'=>$mappedPeople[$pin]??0,'mapped_vehicle_id'=>$mappedVehicles[$pin]??0,'suggested_relationship'=>$this->inferRelationship($levelNames)];}
        return $out;
    }

    private function inferRelationship(array $levelNames): string
    {
        $s=mb_strtoupper(implode(' | ',$levelNames));if(str_contains($s,'PROPIET'))return 'owner';if(str_contains($s,'INQUIL'))return 'tenant';if(str_contains($s,'CHOFER')||str_contains($s,'DRIVER'))return 'driver';if(str_contains($s,'MANTEN'))return 'maintenance';if(str_contains($s,'SEGUR')||str_contains($s,'VIGIL'))return 'security';return 'resident';
    }

    public function adoptDepartmentSubjects(int $propertyId,string $relationshipMode='infer',?int $profileId=null,bool $normalizeNames=false): array
    {
        $this->assertNode();$subjects=$this->peopleByMappedProperty()[$propertyId]??[];if(!$subjects)throw new RuntimeException('El departamento ZKBio vinculado no tiene identidades disponibles para adoptar.');
        $pins=[];foreach($subjects as $subject){$pin=trim((string)($subject['pin']??''));if($pin!=='')$pins[]=$pin;}
        if(!$pins)throw new RuntimeException('Las identidades encontradas no tienen PIN ZKBio utilizable.');
        return $this->adoptSelectedSubjects($propertyId,$pins,'auto',$relationshipMode,$profileId,$normalizeNames);
    }

    public function adoptDepartmentPeople(int $propertyId,string $relationshipMode='infer',?int $profileId=null): array
    {
        $this->assertNode();$subjects=$this->peopleByMappedProperty()[$propertyId]??[];$people=array_values(array_filter($subjects,static fn(array $r):bool=>(string)($r['subject_type']??'person')==='person'));if(!$people)throw new RuntimeException('El departamento ZKBio vinculado no tiene personas humanas disponibles para adoptar. Las identidades vehiculares se mantienen separadas.');$allowed=['infer','owner','tenant','resident','family','driver','security','maintenance','authorized'];if(!in_array($relationshipMode,$allowed,true))$relationshipMode='infer';$repo=new NodeRepository($this->pdo);$iid=$this->installationId();$cid=$this->connectorId();$result=['created'=>0,'linked'=>0,'already'=>0,'vehicles_skipped'=>count($subjects)-count($people)];
        foreach($people as $zk){$pin=trim((string)$zk['pin']);if($pin==='')continue;$personId=(int)($zk['mapped_person_id']??0);$created=false;
            if($personId<1){$candidates=[];$email=trim((string)$zk['email']);if($email!==''){$q=$this->pdo->prepare("SELECT id FROM bh_people WHERE installation_id=? AND LOWER(email)=LOWER(?) LIMIT 2");$q->execute([$iid,$email]);$candidates=$q->fetchAll(PDO::FETCH_COLUMN)?:[];}
                if(count($candidates)!==1){$q=$this->pdo->prepare("SELECT p.id FROM bh_people p JOIN bh_property_memberships m ON m.person_id=p.id AND m.property_id=? AND m.status='active' WHERE p.installation_id=? AND UPPER(p.display_name)=UPPER(?) LIMIT 2");$q->execute([$propertyId,$iid,(string)$zk['name']]);$candidates=$q->fetchAll(PDO::FETCH_COLUMN)?:[];}
                if(count($candidates)===1)$personId=(int)$candidates[0];else{$first=(string)($zk['first_name']??'');$last=(string)($zk['last_name']??'');if(trim($first)===''&&trim($last)===''){$parts=preg_split('/\s+/u',trim((string)$zk['name']),2)?:[];$first=(string)($parts[0]??$zk['name']);$last=(string)($parts[1]??'');}$rel=$relationshipMode==='infer'?(string)$zk['suggested_relationship']:$relationshipMode;$personId=$repo->savePerson(['first_name'=>$first,'last_name'=>$last,'display_name'=>(string)$zk['name'],'document_type'=>'','document_number'=>'','email'=>$email,'phone'=>(string)$zk['phone'],'status'=>'active','property_id'=>$propertyId,'relationship_type'=>$rel,'access_profile_id'=>$profileId?:0,'is_primary'=>0],null);$created=true;$result['created']++;}
                $this->pdo->prepare("INSERT INTO bh_external_mappings (installation_id,connector_instance_id,local_entity_type,local_entity_id,external_object_type,external_id,external_name,authority,sync_status,last_synced_at) VALUES (?,?,'person',?,'person',?,?,'external','synced',NOW()) ON DUPLICATE KEY UPDATE external_id=VALUES(external_id),external_name=VALUES(external_name),authority='shared',sync_status='synced',last_synced_at=NOW(),last_error=NULL")->execute([$iid,$cid,$personId,$pin,(string)$zk['name']]);
            }
            $q=$this->pdo->prepare("SELECT COUNT(*) FROM bh_property_memberships WHERE person_id=? AND property_id=? AND status='active'");$q->execute([$personId,$propertyId]);if((int)$q->fetchColumn()===0){$rel=$relationshipMode==='infer'?(string)$zk['suggested_relationship']:$relationshipMode;$repo->saveMembership(['person_id'=>$personId,'property_id'=>$propertyId,'relationship_type'=>$rel,'access_profile_id'=>$profileId?:0,'status'=>'active'],null);$result['linked']++;}elseif(!$created)$result['already']++;
        }
        $this->autoMapPersons();return $result;
    }

    public function adoptSelectedSubjects(int $propertyId,array $pins,string $targetMode='auto',string $relationshipMode='infer',?int $profileId=null,bool $normalizeNames=false): array
    {
        $this->assertNode();$pins=array_values(array_unique(array_filter(array_map('strval',$pins),static fn(string $v):bool=>trim($v)!=='')));if(!$pins)throw new InvalidArgumentException('Seleccione al menos una identidad ZKBio.');
        if(!in_array($targetMode,['auto','person','vehicle'],true))$targetMode='auto';$allowed=['infer','owner','tenant','resident','family','driver','security','maintenance','authorized'];if(!in_array($relationshipMode,$allowed,true))$relationshipMode='infer';
        $subjects=$this->peopleByMappedProperty()[$propertyId]??[];$byPin=[];foreach($subjects as $subject)$byPin[(string)$subject['pin']]=$subject;
        $repo=new NodeRepository($this->pdo);$iid=$this->installationId();$cid=$this->connectorId();$personIds=[];$result=['persons_created'=>0,'persons_linked'=>0,'vehicles_created'=>0,'reclassified'=>0,'already'=>0,'skipped'=>0,'errors'=>[],'names_normalized'=>0,'names_unchanged'=>0];
        foreach($pins as $pin){$zk=$byPin[$pin]??null;if(!is_array($zk)){$result['skipped']++;$result['errors'][]='PIN '.$pin.': no pertenece al departamento/unidad seleccionada.';continue;}$mode=$targetMode==='auto'?(string)($zk['subject_type']??'person'):$targetMode;
            try{
                if($mode==='vehicle'){
                    if((int)($zk['mapped_vehicle_id']??0)>0){$result['already']++;continue;}
                    if((int)($zk['mapped_person_id']??0)>0){$repo->reclassifyPersonToVehicle((int)$zk['mapped_person_id'],['property_id'=>$propertyId,'display_name'=>(string)$zk['name'],'plate'=>(string)$zk['car_plate'],'rfid_card_no'=>(string)$zk['card_no'],'make'=>(string)($zk['first_name']??''),'model'=>(string)($zk['last_name']??''),'zk_access_level_ids'=>implode(',',(array)($zk['access_level_ids']??[]))]);$result['reclassified']++;continue;}
                    $display=trim((string)$zk['name'])?:('Vehículo ZK '.$pin);$plate=mb_strtoupper(trim((string)$zk['car_plate']));$make=trim((string)($zk['first_name']??''));$model=trim((string)($zk['last_name']??''));$levels=implode(',',(array)($zk['access_level_ids']??[]));$rfid=trim((string)$zk['card_no']);
                    // Dedupe conservador: RFID es la identidad más fuerte en Premier; placa es secundaria.
                    // Sólo reutilizamos un Vehicle existente cuando pertenece a la misma unidad. Si apunta a otra,
                    // se detiene la adopción para que el administrador resuelva el conflicto explícitamente.
                    $vehicleId=0;$existingPropertyId=0;
                    if($rfid!==''){$x=$this->pdo->prepare("SELECT v.id,v.property_id FROM bh_vehicle_credentials vc JOIN bh_vehicles v ON v.id=vc.vehicle_id WHERE vc.installation_id=? AND vc.provider='zkbio' AND vc.credential_type IN ('rfid','card') AND vc.credential_value=? AND vc.status='active' LIMIT 1");$x->execute([$iid,$rfid]);if($er=$x->fetch()){$vehicleId=(int)$er['id'];$existingPropertyId=(int)$er['property_id'];}}
                    if($vehicleId<1&&$plate!==''){$x=$this->pdo->prepare("SELECT id,property_id FROM bh_vehicles WHERE installation_id=? AND plate=? AND status<>'inactive' LIMIT 1");$x->execute([$iid,$plate]);if($er=$x->fetch()){$vehicleId=(int)$er['id'];$existingPropertyId=(int)$er['property_id'];}}
                    if($vehicleId>0&&$existingPropertyId!==$propertyId)throw new RuntimeException('La identidad vehicular coincide con un vehículo ya asociado a otra unidad Property. Revise RFID/placa antes de adoptar.');
                    $this->pdo->beginTransaction();try{
                        if($vehicleId<1){$this->pdo->prepare("INSERT INTO bh_vehicles (public_uuid,installation_id,property_id,person_id,display_name,plate,make,model,color,notes,zk_access_level_ids,status,external_provider,external_sync_status,external_id) VALUES (?,?,?,?,?,?,?,?,NULL,?,?, 'active','zkbio','synced',?)")->execute([self::uuid(),$iid,$propertyId,null,$display,$plate!==''?$plate:null,$make?:null,$model?:null,'Adoptado desde identidad ZKBio PIN '.$pin,$levels?:null,$pin]);$vehicleId=(int)$this->pdo->lastInsertId();$result['vehicles_created']++;}
                        else{$this->pdo->prepare("UPDATE bh_vehicles SET display_name=COALESCE(NULLIF(?,''),display_name),plate=COALESCE(NULLIF(?,''),plate),make=COALESCE(NULLIF(?,''),make),model=COALESCE(NULLIF(?,''),model),zk_access_level_ids=COALESCE(NULLIF(?,''),zk_access_level_ids),external_provider='zkbio',external_sync_status='synced',external_id=? WHERE id=? AND installation_id=?")->execute([$display,$plate,$make,$model,$levels,$pin,$vehicleId,$iid]);$result['already']++;}
                        if($rfid!=='')$this->saveVehicleCredential($vehicleId,'rfid',$rfid,'zkbio',true);$this->pdo->prepare("INSERT INTO bh_external_mappings (installation_id,connector_instance_id,local_entity_type,local_entity_id,external_object_type,external_id,external_name,authority,sync_status,last_synced_at) VALUES (?,?,'vehicle',?,'person',?,?,'external','synced',NOW()) ON DUPLICATE KEY UPDATE local_entity_type='vehicle',local_entity_id=VALUES(local_entity_id),external_name=VALUES(external_name),authority='shared',sync_status='synced',last_synced_at=NOW(),last_error=NULL")->execute([$iid,$cid,$vehicleId,$pin,$display]);$this->pdo->prepare("UPDATE bh_external_objects SET adoption_status='adopted',adopted_at=NOW() WHERE installation_id=? AND connector_instance_id=? AND object_type='person' AND (external_code=? OR external_id=?)")->execute([$iid,$cid,$pin,$pin]);$this->pdo->commit();}catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}continue;
                }
                if((int)($zk['mapped_person_id']??0)>0){$personId=(int)$zk['mapped_person_id'];$result['already']++;}elseif((int)($zk['mapped_vehicle_id']??0)>0){throw new RuntimeException('Ya está adoptado como vehículo. La conversión vehículo → persona requiere revisión individual.');}else{
                    $email=trim((string)$zk['email']);$candidates=[];if($email!==''){$q=$this->pdo->prepare("SELECT id FROM bh_people WHERE installation_id=? AND LOWER(email)=LOWER(?) LIMIT 2");$q->execute([$iid,$email]);$candidates=$q->fetchAll(PDO::FETCH_COLUMN)?:[];}if(count($candidates)!==1){$q=$this->pdo->prepare("SELECT p.id FROM bh_people p JOIN bh_property_memberships m ON m.person_id=p.id AND m.property_id=? AND m.status='active' WHERE p.installation_id=? AND UPPER(p.display_name)=UPPER(?) LIMIT 2");$q->execute([$propertyId,$iid,(string)$zk['name']]);$candidates=$q->fetchAll(PDO::FETCH_COLUMN)?:[];}
                    if(count($candidates)===1)$personId=(int)$candidates[0];else{$first=(string)($zk['first_name']??'');$last=(string)($zk['last_name']??'');$rel=$relationshipMode==='infer'?(string)$zk['suggested_relationship']:$relationshipMode;$personId=$repo->savePerson(['first_name'=>$first,'last_name'=>$last,'display_name'=>(string)$zk['name'],'document_type'=>'','document_number'=>'','email'=>$email,'phone'=>(string)$zk['phone'],'status'=>'active','property_id'=>$propertyId,'relationship_type'=>$rel,'access_profile_id'=>$profileId?:0,'is_primary'=>0],null);$result['persons_created']++;}
                    $this->pdo->prepare("INSERT INTO bh_external_mappings (installation_id,connector_instance_id,local_entity_type,local_entity_id,external_object_type,external_id,external_name,authority,sync_status,last_synced_at) VALUES (?,?,'person',?,'person',?,?,'external','synced',NOW()) ON DUPLICATE KEY UPDATE local_entity_type='person',local_entity_id=VALUES(local_entity_id),external_name=VALUES(external_name),authority='shared',sync_status='synced',last_synced_at=NOW(),last_error=NULL")->execute([$iid,$cid,$personId,$pin,normalize_person_name_part((string)$zk['name'])]);
                }
                $q=$this->pdo->prepare("SELECT COUNT(*) FROM bh_property_memberships WHERE person_id=? AND property_id=? AND status='active'");$q->execute([$personId,$propertyId]);if((int)$q->fetchColumn()===0){$rel=$relationshipMode==='infer'?(string)$zk['suggested_relationship']:$relationshipMode;$repo->saveMembership(['person_id'=>$personId,'property_id'=>$propertyId,'relationship_type'=>$rel,'access_profile_id'=>$profileId?:0,'status'=>'active'],null);$result['persons_linked']++;}
                $personIds[]=$personId;$this->pdo->prepare("UPDATE bh_external_objects SET adoption_status='adopted',adopted_at=NOW() WHERE installation_id=? AND connector_instance_id=? AND object_type='person' AND (external_code=? OR external_id=?)")->execute([$iid,$cid,$pin,$pin]);
            }catch(Throwable $e){$result['skipped']++;$result['errors'][]='PIN '.$pin.': '.$e->getMessage();}
        }
        if($normalizeNames&&$personIds){$nr=$repo->normalizePersonNames($personIds);$result['names_normalized']=(int)($nr['updated']??0);$result['names_unchanged']=(int)($nr['unchanged']??0);}
        $this->autoMapPersons();return $result;
    }

    /** Normaliza únicamente personas Property ya vinculadas con ZKBio dentro de una unidad. */
    public function normalizeAdoptedPersonNamesForProperty(int $propertyId): array
    {
        $this->assertNode();if($propertyId<1)throw new InvalidArgumentException('Seleccione una unidad Property.');$iid=$this->installationId();$cid=$this->connectorId();
        $q=$this->pdo->prepare("SELECT DISTINCT p.id FROM bh_people p JOIN bh_property_memberships pm ON pm.person_id=p.id AND pm.property_id=? AND pm.status='active' JOIN bh_external_mappings em ON em.installation_id=p.installation_id AND em.connector_instance_id=? AND em.local_entity_type='person' AND em.local_entity_id=p.id AND em.external_object_type='person' WHERE p.installation_id=?");$q->execute([$propertyId,$cid,$iid]);$ids=array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN)?:[]);
        if(!$ids)throw new RuntimeException('Esta unidad no tiene personas adoptadas/vinculadas con ZKBio para normalizar.');
        return (new NodeRepository($this->pdo))->normalizePersonNames($ids);
    }

    public function personMappingOptions(): array
    {
        $cid=$this->connectorId();$q=$this->pdo->prepare("SELECT p.id,p.display_name,m.external_id zk_pin,m.sync_status,eo.payload_json FROM bh_people p LEFT JOIN bh_external_mappings m ON m.connector_instance_id=? AND m.local_entity_type='person' AND m.local_entity_id=p.id LEFT JOIN bh_external_objects eo ON eo.connector_instance_id=m.connector_instance_id AND eo.object_type='person' AND (eo.external_code=m.external_id OR eo.external_id=m.external_id) WHERE p.installation_id=? ORDER BY p.display_name");$q->execute([$cid,$this->installationId()]);$out=[];foreach($q->fetchAll()?:[] as $r){$raw=json_decode((string)($r['payload_json']??''),true);if(!is_array($raw))$raw=[];$r['zk_first_name']=(string)($raw['name']??'');$r['zk_last_name']=(string)($raw['lastName']??'');$r['zk_card_no']=(string)($raw['cardNo']??'');$r['zk_car_plate']=(string)($raw['carPlate']??'');$r['zk_access_level_ids']=(string)($raw['accLevelIds']??'');unset($r['payload_json']);$out[]=$r;}return $out;
    }

    public function setPersonPin(int $personId,string $pin): void
    {
        $pin=trim($pin);if($pin==='')throw new InvalidArgumentException('Indique el PIN ZKBio.');$q=$this->pdo->prepare('SELECT display_name FROM bh_people WHERE id=? AND installation_id=?');$q->execute([$personId,$this->installationId()]);$name=(string)$q->fetchColumn();if($name==='')throw new InvalidArgumentException('Persona Property no encontrada.');$this->pdo->prepare("INSERT INTO bh_external_mappings (installation_id,connector_instance_id,local_entity_type,local_entity_id,external_object_type,external_id,external_name,authority,sync_status,last_synced_at) VALUES (?,?,'person',?,'person',?,?,'shared','synced',NOW()) ON DUPLICATE KEY UPDATE external_id=VALUES(external_id),external_name=VALUES(external_name),authority='shared',sync_status='synced',last_synced_at=NOW(),last_error=NULL")->execute([$this->installationId(),$this->connectorId(),$personId,$pin,$name]);
    }

    private static function uuid(): string{$d=random_bytes(16);$d[6]=chr((ord($d[6])&0x0f)|0x40);$d[8]=chr((ord($d[8])&0x3f)|0x80);return vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($d),4));}
}
