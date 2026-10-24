<?php
declare(strict_types=1);
require __DIR__.'/_bootstrap.php';
if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST')api_response(['ok'=>false,'error'=>'method_not_allowed'],405);
$input=api_input();$pdo=Connection::pdo();$installation=api_auth_installation($pdo,$input);
$uuid=trim((string)($input['request_uuid']??''));if($uuid==='')api_response(['ok'=>false,'error'=>'request_uuid_required'],422);
$status=(string)($input['status']??'failed');
(new BackupVaultService($pdo))->completeTask((int)$installation['id'],$uuid,$status,(array)($input['details']??[]));
api_response(['ok'=>true,'request_uuid'=>$uuid,'status'=>$status]);
