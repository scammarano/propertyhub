<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit("CLI only\n");}
$root=dirname(__DIR__);require_once $root.'/app/bootstrap.php';
$options=getopt('', ['file:','key:','mode::','confirm:']);$file=(string)($options['file']??'');$key=(string)($options['key']??'');$mode=(string)($options['mode']??'same_installation');$confirm=(string)($options['confirm']??'');
if($file===''||$key===''){fwrite(STDERR,"Uso: php tools/restore-backup.php --file=C:\\ruta\\backup.phbackup --key=CLAVE --mode=same_installation|safe_clone --confirm=CODIGO_ACTUAL\n");exit(2);}if(!hash_equals((string)config('app.instance_code'),$confirm)){fwrite(STDERR,"Confirmación incorrecta. Use --confirm=".config('app.instance_code')."\n");exit(3);}try{$m=new BackupManager(Connection::pdo());$info=$m->inspect($file,$key);echo "Backup: ".($info['manifest']['installation']['name']??'')." · ".($info['manifest']['created_at']??'')."\n";$r=$m->restore($file,$key,$mode,null);echo "OK {$r['message']}\nPunto previo: {$r['safety_backup']['filename']}\n";}catch(Throwable $e){fwrite(STDERR,"ERROR: {$e->getMessage()}\n");exit(1);}
