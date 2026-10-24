<?php
declare(strict_types=1);
require __DIR__.'/_bootstrap.php';
if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST')edge_api_response(['ok'=>false,'error'=>'method_not_allowed'],405);
$agent=edge_api_auth(Connection::pdo());
$incoming=base_path('storage/backups/incoming');
if(!is_dir($incoming)&&!@mkdir($incoming,0775,true)&&!is_dir($incoming))edge_api_response(['ok'=>false,'error'=>'incoming_directory_failed'],500);
$tmp=$incoming.'/edge-'.bin2hex(random_bytes(8)).'.phbackup';
$in=fopen('php://input','rb');$out=fopen($tmp,'wb');
if(!$in||!$out){if(is_resource($in))fclose($in);if(is_resource($out))fclose($out);@unlink($tmp);edge_api_response(['ok'=>false,'error'=>'upload_open_failed'],500);}
stream_copy_to_stream($in,$out);fclose($in);fclose($out);
try{
    $r=(new EdgeNodeService(Connection::pdo()))->storeBackup($agent,$tmp,edge_api_header('X-Backup-Filename'),edge_api_header('X-Backup-Sha256'),rawurldecode(edge_api_header('X-Backup-Uuid')),rawurldecode(edge_api_header('X-Backup-Created-At')));
    edge_api_response($r);
}catch(Throwable $e){
    @unlink($tmp);ErrorReporter::report($e,['operation'=>'edge_backup_upload','agent_code'=>$agent['agent_code']??'']);edge_api_response(['ok'=>false,'error'=>'edge_backup_store_failed','message'=>$e->getMessage()],422);
}
