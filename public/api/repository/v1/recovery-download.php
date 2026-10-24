<?php
declare(strict_types=1);
require __DIR__.'/_bootstrap.php';
if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET')api_response(['ok'=>false,'error'=>'method_not_allowed'],405);
$token=api_header('X-Recovery-Token');if($token==='')$token=trim((string)($_GET['token']??''));
if($token==='')api_response(['ok'=>false,'error'=>'recovery_token_required'],401);
try{$row=(new BackupVaultService(Connection::pdo()))->consumeRecoveryToken($token,(string)($_SERVER['REMOTE_ADDR']??''));}
catch(Throwable $e){api_response(['ok'=>false,'error'=>'recovery_token_rejected','message'=>$e->getMessage()],403);}
$path=(string)$row['storage_path'];$name=(string)$row['filename'];
header('Content-Type: application/octet-stream');header('Content-Length: '.(string)filesize($path));header('Content-Disposition: attachment; filename="'.str_replace('"','',basename($name)).'"');header('X-Checksum-Sha256: '.(string)$row['sha256']);header('X-Installation-Code: '.rawurlencode((string)$row['installation_code']));header('Cache-Control: no-store');
$fp=fopen($path,'rb');while(!feof($fp)){echo fread($fp,1048576);flush();}fclose($fp);exit;
