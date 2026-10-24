<?php
declare(strict_types=1);
require __DIR__.'/_bootstrap.php';
$input=['installation_uuid'=>(string)($_GET['installation_uuid']??api_header('X-Installation-Uuid'))];
$pdo=Connection::pdo(); $installation=api_auth_installation($pdo,$input);
$code=trim((string)($_GET['release']??'')); if($code==='')api_response(['ok'=>false,'error'=>'missing_release'],422);
$stmt=$pdo->prepare("SELECT id,package_path,package_hash_sha256,status FROM bh_repo_releases WHERE release_code=? AND status='published' LIMIT 1");$stmt->execute([$code]);$release=$stmt->fetch();
if(!is_array($release)||empty($release['package_path']))api_response(['ok'=>false,'error'=>'release_not_found'],404);
$relative=ltrim(str_replace(['..','\\'],['','/'],(string)$release['package_path']),'/');$file=base_path($relative);
if(!is_file($file))api_response(['ok'=>false,'error'=>'package_file_not_found'],404);
$pdo->prepare("INSERT INTO bh_repo_delivery_logs (installation_id,release_id,event_type,status,remote_ip) VALUES (?,?,'package_requested','ok',?)")->execute([(int)$installation['id'],(int)$release['id'],(string)($_SERVER['REMOTE_ADDR']??'')]);
header_remove('Content-Type');header('Content-Type: application/zip');header('Content-Length: '.filesize($file));header('Content-Disposition: attachment; filename="'.basename($file).'"');header('X-Checksum-SHA256: '.(string)$release['package_hash_sha256']);readfile($file);exit;
