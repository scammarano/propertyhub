<?php
declare(strict_types=1);

final class EdgeHttp
{
    public static function normalizeBaseUrl(string $url): string
    {
        $url=trim($url);if($url==='')return '';
        if(!preg_match('#^https?://#i',$url))throw new InvalidArgumentException('La URL debe comenzar por http:// o https://.');
        $url=preg_replace('/[?#].*$/','',$url)??$url;$url=rtrim($url,'/');$url=preg_replace('#/index\.php$#i','',$url)??$url;
        return rtrim($url,'/');
    }

    public static function postJson(string $base,string $path,array $payload,array $headers=[],int $timeout=15): array
    {
        if(!extension_loaded('curl'))throw new RuntimeException('La extensión cURL no está disponible.');
        $base=self::normalizeBaseUrl($base);if(!str_ends_with(strtolower($base),'/public'))$base.='/public';
        $url=$base.'/'.ltrim($path,'/');$ch=curl_init($url);if($ch===false)throw new RuntimeException('No fue posible iniciar cURL.');
        curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>$timeout,CURLOPT_CONNECTTIMEOUT=>min(8,$timeout),CURLOPT_HTTPHEADER=>array_merge(['Content-Type: application/json','Accept: application/json'],$headers),CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),CURLOPT_FOLLOWLOCATION=>false]);
        $body=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$error=curl_error($ch);curl_close($ch);
        $text=$body===false?'':(string)$body;if($body===false||$status<200||$status>=300)throw new RuntimeException('Property Node respondió HTTP '.$status.($error!==''?' · '.$error:'').($text!==''?' · '.mb_substr(trim(strip_tags($text)),0,300):''));
        $data=json_decode($text,true);if(!is_array($data))throw new RuntimeException('Property Node no devolvió JSON válido.');return $data+['_endpoint'=>$url,'_base_url'=>$base];
    }

    public static function postFile(string $base,string $path,string $file,array $headers=[],int $timeout=300): array
    {
        if(!extension_loaded('curl'))throw new RuntimeException('La extensión cURL no está disponible.');if(!is_file($file))throw new RuntimeException('Archivo no encontrado.');
        $base=self::normalizeBaseUrl($base);if(!str_ends_with(strtolower($base),'/public'))$base.='/public';$url=$base.'/'.ltrim($path,'/');$fp=fopen($file,'rb');if(!$fp)throw new RuntimeException('No se pudo abrir el archivo.');$size=(int)filesize($file);
        $ch=curl_init($url);if($ch===false){fclose($fp);throw new RuntimeException('No fue posible iniciar cURL.');}
        curl_setopt_array($ch,[CURLOPT_CUSTOMREQUEST=>'POST',CURLOPT_UPLOAD=>true,CURLOPT_INFILE=>$fp,CURLOPT_INFILESIZE=>$size,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>$timeout,CURLOPT_CONNECTTIMEOUT=>12,CURLOPT_HTTPHEADER=>array_merge(['Content-Type: application/octet-stream','Accept: application/json','Content-Length: '.$size],$headers),CURLOPT_FOLLOWLOCATION=>false]);
        $body=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$error=curl_error($ch);curl_close($ch);fclose($fp);$text=$body===false?'':(string)$body;
        if($body===false||$status<200||$status>=300)throw new RuntimeException('Property Node respondió HTTP '.$status.($error!==''?' · '.$error:'').($text!==''?' · '.mb_substr(trim(strip_tags($text)),0,300):''));
        $data=json_decode($text,true);if(!is_array($data))throw new RuntimeException('Property Node recibió el archivo pero no devolvió JSON válido.');return $data+['_endpoint'=>$url,'_base_url'=>$base];
    }
}
