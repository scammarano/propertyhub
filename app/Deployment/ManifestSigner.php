<?php
declare(strict_types=1);

final class ManifestSigner
{
    public static function sign(array $manifest): array
    {
        if (!InstallationMode::isCentral()) throw new RuntimeException('La firma de manifiestos solo está disponible en la fuente central.');
        $path = base_path((string)config('deployment.repository_private_key_path'));
        $private = @file_get_contents($path);
        if ($private === false) throw new RuntimeException('No se encontró la clave privada del repositorio.');
        $canonical = self::canonicalJson($manifest);
        $ok = openssl_sign($canonical, $signature, $private, OPENSSL_ALGO_SHA256);
        if (!$ok) throw new RuntimeException('OpenSSL no pudo firmar el manifiesto.');
        return ['manifest'=>$manifest,'signature'=>base64_encode($signature),'algorithm'=>'RSA-SHA256','canonical_sha256'=>hash('sha256',$canonical)];
    }

    public static function canonicalJson(array $value): string
    {
        $normalize = function(mixed $item) use (&$normalize): mixed {
            if (!is_array($item)) return $item;
            if (array_is_list($item)) return array_map($normalize,$item);
            ksort($item,SORT_STRING);
            foreach ($item as $key=>$child) $item[$key]=$normalize($child);
            return $item;
        };
        return json_encode($normalize($value),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    }
}
