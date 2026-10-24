<?php
declare(strict_types=1);

final class NodeTelemetryService
{
    public function __construct(private ?PDO $pdo=null) { $this->pdo ??= Connection::pdo(); }

    public function payload(): array
    {
        $installationId=$this->installationId();
        $profile=$this->profile($installationId);
        $metrics=[
            'user_count'=>$this->count('bh_local_users','installation_id=?',[$installationId]),
            'people_count'=>$this->count('bh_people','installation_id=?',[$installationId]),
            'unit_count'=>$this->count('bh_properties',"installation_id=? AND property_type IN ('apartment','office','commercial_unit','house','warehouse')",[$installationId]),
            'area_count'=>$this->count('bh_areas','installation_id=?',[$installationId]),
            'active_visitor_count'=>$this->count('bh_visits',"installation_id=? AND status='inside'",[$installationId]),
            'pending_reservation_count'=>$this->count('bh_reservations',"installation_id=? AND status='pending'",[$installationId]),
            'camera_count'=>$this->count('bh_cameras','installation_id=?',[$installationId]),
            'access_point_count'=>$this->count('bh_access_points','installation_id=?',[$installationId]),
            'connector_count'=>$this->count('bh_connector_instances','installation_id=?',[$installationId]),
            'device_count'=>$this->count('bh_devices','installation_id=?',[$installationId]),
            'online_device_count'=>$this->count('bh_devices',"installation_id=? AND status='online'",[$installationId]),
            'warning_device_count'=>$this->count('bh_devices',"installation_id=? AND status IN ('offline','maintenance')",[$installationId]),
            'queue_depth'=>$this->count('bh_update_jobs',"installation_id=? AND status IN ('queued','checking','downloading','downloaded','validated','backing_up','installing')",[$installationId]),
            'critical_alert_count'=>$this->count('bh_connector_instances',"installation_id=? AND status='error'",[$installationId]),
            'disk_free_bytes'=>@disk_free_space(base_path()) ?: null,
        ];
        if(config('app.demo_mode',false) && array_sum(array_filter($metrics,'is_int'))<5){
            $demo=DemoRepository::dashboardMetrics();
            $metrics=array_replace($metrics,[
                'user_count'=>34,'people_count'=>(int)$demo['people'],'unit_count'=>(int)$demo['properties'],
                'area_count'=>count(DemoRepository::areas()),'active_visitor_count'=>(int)$demo['visitors_active'],
                'pending_reservation_count'=>(int)$demo['reservations_pending'],'camera_count'=>count(DemoRepository::cameras()),
                'access_point_count'=>count(DemoRepository::accessPoints()),'connector_count'=>count(DemoRepository::connectors()),
                'device_count'=>76,'online_device_count'=>72,'warning_device_count'=>3,
            ]);
        }
        $infrastructure=[
            'location_count'=>$this->count('bh_site_locations','installation_id=?',[$installationId]),
            'guard_post_count'=>$this->count('bh_site_locations',"installation_id=? AND location_type='guard_post'",[$installationId]),
            'connectivity_link_count'=>$this->count('bh_connectivity_links','installation_id=?',[$installationId]),
            'lorawan_network_count'=>$this->count('bh_lorawan_networks','installation_id=?',[$installationId]),
            'lorawan_gateway_count'=>$this->count('bh_lorawan_gateways','installation_id=?',[$installationId]),
            'lorawan_device_count'=>$this->count('bh_lorawan_devices','installation_id=?',[$installationId]),
            'lorawan_warning_count'=>$this->count('bh_lorawan_devices',"installation_id=? AND status IN ('warning','offline')",[$installationId]),
            'architecture'=>'single_core_direct_connectivity',
            'requires_property_edge'=>InstallationMode::isNode(),
        ];
        $connectors=[];
        if(!empty($profile['share_connector_inventory'])){
            $stmt=$this->pdo->prepare(
                'SELECT cd.code connector_code,ci.name display_name,cd.vendor vendor_name,cd.domain,cd.name product_family,cd.version connector_version,ci.api_family,ci.api_version,ci.integration_mode,ci.status,ci.device_count,ci.capabilities_json '
                .'FROM bh_connector_instances ci JOIN bh_connector_definitions cd ON cd.id=ci.connector_definition_id WHERE ci.installation_id=? ORDER BY ci.id'
            );
            $stmt->execute([$installationId]);$connectors=$stmt->fetchAll();
        }
        $backup=['enabled'=>false,'last_backup_at'=>null,'last_status'=>'unknown','last_size_bytes'=>0];
        try{
            $stmt=$this->pdo->prepare('SELECT enabled,last_backup_at,last_status,last_message FROM bh_backup_settings WHERE installation_id=? LIMIT 1');$stmt->execute([$installationId]);$row=$stmt->fetch();if(is_array($row))$backup=array_merge($backup,$row);
            $stmt=$this->pdo->prepare("SELECT size_bytes,backup_type,status FROM bh_backup_runs WHERE installation_id=? AND status IN ('completed','warning') ORDER BY id DESC LIMIT 1");$stmt->execute([$installationId]);$last=$stmt->fetch();if(is_array($last)){$backup['last_size_bytes']=(int)($last['size_bytes']??0);$backup['last_type']=$last['backup_type']??null;}
        }catch(Throwable $e){}
        return [
            'app_version'=>(string)config('app.version'),'base_url'=>(string)config('app.url'),'api_version'=>'v1','ui_version'=>(string)config('app.ui_standard_version'),
            'instance_role'=>InstallationMode::current(),'parent_property_code'=>InstallationMode::isEdge()?(string)config('deployment.parent_property_code',''):'',
            'health_status'=>$this->health($metrics),'data_scope'=>(string)($profile['data_scope']??'aggregates'),
            'metrics'=>!empty($profile['share_aggregate_metrics'])?$metrics:[],
            'connectors'=>$connectors,
            'infrastructure'=>$infrastructure,
            'backup'=>$backup,
            'privacy'=>['contains_personal_data'=>false,'contains_credentials'=>false,'contains_message_content'=>false],
        ];
    }

    private function installationId(): int
    {
        $stmt=$this->pdo->prepare('SELECT id FROM bh_installations WHERE public_uuid=? LIMIT 1');$stmt->execute([(string)config('deployment.installation_uuid')]);return (int)$stmt->fetchColumn();
    }
    private function profile(int $id): array
    {
        $stmt=$this->pdo->prepare('SELECT * FROM bh_technology_profiles WHERE installation_id=? LIMIT 1');$stmt->execute([$id]);return $stmt->fetch()?:['data_scope'=>'aggregates','share_aggregate_metrics'=>1,'share_connector_inventory'=>1];
    }
    private function count(string $table,string $where,array $params): int
    {
        try{$stmt=$this->pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE {$where}");$stmt->execute($params);return (int)$stmt->fetchColumn();}catch(Throwable){return 0;}
    }
    private function health(array $m): string
    {
        if(($m['critical_alert_count']??0)>0)return 'critical';
        if(($m['warning_device_count']??0)>0)return 'warning';
        return 'healthy';
    }
}
