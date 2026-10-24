<?php
declare(strict_types=1);
require __DIR__.'/_bootstrap.php';
if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST')edge_api_response(['ok'=>false,'error'=>'method_not_allowed'],405);
$input=edge_api_input();$agent=edge_api_auth(Connection::pdo());
try{edge_api_response((new EdgeNodeService(Connection::pdo()))->syncInventory($agent,$input));}
catch(Throwable $e){ErrorReporter::report($e,['operation'=>'edge_inventory_sync','agent_code'=>$agent['agent_code']??'']);edge_api_response(['ok'=>false,'error'=>'edge_inventory_sync_failed','message'=>$e->getMessage()],422);}
