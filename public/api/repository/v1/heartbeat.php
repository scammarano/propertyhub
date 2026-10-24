<?php
declare(strict_types=1);
require __DIR__.'/_bootstrap.php';
if (($_SERVER['REQUEST_METHOD']??'GET')!=='POST') api_response(['ok'=>false,'error'=>'method_not_allowed'],405);
$input=api_input(); $pdo=Connection::pdo(); $installation=api_auth_installation($pdo,$input);
$health=in_array((string)($input['health_status']??''),['healthy','warning','critical','offline','unknown'],true)?(string)$input['health_status']:'unknown';
$scope=in_array((string)($input['data_scope']??''),['metadata_only','aggregates','support_snapshot'],true)?(string)$input['data_scope']:'aggregates';
$instanceRole=in_array((string)($input['instance_role']??($installation['instance_role']??'building_node')),['building_node','edge_agent'],true)?(string)($input['instance_role']??($installation['instance_role']??'building_node')):'building_node';
$parentCode=$instanceRole==='edge_agent'?strtoupper(trim((string)($input['parent_property_code']??($installation['parent_installation_code']??'')))):'';
$stmt=$pdo->prepare("UPDATE bh_repo_installations SET base_url=COALESCE(NULLIF(?,''),base_url),app_version=?,api_version=?,ui_version=?,instance_role=?,parent_installation_code=?,data_scope=?,health_status=?,last_seen_at=NOW(),status=IF(status='offline','active',status),last_error_summary=NULL WHERE id=?");
$stmt->execute([(string)($input['base_url']??''),(string)($input['app_version']??$installation['app_version']),(string)($input['api_version']??$installation['api_version']),(string)($input['ui_version']??$installation['ui_version']),$instanceRole,$parentCode!==''?$parentCode:null,$scope,$health,(int)$installation['id']]);
$metrics=(array)($input['metrics']??[]);
if($metrics!==[]){
    $fields=['user_count','people_count','unit_count','area_count','active_visitor_count','pending_reservation_count','camera_count','access_point_count','connector_count','device_count','online_device_count','warning_device_count','queue_depth','critical_alert_count','disk_free_bytes'];
    $values=[];foreach($fields as $field)$values[]=$metrics[$field]??0;
    $stmt=$pdo->prepare('INSERT INTO bh_repo_installation_metrics (installation_id,user_count,people_count,unit_count,area_count,active_visitor_count,pending_reservation_count,camera_count,access_point_count,connector_count,device_count,online_device_count,warning_device_count,queue_depth,critical_alert_count,disk_free_bytes,summary_json,reported_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())');
    $stmt->execute([(int)$installation['id'],...$values,json_encode(['privacy'=>$input['privacy']??[],'infrastructure'=>$input['infrastructure']??[],'backup'=>$input['backup']??[]],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]);
}
$connectors=(array)($input['connectors']??[]);
if($connectors!==[]){
    $seen=[];
    $stmt=$pdo->prepare("INSERT INTO bh_repo_connector_inventory (installation_id,connector_code,display_name,vendor_name,domain,product_family,connector_version,api_family,api_version,integration_mode,status,device_count,capabilities_json,last_seen_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE vendor_name=VALUES(vendor_name),domain=VALUES(domain),product_family=VALUES(product_family),connector_version=VALUES(connector_version),api_family=VALUES(api_family),api_version=VALUES(api_version),integration_mode=VALUES(integration_mode),status=VALUES(status),device_count=VALUES(device_count),capabilities_json=VALUES(capabilities_json),last_seen_at=NOW()");
    foreach($connectors as $connector){
        if(!is_array($connector))continue;
        $code=preg_replace('/[^a-z0-9_-]/','',strtolower((string)($connector['connector_code']??'')))?:'unknown';
        $name=trim((string)($connector['display_name']??$code));
        $key=$code.'|'.$name;$seen[]=$key;
        $status=in_array((string)($connector['status']??''),['configured','online','offline','warning','error','disabled','planned'],true)?(string)$connector['status']:'configured';
        $stmt->execute([(int)$installation['id'],$code,$name,(string)($connector['vendor_name']??''),(string)($connector['domain']??'other'),(string)($connector['product_family']??''),(string)($connector['connector_version']??''),(string)($connector['api_family']??''),(string)($connector['api_version']??''),(string)($connector['integration_mode']??''),$status,max(0,(int)($connector['device_count']??0)),is_string($connector['capabilities_json']??null)?$connector['capabilities_json']:json_encode($connector['capabilities_json']??[],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]);
    }
}
$log=$pdo->prepare("INSERT INTO bh_repo_delivery_logs (installation_id,event_type,status,remote_ip,details_json) VALUES (?,'heartbeat','ok',?,?)");
$log->execute([(int)$installation['id'],(string)($_SERVER['REMOTE_ADDR']??''),json_encode(['app_version'=>$input['app_version']??null,'api_version'=>$input['api_version']??null,'health_status'=>$health,'data_scope'=>$scope,'metrics_received'=>$metrics!==[],'connectors_received'=>count($connectors),'infrastructure_received'=>!empty($input['infrastructure']),'backup_received'=>!empty($input['backup']),'instance_role'=>$instanceRole,'parent_property_code'=>$parentCode],JSON_UNESCAPED_SLASHES)]);
$vaultService=new BackupVaultService($pdo);
try{$vaultService->scheduleDue();}catch(Throwable $scheduleError){ErrorReporter::report($scheduleError,['operation'=>'central_vault_schedule']);}
$maintenanceTasks=$vaultService->pendingTasks((int)$installation['id']);
$currentStmt=$pdo->prepare("SELECT installation_code,installation_name,last_seen_at,health_status,app_version,api_version,base_url FROM bh_repo_installations WHERE id=? LIMIT 1");
$currentStmt->execute([(int)$installation['id']]);$current=$currentStmt->fetch()?:[];
api_response([
    'ok'=>true,'server_time'=>date(DATE_ATOM),'repository_version'=>(string)config('app.version'),'channel'=>(string)$installation['update_channel'],'accepted_scope'=>$scope,'maintenance_tasks'=>$maintenanceTasks,
    'installation_id'=>(int)$installation['id'],'installation_code'=>(string)($current['installation_code']??$installation['installation_code']??''),'installation_name'=>(string)($current['installation_name']??$installation['installation_name']??''),
    'recorded_last_seen_at'=>(string)($current['last_seen_at']??''),'recorded_health_status'=>(string)($current['health_status']??$health),'recorded_app_version'=>(string)($current['app_version']??''),'recorded_api_version'=>(string)($current['api_version']??''),'recorded_base_url'=>(string)($current['base_url']??''),
]);
