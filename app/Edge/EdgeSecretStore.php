<?php
declare(strict_types=1);

final class EdgeSecretStore
{
    private static function key(): string
    {
        $raw=(string)config('app.key','');
        if($raw==='')throw new RuntimeException('APP_KEY no está configurada.');
        return hash('sha256',$raw,true);
    }

    public static function encrypt(array $data): string
    {
        $nonce=random_bytes(12);$tag='';$plain=json_encode($data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
        $cipher=openssl_encrypt($plain,'aes-256-gcm',self::key(),OPENSSL_RAW_DATA,$nonce,$tag,'property-hub-edge-v1',16);
        if($cipher===false)throw new RuntimeException('No fue posible cifrar la configuración local.');
        return base64_encode(json_encode(['v'=>1,'n'=>base64_encode($nonce),'t'=>base64_encode($tag),'c'=>base64_encode($cipher)],JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
    }

    public static function decrypt(?string $payload): array
    {
        $payload=trim((string)$payload);if($payload==='')return [];
        $outer=base64_decode($payload,true);if($outer===false)return [];
        $box=json_decode($outer,true);if(!is_array($box))return [];
        $nonce=base64_decode((string)($box['n']??''),true);$tag=base64_decode((string)($box['t']??''),true);$cipher=base64_decode((string)($box['c']??''),true);
        if($nonce===false||$tag===false||$cipher===false)return [];
        $plain=openssl_decrypt($cipher,'aes-256-gcm',self::key(),OPENSSL_RAW_DATA,$nonce,$tag,'property-hub-edge-v1');
        if($plain===false)return [];$data=json_decode($plain,true);return is_array($data)?$data:[];
    }
}
