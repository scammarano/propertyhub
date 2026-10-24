<?php
declare(strict_types=1);
require __DIR__.'/_bootstrap.php';
if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST')api_response(['ok'=>false,'error'=>'method_not_allowed'],405);
$input=api_input();$pdo=Connection::pdo();$installation=api_auth_installation($pdo,$input);
if((string)($installation['instance_role']??'building_node')!=='building_node')api_response(['ok'=>false,'error'=>'node_required'],403);
$token=trim((string)($input['token']??''));if($token==='')api_response(['ok'=>false,'error'=>'missing_edge_enrollment_token'],422);
try{
 $pdo->beginTransaction();
 $hasPropertyCode=(int)$pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='bh_repo_edge_deployments' AND COLUMN_NAME='property_code'")->fetchColumn()>0;
 if($hasPropertyCode){
  $stmt=$pdo->prepare("SELECT t.id token_id,t.status token_status,t.expires_at token_expires_at,d.* FROM bh_repo_edge_enrollment_tokens t JOIN bh_repo_edge_deployments d ON d.id=t.edge_deployment_id WHERE (d.parent_installation_id=? OR (d.parent_installation_id IS NULL AND d.property_code=?)) AND t.token_hash=? AND t.status='active' AND t.expires_at>NOW() LIMIT 1 FOR UPDATE");
  $stmt->execute([(int)$installation['id'],(string)$installation['installation_code'],hash('sha256',$token)]);
 }else{
  $stmt=$pdo->prepare("SELECT t.id token_id,t.status token_status,t.expires_at token_expires_at,d.* FROM bh_repo_edge_enrollment_tokens t JOIN bh_repo_edge_deployments d ON d.id=t.edge_deployment_id WHERE d.parent_installation_id=? AND t.token_hash=? AND t.status='active' AND t.expires_at>NOW() LIMIT 1 FOR UPDATE");
  $stmt->execute([(int)$installation['id'],hash('sha256',$token)]);
 }
 $edge=$stmt->fetch();
 if(!is_array($edge)){if($pdo->inTransaction())$pdo->rollBack();api_response(['ok'=>false,'error'=>'invalid_or_expired_edge_enrollment'],403);}
 $code=strtoupper(trim((string)($input['agent_code']??'')));if($code===''||!hash_equals((string)$edge['edge_code'],$code)){if($pdo->inTransaction())$pdo->rollBack();api_response(['ok'=>false,'error'=>'edge_code_mismatch','expected_code'=>(string)$edge['edge_code']],409);}
 $pdo->prepare("UPDATE bh_repo_edge_enrollment_tokens SET status='used',used_at=NOW() WHERE id=?")->execute([(int)$edge['token_id']]);
 if($hasPropertyCode)$pdo->prepare("UPDATE bh_repo_edge_deployments SET parent_installation_id=?,property_code=?,status='claimed',claimed_at=NOW(),claimed_agent_uuid=?,claimed_base_url=? WHERE id=?")->execute([(int)$installation['id'],(string)$installation['installation_code'],trim((string)($input['agent_uuid']??''))?:null,trim((string)($input['base_url']??''))?:null,(int)$edge['id']]);
 else $pdo->prepare("UPDATE bh_repo_edge_deployments SET status='claimed',claimed_at=NOW(),claimed_agent_uuid=?,claimed_base_url=? WHERE id=?")->execute([trim((string)($input['agent_uuid']??''))?:null,trim((string)($input['base_url']??''))?:null,(int)$edge['id']]);
 $pdo->prepare("INSERT INTO bh_repo_delivery_logs (installation_id,event_type,status,remote_ip,details_json) VALUES (?,'edge_enrollment_claimed','ok',?,?)")->execute([(int)$installation['id'],(string)($_SERVER['REMOTE_ADDR']??''),json_encode(['edge_code'=>$code,'edge_name'=>(string)$edge['edge_name'],'agent_uuid'=>(string)($input['agent_uuid']??''),'token_id'=>(int)$edge['token_id']],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]);
 $pdo->commit();
 api_response(['ok'=>true,'property_code'=>(string)$installation['installation_code'],'property_name'=>(string)$installation['installation_name'],'agent_code'=>(string)$edge['edge_code'],'agent_name'=>(string)$edge['edge_name'],'distribution_chain'=>'central>node>edge']);
}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();api_response(['ok'=>false,'error'=>'edge_enrollment_failed','message'=>$e->getMessage()],409);}
