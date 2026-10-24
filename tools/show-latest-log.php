<?php
declare(strict_types=1);
$root=dirname(__DIR__);
require_once $root.'/app/bootstrap.php';
$file=ErrorReporter::logDirectory().DIRECTORY_SEPARATOR.'property-hub-'.date('Y-m-d').'.log';
if(!is_file($file)){echo "No existe log de incidentes para hoy: {$file}\n";exit(0);} 
$lines=file($file,FILE_IGNORE_NEW_LINES)?:[];
echo implode(PHP_EOL,array_slice($lines,-250)).PHP_EOL;
