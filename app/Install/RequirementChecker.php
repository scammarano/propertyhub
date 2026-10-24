<?php
declare(strict_types=1);

final class RequirementChecker
{
    /** @return array<int,array{name:string,ok:bool,required:bool,detail:string,key:string}> */
    public static function all(string $root): array
    {
        $root = rtrim($root, '/\\');
        self::ensureBootstrapStorage($root);

        $rootProbe = self::probeDirectory($root);
        $storageProbe = self::probeDirectory($root.DIRECTORY_SEPARATOR.'storage');
        $envProbe = self::probeEnv($root.DIRECTORY_SEPARATOR.'.env', $rootProbe['ok']);

        return [
            ['key'=>'php','name'=>'PHP 8.1 o superior','ok'=>version_compare(PHP_VERSION, '8.1.0', '>='),'required'=>true,'detail'=>PHP_VERSION],
            ['key'=>'pdo','name'=>'Extensión PDO','ok'=>extension_loaded('pdo'),'required'=>true,'detail'=>extension_loaded('pdo') ? 'Disponible' : 'No disponible'],
            ['key'=>'pdo_mysql','name'=>'Driver PDO MySQL','ok'=>extension_loaded('pdo_mysql'),'required'=>true,'detail'=>extension_loaded('pdo_mysql') ? 'Disponible' : 'Active pdo_mysql en php.ini'],
            ['key'=>'json','name'=>'Extensión JSON','ok'=>extension_loaded('json'),'required'=>true,'detail'=>extension_loaded('json') ? 'Disponible' : 'No disponible'],
            ['key'=>'openssl','name'=>'Extensión OpenSSL','ok'=>extension_loaded('openssl'),'required'=>true,'detail'=>extension_loaded('openssl') ? 'Disponible' : 'No disponible'],
            ['key'=>'zip','name'=>'Extensión ZIP','ok'=>extension_loaded('zip'),'required'=>true,'detail'=>extension_loaded('zip') ? 'Disponible' : 'Obligatoria para respaldo/recuperación y paquetes de actualización'],
            ['key'=>'curl','name'=>'Extensión cURL','ok'=>extension_loaded('curl'),'required'=>true,'detail'=>extension_loaded('curl') ? 'Disponible' : 'Obligatoria para registro, actualizaciones y copias remotas'],
            ['key'=>'sockets','name'=>'Extensión sockets','ok'=>extension_loaded('sockets'),'required'=>false,'detail'=>extension_loaded('sockets') ? 'Disponible · ONVIF/WS-Discovery y Vimar LAN direct habilitados' : 'Opcional para instalaciones sin discovery LAN; necesaria para descubrir cámaras ONVIF y gateways Vimar/VIEW/By-me/KNX directamente por multicast'],
            ['key'=>'dom','name'=>'Extensión DOM/XML','ok'=>extension_loaded('dom'),'required'=>false,'detail'=>extension_loaded('dom') ? 'Disponible · cliente ONVIF SOAP habilitado' : 'Opcional para roles sin cámaras; necesaria para conectar/probar cámaras ONVIF'],
            ['key'=>'root_write','name'=>'Carpeta raíz escribible','ok'=>$rootProbe['ok'],'required'=>true,'detail'=>$rootProbe['detail']],
            ['key'=>'storage_write','name'=>'Storage escribible','ok'=>$storageProbe['ok'],'required'=>true,'detail'=>$storageProbe['detail']],
            ['key'=>'env_write','name'=>'Archivo .env disponible','ok'=>$envProbe['ok'],'required'=>true,'detail'=>$envProbe['detail']],
        ];
    }

    public static function passesRequired(string $root): bool
    {
        return self::failedRequired($root) === [];
    }

    /** @return array<int,array{name:string,ok:bool,required:bool,detail:string,key:string}> */
    public static function failedRequired(string $root): array
    {
        return array_values(array_filter(
            self::all($root),
            static fn(array $check): bool => (bool)$check['required'] && !(bool)$check['ok']
        ));
    }

    public static function failureMessage(string $root): string
    {
        return self::formatFailures(self::failedRequired($root));
    }

    /** @param array<int,array{name:string,ok:bool,required:bool,detail:string,key:string}> $failed */
    public static function formatFailures(array $failed): string
    {
        if ($failed === []) return '';
        $parts = [];
        foreach ($failed as $check) {
            $parts[] = $check['name'].' ('.$check['detail'].')';
        }
        return implode('; ', $parts);
    }

    private static function ensureBootstrapStorage(string $root): void
    {
        foreach (['storage','storage/logs'] as $relative) {
            $dir = $root.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relative);
            if (!is_dir($dir)) @mkdir($dir, 0775, true);
        }
    }

    /** @return array{ok:bool,detail:string} */
    private static function probeDirectory(string $dir): array
    {
        clearstatcache(true, $dir);
        if (!is_dir($dir)) return ['ok'=>false,'detail'=>$dir.' · no existe'];

        $probe = $dir.DIRECTORY_SEPARATOR.'.property-hub-write-test-'.bin2hex(random_bytes(5)).'.tmp';
        $payload = 'property-hub-write-test';
        $written = @file_put_contents($probe, $payload, LOCK_EX);
        $ok = $written === strlen($payload) && is_file($probe);
        if (is_file($probe)) @unlink($probe);

        if ($ok) return ['ok'=>true,'detail'=>$dir.' · escritura real OK'];

        $last = error_get_last();
        $reason = is_array($last) && !empty($last['message']) ? preg_replace('/\s+/', ' ', (string)$last['message']) : 'Apache/PHP no pudo crear un archivo temporal';
        return ['ok'=>false,'detail'=>$dir.' · '.$reason];
    }

    /** @return array{ok:bool,detail:string} */
    private static function probeEnv(string $path, bool $rootWritable): array
    {
        clearstatcache(true, $path);
        if (!is_file($path)) {
            return $rootWritable
                ? ['ok'=>true,'detail'=>'Se creará durante la instalación · escritura real en la raíz OK']
                : ['ok'=>false,'detail'=>'No existe y Apache/PHP no puede crear archivos en la carpeta raíz'];
        }

        $handle = @fopen($path, 'c+');
        if (is_resource($handle)) {
            fclose($handle);
            return ['ok'=>true,'detail'=>'Existe y puede abrirse para escritura'];
        }
        $last = error_get_last();
        $reason = is_array($last) && !empty($last['message']) ? preg_replace('/\s+/', ' ', (string)$last['message']) : 'No puede abrirse para escritura';
        return ['ok'=>false,'detail'=>$path.' · '.$reason];
    }
}
