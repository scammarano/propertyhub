<?php
declare(strict_types=1);

final class RepositoryService
{
    private PDO $pdo;
    private ?bool $edgeDeploymentPropertyCodeColumn=null;
    public function __construct(?PDO $pdo=null) { $this->pdo=$pdo ?? Connection::pdo(); }

    public function dashboard(string $propertyCode=''): array
    {
        $this->assertCentral();
        $propertyCode=strtoupper(trim($propertyCode));
        $baseWhere=$this->visibleInstallationWhere()." AND i.instance_role='building_node'";
        if($propertyCode!=='')$baseWhere.=' AND i.installation_code='.$this->pdo->quote($propertyCode);
        $totals=$this->pdo->query(
            "SELECT COUNT(*) installations,"
            ."SUM(CASE WHEN last_seen_at>=DATE_SUB(NOW(),INTERVAL 20 MINUTE) AND health_status='healthy' THEN 1 ELSE 0 END) healthy,"
            ."SUM(CASE WHEN last_seen_at>=DATE_SUB(NOW(),INTERVAL 20 MINUTE) AND health_status IN ('warning','critical') THEN 1 ELSE 0 END) attention,"
            ."SUM(CASE WHEN health_status='offline' OR last_seen_at<DATE_SUB(NOW(),INTERVAL 20 MINUTE) THEN 1 ELSE 0 END) offline "
            ."FROM bh_repo_installations i WHERE {$baseWhere}"
        )->fetch() ?: [];
        $metrics=$this->pdo->query(
            "SELECT COALESCE(SUM(m.user_count),0) user_count,COALESCE(SUM(m.people_count),0) people_count,COALESCE(SUM(m.unit_count),0) unit_count,"
            ."COALESCE(SUM(m.camera_count),0) camera_count,COALESCE(SUM(m.access_point_count),0) access_point_count,COALESCE(SUM(m.connector_count),0) connector_count,"
            ."COALESCE(SUM(m.queue_depth),0) queue_depth,COALESCE(SUM(m.critical_alert_count),0) critical_alert_count "
            ."FROM bh_repo_installations i LEFT JOIN (SELECT m1.* FROM bh_repo_installation_metrics m1 INNER JOIN (SELECT installation_id,MAX(id) latest_id FROM bh_repo_installation_metrics GROUP BY installation_id) lm ON lm.latest_id=m1.id) m ON m.installation_id=i.id "
            ."WHERE {$baseWhere}"
        )->fetch() ?: [];
        return array_merge($totals,$metrics,[
            'planned'=>(int)$this->pdo->query("SELECT COUNT(*) FROM bh_repo_installation_plans WHERE status IN ('ready','token_issued')")->fetchColumn(),
            'releases'=>(int)$this->pdo->query("SELECT COUNT(*) FROM bh_repo_releases WHERE status='published'")->fetchColumn(),
            'api_versions'=>(int)$this->pdo->query("SELECT COUNT(*) FROM bh_repo_api_versions WHERE status NOT IN ('retired')")->fetchColumn(),
            'vendor_apis'=>(int)$this->pdo->query("SELECT COUNT(*) FROM bh_repo_vendor_apis WHERE lifecycle_status NOT IN ('deprecated')")->fetchColumn(),
            'recent'=>array_slice(array_values(array_filter($this->installations(),static fn(array $r):bool=>(string)($r['instance_role']??'building_node')==='building_node'&&($propertyCode===''||hash_equals((string)$r['installation_code'],$propertyCode)))),0,8),
            'plans'=>$this->installationPlans(6),
            'properties'=>$this->propertyPortfolio(),
            'selected_property'=>$propertyCode,
            'privacy'=>$this->privacySettings(),
        ]);
    }

    public function installations(?int $limit=null): array
    {
        $this->assertCentral();
        $sql="SELECT i.*,i.health_status reported_health_status,CASE WHEN i.last_seen_at IS NULL OR i.last_seen_at<DATE_SUB(NOW(),INTERVAL 20 MINUTE) THEN 'offline' ELSE i.health_status END effective_health_status,"
            ."CASE WHEN i.last_seen_at IS NOT NULL AND i.last_seen_at>=DATE_SUB(NOW(),INTERVAL 20 MINUTE) THEN 1 ELSE 0 END node_connected,"
            ."CASE WHEN i.last_seen_at IS NULL THEN NULL ELSE TIMESTAMPDIFF(SECOND,i.last_seen_at,NOW()) END heartbeat_age_seconds,"
            ."(SELECT MAX(dl.created_at) FROM bh_repo_delivery_logs dl WHERE dl.installation_id=i.id AND dl.event_type='heartbeat' AND dl.status='ok') last_heartbeat_event_at,"
            ."m.user_count,m.people_count,m.unit_count,m.area_count,m.active_visitor_count,m.pending_reservation_count,m.camera_count,m.access_point_count,m.connector_count,m.device_count,m.online_device_count,m.warning_device_count,m.queue_depth,m.critical_alert_count,m.summary_json,m.reported_at "
            ."FROM bh_repo_installations i LEFT JOIN (SELECT m1.* FROM bh_repo_installation_metrics m1 INNER JOIN (SELECT installation_id,MAX(id) latest_id FROM bh_repo_installation_metrics GROUP BY installation_id) lm ON lm.latest_id=m1.id) m ON m.installation_id=i.id "
            ."WHERE ".$this->visibleInstallationWhere()." ORDER BY CASE WHEN i.last_seen_at IS NULL OR i.last_seen_at<DATE_SUB(NOW(),INTERVAL 20 MINUTE) THEN 2 WHEN i.health_status='critical' THEN 1 WHEN i.health_status='warning' THEN 3 ELSE 4 END,i.installation_name";
        if($limit!==null)$sql.=' LIMIT '.max(1,$limit);
        $rows=$this->pdo->query($sql)->fetchAll();
        foreach($rows as &$row){if(!empty($row['effective_health_status']))$row['health_status']=$row['effective_health_status'];$summary=json_decode((string)($row['summary_json']??''),true);$row['backup']=is_array($summary)?(array)($summary['backup']??[]):[];}
        return $rows;
    }

    public function installationDetail(int $id): array
    {
        $this->assertCentral();
        $stmt=$this->pdo->prepare(
            "SELECT i.*,i.health_status reported_health_status,CASE WHEN i.last_seen_at IS NULL OR i.last_seen_at<DATE_SUB(NOW(),INTERVAL 20 MINUTE) THEN 'offline' ELSE i.health_status END effective_health_status,"
            ."CASE WHEN i.last_seen_at IS NOT NULL AND i.last_seen_at>=DATE_SUB(NOW(),INTERVAL 20 MINUTE) THEN 1 ELSE 0 END node_connected,"
            ."CASE WHEN i.last_seen_at IS NULL THEN NULL ELSE TIMESTAMPDIFF(SECOND,i.last_seen_at,NOW()) END heartbeat_age_seconds,"
            ."(SELECT MAX(dl.created_at) FROM bh_repo_delivery_logs dl WHERE dl.installation_id=i.id AND dl.event_type='heartbeat' AND dl.status='ok') last_heartbeat_event_at,"
            ."m.user_count,m.people_count,m.unit_count,m.area_count,m.active_visitor_count,m.pending_reservation_count,m.camera_count,m.access_point_count,m.connector_count,m.device_count,m.online_device_count,m.warning_device_count,m.queue_depth,m.critical_alert_count,m.disk_free_bytes,m.summary_json,m.reported_at "
            ."FROM bh_repo_installations i LEFT JOIN (SELECT m1.* FROM bh_repo_installation_metrics m1 INNER JOIN (SELECT installation_id,MAX(id) latest_id FROM bh_repo_installation_metrics GROUP BY installation_id) lm ON lm.latest_id=m1.id) m ON m.installation_id=i.id WHERE i.id=? LIMIT 1"
        );
        $stmt->execute([$id]);$row=$stmt->fetch();
        if(!is_array($row))throw new RuntimeException('Instalación no encontrada.');
        if(!empty($row['effective_health_status']))$row['health_status']=$row['effective_health_status'];
        $summary=json_decode((string)($row['summary_json']??''),true);
        $row['infrastructure']=is_array($summary)?(array)($summary['infrastructure']??[]):[];
        $row['backup']=is_array($summary)?(array)($summary['backup']??[]):[];
        return $row;
    }

    public function installationConnectors(int $id): array
    {
        $stmt=$this->pdo->prepare('SELECT * FROM bh_repo_connector_inventory WHERE installation_id=? ORDER BY domain,display_name');$stmt->execute([$id]);return $stmt->fetchAll();
    }

    public function installationMetricsHistory(int $id,int $limit=20): array
    {
        $stmt=$this->pdo->prepare('SELECT * FROM bh_repo_installation_metrics WHERE installation_id=? ORDER BY reported_at DESC,id DESC LIMIT '.max(1,$limit));$stmt->execute([$id]);return $stmt->fetchAll();
    }

