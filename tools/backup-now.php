<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit("CLI only\n");}
$root=dirname(__DIR__);require_once $root.'/app/bootstrap.php';
$type=(string)($argv[1]??'manual');if(!in_array($type,['manual','scheduled','pre_update','pre_restore'],true))$type='manual';
try{$r=(new BackupManager(Connection::pdo()))->create($type,null);echo "OK {$r['filename']}\nSHA256 {$r['sha256']}\nPATH {$r['path']}\n";if(!empty($r['warnings']))echo "WARN ".implode(' | ',$r['warnings'])."\n";}catch(Throwable $e){ErrorReporter::report($e,['operation'=>'backup_cli','type'=>$type]);fwrite(STDERR,"ERROR: {$e->getMessage()}\n");exit(1);}
