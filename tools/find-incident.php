<?php
declare(strict_types=1);
$root=dirname(__DIR__);
require_once $root.'/app/bootstrap.php';
$needle=trim((string)($argv[1]??''));
if($needle===''||!preg_match('/^[A-Za-z0-9._-]{6,100}$/',$needle)){
    fwrite(STDERR,"Uso: php tools/find-incident.php NUMERO-DE-INCIDENTE\n");exit(1);
}
$dir=ErrorReporter::logDirectory();$found=false;
foreach(glob($dir.DIRECTORY_SEPARATOR.'*')?:[] as $file){
    if(!is_file($file))continue;$lines=file($file,FILE_IGNORE_NEW_LINES)?:[];
    foreach($lines as $i=>$line){
        if(!str_contains($line,$needle))continue;$found=true;
        echo "\n=== ".basename($file)." · línea ".($i+1)." ===\n";
        $start=max(0,$i-2);$end=min(count($lines)-1,$i+35);
        for($n=$start;$n<=$end;$n++)echo str_pad((string)($n+1),6,' ',STR_PAD_LEFT).' | '.$lines[$n].PHP_EOL;
    }
}
if(!$found){echo "No se encontró el incidente {$needle} en {$dir}\n";exit(2);} 