    public function healthOverview(): array
    {
        $rows=$this->installations();
        return [
            'rows'=>$rows,
            'healthy'=>count(array_filter($rows,fn($r)=>($r['health_status']??'')==='healthy')),
            'warning'=>count(array_filter($rows,fn($r)=>in_array(($r['health_status']??''),['warning','critical'],true))),
            'offline'=>count(array_filter($rows,fn($r)=>($r['health_status']??'')==='offline')),
        ];
    }

    public function consolidatedMetrics(): array
    {
        $rows=$this->installations();
        $keys=['user_count','people_count','unit_count','area_count','active_visitor_count','pending_reservation_count','camera_count','access_point_count','connector_count','device_count','online_device_count','warning_device_count','queue_depth','critical_alert_count'];
        $totals=array_fill_keys($keys,0);
        foreach($rows as $row)foreach($keys as $key)$totals[$key]+=(int)($row[$key]??0);
        return ['totals'=>$totals,'rows'=>$rows];
    }

    public function releases(): array
    {
        $this->assertCentral();$registry=new ComponentRegistryService($this->pdo);
        if($registry->available()){
            $rows=$this->pdo->query("SELECT r.*,c.component_code canonical_component_code,c.display_name canonical_display_name,c.connector_code,c.lifecycle_status component_lifecycle FROM bh_repo_releases r LEFT JOIN bh_repo_components c ON c.id=r.component_id ORDER BY r.created_at DESC,r.id DESC")->fetchAll()?:[];
            $channels=$registry->channelsForReleaseIds(array_map(static fn(array $r):int=>(int)$r['id'],$rows));
            foreach($rows as &$row){$row['channels']=$channels[(int)$row['id']]??[(string)$row['channel']];if(!empty($row['canonical_component_code']))$row['component_code']=$row['canonical_component_code'];if(!empty($row['canonical_display_name']))$row['display_name']=$row['canonical_display_name'];}
            unset($row);return $rows;
        }
        // During the narrow code-deployed/schema-pending upgrade window keep the
        // repository readable; publication remains blocked until migration 0042.
        $rows=$this->pdo->query('SELECT * FROM bh_repo_releases ORDER BY created_at DESC,id DESC')->fetchAll()?:[];
        foreach($rows as &$row)$row['channels']=[(string)$row['channel']];unset($row);return $rows;
    }

    public function components(): array
    {
        $this->assertCentral();
        (new ComponentRegistryService($this->pdo))->reconcileConnectorCatalog();
        return (new ComponentRegistryService($this->pdo))->components();
    }

    public function apiVersions(): array
    {
        $this->assertCentral();
        return $this->pdo->query("SELECT * FROM bh_repo_api_versions ORDER BY api_code,created_at DESC")->fetchAll();
    }

    public function vendorApis(): array
    {
        $this->assertCentral();
        return $this->pdo->query('SELECT * FROM bh_repo_vendor_apis ORDER BY domain,vendor_name,display_name')->fetchAll();
    }

    public function connectorCatalog(): array
    {
        $this->assertCentral();
        // File manifests are authoritative for connector code/version. Do not tie this
        // refresh to pending SQL migrations: many connector updates add no schema.
        (new CatalogSeeder($this->pdo,base_path()))->seedConnectorDefinitions();
        return $this->pdo->query('SELECT * FROM bh_connector_definitions ORDER BY domain,name')->fetchAll();
    }

    /** @return list<string> */
    public function installationDesiredConnectors(int $installationId): array
    {
        return $this->installationDesiredConnectorState($installationId)['codes'];
    }

    /** @return array{items:list<array<string,mixed>>,codes:list<string>,source:string,materialized:bool,explicit:bool} */
    public function installationDesiredConnectorState(int $installationId): array
    {
        $this->assertCentral();
        $stmt=$this->pdo->prepare('SELECT id,installation_code,metadata_json FROM bh_repo_installations WHERE id=? LIMIT 1');
        $stmt->execute([$installationId]);$row=$stmt->fetch();
        if(!is_array($row))throw new RuntimeException('La instalación no existe.');
        return DesiredConnectorPolicy::resolve($this->pdo,$row,true);
    }

    /** @param list<string> $codes */
    public function saveInstallationDesiredConnectors(int $installationId,array $codes,array $policies=[],array $versions=[],array $constraints=[]): array
    {
        $this->assertCentral();
        $catalog=$this->connectorCatalog();$known=[];foreach($catalog as $row)$known[DesiredConnectorPolicy::canonical((string)$row['code'])]=$row;
        $items=[];
        foreach($codes as $rawCode){
            $code=DesiredConnectorPolicy::canonical((string)$rawCode);if(!isset($known[$code]))continue;
            $policy=strtolower(trim((string)($policies[$code]??'latest_compatible')));if($policy==='pinned')$policy='exact';if(!in_array($policy,['latest_compatible','exact','minimum','range'],true))$policy='latest_compatible';
            $item=['code'=>$code,'enabled'=>true,'version_policy'=>$policy];$version=trim((string)($versions[$code]??''));$constraint=trim((string)($constraints[$code]??''));
            if(in_array($policy,['exact','minimum'],true)){
                if($version===''||!DesiredConnectorPolicy::isValidVersion($version))throw new InvalidArgumentException('El conector '.$code.' requiere una versión válida para la política '.$policy.'.');
                $item['version']=$version;
            }
            if($policy==='range'){
                if(!DesiredConnectorPolicy::isValidConstraint($constraint))throw new InvalidArgumentException('El conector '.$code.' requiere un rango válido, por ejemplo >=0.5.0,<0.7.0.');
                $item['version_constraint']=$constraint;
            }
            $items[]=$item;
        }
        $stmt=$this->pdo->prepare('SELECT metadata_json FROM bh_repo_installations WHERE id=? LIMIT 1');$stmt->execute([$installationId]);$raw=$stmt->fetchColumn();
        if($raw===false)throw new RuntimeException('La instalación no existe.');
        $meta=json_decode((string)$raw,true);if(!is_array($meta))$meta=[];
        $desired=DesiredConnectorPolicy::normalize($this->pdo,$items);
        $meta['desired_connectors']=$desired;
        $meta['desired_connectors_updated_at']=date(DATE_ATOM);
        $meta['desired_connectors_source']='central_installation_configuration';
        $this->pdo->prepare('UPDATE bh_repo_installations SET metadata_json=? WHERE id=?')->execute([json_encode($meta,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),$installationId]);
        return $desired;
    }

    public function compatibilityMatrix(): array
    {
        $apis=$this->vendorApis();$connectors=$this->connectorCatalog();$byCode=[];foreach($connectors as $c)$byCode[$c['code']]=$c;
        foreach($apis as &$api)$api['connector']=$byCode[$api['connector_code']]??null;
        return $apis;
    }


    public function installationPlans(?int $limit=null): array
    {
        $this->assertCentral();
        $sql = "SELECT p.*,t.id token_id,t.token_purpose,t.used_count,t.max_uses,t.expires_at,t.status token_status,i.base_url registered_base_url "
            ."FROM bh_repo_installation_plans p "
            ."LEFT JOIN bh_repo_registration_tokens t ON t.id=p.registration_token_id "
            ."LEFT JOIN bh_repo_installations i ON i.id=p.registered_installation_id "
            ."WHERE p.status<>'cancelled' ORDER BY FIELD(p.status,'token_issued','ready','draft','registered'),p.created_at DESC";
        if ($limit !== null) $sql .= ' LIMIT '.max(1,$limit);
        $rows = $this->pdo->query($sql)->fetchAll();
        foreach ($rows as &$row) {
            $technology = json_decode((string)($row['technology_json'] ?? ''), true);
            $row['technology'] = is_array($technology) ? $technology : [];
        }
        return $rows;
    }

