<?php
declare(strict_types=1);
require __DIR__.'/_bootstrap.php';
if (($_SERVER['REQUEST_METHOD']??'GET')!=='POST') api_response(['ok'=>false,'error'=>'method_not_allowed'],405);
$input=api_input();
$token=api_header('X-Registration-Token');
if ($token==='') api_response(['ok'=>false,'error'=>'missing_registration_token'],401);
foreach (['installation_uuid','installation_key','installation_code','installation_name','installation_type','base_url','update_channel'] as $field) {
    if (trim((string)($input[$field]??''))==='') api_response(['ok'=>false,'error'=>'missing_field','field'=>$field],422);
}

$pdo=Connection::pdo();
$stmt=$pdo->prepare(
    "SELECT * FROM bh_repo_registration_tokens " .
    "WHERE token_hash=? AND status='active' AND (expires_at IS NULL OR expires_at>NOW()) AND used_count<max_uses LIMIT 1"
);
$stmt->execute([hash('sha256',$token)]);
$tokenRow=$stmt->fetch();
if (!is_array($tokenRow)) api_response(['ok'=>false,'error'=>'invalid_or_expired_registration_token'],403);

$inputCode=strtoupper(trim((string)$input['installation_code']));
if (!empty($tokenRow['allowed_installation_code']) && !hash_equals((string)$tokenRow['allowed_installation_code'],$inputCode)) {
    api_response(['ok'=>false,'error'=>'token_not_valid_for_installation'],403);
}
$purpose=(string)($tokenRow['token_purpose']??'initial_registration');
$channel=(string)($tokenRow['allowed_channel'] ?: $input['update_channel']);
$appVersion=trim((string)($input['app_version']??config('app.version','0.0.0'))) ?: '0.0.0';
$apiVersion=trim((string)($input['api_version']??'v1')) ?: 'v1';
$uiVersion=trim((string)($input['ui_version']??config('app.ui_standard_version',''))) ?: null;
$requestedRole=(string)($input['instance_role']??'building_node');
if($requestedRole==='edge_agent'){
    api_response(['ok'=>false,'error'=>'direct_edge_registration_disabled','message'=>'Los Edge Agents no se registran directamente en Central. Genere un PHEDGE en el Property Node y use la cadena Central → Node → Edge.'],409);
}
$instanceRole='building_node';
$parentCode='';

