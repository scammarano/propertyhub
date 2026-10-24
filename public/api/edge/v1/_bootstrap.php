<?php
declare(strict_types=1);
$root=dirname(__DIR__,4);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
function edge_api_response(array $data,int $status=200): never { http_response_code($status);echo json_encode($data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);exit; }
function edge_api_input(): array { $raw=file_get_contents('php://input');$d=json_decode((string)$raw,true);return is_array($d)?$d:[]; }
function edge_api_header(string $name): string { $key='HTTP_'.strtoupper(str_replace('-','_',$name));return trim((string)($_SERVER[$key]??'')); }
if(!is_file($root.'/storage/installed.lock')||!is_file($root.'/.env'))edge_api_response(['ok'=>false,'error'=>'not_installed'],503);
require_once $root.'/app/bootstrap.php';if(!InstallationMode::isNode())edge_api_response(['ok'=>false,'error'=>'edge_api_only_on_property_node'],404);
function edge_api_auth(PDO $pdo): array { $uuid=edge_api_header('X-Edge-Agent-Uuid');if($uuid===''){$input=edge_api_input();$uuid=trim((string)($input['agent_uuid']??''));} $key=edge_api_header('X-Edge-Agent-Key');if($uuid===''||$key==='')edge_api_response(['ok'=>false,'error'=>'missing_edge_credentials'],401);try{return (new EdgeNodeService($pdo))->authenticate($uuid,$key);}catch(Throwable $e){edge_api_response(['ok'=>false,'error'=>'invalid_edge_credentials','message'=>$e->getMessage()],401);} }
