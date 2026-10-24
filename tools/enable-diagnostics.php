<?php
declare(strict_types=1);
$root=dirname(__DIR__);
require_once $root.'/app/bootstrap.php';
try{
    $token=bin2hex(random_bytes(24));
    EnvironmentEditor::set([
        'APP_DIAGNOSTICS'=>'true',
        'APP_LOG_LEVEL'=>'debug',
        'APP_LOG_REQUESTS'=>'true',
        'DIAGNOSTIC_TOKEN'=>$token,
    ]);
    $url=rtrim((string)config('app.url',''),'/').'/diagnostics.php?token='.$token;
    echo "Diagnóstico activado.\n";
    echo "URL protegida:\n{$url}\n\n";
    echo "Logs:\n".ErrorReporter::logDirectory()."\n";
}catch(Throwable $e){fwrite(STDERR,"No se pudo activar el diagnóstico: {$e->getMessage()}\n");exit(1);}
