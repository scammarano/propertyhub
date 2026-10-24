<?php
declare(strict_types=1);
$root=dirname(__DIR__);
require_once $root.'/app/bootstrap.php';
try{
    EnvironmentEditor::set([
        'APP_DIAGNOSTICS'=>'false',
        'APP_LOG_LEVEL'=>'error',
        'APP_LOG_REQUESTS'=>'false',
        'DIAGNOSTIC_TOKEN'=>'',
    ]);
    echo "Diagnóstico remoto desactivado. Los errores críticos continuarán registrándose.\n";
}catch(Throwable $e){fwrite(STDERR,"No se pudo desactivar el diagnóstico: {$e->getMessage()}\n");exit(1);}
