<?php
declare(strict_types=1);
require __DIR__.'/_bootstrap.php';
if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST')api_response(['ok'=>false,'error'=>'method_not_allowed'],405);
$input=api_input();$pdo=Connection::pdo();$parent=api_auth_installation($pdo,$input);
if((string)($parent['instance_role']??'building_node')!=='building_node')api_response(['ok'=>false,'error'=>'parent_must_be_property_node'],403);
$agentCode=strtoupper(trim((string)($input['agent_code']??'')));$agentName=trim((string)($input['agent_name']??$agentCode));
if($agentCode===''||!preg_match('/^[A-Z0-9][A-Z0-9_-]{2,79}$/',$agentCode))api_response(['ok'=>false,'error'=>'invalid_agent_code'],422);
if(hash_equals((string)$parent['installation_code'],$agentCode))api_response(['ok'=>false,'error'=>'agent_code_must_differ_from_parent'],422);
try{
    $pdo->beginTransaction();
    $existingStmt=$pdo->prepare("SELECT * FROM bh_repo_installations WHERE installation_code=? AND status<>'retired' LIMIT 1 FOR UPDATE");$existingStmt->execute([$agentCode]);$existing=$existingStmt->fetch();
    if(is_array($existing)){
        if((string)$existing['instance_role']!=='edge_agent')throw new RuntimeException('El código solicitado ya pertenece a una instalación que no es Edge Agent.');
        $requestedParent=(string)$parent['installation_code'];
        $existingParent=trim((string)($existing['parent_installation_code']??''));
        if($existingParent!==''&&!hash_equals($existingParent,$requestedParent)){
            // Compatibilidad con Edge creados antes del emparejamiento administrado por Node:
            // solo impedimos moverlo si el padre anterior todavía existe y está activo.
            $parentState=$pdo->prepare("SELECT id FROM bh_repo_installations WHERE installation_code=? AND instance_role='building_node' AND status NOT IN ('retired','suspended') LIMIT 1");
            $parentState->execute([$existingParent]);
            if($parentState->fetchColumn()!==false)throw new RuntimeException('Ese Edge Agent pertenece a otro Property Node activo ('.$existingParent.').');
        }
        if($existingParent===''||!hash_equals($existingParent,$requestedParent)){
            $pdo->prepare("UPDATE bh_repo_installations SET parent_installation_code=?,updated_at=NOW() WHERE id=?")->execute([$requestedParent,(int)$existing['id']]);
        }
        $pdo->prepare("UPDATE bh_repo_registration_tokens SET status='revoked',revoked_at=NOW(),revocation_reason='Bootstrap Edge sustituido por Property Node' WHERE installation_id=? AND token_purpose='relink' AND status='active'")->execute([(int)$existing['id']]);
        $plain='BHR-RELINK-'.strtoupper(bin2hex(random_bytes(12)));$expires=date('Y-m-d H:i:s',strtotime('+24 hours'));
        $pdo->prepare("INSERT INTO bh_repo_registration_tokens (token_name,token_purpose,token_hash,allowed_installation_code,installation_id,allowed_channel,max_uses,expires_at,status) VALUES (?,'relink',?,?,?,?,1,?,'active')")
            ->execute(['Auto Edge relink '.$agentCode,hash('sha256',$plain),$agentCode,(int)$existing['id'],(string)$parent['update_channel'],$expires]);$purpose='relink';
    }else{
        $pdo->prepare("UPDATE bh_repo_registration_tokens SET status='revoked',revoked_at=NOW(),revocation_reason='Bootstrap Edge inicial sustituido por Property Node' WHERE allowed_installation_code=? AND token_purpose='initial_registration' AND status='active'")->execute([$agentCode]);
        $plain='BHR-'.strtoupper(bin2hex(random_bytes(12)));$expires=date('Y-m-d H:i:s',strtotime('+24 hours'));
        $pdo->prepare("INSERT INTO bh_repo_registration_tokens (token_name,token_purpose,token_hash,allowed_installation_code,allowed_channel,max_uses,expires_at,status) VALUES (?,'initial_registration',?,?,?,?,?,'active')")
            ->execute(['Auto Edge '.$agentCode.' · '.$agentName,hash('sha256',$plain),$agentCode,(string)$parent['update_channel'],1,$expires]);$purpose='initial_registration';
    }
    $log=$pdo->prepare("INSERT INTO bh_repo_delivery_logs (installation_id,event_type,status,remote_ip,details_json) VALUES (?,'registration_token_reissued','ok',?,?)");
    $log->execute([(int)$parent['id'],(string)($_SERVER['REMOTE_ADDR']??''),json_encode(['operation'=>'edge_bootstrap','agent_code'=>$agentCode,'purpose'=>$purpose],JSON_UNESCAPED_SLASHES)]);
    $pdo->commit();
    api_response(['ok'=>true,'registration_token'=>$plain,'expires_at'=>$expires,'purpose'=>$purpose,'agent_code'=>$agentCode,'parent_code'=>(string)$parent['installation_code'],'repository_url'=>(string)config('deployment.repository_public_url',app_base_url())]);
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();ErrorReporter::report($e,['operation'=>'edge_bootstrap_token','agent_code'=>$agentCode]);api_response(['ok'=>false,'error'=>'edge_bootstrap_failed','message'=>$e->getMessage()],422);}
