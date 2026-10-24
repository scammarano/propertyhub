<?php
declare(strict_types=1);

final class SocialAuth
{
    public static function provider(string $code): array
    {
        $provider = config('auth.providers.'.$code, []);
        if (!is_array($provider) || $provider === []) {
            throw new InvalidArgumentException('Proveedor no reconocido.');
        }
        return $provider;
    }

    public static function configured(string $code): bool
    {
        try {
            $p = self::provider($code);
            if (empty($p['enabled']) || empty($p['client_id'])) return false;
            if ($code === 'apple') {
                return !empty($p['client_secret']) || (!empty($p['team_id']) && !empty($p['key_id']) && !empty($p['private_key_path']));
            }
            return !empty($p['client_secret']);
        } catch (Throwable) {
            return false;
        }
    }

    public static function redirectUri(string $code, array $provider): string
    {
        if (!empty($provider['redirect_uri'])) return (string) $provider['redirect_uri'];
        return app_base_url().'/oauth/'.rawurlencode($code).'/callback.php';
    }

    public static function begin(string $code): never
    {
        $provider = self::provider($code);
        self::assertConfigured($code, $provider);
        $state = bin2hex(random_bytes(24));
        $nonce = bin2hex(random_bytes(20));
        $_SESSION['oauth'][$code] = ['state' => $state, 'nonce' => $nonce, 'started_at' => time()];
        $params = [
            'client_id' => $provider['client_id'],
            'redirect_uri' => self::redirectUri($code, $provider),
            'response_type' => 'code',
            'scope' => implode(' ', $provider['scopes'] ?? ['openid', 'email']),
            'state' => $state,
            'nonce' => $nonce,
        ];
        if ($code === 'google') {
            $params['prompt'] = 'select_account';
            $params['access_type'] = 'online';
        }
        if ($code === 'apple') {
            $params['response_mode'] = 'form_post';
        }
        header('Location: '.$provider['authorization_endpoint'].'?'.http_build_query($params));
        exit;
    }

    public static function callback(string $code): array
    {
        $provider = self::provider($code);
        self::assertConfigured($code, $provider);
        $state = (string) ($_POST['state'] ?? $_GET['state'] ?? '');
        $stored = $_SESSION['oauth'][$code] ?? null;
        unset($_SESSION['oauth'][$code]);
        if (!is_array($stored) || $state === '' || !hash_equals((string) $stored['state'], $state) || (int) $stored['started_at'] < time() - 600) {
            throw new RuntimeException('La solicitud de autenticación no es válida o venció.');
        }
        $error = (string) ($_POST['error'] ?? $_GET['error'] ?? '');
        if ($error !== '') throw new RuntimeException('El proveedor rechazó el acceso: '.$error);
        $authCode = (string) ($_POST['code'] ?? $_GET['code'] ?? '');
        if ($authCode === '') throw new RuntimeException('No se recibió el código de autorización.');

        $clientSecret = $code === 'apple' ? AppleClientSecret::make($provider) : (string) $provider['client_secret'];
        $tokens = self::postForm((string) $provider['token_endpoint'], [
            'client_id' => $provider['client_id'],
            'client_secret' => $clientSecret,
            'code' => $authCode,
            'grant_type' => 'authorization_code',
            'redirect_uri' => self::redirectUri($code, $provider),
        ]);
        if (empty($tokens['id_token'])) throw new RuntimeException('El proveedor no devolvió un token de identidad.');

        if ($code === 'google') {
            $claims = JwtVerifier::verifyGoogle((string) $tokens['id_token'], $provider, (string) $stored['nonce']);
            return [
                'provider' => 'google',
                'provider_sub' => (string) ($claims['sub'] ?? ''),
                'email' => (string) ($claims['email'] ?? ''),
                'email_verified' => (bool) ($claims['email_verified'] ?? false),
                'name' => (string) ($claims['name'] ?? $claims['email'] ?? 'Usuario Google'),
                'avatar' => (string) ($claims['picture'] ?? ''),
            ];
        }

        $claims = JwtVerifier::verifyApple((string) $tokens['id_token'], $provider, (string) $stored['nonce']);
        $name = 'Usuario Apple';
        if (!empty($_POST['user'])) {
            $user = json_decode((string) $_POST['user'], true);
            $name = trim((string) ($user['name']['firstName'] ?? '').' '.(string) ($user['name']['lastName'] ?? '')) ?: $name;
        }
        return [
            'provider' => 'apple',
            'provider_sub' => (string) ($claims['sub'] ?? ''),
            'email' => (string) ($claims['email'] ?? ''),
            'email_verified' => (string) ($claims['email_verified'] ?? '') === 'true' || ($claims['email_verified'] ?? false) === true,
            'name' => $name,
            'avatar' => '',
        ];
    }

    private static function assertConfigured(string $code, array $provider): void
    {
        if (!self::configured($code)) throw new RuntimeException('El proveedor todavía no tiene credenciales completas en .env.');
        if (!extension_loaded('curl') || !extension_loaded('openssl')) throw new RuntimeException('PHP necesita cURL y OpenSSL para autenticación social.');
    }

    private static function postForm(string $url, array $data): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/x-www-form-urlencoded'],
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if (!is_string($body) || $status < 200 || $status >= 300) {
            throw new RuntimeException('Falló el intercambio con el proveedor: '.($error ?: 'HTTP '.$status.' '.substr((string) $body, 0, 180)));
        }
        return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    }
}
