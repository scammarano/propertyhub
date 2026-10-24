<?php
declare(strict_types=1);
require __DIR__.'/_bootstrap.php';
if (!in_array(($_SERVER['REQUEST_METHOD']??'GET'), ['GET','POST'], true)) api_response(['ok'=>false,'error'=>'method_not_allowed'],405);
api_response([
    'ok'=>true,
    'service'=>'property-hub-central-repository',
    'repository_api'=>'v1',
    'repository_version'=>(string)config('app.version'),
    'instance_code'=>(string)config('app.instance_code'),
    'server_time'=>date(DATE_ATOM),
]);
