<?php
declare(strict_types=1);
require __DIR__.'/_bootstrap.php';
if (($_SERVER['REQUEST_METHOD']??'GET')!=='POST') api_response(['ok'=>false,'error'=>'method_not_allowed'],405);
$input=api_input(); $pdo=Connection::pdo(); $installation=api_auth_installation($pdo,$input);
$apis=$pdo->query("SELECT api_code,version,status,minimum_app_version,maximum_app_version,documentation_path,released_at,support_ends_at FROM bh_repo_api_versions WHERE status NOT IN ('retired') ORDER BY api_code,created_at DESC")->fetchAll();
$connectors=[]; foreach(glob(base_path('connectors/*/manifest.json'))?:[] as $file){$item=json_decode((string)file_get_contents($file),true);if(is_array($item))$connectors[]=$item;}
api_response(['ok'=>true,'installation_uuid'=>(string)$installation['installation_uuid'],'api_versions'=>$apis,'connectors'=>$connectors,'server_time'=>date(DATE_ATOM)]);
