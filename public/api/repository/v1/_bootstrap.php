<?php
declare(strict_types=1);

$root = dirname(__DIR__, 4);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function api_response(array $data, int $status=200): never { http_response_code($status); echo json_encode($data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT); exit; }
function api_input(): array { $raw=file_get_contents('php://input'); $data=json_decode((string)$raw,true); return is_array($data)?$data:[]; }
function api_header(string $name): string {
    $key='HTTP_'.strtoupper(str_replace('-','_',$name));
    return trim((string)($_SERVER[$key]??''));
}

if (!is_file($root.'/storage/installed.lock') || !is_file($root.'/.env')) api_response(['ok'=>false,'error'=>'not_installed'],503);
require_once $root.'/app/bootstrap.php';
if (!InstallationMode::isCentral() || !config('deployment.repository_enabled',false)) api_response(['ok'=>false,'error'=>'repository_not_enabled'],404);

function api_auth_installation(PDO $pdo, array $input): array {
    $uuid=trim((string)($input['installation_uuid']??''));
    $key=api_header('X-Installation-Key');
    if ($uuid==='' || $key==='') api_response(['ok'=>false,'error'=>'missing_installation_credentials'],401);
    $stmt=$pdo->prepare('SELECT * FROM bh_repo_installations WHERE installation_uuid=? LIMIT 1');
    $stmt->execute([$uuid]); $row=$stmt->fetch();
    if (!is_array($row) || !hash_equals((string)$row['installation_key_hash'],hash('sha256',$key))) api_response(['ok'=>false,'error'=>'invalid_installation_credentials'],401);
    if (in_array((string)$row['status'],['suspended','retired'],true)) api_response(['ok'=>false,'error'=>'installation_not_allowed'],403);
    return $row;
}


function api_auth_installation_headers(PDO $pdo): array {
    $uuid=api_header('X-Installation-Uuid');
    $key=api_header('X-Installation-Key');
    if($uuid===''||$key==='')api_response(['ok'=>false,'error'=>'missing_installation_credentials'],401);
    $stmt=$pdo->prepare('SELECT * FROM bh_repo_installations WHERE installation_uuid=? LIMIT 1');
    $stmt->execute([$uuid]);$row=$stmt->fetch();
    if(!is_array($row)||!hash_equals((string)$row['installation_key_hash'],hash('sha256',$key)))api_response(['ok'=>false,'error'=>'invalid_installation_credentials'],401);
    if(in_array((string)$row['status'],['suspended','retired'],true))api_response(['ok'=>false,'error'=>'installation_not_allowed'],403);
    return $row;
}
