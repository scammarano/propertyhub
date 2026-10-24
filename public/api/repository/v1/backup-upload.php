<?php
declare(strict_types=1);
require __DIR__.'/_bootstrap.php';
if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST')api_response(['ok'=>false,'error'=>'method_not_allowed'],405);
$pdo=Connection::pdo();$installation=api_auth_installation_headers($pdo);
$filename=rawurldecode(api_header('X-Backup-Filename'));$filename=preg_replace('/[^A-Za-z0-9_.-]/','_',basename($filename?:'backup.phbackup'))?:'backup.phbackup';
if(!str_ends_with(strtolower($filename),'.phbackup'))api_response(['ok'=>false,'error'=>'invalid_backup_extension'],422);
$expectedSha=strtolower(api_header('X-Backup-Sha256'));if(!preg_match('/^[a-f0-9]{64}$/',$expectedSha))api_response(['ok'=>false,'error'=>'missing_or_invalid_sha256'],422);
$createdAt=rawurldecode(api_header('X-Backup-Created-At'));
$backupType=rawurldecode(api_header('X-Backup-Type'));$backupVersion=rawurldecode(api_header('X-Backup-App-Version'));$requestUuid=rawurldecode(api_header('X-Backup-Request-Uuid'));
$code=preg_replace('/[^A-Za-z0-9_-]/','_', (string)$installation['installation_code'])?:'node';
$dir=base_path('storage/backups/offsite/'.$code);if(!is_dir($dir)&&!@mkdir($dir,0775,true)&&!is_dir($dir))api_response(['ok'=>false,'error'=>'offsite_directory_unavailable'],500);
$target=$dir.'/'.date('Ymd_His').'_'.bin2hex(random_bytes(3)).'_'.$filename;$in=fopen('php://input','rb');$out=@fopen($target,'wb');if(!$in||!$out)api_response(['ok'=>false,'error'=>'stream_open_failed'],500);
$hash=hash_init('sha256');$size=0;while(!feof($in)){$chunk=fread($in,1048576);if($chunk===false){fclose($in);fclose($out);@unlink($target);api_response(['ok'=>false,'error'=>'stream_read_failed'],500);}if($chunk==='')break;$size+=strlen($chunk);hash_update($hash,$chunk);if(fwrite($out,$chunk)===false){fclose($in);fclose($out);@unlink($target);api_response(['ok'=>false,'error'=>'stream_write_failed'],500);}}fclose($in);fclose($out);
$actualSha=hash_final($hash);if(!hash_equals($expectedSha,$actualSha)){@unlink($target);api_response(['ok'=>false,'error'=>'sha256_mismatch','received_sha256'=>$actualSha],422);}
$pdo->prepare('INSERT INTO bh_backup_offsite_archives (installation_uuid,installation_code,instance_role,parent_installation_code,filename,size_bytes,sha256,storage_path,backup_created_at,metadata_json) VALUES (?,?,?,?,?,?,?,?,?,?)')->execute([(string)$installation['installation_uuid'],(string)$installation['installation_code'],(string)($installation['instance_role']??'building_node'),!empty($installation['parent_installation_code'])?(string)$installation['parent_installation_code']:null,$filename,$size,$actualSha,$target,$createdAt!==''?date('Y-m-d H:i:s',strtotime($createdAt)):null,json_encode(['remote_ip'=>$_SERVER['REMOTE_ADDR']??'','app_version'=>$backupVersion!==''?$backupVersion:($installation['app_version']??null),'backup_type'=>$backupType!==''?$backupType:'manual','request_uuid'=>$requestUuid!==''?$requestUuid:null],JSON_UNESCAPED_SLASHES)]);
$archiveId=(int)$pdo->lastInsertId();
$vaultService=new BackupVaultService($pdo);
if($requestUuid!=='')$vaultService->linkUploadedArchive((int)$installation['id'],$requestUuid,$archiveId,['filename'=>$filename,'size_bytes'=>$size,'sha256'=>$actualSha]);
$pruned=$vaultService->pruneInstallationArchives((string)$installation['installation_uuid']);
$pdo->prepare("INSERT INTO bh_repo_delivery_logs (installation_id,event_type,status,remote_ip,details_json) VALUES (?,'backup_upload','ok',?,?)")->execute([(int)$installation['id'],(string)($_SERVER['REMOTE_ADDR']??''),json_encode(['filename'=>$filename,'size_bytes'=>$size,'sha256'=>$actualSha,'pruned'=>$pruned],JSON_UNESCAPED_SLASHES)]);
api_response(['ok'=>true,'stored'=>true,'filename'=>$filename,'size_bytes'=>$size,'sha256'=>$actualSha,'received_at'=>date(DATE_ATOM),'pruned'=>$pruned],201);