try {
    $pdo->beginTransaction();

    if ($purpose==='relink') {
        $installationId=(int)($tokenRow['installation_id']??0);
        if ($installationId<=0) throw new RuntimeException('Token de reconexión sin instalación asociada.');
        $lock=$pdo->prepare("SELECT * FROM bh_repo_installations WHERE id=? AND status<>'retired' FOR UPDATE");
        $lock->execute([$installationId]);
        $existing=$lock->fetch();
        if(!is_array($existing)) throw new RuntimeException('La instalación asociada al token ya no existe.');
        if(!hash_equals((string)$existing['installation_code'],$inputCode)) throw new RuntimeException('El código del nodo no coincide con el expediente central.');

        $collision=$pdo->prepare('SELECT id FROM bh_repo_installations WHERE installation_uuid=? AND id<>? LIMIT 1');
        $collision->execute([(string)$input['installation_uuid'],$installationId]);
        if($collision->fetchColumn()!==false) throw new RuntimeException('El UUID presentado ya pertenece a otra instalación.');

        $stmt=$pdo->prepare(
            "UPDATE bh_repo_installations SET installation_uuid=?,installation_type=?,instance_role=?,parent_installation_code=?,base_url=?,app_version=?,api_version=?,ui_version=?,update_channel=?,installation_key_hash=?,public_key=?,status='active',last_seen_at=NOW(),last_credentials_rotated_at=NOW(),last_error_summary=NULL WHERE id=?"
        );
        $stmt->execute([
            (string)$input['installation_uuid'],(string)$input['installation_type'],$instanceRole,$parentCode!==''?$parentCode:null,
            rtrim((string)$input['base_url'],'/'),$appVersion,$apiVersion,$uiVersion,$channel,
            hash('sha256',(string)$input['installation_key']),(string)($input['public_key']??''),$installationId
        ]);
        $eventType='credentials_rotated';
        $eventDetails=['channel'=>$channel,'purpose'=>'relink','token_id'=>(int)$tokenRow['id'],'previous_uuid'=>(string)$existing['installation_uuid'],'instance_role'=>$instanceRole,'parent_property_code'=>$parentCode];
    } else {
        $existingByCode=$pdo->prepare("SELECT id FROM bh_repo_installations WHERE installation_code=? AND status<>'retired' LIMIT 1 FOR UPDATE");
        $existingByCode->execute([$inputCode]);
        if($existingByCode->fetchColumn()!==false){
            throw new RuntimeException('La instalación ya está registrada. Genere un token de reconexión desde su ficha en Property Hub Central.');
        }
        $stmt=$pdo->prepare(
            "INSERT INTO bh_repo_installations (installation_uuid,installation_code,installation_name,installation_type,instance_role,parent_installation_code,base_url,app_version,api_version,ui_version,update_channel,installation_key_hash,public_key,status,last_seen_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,'active',NOW())"
        );
        $stmt->execute([
            (string)$input['installation_uuid'],$inputCode,(string)$input['installation_name'],(string)$input['installation_type'],$instanceRole,$parentCode!==''?$parentCode:null,
            rtrim((string)$input['base_url'],'/'),$appVersion,$apiVersion,$uiVersion,$channel,
            hash('sha256',(string)$input['installation_key']),(string)($input['public_key']??'')
        ]);
        $installationId=(int)$pdo->lastInsertId();
        $planLookup=$pdo->prepare("SELECT id,installation_name,data_scope,environment_type,clone_source_code,technology_json FROM bh_repo_installation_plans WHERE installation_code=? AND status IN ('ready','token_issued','draft') LIMIT 1 FOR UPDATE");
        $planLookup->execute([$inputCode]);
        $planRow=$planLookup->fetch();
        if(is_array($planRow)) {
            $tech=json_decode((string)($planRow['technology_json']??''),true);$desired=[];foreach((array)$tech as $code){$code=strtolower(trim((string)$code));if($code!=='')$desired[]=['code'=>$code,'enabled'=>true];}
            $metadata=['desired_connectors'=>$desired,'desired_connectors_updated_at'=>date(DATE_ATOM),'desired_connectors_source'=>'installation_plan'];
            $pdo->prepare("UPDATE bh_repo_installations SET installation_name=?,data_scope=?,environment_type=?,clone_source_code=?,metadata_json=? WHERE id=?")->execute([
                (string)$planRow['installation_name'],
                (string)$planRow['data_scope'],
                (string)($planRow['environment_type']??'production'),
                !empty($planRow['clone_source_code'])?(string)$planRow['clone_source_code']:null,
                json_encode($metadata,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),
                $installationId
            ]);
            $pdo->prepare("UPDATE bh_repo_installation_plans SET status='registered',registered_installation_id=?,registered_at=NOW() WHERE id=?")
                ->execute([$installationId,(int)$planRow['id']]);
        }
        $eventType='registered';
        $eventDetails=['channel'=>$channel,'purpose'=>'initial_registration','token_id'=>(int)$tokenRow['id'],'instance_role'=>$instanceRole,'parent_property_code'=>$parentCode];
    }

    // Attach Edge Agents prepared from the property expediente before the Node existed.
    // This is migration-aware so an update request cannot break registration while 0045 is pending.
    $hasEdgePropertyCode=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_repo_edge_deployments' AND COLUMN_NAME='property_code'")->fetchColumn()>0;
    if($hasEdgePropertyCode)$pdo->prepare("UPDATE bh_repo_edge_deployments SET parent_installation_id=?,property_code=? WHERE property_code=? AND (parent_installation_id IS NULL OR parent_installation_id<>?)")
        ->execute([$installationId,$inputCode,$inputCode,$installationId]);

    $stmt=$pdo->prepare("UPDATE bh_repo_registration_tokens SET installation_id=COALESCE(installation_id,?),status=IF(used_count+1>=max_uses,'expired',status),used_count=used_count+1,last_used_at=NOW() WHERE id=?");
    $stmt->execute([$installationId,(int)$tokenRow['id']]);

    $log=$pdo->prepare("INSERT INTO bh_repo_delivery_logs (installation_id,event_type,status,remote_ip,details_json) VALUES (?,?, 'ok',?,?)");
    $log->execute([$installationId,$eventType,(string)($_SERVER['REMOTE_ADDR']??''),json_encode($eventDetails,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]);
    $pdo->commit();
} catch (Throwable $e) {
    if($pdo->inTransaction())$pdo->rollBack();
    api_response(['ok'=>false,'error'=>$purpose==='relink'?'relink_failed':'registration_failed','message'=>$e->getMessage()],409);
}

$publicPath=base_path((string)config('deployment.repository_public_key_path'));
api_response([
    'ok'=>true,'operation'=>$purpose==='relink'?'relinked':'registered','installation_id'=>$installationId,
    'installation_uuid'=>(string)$input['installation_uuid'],'installation_code'=>$inputCode,'instance_role'=>$instanceRole,'parent_property_code'=>$parentCode,'channel'=>$channel,
    'repository_public_key'=>(string)@file_get_contents($publicPath),'repository_api'=>'v1','server_time'=>date(DATE_ATOM)
],$purpose==='relink'?200:201);
