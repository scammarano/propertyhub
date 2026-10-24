<?php
declare(strict_types=1);

final class RemoteMaintenanceAgent
{
    public static function process(array $tasks): array
    {
        if(InstallationMode::isCentral()||$tasks===[])return [];
        $results=[];
        foreach($tasks as $task){
            if(!is_array($task)||(string)($task['type']??'')!=='backup_to_central')continue;
            $uuid=trim((string)($task['request_uuid']??''));if($uuid==='')continue;
            try{
                $backup=(new BackupManager(Connection::pdo()))->create('manual',null,['force_central_copy'=>true,'central_request_uuid'=>$uuid,'skip_prune'=>false]);
                $centralOk=false;foreach((array)($backup['destinations']??[]) as $d)if(($d['type']??'')==='central'&&($d['status']??'')==='ok')$centralOk=true;
                if(!$centralOk)throw new RuntimeException('El respaldo local se creó, pero la copia cifrada no pudo confirmarse en Property Hub Central.');
                RepositoryClient::completeMaintenanceTask($uuid,'completed',['filename'=>$backup['filename']??'','sha256'=>$backup['sha256']??'','size_bytes'=>$backup['size']??0]);
                $results[]=['request_uuid'=>$uuid,'status'=>'completed','filename'=>$backup['filename']??''];
            }catch(Throwable $e){
                try{RepositoryClient::completeMaintenanceTask($uuid,'failed',['message'=>$e->getMessage()]);}catch(Throwable){}
                ErrorReporter::report($e,['operation'=>'remote_backup_request','request_uuid'=>$uuid]);
                $results[]=['request_uuid'=>$uuid,'status'=>'failed','message'=>$e->getMessage()];
            }
        }
        return $results;
    }
}
