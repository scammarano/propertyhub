<?php
declare(strict_types=1);
require __DIR__.'/_bootstrap.php';
if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST')edge_api_response(['ok'=>false,'error'=>'method_not_allowed'],405);
$agent=edge_api_auth(Connection::pdo());
try{
    edge_api_response((new EdgeNodeService(Connection::pdo()))->centralBootstrapForAgent($agent));
}catch(Throwable $e){
    ErrorReporter::report($e,['operation'=>'edge_central_bootstrap_repair','agent_code'=>(string)($agent['agent_code']??'')]);
    edge_api_response(['ok'=>false,'error'=>'edge_central_bootstrap_failed','message'=>$e->getMessage()],422);
}
