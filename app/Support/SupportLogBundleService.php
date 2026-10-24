<?php
declare(strict_types=1);

/**
 * Generates a portable ZIP with recent Property Hub logs for support.
 * The bundle is read-only, excludes runtime secrets/configuration and applies
 * a second redaction pass before placing text logs inside the archive.
 */
final class SupportLogBundleService
{
    private const MAX_FILE_BYTES = 12_000_000;
    private const MAX_TOTAL_BYTES = 80_000_000;

    /** @return array{path:string,filename:string,file_count:int,bytes:int,skipped:list<array<string,mixed>>} */
    public function create(int $days=7, ?string $incident=null, ?string $requestId=null): array
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('La extensión ZIP de PHP es necesaria para descargar los logs.');
        }
        $days=max(1,min(30,$days));
        $logDir=ErrorReporter::logDirectory();
        if(!is_dir($logDir))throw new RuntimeException('No existe la carpeta de logs de esta instalación.');

        $tmpDir=base_path('storage/tmp/support');
        if(!is_dir($tmpDir)&&!@mkdir($tmpDir,0775,true)&&!is_dir($tmpDir))throw new RuntimeException('No se pudo crear el área temporal para soporte.');
        $instance=preg_replace('/[^A-Za-z0-9._-]+/','-',trim((string)config('app.instance_code','property-hub')))?:'property-hub';
        $filename='PROPERTY_HUB_LOGS_'.$instance.'_'.date('Ymd_His').'.zip';
        $path=$tmpDir.'/'.$filename;

        $zip=new ZipArchive();
        if($zip->open($path,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)throw new RuntimeException('No se pudo crear el ZIP de logs.');

        $cutoff=time()-($days*86400);
        $fileCount=0;$totalBytes=0;$skipped=[];$included=[];
        $allowedExtensions=['log','jsonl','txt'];
        $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($logDir,FilesystemIterator::SKIP_DOTS));
        try{
            foreach($iterator as $file){
                if(!$file->isFile())continue;
                $ext=strtolower(pathinfo($file->getFilename(),PATHINFO_EXTENSION));
                if(!in_array($ext,$allowedExtensions,true))continue;
                if($file->getMTime()<$cutoff)continue;
                $size=(int)$file->getSize();
                $relative=str_replace('\\','/',substr($file->getPathname(),strlen($logDir)+1));
                if($size>self::MAX_FILE_BYTES){$skipped[]=['file'=>$relative,'reason'=>'file_too_large','bytes'=>$size];continue;}
                if($totalBytes+$size>self::MAX_TOTAL_BYTES){$skipped[]=['file'=>$relative,'reason'=>'bundle_limit','bytes'=>$size];continue;}
                $raw=@file_get_contents($file->getPathname());
                if($raw===false){$skipped[]=['file'=>$relative,'reason'=>'read_failed','bytes'=>$size];continue;}
                $safe=$this->redact($raw);
                $zip->addFromString('logs/'.$relative,$safe);
                $fileCount++;$totalBytes+=strlen($safe);
                $included[]=['file'=>$relative,'bytes'=>strlen($safe),'modified_at'=>date(DATE_ATOM,$file->getMTime())];
            }
            $manifest=[
                'schema'=>'2cam-property-support-logs-v1',
                'generated_at'=>date(DATE_ATOM),
                'installation_code'=>(string)config('app.instance_code',''),
                'role'=>InstallationMode::isCentral()?'central':(InstallationMode::isEdge()?'edge_agent':'building_node'),
                'app_version'=>(string)config('app.version',''),
                'php_version'=>PHP_VERSION,
                'os'=>PHP_OS_FAMILY,
                'timezone'=>date_default_timezone_get(),
                'window_days'=>$days,
                'incident'=>$this->cleanId($incident),
                'request_id'=>$this->cleanId($requestId),
                'files'=>$included,
                'skipped'=>$skipped,
                'privacy_note'=>'Passwords, authorization headers, cookies, tokens and embedded RTSP credentials are redacted again while building this support bundle.',
            ];
            $zip->addFromString('support-info.json',json_encode($manifest,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE));
        }finally{
            $zip->close();
        }
        if(!is_file($path)||filesize($path)<=0)throw new RuntimeException('El ZIP de logs quedó vacío o no pudo cerrarse correctamente.');
        @chmod($path,0640);
        return ['path'=>$path,'filename'=>$filename,'file_count'=>$fileCount,'bytes'=>(int)filesize($path),'skipped'=>$skipped];
    }

    private function cleanId(?string $value): string
    {
        $value=trim((string)$value);
        return preg_replace('/[^A-Za-z0-9._:-]+/','',substr($value,0,120))?:'';
    }

    private function redact(string $text): string
    {
        $patterns=[
            '/(Authorization\s*[:=]\s*)(?:Bearer\s+)?[^\s\"\'<>]+/i',
            '/((?:access[_-]?token|refresh[_-]?token|api[_-]?key|password|passwd|secret|client[_-]?secret|cookie|set-cookie)\s*[\"\']?\s*[:=]\s*[\"\']?)[^\"\'\r\n,;}]*/i',
            '#(rtsp://)([^:/@\s]+):([^@/\s]+)@#i',
            '#(https?://)([^:/@\s]+):([^@/\s]+)@#i',
        ];
        $replacements=['$1[REDACTED]','$1[REDACTED]','$1[REDACTED]:[REDACTED]@','$1[REDACTED]:[REDACTED]@'];
        return (string)preg_replace($patterns,$replacements,$text);
    }
}
