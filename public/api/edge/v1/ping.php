<?php
declare(strict_types=1);
require __DIR__.'/_bootstrap.php';
if (($_SERVER['REQUEST_METHOD']??'GET')!=='POST') edge_api_response(['ok'=>false,'error'=>'method_not_allowed'],405);
if (!InstallationMode::isNode()) {
    edge_api_response([
        'ok'=>false,
        'error'=>'not_property_node',
        'instance_role'=>InstallationMode::current(),
        'instance_code'=>(string)config('app.instance_code'),
    ],409);
}
$input=edge_api_input();
$requested=strtoupper(trim((string)($input['parent_property_code']??'')));
$actual=strtoupper(trim((string)config('app.instance_code')));
if($requested!=='' && !hash_equals($actual,$requested)){
    edge_api_response(['ok'=>false,'error'=>'wrong_property_node','instance_role'=>'building_node','instance_code'=>$actual],409);
}
edge_api_response([
    'ok'=>true,
    'instance_role'=>'building_node',
    'instance_code'=>$actual,
    'instance_name'=>(string)config('app.instance_name'),
    'app_version'=>(string)config('app.version'),
    'server_time'=>date(DATE_ATOM),
]);
