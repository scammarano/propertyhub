<?php
declare(strict_types=1);

/**
 * Worker saliente Edge -> Node para comandos de baja latencia.
 * No abre puertos entrantes en el Edge: mantiene el principio LOCAL FIRST.
 */
final class EdgeCommandWorkerService
{
    private const TASK_NAME = 'PropertyHub Edge Command Worker';
    private static function statusPath(): string{return base_path('storage/run/edge-command-worker.json');}
    private static function stopPath(): string{return base_path('storage/run/edge-command-worker.stop');}
    private static function supervisorStopPath(): string{return base_path('storage/run/edge-command-worker-supervisor.stop');}
    private static function bootstrapLogPath(): string{return base_path('storage/logs/edge-command-worker-bootstrap.log');}
    private static function ensureRunDir(): void{$d=dirname(self::statusPath());if(!is_dir($d))@mkdir($d,0775,true);$l=dirname(self::bootstrapLogPath());if(!is_dir($l))@mkdir($l,0775,true);}

    public static function status(): array
    {
        self::ensureRunDir();$raw=is_file(self::statusPath())?json_decode((string)file_get_contents(self::statusPath()),true):null;$row=is_array($raw)?$raw:[];$last=(int)($row['heartbeat_unix']??0);$state=strtolower((string)($row['state']??''));$alive=$last>0&&(time()-$last)<=12&&!in_array($state,['stopped','stopping'],true);
        return $row+[
            'running'=>$alive,
            'age_seconds'=>$last>0?max(0,time()-$last):null,
            'install_script'=>'tools\\install-edge-command-worker.bat',
            'bootstrap_log'=>'storage/logs/edge-command-worker-bootstrap.log',
            'startup_task'=>self::startupTaskStatus(),
        ];
    }

    public static function startupTaskStatus(): array
    {
        if(PHP_OS_FAMILY!=='Windows')return ['installed'=>false,'configured_current'=>false,'supported'=>false,'label'=>'No aplica'];
        if(!function_exists('exec'))return ['installed'=>false,'configured_current'=>false,'supported'=>true,'label'=>'No verificable'];
        $out=[];$code=1;@exec('schtasks /Query /TN "'.self::TASK_NAME.'" /XML 2>NUL',$out,$code);$xml=implode("\n",$out);
        if($code!==0||trim($xml)==='')return ['installed'=>false,'configured_current'=>false,'supported'=>true,'label'=>'No configurado'];
        $normalized=strtolower(str_replace('\\','/',html_entity_decode($xml,ENT_QUOTES|ENT_XML1,'UTF-8')));
        $hasBoot=str_contains($normalized,'<boottrigger>');
        $enabled=!str_contains($normalized,'<enabled>false</enabled>');
        $hasRunner=str_contains($normalized,'edge-command-worker-runner.cmd');
        $current=$hasBoot&&$enabled&&$hasRunner;
        return [
            'installed'=>true,
            'configured_current'=>$current,
            'supported'=>true,
            'label'=>$current?'Configurado y válido':'Registrado pero desactualizado',
            'boot_trigger'=>$hasBoot,
            'enabled'=>$enabled,
            'runner_action'=>$hasRunner,
        ];
    }

    public static function startDetached(): array
    {
        if(!InstallationMode::isEdge())throw new RuntimeException('El worker de comandos solo corresponde a Property Edge Agent.');
        $status=self::status();if(!empty($status['running']))return ['ok'=>true,'already_running'=>true]+$status;
        self::ensureRunDir();@unlink(self::stopPath());@unlink(self::supervisorStopPath());$php=PHP_BINARY?:'php';$script=base_path('tools/edge-command-worker.php');if(!is_file($script))throw new RuntimeException('No se encontró tools/edge-command-worker.php.');
        $method='direct';$launchCode=null;
        if(PHP_OS_FAMILY==='Windows'){
            $task=self::startupTaskStatus();
            if(!empty($task['configured_current'])&&function_exists('exec')){
                $out=[];$code=1;@exec('schtasks /Run /TN "'.self::TASK_NAME.'" 2>NUL',$out,$code);$launchCode=$code;
                if($code===0)$method='scheduled_task';
            }
            if($method!=='scheduled_task'){
                $log=self::bootstrapLogPath();
                $cmd='cmd.exe /D /S /C start "" /B "'.str_replace('"','""',$php).'" "'.str_replace('"','""',$script).'" >> "'.str_replace('"','""',$log).'" 2>&1';
                @pclose(@popen($cmd,'r'));$method='direct_fallback';
            }
        }else{
            $cmd='nohup '.escapeshellarg($php).' '.escapeshellarg($script).' >>'.escapeshellarg(self::bootstrapLogPath()).' 2>&1 &';@exec($cmd);
        }
        // El runner/tarea puede tardar un poco en crear el primer heartbeat. No declaremos
        // éxito por el mero hecho de haber enviado el comando de arranque.
        for($i=0;$i<12;$i++){
            usleep(250000);$now=self::status();if(!empty($now['running']))return ['ok'=>true,'started'=>true,'launch_method'=>$method,'launch_code'=>$launchCode]+$now;
        }
        return ['ok'=>false,'started'=>false,'launch_method'=>$method,'launch_code'=>$launchCode,'message'=>'Se solicitó iniciar el worker, pero no apareció heartbeat en 3 segundos. Revise storage/logs/edge-command-worker-bootstrap.log.']+self::status();
    }

    public static function requestStop(): void{self::ensureRunDir();@file_put_contents(self::stopPath(),date(DATE_ATOM));}
    public static function shouldStop(): bool{return is_file(self::stopPath());}

    public static function mark(array $extra=[]): void
    {
        self::ensureRunDir();$data=['pid'=>getmypid(),'heartbeat_at'=>date(DATE_ATOM),'heartbeat_unix'=>time(),'app_version'=>(string)config('app.version'),'role'=>(string)config('app.role')]+$extra;@file_put_contents(self::statusPath(),json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),LOCK_EX);
    }
}