    public function createInstallationPlan(array $data, ?int $userId=null): array
    {
        $this->assertCentral();
        $name = trim((string)($data['installation_name'] ?? ''));
        $code = strtoupper(trim((string)($data['installation_code'] ?? '')));
        $type = trim((string)($data['installation_type'] ?? 'mixed'));
        $baseUrl = rtrim(trim((string)($data['planned_base_url'] ?? '')), '/');
        $channel = trim((string)($data['update_channel'] ?? 'stable'));
        $scope = trim((string)($data['data_scope'] ?? 'aggregates'));
        $environment = trim((string)($data['environment_type'] ?? 'production'));
        $cloneSource = strtoupper(trim((string)($data['clone_source_code'] ?? '')));
        $notes = trim((string)($data['notes'] ?? ''));
        $technology = array_values(array_unique(array_filter(array_map(
            static fn($v): string => preg_replace('/[^a-z0-9_-]/', '', strtolower((string)$v)) ?: '',
            (array)($data['technology'] ?? [])
        ))));

        if ($name === '') throw new InvalidArgumentException('Indique el nombre de la instalación.');
        if (!preg_match('/^[A-Z0-9][A-Z0-9_-]{2,59}$/', $code)) {
            throw new InvalidArgumentException('El código debe tener entre 3 y 60 caracteres y usar solo letras, números, guion o guion bajo.');
        }
        $validTypes = ['urbanization','residential_complex','residential','office_building','office','shopping_center','commercial','mixed','campus','industrial','other'];
        if (!in_array($type, $validTypes, true)) throw new InvalidArgumentException('Tipo de instalación no válido.');
        if (!in_array($channel, ['development','beta','stable','pinned'], true)) throw new InvalidArgumentException('Canal de actualización no válido.');
        if (!in_array($scope, ['metadata_only','aggregates','support_snapshot'], true)) throw new InvalidArgumentException('Alcance de datos no válido.');
        if (!in_array($environment, ['production','test','lab'], true)) throw new InvalidArgumentException('Entorno de instalación no válido.');
        if ($baseUrl !== '' && !filter_var($baseUrl, FILTER_VALIDATE_URL)) throw new InvalidArgumentException('La URL prevista no es válida.');
        if ($baseUrl !== '' && !str_ends_with($baseUrl, '/public')) throw new InvalidArgumentException('La URL prevista debe terminar en /public.');

        $exists = $this->pdo->prepare("SELECT COUNT(*) FROM bh_repo_installations WHERE installation_code=? AND status<>'retired'");
        $exists->execute([$code]);
        if ((int)$exists->fetchColumn() > 0) throw new RuntimeException('Ya existe una instalación registrada con ese código.');

        $plain = 'BHR-'.strtoupper(bin2hex(random_bytes(12)));
        $planUuid = self::uuid();
        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));

        $this->pdo->beginTransaction();
        try {
            $oldPlan=$this->pdo->prepare("SELECT registration_token_id,status FROM bh_repo_installation_plans WHERE installation_code=? FOR UPDATE");
            $oldPlan->execute([$code]);
            $oldPlanRow=$oldPlan->fetch();
            if(is_array($oldPlanRow) && ($oldPlanRow['status']??'')==='registered') throw new RuntimeException('La instalación ya completó su registro.');
            if(is_array($oldPlanRow) && (int)($oldPlanRow['registration_token_id']??0)>0) {
                $this->pdo->prepare("UPDATE bh_repo_registration_tokens SET status='revoked',revoked_at=NOW(),revoked_by=?,revocation_reason='Reemplazado al regenerar el expediente' WHERE id=?")->execute([$userId,(int)$oldPlanRow['registration_token_id']]);
            }
            $stmt = $this->pdo->prepare(
                "INSERT INTO bh_repo_registration_tokens (token_name,token_purpose,token_hash,allowed_installation_code,allowed_channel,max_uses,expires_at,status,created_by) "
                ."VALUES (?,'initial_registration',?,?,?,1,?,'active',?)"
            );
            $stmt->execute(['Instalación '.$name,hash('sha256',$plain),$code,$channel,$expiresAt,$userId]);
            $tokenId = (int)$this->pdo->lastInsertId();

            $stmt = $this->pdo->prepare(
                "INSERT INTO bh_repo_installation_plans (plan_uuid,installation_code,installation_name,installation_type,planned_base_url,update_channel,data_scope,environment_type,clone_source_code,status,technology_json,notes,registration_token_id,created_by) "
                ."VALUES (?,?,?,?,?,?,?,?,?,'token_issued',?,?,?,?) "
                ."ON DUPLICATE KEY UPDATE installation_name=VALUES(installation_name),installation_type=VALUES(installation_type),planned_base_url=VALUES(planned_base_url),update_channel=VALUES(update_channel),data_scope=VALUES(data_scope),environment_type=VALUES(environment_type),clone_source_code=VALUES(clone_source_code),status='token_issued',technology_json=VALUES(technology_json),notes=VALUES(notes),registration_token_id=VALUES(registration_token_id),created_by=VALUES(created_by),registered_installation_id=NULL,registered_at=NULL"
            );
            $stmt->execute([
                $planUuid,$code,$name,$type,$baseUrl !== '' ? $baseUrl : null,$channel,$scope,$environment,$cloneSource !== '' ? $cloneSource : null,
                json_encode($technology,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
                $notes !== '' ? $notes : null,$tokenId,$userId
            ]);
            $planId = (int)$this->pdo->lastInsertId();
            $find = $this->pdo->prepare('SELECT id,plan_uuid FROM bh_repo_installation_plans WHERE installation_code=?');
            $find->execute([$code]);
            $storedPlan=$find->fetch();
            if(is_array($storedPlan)) {
                $planId=(int)$storedPlan['id'];
                $planUuid=(string)$storedPlan['plan_uuid'];
            }
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }

        return [
            'plan_id'=>$planId,
            'plan_uuid'=>$planUuid,
            'installation_name'=>$name,
            'installation_code'=>$code,
            'installation_type'=>$type,
            'planned_base_url'=>$baseUrl,
            'update_channel'=>$channel,
            'data_scope'=>$scope,
            'environment_type'=>$environment,
            'clone_source_code'=>$cloneSource,
            'technology'=>$technology,
            'registration_token'=>$plain,
            'expires_at'=>$expiresAt,
            'repository_url'=>(string)config('deployment.repository_public_url', app_base_url()),
        ];
    }

    public function cancelInstallationPlan(int $id): void
    {
        $this->assertCentral();
        $stmt=$this->pdo->prepare("SELECT registration_token_id FROM bh_repo_installation_plans WHERE id=? AND status<>'registered' LIMIT 1");
        $stmt->execute([$id]);
        $tokenId=$stmt->fetchColumn();
        if ($tokenId === false) throw new RuntimeException('La instalación preparada no existe o ya fue registrada.');
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare("UPDATE bh_repo_installation_plans SET status='cancelled' WHERE id=?")->execute([$id]);
            if ((int)$tokenId > 0) $this->pdo->prepare("UPDATE bh_repo_registration_tokens SET status='revoked',revoked_at=NOW(),revocation_reason='Preparación cancelada' WHERE id=?")->execute([(int)$tokenId]);
            $this->pdo->commit();
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) $this->pdo->rollBack();
            throw $e;
        }
    }


    public function renameProperty(string $propertyCode,string $newName,?int $userId=null): array
    {
        $this->assertCentral();
        $code=strtoupper(trim($propertyCode));$newName=trim($newName);
        if($code==='')throw new InvalidArgumentException('Falta el código de la instalación.');
        if(mb_strlen($newName)<2||mb_strlen($newName)>190)throw new InvalidArgumentException('El nombre debe tener entre 2 y 190 caracteres.');
        $this->pdo->beginTransaction();
        try{
            $planStmt=$this->pdo->prepare("SELECT id,installation_name FROM bh_repo_installation_plans WHERE installation_code=? AND status<>'cancelled' LIMIT 1 FOR UPDATE");
            $planStmt->execute([$code]);$plan=$planStmt->fetch();
            $nodeStmt=$this->pdo->prepare("SELECT id,installation_name FROM bh_repo_installations WHERE installation_code=? AND instance_role='building_node' AND status<>'retired' LIMIT 1 FOR UPDATE");
            $nodeStmt->execute([$code]);$node=$nodeStmt->fetch();
            if(!is_array($plan)&&!is_array($node))throw new RuntimeException('Instalación no encontrada.');
            $oldName=(string)($node['installation_name']??$plan['installation_name']??$code);
            if(is_array($plan))$this->pdo->prepare('UPDATE bh_repo_installation_plans SET installation_name=?,updated_at=NOW() WHERE id=?')->execute([$newName,(int)$plan['id']]);
            if(is_array($node)){
                $this->pdo->prepare('UPDATE bh_repo_installations SET installation_name=?,updated_at=NOW() WHERE id=?')->execute([$newName,(int)$node['id']]);
                $this->pdo->prepare("INSERT INTO bh_repo_delivery_logs (installation_id,event_type,status,remote_ip,details_json) VALUES (?,'property_renamed','ok',?,?)")->execute([(int)$node['id'],(string)($_SERVER['REMOTE_ADDR']??''),json_encode(['old_name'=>$oldName,'new_name'=>$newName,'changed_by'=>$userId],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]);
            }
            $this->pdo->commit();
            return ['code'=>$code,'old_name'=>$oldName,'name'=>$newName];
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    public function setInstallationEnvironment(int $installationId,string $environment): void
    {
        $this->assertCentral();
        if(!in_array($environment,['production','test','lab'],true))throw new InvalidArgumentException('Entorno no válido.');
        $stmt=$this->pdo->prepare("SELECT installation_code FROM bh_repo_installations WHERE id=? AND status<>'retired' LIMIT 1");
        $stmt->execute([$installationId]);$code=$stmt->fetchColumn();
        if($code===false)throw new RuntimeException('Instalación no encontrada.');
        $this->pdo->prepare('UPDATE bh_repo_installations SET environment_type=?,updated_at=NOW() WHERE id=?')->execute([$environment,$installationId]);
        $this->pdo->prepare('UPDATE bh_repo_installation_plans SET environment_type=? WHERE registered_installation_id=?')->execute([$environment,$installationId]);
    }

    public function duplicateInstallationAsPlan(int $installationId,string $newName,string $newCode,string $environment='test',?int $userId=null): array
    {
        $this->assertCentral();
        $source=$this->installationDetail($installationId);
        $newName=trim($newName);$newCode=strtoupper(trim($newCode));
        if($newName==='')$newName=(string)$source['installation_name'].' · copia';
        if($newCode==='')throw new InvalidArgumentException('Indique el código de la nueva instalación.');
        if(!in_array($environment,['test','lab'],true))throw new InvalidArgumentException('Las duplicaciones desde Central se crean como Prueba o Laboratorio.');

        $stmt=$this->pdo->prepare('SELECT connector_code FROM bh_repo_connector_inventory WHERE installation_id=? ORDER BY connector_code');
        $stmt->execute([$installationId]);
        $technology=array_values(array_unique(array_filter(array_map('strval',$stmt->fetchAll(PDO::FETCH_COLUMN)?:[]))));

        return $this->createInstallationPlan([
            'installation_name'=>$newName,
            'installation_code'=>$newCode,
            'installation_type'=>(string)$source['installation_type'],
            'planned_base_url'=>'',
            'update_channel'=>(string)$source['update_channel'],
            'data_scope'=>(string)($source['data_scope']??'aggregates'),
            'environment_type'=>$environment,
            'clone_source_code'=>(string)$source['installation_code'],
            'technology'=>$technology,
            'notes'=>'Expediente duplicado desde '.$source['installation_code'].'. No copia datos privados ni operativos del nodo. Para clonar datos reales use un .phbackup en modo copia segura.',
        ],$userId);
    }

    public function deleteTestInstallation(int $installationId,string $confirmation,bool $purgeOffsite=false,bool $ackActive=false): array
    {
        $this->assertCentral();
        $this->pdo->beginTransaction();
        try{
            $stmt=$this->pdo->prepare("SELECT * FROM bh_repo_installations WHERE id=? FOR UPDATE");
            $stmt->execute([$installationId]);$row=$stmt->fetch();
            if(!is_array($row))throw new RuntimeException('Instalación no encontrada.');
            $environment=(string)($row['environment_type']??'production');
            if(empty($row['is_demo'])&&!in_array($environment,['test','lab'],true)){
                throw new RuntimeException('Solo se pueden eliminar físicamente instalaciones marcadas como Prueba, Laboratorio o Demo. Una instalación de producción debe conservar su expediente.');
            }
            $code=(string)$row['installation_code'];
            if(!hash_equals($code,trim($confirmation)))throw new InvalidArgumentException('La confirmación no coincide con el código de la instalación.');
            $recent=false;
            if(!empty($row['last_seen_at'])){
                $seen=strtotime((string)$row['last_seen_at']);
                $recent=$seen!==false&&$seen>=time()-300;
            }
            if($recent&&!$ackActive)throw new RuntimeException('El nodo reportó actividad en los últimos 5 minutos. Marque la confirmación de nodo activo si realmente desea romper el vínculo y eliminarlo.');

            // Estas dos tablas históricas no tienen ON DELETE CASCADE.
            $this->pdo->prepare('DELETE FROM bh_repo_assignments WHERE installation_id=?')->execute([$installationId]);
            $this->pdo->prepare('DELETE FROM bh_repo_delivery_logs WHERE installation_id=?')->execute([$installationId]);

            // El expediente se conserva como cancelado para poder auditar el origen, pero libera el registro.
            $this->pdo->prepare("UPDATE bh_repo_installation_plans SET status='cancelled',registered_installation_id=NULL,registered_at=NULL WHERE registered_installation_id=?")->execute([$installationId]);

            $offsite=[];
            if($purgeOffsite){
                $os=$this->pdo->prepare("SELECT id,storage_path FROM bh_backup_offsite_archives WHERE installation_uuid=? AND status<>'deleted'");
                $os->execute([(string)$row['installation_uuid']]);$offsite=$os->fetchAll()?:[];
                $this->pdo->prepare("UPDATE bh_backup_offsite_archives SET status='deleted' WHERE installation_uuid=?")->execute([(string)$row['installation_uuid']]);
            }

            // Métricas, conectores, soporte y tokens se eliminan por CASCADE.
            $this->pdo->prepare('DELETE FROM bh_repo_installations WHERE id=?')->execute([$installationId]);
            $this->pdo->commit();

            $removedFiles=0;
            foreach($offsite as $archive){
                $path=(string)($archive['storage_path']??'');
                if($path!==''&&is_file($path)&&@unlink($path))$removedFiles++;
            }
            return ['code'=>$code,'environment'=>$environment,'offsite_files'=>$removedFiles];
        }catch(Throwable $e){
            if($this->pdo->inTransaction())$this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * Returns whether a property can be physically removed from Central without
     * breaking a live deployment. Prepared plans with no Node are always removable.
     */
    public function propertyDeletionState(string $propertyCode): array
    {
        $this->assertCentral();
        $code=strtoupper(trim($propertyCode));
        if($code==='') throw new InvalidArgumentException('Falta el código de la propiedad.');
        $plan=$this->pdo->prepare("SELECT id,status,registration_token_id FROM bh_repo_installation_plans WHERE installation_code=? AND status<>'cancelled' LIMIT 1");
        $plan->execute([$code]);$planRow=$plan->fetch();if(!is_array($planRow))$planRow=null;
        $nodes=$this->pdo->prepare("SELECT id,installation_code,installation_name,instance_role,status,last_seen_at,installation_uuid FROM bh_repo_installations WHERE (installation_code=? AND instance_role='building_node') OR (parent_installation_code=? AND instance_role='edge_agent') ORDER BY instance_role,id");
        $nodes->execute([$code,$code]);$rows=$nodes->fetchAll()?:[];
        if($planRow===null&&$rows===[]) throw new RuntimeException('Propiedad no encontrada.');
        $recent=[];$cutoff=time()-300;
        foreach($rows as $row){
            $seen=!empty($row['last_seen_at'])?strtotime((string)$row['last_seen_at']):false;
            if($seen!==false&&$seen>=$cutoff)$recent[]=['code'=>(string)$row['installation_code'],'role'=>(string)$row['instance_role'],'last_seen_at'=>(string)$row['last_seen_at']];
        }
        return [
            'code'=>$code,
            'plan_only'=>$rows===[],
            'has_node'=>count(array_filter($rows,static fn(array $r):bool=>(string)$r['instance_role']==='building_node'))>0,
            'registered_count'=>count($rows),
            'recent'=>$recent,
            'can_delete'=>$recent===[],
            'reason'=>$recent===[]?'':'Hay Node/Edge con actividad en los últimos 5 minutos.',
        ];
    }

    /**
     * Hard-delete a property created by mistake or an inactive property. This is
     * intentionally different from retiring a production property: any Node/Edge
     * heartbeat in the last five minutes blocks deletion. Offsite backup files are
     * preserved unless the operator explicitly asks to purge them.
     */
    public function deleteInactiveProperty(string $propertyCode,string $confirmation,bool $purgeOffsite=false): array
    {
        $this->assertCentral();
        $state=$this->propertyDeletionState($propertyCode);$code=(string)$state['code'];
        if(!hash_equals($code,strtoupper(trim($confirmation)))) throw new InvalidArgumentException('La confirmación no coincide con el código de la propiedad.');
        if(!$state['can_delete']) throw new RuntimeException((string)$state['reason'].' Espere a que deje de reportar o desactive primero el Node/Edge correspondiente.');

        $this->pdo->beginTransaction();
        try{
            $planStmt=$this->pdo->prepare("SELECT id,registration_token_id FROM bh_repo_installation_plans WHERE installation_code=? LIMIT 1 FOR UPDATE");
            $planStmt->execute([$code]);$plan=$planStmt->fetch();if(!is_array($plan))$plan=null;
            $rowStmt=$this->pdo->prepare("SELECT * FROM bh_repo_installations WHERE (installation_code=? AND instance_role='building_node') OR (parent_installation_code=? AND instance_role='edge_agent') ORDER BY CASE WHEN instance_role='edge_agent' THEN 0 ELSE 1 END,id FOR UPDATE");
            $rowStmt->execute([$code,$code]);$rows=$rowStmt->fetchAll()?:[];
            if($plan===null&&$rows===[]) throw new RuntimeException('La propiedad ya no existe.');
            $cutoff=time()-300;foreach($rows as $row){$seen=!empty($row['last_seen_at'])?strtotime((string)$row['last_seen_at']):false;if($seen!==false&&$seen>=$cutoff)throw new RuntimeException('La instalación volvió a reportar actividad mientras se confirmaba el borrado. Operación cancelada.');}

            $ids=array_map(static fn(array $r):int=>(int)$r['id'],$rows);
            $nodeIds=array_values(array_map(static fn(array $r):int=>(int)$r['id'],array_filter($rows,static fn(array $r):bool=>(string)$r['instance_role']==='building_node')));
            $uuids=array_values(array_filter(array_map(static fn(array $r):string=>(string)($r['installation_uuid']??''),$rows)));

            // Edge enrollment history belongs to the property deployment and has
            // no FK to bh_repo_installations, so remove it explicitly first.
            if($this->hasEdgeDeploymentPropertyCode())$this->pdo->prepare('DELETE FROM bh_repo_edge_deployments WHERE property_code=?')->execute([$code]);
            foreach($nodeIds as $nodeId)$this->pdo->prepare('DELETE FROM bh_repo_edge_deployments WHERE parent_installation_id=?')->execute([$nodeId]);

            // Non-cascading historical tables must be cleared before registry rows.
            foreach($ids as $id){
                $this->pdo->prepare('DELETE FROM bh_repo_assignments WHERE installation_id=?')->execute([$id]);
                $this->pdo->prepare('DELETE FROM bh_repo_delivery_logs WHERE installation_id=?')->execute([$id]);
            }

            // Remove the property master/plan itself instead of leaving a cancelled
            // shell: this action exists specifically for records created by mistake.
            $planTokenId=$plan!==null?(int)($plan['registration_token_id']??0):0;
            $this->pdo->prepare('DELETE FROM bh_repo_installation_plans WHERE installation_code=?')->execute([$code]);

            // Delete children before the Property Node. Remaining dependent tables
            // use ON DELETE CASCADE/SET NULL according to their historical contract.
            foreach($ids as $id)$this->pdo->prepare('DELETE FROM bh_repo_installations WHERE id=?')->execute([$id]);
            if($planTokenId>0)$this->pdo->prepare('DELETE FROM bh_repo_registration_tokens WHERE id=? AND installation_id IS NULL')->execute([$planTokenId]);

            $offsite=[];
            if($purgeOffsite&&$uuids!==[]){
                $marks=implode(',',array_fill(0,count($uuids),'?'));
                $os=$this->pdo->prepare("SELECT id,storage_path FROM bh_backup_offsite_archives WHERE installation_uuid IN ({$marks}) AND status<>'deleted'");$os->execute($uuids);$offsite=$os->fetchAll()?:[];
                $q=$this->pdo->prepare("UPDATE bh_backup_offsite_archives SET status='deleted' WHERE installation_uuid IN ({$marks})");$q->execute($uuids);
            }
            $this->pdo->commit();

            $removedFiles=0;foreach($offsite as $archive){$path=(string)($archive['storage_path']??'');if($path!==''&&is_file($path)&&@unlink($path))$removedFiles++;}
            return ['code'=>$code,'registered_removed'=>count($ids),'offsite_files'=>$removedFiles,'plan_removed'=>$plan!==null];
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    public function tokens(): array
    {
        $this->assertCentral();
        return $this->pdo->query(
            "SELECT t.id,t.token_name,t.token_purpose,t.allowed_installation_code,t.allowed_channel,t.max_uses,t.used_count,t.expires_at,t.status,t.created_at,t.last_used_at,t.revoked_at,t.revocation_reason,t.installation_id,".
            "i.installation_name,p.id plan_id,p.installation_name plan_name " .
            "FROM bh_repo_registration_tokens t " .
            "LEFT JOIN bh_repo_installations i ON i.id=t.installation_id " .
            "LEFT JOIN bh_repo_installation_plans p ON p.registration_token_id=t.id " .
            "ORDER BY t.created_at DESC"
        )->fetchAll();
    }

    public function regeneratePlanToken(int $planId, ?int $userId=null): array
    {
        $this->assertCentral();
        $this->pdo->beginTransaction();
        try {
            $stmt=$this->pdo->prepare("SELECT * FROM bh_repo_installation_plans WHERE id=? FOR UPDATE");
            $stmt->execute([$planId]);
            $plan=$stmt->fetch();
            if(!is_array($plan)) throw new RuntimeException('La preparación de instalación no existe.');
            if(in_array((string)$plan['status'],['registered','cancelled'],true)) {
                throw new RuntimeException('Este expediente ya no admite token inicial. Use la reconexión desde la ficha del nodo registrado.');
            }
            $oldTokenId=(int)($plan['registration_token_id']??0);
            if($oldTokenId>0){
                $this->pdo->prepare("UPDATE bh_repo_registration_tokens SET status='revoked',revoked_at=NOW(),revoked_by=?,revocation_reason='Token inicial sustituido' WHERE id=? AND status='active'")
                    ->execute([$userId,$oldTokenId]);
            }
            $plain='BHR-'.strtoupper(bin2hex(random_bytes(12)));
            $expiresAt=date('Y-m-d H:i:s',strtotime('+30 days'));
            $stmt=$this->pdo->prepare(
                "INSERT INTO bh_repo_registration_tokens (token_name,token_purpose,token_hash,allowed_installation_code,allowed_channel,replaced_token_id,max_uses,expires_at,status,created_by) " .
                "VALUES (?,'initial_registration',?,?,?,?,1,?,'active',?)"
            );
            $stmt->execute([
                'Instalación '.$plan['installation_name'],hash('sha256',$plain),(string)$plan['installation_code'],
                (string)$plan['update_channel'],$oldTokenId>0?$oldTokenId:null,$expiresAt,$userId
            ]);
            $tokenId=(int)$this->pdo->lastInsertId();
            $this->pdo->prepare("UPDATE bh_repo_installation_plans SET registration_token_id=?,status='token_issued',updated_at=NOW() WHERE id=?")
                ->execute([$tokenId,$planId]);
            $this->pdo->commit();
            $technology=json_decode((string)($plan['technology_json']??''),true);
            return [
                'kind'=>'initial_registration','plan_id'=>$planId,'installation_name'=>(string)$plan['installation_name'],
                'installation_code'=>(string)$plan['installation_code'],'installation_type'=>(string)$plan['installation_type'],
                'planned_base_url'=>(string)($plan['planned_base_url']??''),'update_channel'=>(string)$plan['update_channel'],
                'data_scope'=>(string)$plan['data_scope'],'technology'=>is_array($technology)?$technology:[],
                'registration_token'=>$plain,'expires_at'=>$expiresAt,
                'repository_url'=>(string)config('deployment.repository_public_url',app_base_url()),
            ];
        } catch(Throwable $e){
            if($this->pdo->inTransaction())$this->pdo->rollBack();
            throw $e;
        }
    }

    public function createRelinkToken(int $installationId, ?int $userId=null): array
    {
        $this->assertCentral();
        $this->pdo->beginTransaction();
        try {
            $stmt=$this->pdo->prepare("SELECT * FROM bh_repo_installations WHERE id=? AND status<>'retired' FOR UPDATE");
            $stmt->execute([$installationId]);
            $installation=$stmt->fetch();
            if(!is_array($installation)) throw new RuntimeException('La instalación no existe o fue retirada.');
            if((string)($installation['instance_role']??'building_node')==='edge_agent') throw new RuntimeException('Los Edge Agents no se reconectan directamente a Central. Genere un PHEDGE desde su Property Node.');

            $active=$this->pdo->prepare("SELECT id FROM bh_repo_registration_tokens WHERE installation_id=? AND token_purpose='relink' AND status='active' FOR UPDATE");
            $active->execute([$installationId]);
            $oldIds=array_map('intval',array_column($active->fetchAll(),'id'));
            if($oldIds){
                $marks=implode(',',array_fill(0,count($oldIds),'?'));
                $params=array_merge([$userId],$oldIds);
                $this->pdo->prepare("UPDATE bh_repo_registration_tokens SET status='revoked',revoked_at=NOW(),revoked_by=?,revocation_reason='Token de reconexión sustituido' WHERE id IN ($marks)")
                    ->execute($params);
            }

            $plain='BHR-RELINK-'.strtoupper(bin2hex(random_bytes(12)));
            $expiresAt=date('Y-m-d H:i:s',strtotime('+72 hours'));
            $replaced=$oldIds?end($oldIds):null;
            $stmt=$this->pdo->prepare(
                "INSERT INTO bh_repo_registration_tokens (token_name,token_purpose,token_hash,allowed_installation_code,installation_id,replaced_token_id,allowed_channel,max_uses,expires_at,status,created_by) " .
                "VALUES (?,'relink',?,?,?,?,?,1,?,'active',?)"
            );
            $stmt->execute([
                'Reconexión '.$installation['installation_name'],hash('sha256',$plain),(string)$installation['installation_code'],
                $installationId,$replaced?:null,(string)$installation['update_channel'],$expiresAt,$userId
            ]);
            $tokenId=(int)$this->pdo->lastInsertId();
            $log=$this->pdo->prepare("INSERT INTO bh_repo_delivery_logs (installation_id,event_type,status,remote_ip,details_json) VALUES (?,'registration_token_reissued','ok',?,?)");
            $log->execute([$installationId,(string)($_SERVER['REMOTE_ADDR']??''),json_encode(['token_id'=>$tokenId,'expires_at'=>$expiresAt],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]);
            $this->pdo->commit();
            return [
                'kind'=>'relink','installation_id'=>$installationId,'installation_name'=>(string)$installation['installation_name'],
                'installation_code'=>(string)$installation['installation_code'],'registration_token'=>$plain,
                'expires_at'=>$expiresAt,'repository_url'=>(string)config('deployment.repository_public_url',app_base_url()),
            ];
        } catch(Throwable $e){
            if($this->pdo->inTransaction())$this->pdo->rollBack();
            throw $e;
        }
    }

    public function revokeToken(int $tokenId, ?int $userId=null, string $reason='Revocado manualmente'): void
    {
        $this->assertCentral();
        $stmt=$this->pdo->prepare("UPDATE bh_repo_registration_tokens SET status='revoked',revoked_at=NOW(),revoked_by=?,revocation_reason=? WHERE id=? AND status='active'");
        $stmt->execute([$userId,mb_substr(trim($reason)?:'Revocado manualmente',0,255),$tokenId]);
        if($stmt->rowCount()===0) throw new RuntimeException('El token no existe o ya no está activo.');
    }

    public function installationTokens(int $installationId): array
    {
        $this->assertCentral();
        $stmt=$this->pdo->prepare(
            "SELECT id,token_name,token_purpose,allowed_installation_code,allowed_channel,max_uses,used_count,expires_at,status,created_at,last_used_at,revoked_at,revocation_reason " .
            "FROM bh_repo_registration_tokens WHERE installation_id=? ORDER BY created_at DESC"
        );
        $stmt->execute([$installationId]);
        return $stmt->fetchAll();
    }

    public function privacySettings(): array
    {
        $this->assertCentral();
        $row=$this->pdo->query("SELECT * FROM bh_repo_privacy_settings WHERE setting_scope='global' LIMIT 1")->fetch();
        return $row?:['default_data_scope'=>'aggregates','receive_personal_data'=>0,'receive_operational_messages'=>0,'retain_metrics_days'=>365,'remote_support_enabled'=>0,'remote_support_requires_local_approval'=>1];
    }

    public function savePrivacySettings(array $data): void
    {
        $scope=in_array((string)($data['default_data_scope']??''),['metadata_only','aggregates','support_snapshot'],true)?(string)$data['default_data_scope']:'aggregates';
        $stmt=$this->pdo->prepare(
            "INSERT INTO bh_repo_privacy_settings (setting_scope,default_data_scope,receive_personal_data,receive_operational_messages,retain_metrics_days,remote_support_enabled,remote_support_requires_local_approval,notes) VALUES ('global',?,?,?,?,?,?,?) "
            ."ON DUPLICATE KEY UPDATE default_data_scope=VALUES(default_data_scope),receive_personal_data=VALUES(receive_personal_data),receive_operational_messages=VALUES(receive_operational_messages),retain_metrics_days=VALUES(retain_metrics_days),remote_support_enabled=VALUES(remote_support_enabled),remote_support_requires_local_approval=VALUES(remote_support_requires_local_approval),notes=VALUES(notes)"
        );
        $stmt->execute([$scope,0,0,max(30,(int)($data['retain_metrics_days']??365)),!empty($data['remote_support_enabled'])?1:0,!empty($data['remote_support_requires_local_approval'])?1:0,trim((string)($data['notes']??''))?:null]);
    }

    public function supportSessions(): array
    {
        return $this->pdo->query('SELECT s.*,i.installation_name FROM bh_repo_support_sessions s JOIN bh_repo_installations i ON i.id=s.installation_id ORDER BY s.created_at DESC')->fetchAll();
    }

    public function createRegistrationToken(string $name, ?string $code, string $channel, int $maxUses, ?string $expiresAt, ?int $userId=null): string
    {
        $this->assertCentral();
        $plain='BHR-'.strtoupper(bin2hex(random_bytes(12)));
        $stmt=$this->pdo->prepare("INSERT INTO bh_repo_registration_tokens (token_name,token_purpose,token_hash,allowed_installation_code,allowed_channel,max_uses,expires_at,status,created_by) VALUES (?,'initial_registration',?,?,?,?,?,'active',?)");
        $stmt->execute([$name,hash('sha256',$plain),$code ?: null,$channel ?: null,max(1,$maxUses),$expiresAt ?: null,$userId]);
        return $plain;
    }

    public function deploymentCenter(int $installationId): array
    {
        $this->assertCentral();
        $installation=$this->installationDetail($installationId);
        $stmt=$this->pdo->prepare("SELECT * FROM bh_repo_edge_deployments WHERE parent_installation_id=? ORDER BY id");
        $stmt->execute([$installationId]);
        $edges=$stmt->fetchAll()?:[];
        return [
            'installation'=>$installation,
            'property_code'=>(string)$installation['installation_code'],
            'node_code'=>(string)$installation['installation_code'],
            'node_url'=>(string)($installation['base_url']?:''),
            'central_url'=>(string)config('deployment.repository_public_url',app_base_url()),
            'edges'=>$edges,
        ];
    }

    public function createEdgeAgentRegistrationToken(int $parentInstallationId,string $agentCode,?int $userId=null,string $agentName=''): array
    {
        $this->assertCentral();
        $stmt=$this->pdo->prepare("SELECT installation_code FROM bh_repo_installations WHERE id=? AND instance_role='building_node' AND status<>'retired' LIMIT 1");
        $stmt->execute([$parentInstallationId]);$code=strtoupper(trim((string)($stmt->fetchColumn()?:'')));
        if($code==='')throw new RuntimeException('La propiedad no existe o no tiene un Property Node válido.');
        return $this->createEdgeAgentRegistrationTokenForProperty($code,$agentCode,$userId,$agentName);
    }

    /**
     * Prepare or rotate an Edge enrollment by permanent property code. Since 0.14.57
     * an Edge can be prepared before the Property Node has registered; when the Node
     * later registers Central binds all property-scoped Edge deployments to that row.
     */
    public function createEdgeAgentRegistrationTokenForProperty(string $propertyCode,string $agentCode,?int $userId=null,string $agentName=''): array
    {
        $this->assertCentral();$propertyCode=strtoupper(trim($propertyCode));
        if($propertyCode==='')throw new InvalidArgumentException('Falta el código de la propiedad.');
        $planStmt=$this->pdo->prepare("SELECT * FROM bh_repo_installation_plans WHERE installation_code=? AND status<>'cancelled' LIMIT 1");
        $planStmt->execute([$propertyCode]);$plan=$planStmt->fetch();if(!is_array($plan))$plan=null;
        $nodeStmt=$this->pdo->prepare("SELECT * FROM bh_repo_installations WHERE installation_code=? AND instance_role='building_node' AND status<>'retired' LIMIT 1");
        $nodeStmt->execute([$propertyCode]);$node=$nodeStmt->fetch();if(!is_array($node))$node=null;
        if($plan===null&&$node===null)throw new RuntimeException('La propiedad no existe.');
        $nodeId=(int)($node['id']??0);$propertyName=(string)($node['installation_name']??$plan['installation_name']??$propertyCode);
        $nodeUrl=rtrim((string)($node['base_url']??$plan['planned_base_url']??''),'/');
        $hasPropertyCode=$this->hasEdgeDeploymentPropertyCode();
        if(!$hasPropertyCode&&$nodeId<=0)throw new RuntimeException('Central todavía no aplicó la migración 0045 requerida para preparar Edge Agents antes del Node. Ejecute Administración → Reparar esquema/migraciones y vuelva a intentarlo.');

        $agentCode=strtoupper(trim($agentCode));
        if($agentCode===''){
            $base=preg_replace('/[^A-Z0-9_-]/','',$propertyCode)?:'PROPERTY';
            if($hasPropertyCode){$q=$this->pdo->prepare("SELECT COUNT(*) FROM bh_repo_edge_deployments WHERE property_code=? OR (property_code IS NULL AND parent_installation_id=?)");$q->execute([$propertyCode,$nodeId>0?$nodeId:-1]);}
            else{$q=$this->pdo->prepare("SELECT COUNT(*) FROM bh_repo_edge_deployments WHERE parent_installation_id=?");$q->execute([$nodeId]);}
            $n=(int)$q->fetchColumn()+1;$agentCode=$base.'-EDGE-'.str_pad((string)$n,2,'0',STR_PAD_LEFT);
        }
        if(!preg_match('/^[A-Z0-9][A-Z0-9_-]{2,79}$/',$agentCode))throw new InvalidArgumentException('Código Edge no válido.');
        $agentName=trim($agentName)!==''?trim($agentName):($agentCode===$propertyCode.'-EDGE-01'?'Edge Principal':'Edge '.str_replace(['-','_'],' ',$agentCode));
        $plain='PHEDGE-C-'.strtoupper(bin2hex(random_bytes(16)));$expires=date('Y-m-d H:i:s',time()+86400);

        $this->pdo->beginTransaction();
        try{
            if($hasPropertyCode){
                $existing=$this->pdo->prepare("SELECT id FROM bh_repo_edge_deployments WHERE property_code=? AND edge_code=? LIMIT 1 FOR UPDATE");$existing->execute([$propertyCode,$agentCode]);$deploymentId=(int)($existing->fetchColumn()?:0);
                if($deploymentId<=0&&$nodeId>0){$legacy=$this->pdo->prepare("SELECT id FROM bh_repo_edge_deployments WHERE property_code IS NULL AND parent_installation_id=? AND edge_code=? LIMIT 1 FOR UPDATE");$legacy->execute([$nodeId,$agentCode]);$deploymentId=(int)($legacy->fetchColumn()?:0);}
            }else{
                $existing=$this->pdo->prepare("SELECT id FROM bh_repo_edge_deployments WHERE parent_installation_id=? AND edge_code=? LIMIT 1 FOR UPDATE");$existing->execute([$nodeId,$agentCode]);$deploymentId=(int)($existing->fetchColumn()?:0);
            }
            if($deploymentId>0){
                $this->pdo->prepare("UPDATE bh_repo_edge_enrollment_tokens SET status='revoked',revoked_at=NOW(),revocation_reason='Reemplazado por un nuevo token de enrolamiento' WHERE edge_deployment_id=? AND status='active'")->execute([$deploymentId]);
                if($hasPropertyCode){$q=$this->pdo->prepare("UPDATE bh_repo_edge_deployments SET parent_installation_id=?,property_code=?,edge_name=?,token_hash=?,status='pending',expires_at=?,claimed_at=NULL,claimed_agent_uuid=NULL,claimed_base_url=NULL,created_by=? WHERE id=?");$q->execute([$nodeId>0?$nodeId:null,$propertyCode,$agentName,hash('sha256',$plain),$expires,$userId,$deploymentId]);}
                else{$q=$this->pdo->prepare("UPDATE bh_repo_edge_deployments SET edge_name=?,token_hash=?,status='pending',expires_at=?,claimed_at=NULL,claimed_agent_uuid=NULL,claimed_base_url=NULL,created_by=? WHERE id=?");$q->execute([$agentName,hash('sha256',$plain),$expires,$userId,$deploymentId]);}
            }else{
                if($hasPropertyCode){$q=$this->pdo->prepare("INSERT INTO bh_repo_edge_deployments (parent_installation_id,property_code,edge_code,edge_name,token_hash,status,expires_at,created_by) VALUES (?,?,?,?,?,'pending',?,?)");$q->execute([$nodeId>0?$nodeId:null,$propertyCode,$agentCode,$agentName,hash('sha256',$plain),$expires,$userId]);}
                else{$q=$this->pdo->prepare("INSERT INTO bh_repo_edge_deployments (parent_installation_id,edge_code,edge_name,token_hash,status,expires_at,created_by) VALUES (?,?,?,?,'pending',?,?)");$q->execute([$nodeId,$agentCode,$agentName,hash('sha256',$plain),$expires,$userId]);}
                $deploymentId=(int)$this->pdo->lastInsertId();
            }
            $this->pdo->prepare("INSERT INTO bh_repo_edge_enrollment_tokens (edge_deployment_id,token_hash,status,expires_at,created_by) VALUES (?,?,'active',?,?)")->execute([$deploymentId,hash('sha256',$plain),$expires,$userId]);
            $this->pdo->commit();
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
        return ['token'=>$plain,'expires_at'=>$expires,'agent_code'=>$agentCode,'agent_name'=>$agentName,'property_code'=>$propertyCode,'property_name'=>$propertyName,'property_node_url'=>$nodeUrl,'central_url'=>(string)config('deployment.repository_public_url',app_base_url()),'distribution_chain'=>'central>node>edge'];
    }

    /**
     * Property-first view of Central. A property is the installation plan/master
     * record; its Property Node and Edge Agents are children of that property.
     * Historical Nodes without a plan are synthesized as a property so upgrades
     * never hide existing deployments.
     */
    public function propertyPortfolio(): array
    {
        $this->assertCentral();
        $properties=[];
        foreach($this->installationPlans() as $plan){
            $code=strtoupper((string)$plan['installation_code']);
            $properties[$code]=[
                'code'=>$code,'name'=>(string)$plan['installation_name'],'installation_type'=>(string)$plan['installation_type'],
                'environment_type'=>(string)($plan['environment_type']??'production'),'update_channel'=>(string)$plan['update_channel'],
                'plan'=>$plan,'node'=>null,'registered_edges'=>[],'edge_deployments'=>[],
            ];
        }
        $rows=$this->installations();$nodeIdToCode=[];
        foreach($rows as $row){
            if((string)($row['instance_role']??'building_node')==='edge_agent')continue;
            $code=strtoupper((string)$row['installation_code']);$nodeIdToCode[(int)$row['id']]=$code;
            if(!isset($properties[$code]))$properties[$code]=[
                'code'=>$code,'name'=>(string)$row['installation_name'],'installation_type'=>(string)$row['installation_type'],
                'environment_type'=>(string)($row['environment_type']??'production'),'update_channel'=>(string)$row['update_channel'],
                'plan'=>null,'node'=>null,'registered_edges'=>[],'edge_deployments'=>[],
            ];
            $properties[$code]['node']=$row;
            $properties[$code]['name']=(string)$row['installation_name'];
            $properties[$code]['installation_type']=(string)$row['installation_type'];
            $properties[$code]['environment_type']=(string)($row['environment_type']??$properties[$code]['environment_type']);
            $properties[$code]['update_channel']=(string)$row['update_channel'];
        }
        foreach($rows as $row){
            if((string)($row['instance_role']??'building_node')!=='edge_agent')continue;
            $parent=strtoupper(trim((string)($row['parent_installation_code']??'')));
            // A Property-first portfolio only exposes Edge Agents whose parent property
            // is known. Orphan technical rows stay available to diagnostics/cleanup but
            // must not become fake top-level properties that cannot be opened.
            if($parent==='' || !isset($properties[$parent])) continue;
            $properties[$parent]['registered_edges'][]=$row;
        }
        if($this->hasEdgeDeploymentPropertyCode()){$deployments=$this->pdo->query("SELECT d.* FROM bh_repo_edge_deployments d ORDER BY d.property_code,d.parent_installation_id,d.id")->fetchAll()?:[];}else{$deployments=$this->pdo->query("SELECT d.* FROM bh_repo_edge_deployments d ORDER BY d.parent_installation_id,d.id")->fetchAll()?:[];}
        foreach($deployments as $d){$code=$this->hasEdgeDeploymentPropertyCode()?strtoupper(trim((string)($d['property_code']??''))):'';if($code==='')$code=$nodeIdToCode[(int)($d['parent_installation_id']??0)]??'';if($code!==''&&isset($properties[$code]))$properties[$code]['edge_deployments'][]=$d;}
        $list=array_values($properties);
        foreach($list as &$property){
            $node=(array)($property['node']??[]);$plan=(array)($property['plan']??[]);
            $property['status']=$node?'registered':(($plan['status']??'')==='token_issued'?'waiting_node':'prepared');
            $property['health_status']=$node?(string)($node['health_status']??'unknown'):'pending';
            $property['node_connected']=$node?!empty($node['node_connected']):false;
            $property['last_seen_at']=$node?(string)($node['last_seen_at']??''):'';
            $edgeCodes=[];foreach((array)$property['registered_edges'] as $e)$edgeCodes[(string)$e['installation_code']]=true;foreach((array)$property['edge_deployments'] as $e)$edgeCodes[(string)$e['edge_code']]=true;
            $property['edge_count']=count($edgeCodes);
            $property['claimed_edge_count']=count(array_filter((array)$property['edge_deployments'],static fn(array $e):bool=>(string)($e['status']??'')==='claimed'));
        }
        unset($property);
        usort($list,static fn(array $a,array $b):int=>strnatcasecmp((string)$a['name'],(string)$b['name']));
        return $list;
    }

    /** @return array<string,mixed> */
    public function propertyDetail(string $propertyCode): array
    {
        $this->assertCentral();$code=strtoupper(trim($propertyCode));if($code==='')throw new InvalidArgumentException('Falta el código de la propiedad.');
        $planStmt=$this->pdo->prepare("SELECT p.*,t.id token_id,t.token_purpose,t.used_count,t.max_uses,t.expires_at token_expires_at,t.status token_status FROM bh_repo_installation_plans p LEFT JOIN bh_repo_registration_tokens t ON t.id=p.registration_token_id WHERE p.installation_code=? AND p.status<>'cancelled' LIMIT 1");
        $planStmt->execute([$code]);$plan=$planStmt->fetch();if(!is_array($plan))$plan=null;
        $nodeStmt=$this->pdo->prepare("SELECT id FROM bh_repo_installations WHERE installation_code=? AND instance_role='building_node' AND status<>'retired' LIMIT 1");$nodeStmt->execute([$code]);$nodeId=(int)($nodeStmt->fetchColumn()?:0);
        $node=$nodeId>0?$this->installationDetail($nodeId):null;
        if($plan===null&&$node===null)throw new RuntimeException('Propiedad no encontrada.');
        $edgeRows=[];$deployments=[];$edgeTokens=[];
        if($nodeId>0){
            $q=$this->pdo->prepare("SELECT * FROM bh_repo_installations WHERE instance_role='edge_agent' AND parent_installation_code=? AND status<>'retired' ORDER BY installation_name,installation_code");$q->execute([$code]);$edgeRows=$q->fetchAll()?:[];
        }
        if($this->hasEdgeDeploymentPropertyCode()){
            $q=$this->pdo->prepare("SELECT * FROM bh_repo_edge_deployments WHERE property_code=? OR (property_code IS NULL AND parent_installation_id=?) ORDER BY edge_name,edge_code");$q->execute([$code,$nodeId>0?$nodeId:-1]);$deployments=$q->fetchAll()?:[];
            $q=$this->pdo->prepare("SELECT t.*,d.edge_code,d.edge_name,d.status deployment_status FROM bh_repo_edge_enrollment_tokens t JOIN bh_repo_edge_deployments d ON d.id=t.edge_deployment_id WHERE d.property_code=? OR (d.property_code IS NULL AND d.parent_installation_id=?) ORDER BY t.created_at DESC,t.id DESC");$q->execute([$code,$nodeId>0?$nodeId:-1]);$edgeTokens=$q->fetchAll()?:[];
        }elseif($nodeId>0){
            $q=$this->pdo->prepare("SELECT * FROM bh_repo_edge_deployments WHERE parent_installation_id=? ORDER BY edge_name,edge_code");$q->execute([$nodeId]);$deployments=$q->fetchAll()?:[];
            $q=$this->pdo->prepare("SELECT t.*,d.edge_code,d.edge_name,d.status deployment_status FROM bh_repo_edge_enrollment_tokens t JOIN bh_repo_edge_deployments d ON d.id=t.edge_deployment_id WHERE d.parent_installation_id=? ORDER BY t.created_at DESC,t.id DESC");$q->execute([$nodeId]);$edgeTokens=$q->fetchAll()?:[];
        }
        $targetCodes=[$code];$installationIds=$nodeId>0?[$nodeId]:[];
        foreach($edgeRows as $e){$targetCodes[]=(string)$e['installation_code'];$installationIds[]=(int)$e['id'];}
        foreach($deployments as $e)$targetCodes[]=(string)$e['edge_code'];
        $targetCodes=array_values(array_unique(array_filter($targetCodes)));
        $where=[];$params=[];
        if($targetCodes){$where[]='allowed_installation_code IN ('.implode(',',array_fill(0,count($targetCodes),'?')).')';$params=array_merge($params,$targetCodes);}
        if($installationIds){$where[]='installation_id IN ('.implode(',',array_fill(0,count($installationIds),'?')).')';$params=array_merge($params,$installationIds);}
        $registrationTokens=[];
        if($where){$q=$this->pdo->prepare("SELECT id,token_name,token_purpose,allowed_installation_code,allowed_channel,installation_id,max_uses,used_count,expires_at,status,created_at,last_used_at,revoked_at,revocation_reason FROM bh_repo_registration_tokens WHERE ".implode(' OR ',$where)." ORDER BY created_at DESC,id DESC");$q->execute($params);$registrationTokens=$q->fetchAll()?:[];}
        $now=time();foreach($registrationTokens as &$t){if((string)($t['status']??'')==='active'&&!empty($t['expires_at'])&&strtotime((string)$t['expires_at'])<$now)$t['status']='expired';}unset($t);foreach($edgeTokens as &$t){if((string)($t['status']??'')==='active'&&!empty($t['expires_at'])&&strtotime((string)$t['expires_at'])<$now)$t['status']='expired';}unset($t);
        $activity=[];if($installationIds){$q=$this->pdo->prepare("SELECT l.*,i.installation_code,i.installation_name FROM bh_repo_delivery_logs l JOIN bh_repo_installations i ON i.id=l.installation_id WHERE l.installation_id IN (".implode(',',array_fill(0,count($installationIds),'?')).") ORDER BY l.id DESC LIMIT 50");$q->execute($installationIds);$activity=$q->fetchAll()?:[];}
        $deploymentByCode=[];foreach($deployments as $d)$deploymentByCode[(string)$d['edge_code']]=$d;
        $registeredByCode=[];foreach($edgeRows as $e)$registeredByCode[(string)$e['installation_code']]=$e;
        $edgeCodes=array_values(array_unique(array_merge(array_keys($deploymentByCode),array_keys($registeredByCode))));sort($edgeCodes,SORT_NATURAL|SORT_FLAG_CASE);
        $edges=[];foreach($edgeCodes as $edgeCode)$edges[]=['code'=>$edgeCode,'deployment'=>$deploymentByCode[$edgeCode]??null,'registry'=>$registeredByCode[$edgeCode]??null];
        return [
            'code'=>$code,'name'=>(string)($node['installation_name']??$plan['installation_name']??$code),'installation_type'=>(string)($node['installation_type']??$plan['installation_type']??'other'),
            'environment_type'=>(string)($node['environment_type']??$plan['environment_type']??'production'),'update_channel'=>(string)($node['update_channel']??$plan['update_channel']??'stable'),
            'plan'=>$plan,'node'=>$node,'node_id'=>$nodeId,'edges'=>$edges,'edge_deployments'=>$deployments,'registration_tokens'=>$registrationTokens,'edge_enrollment_tokens'=>$edgeTokens,'activity'=>$activity,
            'central_url'=>(string)config('deployment.repository_public_url',app_base_url()),'node_url'=>(string)($node['base_url']??''),
        ];
    }

    public function propertyCodeForInstallation(int $installationId): string
    {
        $this->assertCentral();$stmt=$this->pdo->prepare("SELECT installation_code,parent_installation_code,instance_role FROM bh_repo_installations WHERE id=? LIMIT 1");$stmt->execute([$installationId]);$row=$stmt->fetch();if(!is_array($row))throw new RuntimeException('Instalación no encontrada.');return (string)(((string)($row['instance_role']??'building_node')==='edge_agent'&&trim((string)($row['parent_installation_code']??''))!=='')?$row['parent_installation_code']:$row['installation_code']);
    }

    public function revokeEdgeEnrollmentToken(int $tokenId,?int $userId=null,string $reason='Revocado manualmente'): string
    {
        $this->assertCentral();$this->pdo->beginTransaction();try{
            $sql=$this->hasEdgeDeploymentPropertyCode()?"SELECT t.*,d.parent_installation_id,d.property_code,d.edge_code FROM bh_repo_edge_enrollment_tokens t JOIN bh_repo_edge_deployments d ON d.id=t.edge_deployment_id WHERE t.id=? FOR UPDATE":"SELECT t.*,d.parent_installation_id,d.edge_code FROM bh_repo_edge_enrollment_tokens t JOIN bh_repo_edge_deployments d ON d.id=t.edge_deployment_id WHERE t.id=? FOR UPDATE";
            $q=$this->pdo->prepare($sql);$q->execute([$tokenId]);$row=$q->fetch();if(!is_array($row))throw new RuntimeException('Token Edge no encontrado.');
            if((string)$row['status']==='active')$this->pdo->prepare("UPDATE bh_repo_edge_enrollment_tokens SET status='revoked',revoked_at=NOW(),revocation_reason=? WHERE id=?")->execute([trim($reason)?:'Revocado manualmente',$tokenId]);
            $this->pdo->prepare("UPDATE bh_repo_edge_deployments SET status=CASE WHEN status='pending' THEN 'revoked' ELSE status END WHERE id=?")->execute([(int)$row['edge_deployment_id']]);
            $code=strtoupper(trim((string)($row['property_code']??'')));if($code===''&&(int)($row['parent_installation_id']??0)>0)$code=$this->propertyCodeForInstallation((int)$row['parent_installation_id']);if($code==='')throw new RuntimeException('El token Edge no tiene una propiedad asociada.');$this->pdo->commit();return $code;
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    public function createApiVersion(array $data): void
    {
        $this->assertCentral();
        $stmt=$this->pdo->prepare("INSERT INTO bh_repo_api_versions (api_code,version,status,minimum_app_version,maximum_app_version,documentation_path,changelog,released_at,support_ends_at) VALUES (?,?,?,?,?,?,?,?,?)");
        $stmt->execute([$data['api_code'],$data['version'],$data['status'],$data['minimum_app_version']?:null,$data['maximum_app_version']?:null,$data['documentation_path']?:null,$data['changelog']?:null,$data['status']==='stable'?date('Y-m-d H:i:s'):null,$data['support_ends_at']?:null]);
    }

    private function hasEdgeDeploymentPropertyCode(): bool
    {
        if($this->edgeDeploymentPropertyCodeColumn!==null)return $this->edgeDeploymentPropertyCodeColumn;
        try{$q=$this->pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_repo_edge_deployments' AND COLUMN_NAME='property_code'");$this->edgeDeploymentPropertyCodeColumn=((int)$q->fetchColumn()>0);}catch(Throwable $e){$this->edgeDeploymentPropertyCodeColumn=false;}
        return $this->edgeDeploymentPropertyCodeColumn;
    }

    private function visibleInstallationWhere(): string
    {
        $uuid=$this->pdo->quote((string)config('deployment.installation_uuid'));
        return "i.status<>'retired' AND i.installation_uuid<>{$uuid}";
    }

    private static function uuid(): string
    {
        $data=random_bytes(16);
        $data[6]=chr((ord($data[6])&0x0f)|0x40);
        $data[8]=chr((ord($data[8])&0x3f)|0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($data),4));
    }

    private function assertCentral(): void
    {
        if (!InstallationMode::isCentral()) throw new RuntimeException('Módulo disponible únicamente en la fuente oficial.');
    }
}
