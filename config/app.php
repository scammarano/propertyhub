<?php
declare(strict_types=1);
$packageVersion='0.14.61';
$versionFile=dirname(__DIR__).'/VERSION.json';
if(is_file($versionFile)){
    $versionMeta=json_decode((string)@file_get_contents($versionFile),true);
    if(is_array($versionMeta)&&trim((string)($versionMeta['version']??''))!=='')$packageVersion=trim((string)$versionMeta['version']);
}
return [
    'name' => env('APP_NAME', '2CAM Property Hub'),
    'env' => env('APP_ENV', 'local'),
    'debug' => env_bool('APP_DEBUG', false),
    'diagnostics' => env_bool('APP_DIAGNOSTICS', true),
    'log_level' => env('APP_LOG_LEVEL', 'debug'),
    'log_requests' => env_bool('APP_LOG_REQUESTS', true),
    'diagnostic_token' => env('DIAGNOSTIC_TOKEN', ''),
    'url' => rtrim((string) env('APP_URL', ''), '/'),
    'timezone' => env('APP_TIMEZONE', 'America/Caracas'),
    'demo_mode' => env_bool('APP_DEMO_MODE', false),
    'session_name' => env('APP_SESSION_NAME', 'property_hub_session'),
    'key' => env('APP_KEY', ''),
    'instance_code' => env('INSTANCE_CODE', 'LAB-001'),
    'instance_name' => env('INSTANCE_NAME', 'Instalación de Laboratorio'),
    'instance_type' => env('INSTANCE_TYPE', 'mixed'),
    'ui_standard_version' => '1.2.0',
    'version' => $packageVersion,
    'instance_role' => env('INSTANCE_ROLE', 'building_node'),
    'installation_uuid' => env('INSTANCE_UUID', ''),
];
