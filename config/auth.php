<?php
declare(strict_types=1);
return [
    'providers' => [
        'google' => [
            'name' => 'Google',
            'enabled' => env_bool('GOOGLE_AUTH_ENABLED', false),
            'client_id' => env('GOOGLE_CLIENT_ID', ''),
            'client_secret' => env('GOOGLE_CLIENT_SECRET', ''),
            'redirect_uri' => env('GOOGLE_REDIRECT_URI', ''),
            'authorization_endpoint' => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token_endpoint' => 'https://oauth2.googleapis.com/token',
            'jwks_endpoint' => 'https://www.googleapis.com/oauth2/v3/certs',
            'issuers' => ['https://accounts.google.com', 'accounts.google.com'],
            'scopes' => ['openid', 'email', 'profile'],
        ],
        'apple' => [
            'name' => 'Apple',
            'enabled' => env_bool('APPLE_AUTH_ENABLED', false),
            'client_id' => env('APPLE_CLIENT_ID', ''),
            'client_secret' => env('APPLE_CLIENT_SECRET', ''),
            'team_id' => env('APPLE_TEAM_ID', ''),
            'key_id' => env('APPLE_KEY_ID', ''),
            'private_key_path' => env('APPLE_PRIVATE_KEY_PATH', ''),
            'redirect_uri' => env('APPLE_REDIRECT_URI', ''),
            'authorization_endpoint' => 'https://appleid.apple.com/auth/authorize',
            'token_endpoint' => 'https://appleid.apple.com/auth/token',
            'jwks_endpoint' => 'https://appleid.apple.com/auth/keys',
            'issuer' => 'https://appleid.apple.com',
            'scopes' => ['name', 'email'],
        ],
    ],
];
