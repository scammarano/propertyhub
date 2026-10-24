<?php
declare(strict_types=1);

final class EnvironmentEditor
{
    public static function set(array $changes): void
    {
        self::setFile(base_path('.env'), $changes);
    }

    public static function setFile(string $file, array $changes): void
    {
        if(!is_file($file))throw new RuntimeException('No existe el archivo .env.');
        $lines=file($file,FILE_IGNORE_NEW_LINES)?:[];
        $found=[];
        foreach($lines as &$line){
            $trim=trim($line);
            if($trim===''||str_starts_with($trim,'#')||!str_contains($line,'='))continue;
            [$key]=explode('=',$line,2);$key=trim($key);
            if(array_key_exists($key,$changes)){$line=$key.'='.self::quote((string)$changes[$key]);$found[$key]=true;}
        }unset($line);
        foreach($changes as $key=>$value)if(!isset($found[$key]))$lines[]=$key.'='.self::quote((string)$value);
        $tmp=$file.'.tmp.'.bin2hex(random_bytes(4));
        if(file_put_contents($tmp,implode(PHP_EOL,$lines).PHP_EOL,LOCK_EX)===false)throw new RuntimeException('No fue posible actualizar .env.');
        @chmod($tmp,0600);if(!@rename($tmp,$file)){@unlink($tmp);throw new RuntimeException('No fue posible activar la configuración.');}
    }
    private static function quote(string $value): string { if($value==='')return '""';if(preg_match('/^[A-Za-z0-9_.:\/\-]+$/',$value))return $value;return '"'.str_replace(['\\','"'],['\\\\','\\"'],$value).'"'; }
}
