<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$bootstrap=$root.'/app/bootstrap.php';
if(!is_file($bootstrap)){fwrite(STDERR,"Bootstrap de Property Hub no encontrado: {$bootstrap}\n");exit(3);}
require_once $bootstrap;
if(!InstallationMode::isEdge()){fwrite(STDERR,"Este worker solo corresponde a Property Edge Agent.\n");exit(2);}
$runDir=base_path('storage/run');if(!is_dir($runDir))@mkdir($runDir,0775,true);$lock=fopen($runDir.'/edge-command-worker.lock','c+');if(!$lock||!flock($lock,LOCK_EX|LOCK_NB)){exit(0);}
EdgeCommandWorkerService::mark(['state'=>'starting']);$consecutiveErrors=0;$lastMediaWatchdog=0;$mediaWatchdog=['ok'=>false,'state'=>'pending'];

// El mismo worker de baja latencia es ahora supervisor del runtime local. Si Windows
// lo arranca mediante la tarea programada, MediaMTX y los túneles se recuperan sin
// necesidad de abrir primero la consola web.
try{$mediaWatchdog=(new EdgeMediaGatewayService(Connection::pdo()))->watchdog(true);$lastMediaWatchdog=time();}
catch(Throwable $e){$mediaWatchdog=['ok'=>false,'error'=>$e->getMessage()];if(class_exists('ErrorReporter'))ErrorReporter::report($e,['operation'=>'edge_worker_media_watchdog_start']);}

while(!EdgeCommandWorkerService::shouldStop()){
    try{
        $r=EdgeNodeClient::pollCommand();$consecutiveErrors=0;
        if((time()-$lastMediaWatchdog)>=30){
            try{$mediaWatchdog=(new EdgeMediaGatewayService(Connection::pdo()))->watchdog(true);}
            catch(Throwable $mediaError){$mediaWatchdog=['ok'=>false,'error'=>$mediaError->getMessage()];if(class_exists('ErrorReporter'))ErrorReporter::report($mediaError,['operation'=>'edge_worker_media_watchdog']);}
            $lastMediaWatchdog=time();
        }
        EdgeCommandWorkerService::mark(['state'=>'running','last_poll_ok'=>true,'last_command'=>$r['command_processed']??null,'media_watchdog'=>$mediaWatchdog]);$sleep=!empty($r['command_processed'])?250000:1500000;
    }
    catch(Throwable $e){
        $consecutiveErrors++;
        if((time()-$lastMediaWatchdog)>=30){
            try{$mediaWatchdog=(new EdgeMediaGatewayService(Connection::pdo()))->watchdog(true);}
            catch(Throwable $mediaError){$mediaWatchdog=['ok'=>false,'error'=>$mediaError->getMessage()];}
            $lastMediaWatchdog=time();
        }
        EdgeCommandWorkerService::mark(['state'=>'degraded','last_poll_ok'=>false,'last_error'=>$e->getMessage(),'error_count'=>$consecutiveErrors,'media_watchdog'=>$mediaWatchdog]);if(class_exists('ErrorReporter'))ErrorReporter::report($e,['operation'=>'edge_command_worker_poll']);$sleep=min(10000000,1500000*$consecutiveErrors);
    }
    usleep($sleep);
}
@unlink(base_path('storage/run/edge-command-worker.stop'));EdgeCommandWorkerService::mark(['state'=>'stopped','media_watchdog'=>$mediaWatchdog]);flock($lock,LOCK_UN);fclose($lock);
