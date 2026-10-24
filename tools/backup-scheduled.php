<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit("CLI only\n");}
$root=dirname(__DIR__);require_once $root.'/app/bootstrap.php';
try{
    $now=date(DATE_ATOM);
    $schedulerMarker=$root.'/storage/backups/scheduler-last-run.json';@mkdir(dirname($schedulerMarker),0775,true);$schedulerState=['started_at'=>$now,'finished_at'=>null,'status'=>'running','role'=>InstallationMode::current(),'central_heartbeat_online'=>null,'central_message'=>null];@file_put_contents($schedulerMarker,json_encode($schedulerState,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
    // En Edge, el Node es primero: él entrega/corrige la topología hacia Central.
    if(InstallationMode::isEdge()){
        try{
            $cfg=EdgeNodeClient::refreshProvisioning();
            echo $now.' NODE CONFIG '.(!empty($cfg['ok'])?'OK':'PENDIENTE').' '.(string)($cfg['property_code']??'')."\n";
        }catch(Throwable $e){ErrorReporter::report($e,['operation'=>'scheduler_edge_provisioning']);echo $now.' NODE CONFIG ERROR '.$e->getMessage()."\n";}
        try{
            $node=EdgeNodeClient::heartbeat();
            echo $now.' HEARTBEAT NODE '.(!empty($node['ok'])?'OK':(!empty($node['configured'])?'WARN':'PENDIENTE'))."\n";
        }catch(Throwable $e){ErrorReporter::report($e,['operation'=>'scheduler_property_node_heartbeat']);echo $now.' HEARTBEAT NODE ERROR '.$e->getMessage()."\n";}
        try{
            $sync=EdgeNodeClient::syncCameras(true);
            echo $now.' EDGE INVENTORY '.(!empty($sync['ok'])?'OK':'WARN').' cameras='.(int)($sync['received']??0).' snapshots='.(int)($sync['snapshots_uploaded']??0)."\n";
        }catch(Throwable $e){ErrorReporter::report($e,['operation'=>'scheduler_edge_inventory']);echo $now.' EDGE INVENTORY ERROR '.$e->getMessage()."\n";}
        if(LocalIntegrationProvisioning::edgeEnabled('vimar')){try{
            $vimarSync=EdgeNodeClient::syncEquipment('vimar');
            echo $now.' VIMAR INVENTORY '.(!empty($vimarSync['ok'])?'OK':(!empty($vimarSync['skipped'])?'SKIP':'WARN')).' devices='.(int)($vimarSync['sent_devices']??0)."\n";
        }catch(Throwable $e){ErrorReporter::report($e,['operation'=>'scheduler_vimar_inventory']);echo $now.' VIMAR INVENTORY ERROR '.$e->getMessage()."\n";}}
    }
    if(InstallationMode::isManagedInstallation()){
        try{
            $central=RepositoryClient::status();
            $schedulerState['central_heartbeat_online']=!empty($central['online']);$schedulerState['central_message']=(string)($central['message']??'');@file_put_contents($schedulerMarker,json_encode($schedulerState,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
            echo $now.' HEARTBEAT CENTRAL '.(!empty($central['online'])?'OK':'WARN').' '.($central['message']??'')."\n";
        }catch(Throwable $heartbeatError){ErrorReporter::report($heartbeatError,['operation'=>'scheduler_central_heartbeat']);echo $now.' HEARTBEAT CENTRAL ERROR '.$heartbeatError->getMessage()."\n";}
    }
    $m=new BackupManager(Connection::pdo());
    if(!$m->due()){$schedulerState['status']='ok';$schedulerState['finished_at']=date(DATE_ATOM);@file_put_contents($schedulerMarker,json_encode($schedulerState,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));echo $now." BACKUP SKIP no corresponde respaldo ahora\n";exit(0);}
    $r=$m->create('scheduled',null);$schedulerState['status']='ok';$schedulerState['finished_at']=date(DATE_ATOM);@file_put_contents($schedulerMarker,json_encode($schedulerState,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));echo $now." BACKUP OK {$r['filename']}\n";
}catch(Throwable $e){if(isset($schedulerMarker,$schedulerState)){$schedulerState['status']='error';$schedulerState['finished_at']=date(DATE_ATOM);$schedulerState['error']=$e->getMessage();@file_put_contents($schedulerMarker,json_encode($schedulerState,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));}ErrorReporter::report($e,['operation'=>'property_hub_scheduler']);fwrite(STDERR,date(DATE_ATOM)." ERROR {$e->getMessage()}\n");exit(1);}
