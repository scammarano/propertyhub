<?php
declare(strict_types=1);

final class JwtVerifier
{
    public static function verifyGoogle(string $jwt, array $provider, string $nonce): array
    {
        return self::verifyRs256(
            $jwt,
            (string) $provider['jwks_endpoint'],
            (string) $provider['client_id'],
            (array) $provider['issuers'],
            $nonce
        );
    }

    public static function verifyApple(string $jwt, array $provider, string $nonce): array
    {
        return self::verifyRs256(
            $jwt,
            (string) $provider['jwks_endpoint'],
            (string) $provider['client_id'],
            [(string) $provider['issuer']],
            $nonce
        );
    }

    private static function verifyRs256(string $jwt, string $jwksUrl, string $clientId, array $issuers, string $nonce): array
    {
        [$h64, $p64, $s64] = array_pad(explode('.', $jwt), 3, '');
        if ($h64 === '' || $p64 === '' || $s64 === '') {
            throw new RuntimeException('Token de identidad incompleto.');
        }
        $header = json_decode(self::b64urlDecode($h64), true, 512, JSON_THROW_ON_ERROR);
        $payload = json_decode(self::b64urlDecode($p64), true, 512, JSON_THROW_ON_ERROR);
        if (($header['alg'] ?? '') !== 'RS256' || empty($header['kid'])) {
            throw new RuntimeException('Algoritmo de token no admitido.');
        }
        $jwks = self::fetchJwks($jwksUrl);
        $key = null;
        foreach ($jwks['keys'] ?? [] as $candidate) {
            if (($candidate['kid'] ?? null) === $header['kid']) {
                $key = $candidate;
                break;
            }
        }
        if (!$key || empty($key['n']) || empty($key['e'])) {
            throw new RuntimeException('No se encontró la clave pública del proveedor.');
        }
        $pem = self::rsaJwkToPem((string) $key['n'], (string) $key['e']);
        if (openssl_verify($h64.'.'.$p64, self::b64urlDecode($s64), $pem, OPENSSL_ALGO_SHA256) !== 1) {
            throw new RuntimeException('La firma del token no es válida.');
        }
        $now = time();
        if (!in_array((string) ($payload['iss'] ?? ''), $issuers, true)) {
            throw new RuntimeException('Emisor de token no válido.');
        }
        $aud = $payload['aud'] ?? null;
        $audOk = is_string($aud) ? hash_equals($clientId, $aud) : (is_array($aud) && in_array($clientId, $aud, true));
        if (!$audOk) {
            throw new RuntimeException('Audiencia de token no válida.');
        }
        if ((int) ($payload['exp'] ?? 0) < $now - 60) {
            throw new RuntimeException('El token de identidad venció.');
        }
        if ((int) ($payload['iat'] ?? 0) > $now + 120) {
            throw new RuntimeException('La fecha del token no es válida.');
        }
        if ($nonce !== '' && !hash_equals($nonce, (string) ($payload['nonce'] ?? ''))) {
            throw new RuntimeException('El nonce del token no coincide.');
        }
        return $payload;
    }

    private static function fetchJwks(string $url): array
    {
        $cache = base_path('storage/cache/jwks_'.sha1($url).'.json');
        if (is_file($cache) && filemtime($cache) > time() - 43200) {
            return json_decode((string) file_get_contents($cache), true, 512, JSON_THROW_ON_ERROR);
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 12, CURLOPT_SSL_VERIFYPEER => true]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if (!is_string($body) || $status < 200 || $status >= 300) {
            throw new RuntimeException('No se pudieron obtener las claves públicas: '.($error ?: 'HTTP '.$status));
        }
        if (!is_dir(dirname($cache))) {
            mkdir(dirname($cache), 0775, true);
        }
        file_put_contents($cache, $body, LOCK_EX);
        return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    }

    private static function rsaJwkToPem(string $n, string $e): string
    {
        $rsa = self::seq(self::integer(self::b64urlDecode($n)).self::integer(self::b64urlDecode($e)));
        $alg = hex2bin('300d06092a864886f70d0101010500');
        $spki = self::seq($alg."\x03".self::len(strlen($rsa) + 1)."\x00".$rsa);
        return "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($spki), 64, "\n")."-----END PUBLIC KEY-----\n";
    }

    private static function integer(string $bytes): string
    {
        $bytes = ltrim($bytes, "\x00");
        if ($bytes === '') $bytes = "\x00";
        if ((ord($bytes[0]) & 0x80) !== 0) $bytes = "\x00".$bytes;
        return "\x02".self::len(strlen($bytes)).$bytes;
    }

    private static function seq(string $bytes): string { return "\x30".self::len(strlen($bytes)).$bytes; }

    private static function len(int $length): string
    {
        if ($length < 128) return chr($length);
        $temp = '';
        while ($length > 0) {
            $temp = chr($length & 0xff).$temp;
            $length >>= 8;
        }
        return chr(0x80 | strlen($temp)).$temp;
    }

    private static function b64urlDecode(string $value): string
    {
        $pad = strlen($value) % 4;
        if ($pad) $value .= str_repeat('=', 4 - $pad);
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        if ($decoded === false) throw new RuntimeException('Token codificado de forma inválida.');
        return $decoded;
    }
}
