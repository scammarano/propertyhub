<?php
declare(strict_types=1);

final class RepositoryTransport
{
    /**
     * Normaliza URLs copiadas desde el navegador.
     * Acepta /public, /public/index.php y URLs con ?route=...
     */
    public static function normalizeBaseUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') return '';
        if (!preg_match('#^https?://#i', $url)) {
            throw new InvalidArgumentException('La URL del Property Hub Central debe comenzar por http:// o https://.');
        }

        $url = preg_replace('/[?#].*$/', '', $url) ?? $url;
        $url = rtrim($url, '/');
        $url = preg_replace('#/index\.php$#i', '', $url) ?? $url;
        return rtrim($url, '/');
    }

    /** @return list<string> */
    public static function baseCandidates(string $url): array
    {
        $base = self::normalizeBaseUrl($url);
        if ($base === '') return [];

        $candidates = [$base];
        if (!str_ends_with(strtolower($base), '/public')) {
            $candidates[] = $base.'/public';
        }

        return array_values(array_unique($candidates));
    }

    /** @return list<array{url:string,base_url:string,mode:string}> */
    public static function endpointCandidates(string $repositoryUrl, string $action): array
    {
        if (!preg_match('/^[a-z][a-z0-9_-]*$/', $action)) {
            throw new InvalidArgumentException('Acción de repositorio no válida.');
        }

        $result = [];
        foreach (self::baseCandidates($repositoryUrl) as $base) {
            $result[] = [
                'url' => $base.'/api/repository/v1/'.$action.'.php',
                'base_url' => $base,
                'mode' => 'direct',
            ];
            $result[] = [
                'url' => $base.'/index.php?route=repository-api-'.$action,
                'base_url' => $base,
                'mode' => 'front_controller',
            ];
        }
        return $result;
    }

    /**
     * @param array<string,mixed> $payload
     * @param list<string> $headers
     * @return array{data:array<string,mixed>,endpoint:string,base_url:string,mode:string,http_status:int}
     */
    public static function postJson(string $repositoryUrl, string $action, array $payload, array $headers = [], int $timeout = 15): array
    {
        if (!extension_loaded('curl')) {
            throw new RuntimeException('La extensión cURL no está disponible.');
        }

        $attempts = [];
        foreach (self::endpointCandidates($repositoryUrl, $action) as $candidate) {
            $ch = curl_init($candidate['url']);
            if ($ch === false) throw new RuntimeException('No fue posible iniciar cURL.');

            $httpHeaders = array_merge(['Content-Type: application/json', 'Accept: application/json'], $headers);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => min(8, $timeout),
                CURLOPT_HTTPHEADER => $httpHeaders,
                CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                CURLOPT_FOLLOWLOCATION => false,
            ]);

            $body = curl_exec($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            $error = curl_error($ch);
            $contentType = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            curl_close($ch);

            $bodyText = $body === false ? '' : (string)$body;
            $attempts[] = [
                'url' => $candidate['url'],
                'status' => $status,
                'curl_error' => $error,
                'content_type' => $contentType,
                'body_preview' => self::preview($bodyText),
            ];

            if ($body !== false && $status >= 200 && $status < 300) {
                $decoded = json_decode($bodyText, true);
                if (!is_array($decoded)) {
                    throw new RuntimeException('El Property Hub Central respondió HTTP '.$status.' pero no devolvió JSON válido. Endpoint: '.$candidate['url']);
                }
                return [
                    'data' => $decoded,
                    'endpoint' => $candidate['url'],
                    'base_url' => $candidate['base_url'],
                    'mode' => $candidate['mode'],
                    'http_status' => $status,
                ];
            }

            // 404/405 permiten probar la ruta alternativa. Algunos hosting/cPanel
            // bloquean o redirigen los PHP profundos aunque el front-controller sí funcione.
            // En ese caso continuamos solamente si la respuesta NO es un error JSON de la API;
            // un 401/403 JSON real sigue siendo definitivo para no ocultar credenciales inválidas.
            $decodedFailure=json_decode($bodyText,true);
            $apiFailure=is_array($decodedFailure)&&isset($decodedFailure['error']);
            $genericDirectFailure=$candidate['mode']==='direct' && in_array($status,[301,302,307,308,403],true) && !$apiFailure;
            if (!in_array($status, [0, 404, 405], true) && !$genericDirectFailure) {
                throw new RuntimeException(self::formatFailure($action, $attempts));
            }
        }

        throw new RuntimeException(self::formatFailure($action, $attempts));
    }



    /**
     * Envía un archivo cifrado sin cargarlo completo en memoria.
     * @param list<string> $headers
     * @return array{data:array<string,mixed>,endpoint:string,base_url:string,mode:string,http_status:int}
     */
    public static function postFile(string $repositoryUrl, string $action, string $filePath, array $headers = [], int $timeout = 180): array
    {
        if (!extension_loaded('curl')) throw new RuntimeException('La extensión cURL no está disponible.');
        if (!is_file($filePath)) throw new RuntimeException('Archivo a enviar no encontrado.');
        $size=(int)filesize($filePath);$attempts=[];
        foreach (self::endpointCandidates($repositoryUrl,$action) as $candidate) {
            $fp=@fopen($filePath,'rb'); if(!$fp) throw new RuntimeException('No fue posible abrir el respaldo para enviarlo.');
            $ch=curl_init($candidate['url']); if($ch===false){fclose($fp);throw new RuntimeException('No fue posible iniciar cURL.');}
            $httpHeaders=array_merge(['Content-Type: application/octet-stream','Accept: application/json','Content-Length: '.$size],$headers);
            curl_setopt_array($ch,[
                CURLOPT_CUSTOMREQUEST=>'POST',CURLOPT_UPLOAD=>true,CURLOPT_INFILE=>$fp,CURLOPT_INFILESIZE=>$size,
                CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>$timeout,CURLOPT_CONNECTTIMEOUT=>min(12,$timeout),
                CURLOPT_HTTPHEADER=>$httpHeaders,CURLOPT_FOLLOWLOCATION=>false,
            ]);
            $body=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$error=curl_error($ch);$contentType=(string)curl_getinfo($ch,CURLINFO_CONTENT_TYPE);curl_close($ch);fclose($fp);
            $bodyText=$body===false?'':(string)$body;
            $attempts[]=['url'=>$candidate['url'],'status'=>$status,'curl_error'=>$error,'content_type'=>$contentType,'body_preview'=>self::preview($bodyText)];
            if($body!==false&&$status>=200&&$status<300){$decoded=json_decode($bodyText,true);if(!is_array($decoded))throw new RuntimeException('El Central recibió el respaldo pero no devolvió JSON válido.');return ['data'=>$decoded,'endpoint'=>$candidate['url'],'base_url'=>$candidate['base_url'],'mode'=>$candidate['mode'],'http_status'=>$status];}
            if(!in_array($status,[0,404,405],true))throw new RuntimeException(self::formatFailure($action,$attempts));
        }
        throw new RuntimeException(self::formatFailure($action,$attempts));
    }


    /**
     * Descarga un paquete de actualización desde el repositorio autenticado sin
     * cargarlo completo en memoria.
     *
     * @param list<string> $headers
     * @return array{endpoint:string,base_url:string,mode:string,http_status:int,bytes:int,sha256:string}
     */
    public static function downloadPackage(string $repositoryUrl,string $releaseCode,string $destination,array $headers=[],int $timeout=300): array
    {
        if (!extension_loaded('curl')) throw new RuntimeException('La extensión cURL no está disponible.');
        $releaseCode=trim($releaseCode);if($releaseCode==='')throw new InvalidArgumentException('Código de versión vacío.');
        $dir=dirname($destination);if(!is_dir($dir)&&!@mkdir($dir,0775,true)&&!is_dir($dir))throw new RuntimeException('No fue posible crear la carpeta de descargas.');
        $attempts=[];
        foreach(self::endpointCandidates($repositoryUrl,'package') as $candidate){
            $sep=str_contains($candidate['url'],'?')?'&':'?';
            $url=$candidate['url'].$sep.'release='.rawurlencode($releaseCode);
            $fp=@fopen($destination,'wb');if(!$fp)throw new RuntimeException('No fue posible crear el archivo temporal de actualización.');
            $responseHeaders=[];
            $ch=curl_init($url);if($ch===false){fclose($fp);throw new RuntimeException('No fue posible iniciar cURL.');}
            curl_setopt_array($ch,[
                CURLOPT_HTTPGET=>true,CURLOPT_FILE=>$fp,CURLOPT_TIMEOUT=>$timeout,CURLOPT_CONNECTTIMEOUT=>min(12,$timeout),
                CURLOPT_HTTPHEADER=>array_merge(['Accept: application/zip'], $headers),CURLOPT_FOLLOWLOCATION=>false,
                CURLOPT_HEADERFUNCTION=>static function($ch,$line) use (&$responseHeaders){$len=strlen($line);$pos=strpos($line,':');if($pos!==false){$name=strtolower(trim(substr($line,0,$pos)));$responseHeaders[$name]=trim(substr($line,$pos+1));}return $len;},
            ]);
            $ok=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$error=curl_error($ch);$contentType=(string)curl_getinfo($ch,CURLINFO_CONTENT_TYPE);curl_close($ch);fclose($fp);
            $preview='';if(is_file($destination)&&filesize($destination)!==false&&(int)filesize($destination)<65536){$preview=self::preview((string)@file_get_contents($destination));}
            $attempts[]=['url'=>$url,'status'=>$status,'curl_error'=>$error,'content_type'=>$contentType,'body_preview'=>$preview];
            if($ok!==false&&$status>=200&&$status<300){
                $bytes=(int)(filesize($destination)?:0);if($bytes<=0){@unlink($destination);throw new RuntimeException('El Central respondió sin contenido de paquete.');}
                return ['endpoint'=>$url,'base_url'=>$candidate['base_url'],'mode'=>$candidate['mode'],'http_status'=>$status,'bytes'=>$bytes,'sha256'=>(string)(hash_file('sha256',$destination)?:''),'response_sha256'=>(string)($responseHeaders['x-checksum-sha256']??'')];
            }
            @unlink($destination);
            if(!in_array($status,[0,404,405],true))throw new RuntimeException(self::formatFailure('package',$attempts));
        }
        throw new RuntimeException(self::formatFailure('package',$attempts));
    }


    /** Descarga una copia cifrada desde el Backup Vault usando un token de recuperación de un solo uso. */
    public static function downloadRecoveryBackup(string $repositoryUrl,string $token,string $destination,int $timeout=300): array
    {
        if(!extension_loaded('curl'))throw new RuntimeException('La extensión cURL no está disponible.');
        $dir=dirname($destination);if(!is_dir($dir)&&!@mkdir($dir,0775,true)&&!is_dir($dir))throw new RuntimeException('No fue posible crear la carpeta temporal de recuperación.');
        $attempts=[];
        foreach(self::endpointCandidates($repositoryUrl,'recovery-download') as $candidate){
            $fp=@fopen($destination,'wb');if(!$fp)throw new RuntimeException('No fue posible crear el archivo de recuperación.');
            $headers=[];$ch=curl_init($candidate['url']);if($ch===false){fclose($fp);throw new RuntimeException('No fue posible iniciar cURL.');}
            curl_setopt_array($ch,[CURLOPT_HTTPGET=>true,CURLOPT_FILE=>$fp,CURLOPT_TIMEOUT=>$timeout,CURLOPT_CONNECTTIMEOUT=>min(12,$timeout),CURLOPT_HTTPHEADER=>['Accept: application/octet-stream','X-Recovery-Token: '.trim($token)],CURLOPT_FOLLOWLOCATION=>false,CURLOPT_HEADERFUNCTION=>static function($ch,$line) use (&$headers){$len=strlen($line);$pos=strpos($line,':');if($pos!==false)$headers[strtolower(trim(substr($line,0,$pos)))]=trim(substr($line,$pos+1));return $len;}]);
            $ok=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$error=curl_error($ch);$contentType=(string)curl_getinfo($ch,CURLINFO_CONTENT_TYPE);curl_close($ch);fclose($fp);
            $preview='';if(is_file($destination)&&filesize($destination)!==false&&(int)filesize($destination)<65536)$preview=self::preview((string)@file_get_contents($destination));
            $attempts[]=['url'=>$candidate['url'],'status'=>$status,'curl_error'=>$error,'content_type'=>$contentType,'body_preview'=>$preview];
            if($ok!==false&&$status>=200&&$status<300){$bytes=(int)(filesize($destination)?:0);$sha=(string)(hash_file('sha256',$destination)?:'');$expected=(string)($headers['x-checksum-sha256']??'');if($expected!==''&&!hash_equals(strtolower($expected),strtolower($sha))){@unlink($destination);throw new RuntimeException('La copia descargada del Central no coincide con su SHA-256.');}return ['endpoint'=>$candidate['url'],'base_url'=>$candidate['base_url'],'mode'=>$candidate['mode'],'http_status'=>$status,'bytes'=>$bytes,'sha256'=>$sha,'installation_code'=>rawurldecode((string)($headers['x-installation-code']??''))];}
            @unlink($destination);if(!in_array($status,[0,404,405],true))throw new RuntimeException(self::formatFailure('recovery-download',$attempts));
        }
        throw new RuntimeException(self::formatFailure('recovery-download',$attempts));
    }

    /** @param list<array<string,mixed>> $attempts */
    private static function formatFailure(string $action, array $attempts): string
    {
        $parts = [];
        foreach ($attempts as $attempt) {
            $parts[] = sprintf(
                '%s → HTTP %d%s%s',
                (string)$attempt['url'],
                (int)$attempt['status'],
                (string)$attempt['curl_error'] !== '' ? ' · cURL: '.$attempt['curl_error'] : '',
                (string)$attempt['body_preview'] !== '' ? ' · '.(string)$attempt['body_preview'] : ''
            );
        }
        return 'No se encontró un endpoint válido para '.$action.'. Se probaron: '.implode(' | ', $parts);
    }

    private static function preview(string $body): string
    {
        $text = trim(preg_replace('/\s+/', ' ', strip_tags($body)) ?? '');
        if ($text === '') return '';
        return mb_substr($text, 0, 280).(mb_strlen($text) > 280 ? '…' : '');
    }
}
