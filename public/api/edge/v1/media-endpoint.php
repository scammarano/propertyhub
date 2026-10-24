<?php
declare(strict_types=1);
require __DIR__.'/_bootstrap.php';
if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST')edge_api_response(['ok'=>false,'error'=>'method_not_allowed'],405);
$input=edge_api_input();$agent=edge_api_auth(Connection::pdo());
try{edge_api_response((new EdgeNodeService(Connection::pdo()))->updateMediaEndpoint($agent,$input));}
catch(InvalidArgumentException $e){edge_api_response(['ok'=>false,'error'=>'invalid_media_endpoint','message'=>$e->getMessage()],422);}
catch(Throwable $e){ErrorReporter::report($e,['operation'=>'edge_media_endpoint']);edge_api_response(['ok'=>false,'error'=>'edge_media_endpoint_failed','message'=>$e->getMessage()],500);}
