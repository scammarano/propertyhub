<?php
declare(strict_types=1);
require __DIR__.'/_bootstrap.php';
if(($_SERVER['REQUEST_METHOD']??'GET')!=='GET')edge_api_response(['ok'=>false,'error'=>'method_not_allowed'],405);
$agent=edge_api_auth(Connection::pdo());$release=trim((string)($_GET['release']??''));
try{
    $result=(new EdgeNodeService(Connection::pdo()))->updatePackagePathForAgent($agent,$release);
    $file=(string)$result['path'];if(!is_file($file))throw new RuntimeException('El paquete cacheado no está disponible.');
    header_remove('Content-Type');header('Content-Type: application/zip');header('Cache-Control: private, no-store');header('Content-Length: '.filesize($file));header('Content-Disposition: attachment; filename="'.basename($file).'"');header('X-Checksum-SHA256: '.(string)$result['sha256']);header('X-Distribution-Chain: central>node>edge');header('X-Node-Cache: '.(!empty($result['cached'])?'HIT':'MISS'));readfile($file);exit;
}catch(Throwable $e){
    ErrorReporter::report($e,['operation'=>'edge_update_package_relay','agent_code'=>(string)($agent['agent_code']??''),'release'=>$release]);
    edge_api_response(['ok'=>false,'error'=>'edge_update_package_failed','message'=>$e->getMessage()],502);
}
