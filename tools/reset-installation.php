<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit("CLI only\n");}
$root=dirname(__DIR__);
require_once $root.'/app/Support/helpers.php';
require_once $root.'/app/Database/Connection.php';
require_once $root.'/app/Deployment/InstallationMode.php';
require_once $root.'/app/Seeders/CentralDemoSeeder.php';
$options=getopt('', ['demo-only','factory-reset','confirm:']);
$confirm=(string)($options['confirm']??'');
$expected=(string)config('app.instance_code','');
if($expected===''||!hash_equals($expected,$confirm)){fwrite(STDERR,"Confirmación inválida. Use --confirm={$expected}\n");exit(2);}
$pdo=Connection::pdo();
if(isset($options['demo-only'])){
 if(!InstallationMode::isCentral()){fwrite(STDERR,"--demo-only solo aplica al Property Hub Central.\n");exit(3);}
 $pdo->exec("DELETE FROM bh_repo_installations WHERE is_demo=1");
 $pdo->exec("DELETE FROM bh_repo_installation_plans WHERE installation_code LIKE 'DEMO-%'");
 $result=(new CentralDemoSeeder($pdo))->seed();
 echo "Demo central restablecida: {$result['installations']} instalaciones, {$result['plans']} preparaciones, {$result['connectors']} conectores.\n";exit(0);
}
if(!isset($options['factory-reset'])){fwrite(STDERR,"Indique --demo-only o --factory-reset.\n");exit(4);}
$pdo->exec('SET FOREIGN_KEY_CHECKS=0');
$tables=$pdo->query('SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE()')->fetchAll(PDO::FETCH_COLUMN);
foreach($tables as $table){$quoted='`'.str_replace('`','``',(string)$table).'`';$pdo->exec("DROP TABLE {$quoted}");echo "Eliminada {$table}\n";}
$pdo->exec('SET FOREIGN_KEY_CHECKS=1');
foreach([$root.'/storage/installed.lock',$root.'/.env'] as $file)if(is_file($file))unlink($file);
foreach([$root.'/storage/keys/repository-private.pem',$root.'/storage/keys/repository-public.pem'] as $file)if(is_file($file))unlink($file);
echo "Instalación reiniciada. La base permanece creada pero vacía. Abra /install/ para instalar nuevamente.\n";
