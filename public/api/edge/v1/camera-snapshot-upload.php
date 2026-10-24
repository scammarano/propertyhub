<?php
declare(strict_types=1);
require __DIR__.'/_bootstrap.php';
if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST')edge_api_response(['ok'=>false,'error'=>'method_not_allowed'],405);
$agent=edge_api_auth(Connection::pdo());$cameraUuid=strtolower(edge_api_header('X-Edge-Camera-Uuid'));$sha=edge_api_header('X-Snapshot-Sha256');$mime=edge_api_header('X-Snapshot-Mime');
$incoming=base_path('storage/uploads/edge-cameras/incoming');if(!is_dir($incoming)&&!@mkdir($incoming,0775,true)&&!is_dir($incoming))edge_api_response(['ok'=>false,'error'=>'incoming_directory_failed'],500);
$tmp=$incoming.'/snapshot-'.bin2hex(random_bytes(8)).'.bin';$in=fopen('php://input','rb');$out=fopen($tmp,'wb');if(!$in||!$out){if(is_resource($in))fclose($in);if(is_resource($out))fclose($out);@unlink($tmp);edge_api_response(['ok'=>false,'error'=>'upload_open_failed'],500);}stream_copy_to_stream($in,$out);fclose($in);fclose($out);
try{edge_api_response((new EdgeNodeService(Connection::pdo()))->storeCameraSnapshot($agent,$cameraUuid,$tmp,$sha,$mime));}
catch(Throwable $e){@unlink($tmp);ErrorReporter::report($e,['operation'=>'edge_camera_snapshot_upload','agent_code'=>$agent['agent_code']??'','camera_uuid'=>$cameraUuid]);edge_api_response(['ok'=>false,'error'=>'edge_snapshot_store_failed','message'=>$e->getMessage()],422);}
