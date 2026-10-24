<?php
declare(strict_types=1);
$root=dirname(__DIR__);
require_once $root.'/app/bootstrap.php';
$options=getopt('', ['confirm:']);
$confirm=(string)($options['confirm']??'');
$expected=(string)config('app.instance_code');
if($confirm!==$expected){fwrite(STDERR,"Confirme con --confirm={$expected}\n");exit(2);}
$result=(new DemoDataCleaner(Connection::pdo()))->clean();
echo "Datos demo eliminados y APP_DEMO_MODE=false.\n";
foreach($result as $key=>$value)if(is_int($value))echo "- {$key}: {$value}\n";
