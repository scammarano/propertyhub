<?php
declare(strict_types=1);
return [
    'role' => env('INSTANCE_ROLE', 'building_node'),
    'installation_uuid' => env('INSTANCE_UUID', ''),
    'installation_api_key' => env('INSTALLATION_API_KEY', ''),
    'update_channel' => env('UPDATE_CHANNEL', 'stable'),
    'allow_offline' => env_bool('ALLOW_OFFLINE', true),
    'central_repository_url' => rtrim((string)env('CENTRAL_REPOSITORY_URL', ''), '/'),
    'repository_enabled' => env_bool('REPOSITORY_ENABLED', false),
    'repository_public_url' => rtrim((string)env('REPOSITORY_PUBLIC_URL', ''), '/'),
    'repository_private_key_path' => env('REPOSITORY_PRIVATE_KEY_PATH', 'storage/keys/repository-private.pem'),
    'repository_public_key_path' => env('REPOSITORY_PUBLIC_KEY_PATH', 'storage/keys/repository-public.pem'),
    'parent_property_code' => env('PARENT_PROPERTY_CODE', ''),
    'property_node_url' => rtrim((string)env('PROPERTY_NODE_URL', ''), '/'),
    'property_node_agent_key' => env('PROPERTY_NODE_AGENT_KEY', ''),
];
