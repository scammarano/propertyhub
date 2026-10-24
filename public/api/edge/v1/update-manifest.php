<?php
declare(strict_types=1);
require __DIR__.'/_bootstrap.php';
if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST')edge_api_response(['ok'=>false,'error'=>'method_not_allowed'],405);
$input=edge_api_input();$agent=edge_api_auth(Connection::pdo());
try{
    $result=(new EdgeNodeService(Connection::pdo()))->updateManifestForAgent($agent,$input);
    edge_api_response($result);
}catch(Throwable $e){
    ErrorReporter::report($e,['operation'=>'edge_update_manifest_relay','agent_code'=>(string)($agent['agent_code']??'')]);
    edge_api_response(['ok'=>false,'error'=>'edge_update_manifest_failed','message'=>$e->getMessage()],502);
}
