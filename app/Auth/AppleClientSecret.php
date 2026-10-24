<?php
declare(strict_types=1);

final class AppleClientSecret
{
    public static function make(array $provider): string
    {
        $static = trim((string) ($provider['client_secret'] ?? ''));
        if ($static !== '') {
            return $static;
        }

        $teamId = trim((string) ($provider['team_id'] ?? ''));
        $keyId = trim((string) ($provider['key_id'] ?? ''));
        $clientId = trim((string) ($provider['client_id'] ?? ''));
        $path = trim((string) ($provider['private_key_path'] ?? ''));
        if ($path !== '' && !str_starts_with($path, '/') && !preg_match('/^[A-Za-z]:[\\\\\/]/', $path)) {
            $path = base_path($path);
        }
        if ($teamId === '' || $keyId === '' || $clientId === '' || !is_file($path)) {
            throw new RuntimeException('Apple requiere TEAM_ID, KEY_ID y la ruta de la clave privada .p8.');
        }

        $header = ['alg' => 'ES256', 'kid' => $keyId, 'typ' => 'JWT'];
        $now = time();
        $payload = [
            'iss' => $teamId,
            'iat' => $now,
            'exp' => $now + 86400 * 150,
            'aud' => 'https://appleid.apple.com',
            'sub' => $clientId,
        ];
        $input = self::b64url(json_encode($header, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)).'.'.self::b64url(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $key = openssl_pkey_get_private((string) file_get_contents($path));
        if ($key === false) {
            throw new RuntimeException('No se pudo leer la clave privada de Apple.');
        }
        $signature = '';
        if (!openssl_sign($input, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('No se pudo firmar el client secret de Apple.');
        }
        return $input.'.'.self::b64url(self::derToJose($signature, 64));
    }

    private static function derToJose(string $der, int $partLength): string
    {
        $offset = 0;
        if (ord($der[$offset++]) !== 0x30) {
            throw new RuntimeException('Firma ECDSA inválida.');
        }
        self::readLength($der, $offset);
        if (ord($der[$offset++]) !== 0x02) {
            throw new RuntimeException('Firma ECDSA inválida.');
        }
        $rLength = self::readLength($der, $offset);
        $r = substr($der, $offset, $rLength);
        $offset += $rLength;
        if (ord($der[$offset++]) !== 0x02) {
            throw new RuntimeException('Firma ECDSA inválida.');
        }
        $sLength = self::readLength($der, $offset);
        $s = substr($der, $offset, $sLength);
        $r = str_pad(ltrim($r, "\x00"), $partLength / 2, "\x00", STR_PAD_LEFT);
        $s = str_pad(ltrim($s, "\x00"), $partLength / 2, "\x00", STR_PAD_LEFT);
        return $r.$s;
    }

    private static function readLength(string $data, int &$offset): int
    {
        $length = ord($data[$offset++]);
        if (($length & 0x80) === 0) {
            return $length;
        }
        $count = $length & 0x7f;
        $length = 0;
        for ($i = 0; $i < $count; $i++) {
            $length = ($length << 8) | ord($data[$offset++]);
        }
        return $length;
    }

    private static function b64url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
