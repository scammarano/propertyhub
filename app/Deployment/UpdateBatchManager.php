<?php
declare(strict_types=1);

/**
 * Persistent one-click update orchestrator.
 *
 * The browser starts the operation once. The manager downloads every currently-known
 * applicable package (Core bridges plus local components), validates every signed SHA-256,
 * then installs exactly one step per HTTP request. After Core progression it refreshes the
 * manifest and appends components that only became eligible on the newer Core. This keeps
 * skipped connector/module updates pending instead of losing them behind a Core upgrade.
 */
final class UpdateBatchManager
{
    private PDO $pdo;
    private string $root;
    private string $baseDir;

    public function __construct(?PDO $pdo=null,string $root='')
    {
        $this->pdo=$pdo??Connection::pdo();
        $this->root=$root!==''?rtrim($root,'/\\'):base_path();
        $this->baseDir=$this->root.'/storage/updates/batches';
    }

    /** @return array<string,mixed> */
    public function create(array $repositoryResponse,?int $userId=null): array
    {
        if(InstallationMode::isCentral())throw new RuntimeException('El Property Hub Central no se actualiza mediante un lote local.');
        $manifest=(array)($repositoryResponse['manifest']??[]);
        $diag=(array)($manifest['diagnostics']??[]);
        $corePath=(array)($diag['core_update_path']??[]);
        if($corePath!==[]&&empty($diag['core_update_path_complete']))throw new RuntimeException('Central no tiene una ruta completa de actualización para este Core.');
        $current=(string)config('app.version','0.0.0');
        $steps=[];$expectedFrom=$current;$planned=[];
        foreach($corePath as $item){
            if(!is_array($item))continue;
            $version=trim((string)($item['version']??''));$releaseCode=trim((string)($item['release_code']??''));$sha=strtolower(trim((string)($item['package_hash_sha256']??'')));
            if($version===''||$releaseCode===''||version_compare($version,$current,'<=') )continue;
            if(!preg_match('/^[a-f0-9]{64}$/',$sha))throw new RuntimeException('La ruta firmada no contiene SHA-256 válido para '.$releaseCode.'. Vuelva a sincronizar Central con 0.14.61 o superior.');
            if(InstallationMode::isEdge()){
                $dist=(array)($repositoryResponse['node_distribution']??[]);
                $allowed=array_map('strval',(array)($dist['batch_allowed_release_codes']??$dist['allowed_release_codes']??[]));
                if($allowed!==[]&&!in_array($releaseCode,$allowed,true))throw new RuntimeException('El Property Node no autorizó '.$releaseCode.' para descarga anticipada de este Edge.');
            }
            $steps[]=[
                'index'=>count($steps),'release_code'=>$releaseCode,'component_code'=>'property-hub-core','component_type'=>'core',
                'display_name'=>(string)($item['display_name']??'Property Hub Core'),'version'=>$version,'from_version'=>$expectedFrom,
                'channel'=>(string)($item['channel']??$manifest['channel']??'stable'),'package_hash_sha256'=>$sha,
                'package_size_bytes'=>(int)($item['package_size_bytes']??0),'release_notes'=>(string)($item['release_notes']??''),
                'repair_bridge'=>!empty($item['repair_bridge']),'download_status'=>'pending','install_status'=>'pending','downloaded_bytes'=>0,
            ];
            $planned[$releaseCode]=true;$expectedFrom=$version;
        }
        $targetCore=$expectedFrom;
        $analysis=(new UpdateManager($this->pdo,$this->root))->analyzeManifest($repositoryResponse);
        foreach((array)($analysis['available']??[]) as $item){
            if(!is_array($item))continue;$componentCode=trim((string)($item['component_code']??''));if($componentCode===''||$componentCode==='property-hub-core')continue;
            $releaseCode=trim((string)($item['release_code']??''));$version=trim((string)($item['version']??''));$sha=strtolower(trim((string)($item['package_hash_sha256']??'')));
            if($releaseCode===''||$version===''||isset($planned[$releaseCode]))continue;
            if(!preg_match('/^[a-f0-9]{64}$/',$sha))throw new RuntimeException('El manifiesto firmado no contiene SHA-256 válido para '.$releaseCode.'.');
            $min=trim((string)($item['min_core_version']??''));$max=trim((string)($item['max_core_version']??''));
            // Si el lote también subirá el Core, solo preplanificamos componentes que
            // seguirán siendo compatibles al final de la ruta. Los demás se redescubren después.
            if($min!==''&&version_compare($targetCore,$min,'<'))continue;
            if($max!==''&&version_compare($targetCore,$max,'>'))continue;
            $steps[]=[
                'index'=>count($steps),'release_code'=>$releaseCode,'component_code'=>$componentCode,'component_type'=>(string)($item['component_type']??'component'),
                'display_name'=>(string)($item['display_name']??$componentCode),'version'=>$version,'from_version'=>(string)($item['installed_version']??''),
                'channel'=>(string)($item['channel']??$manifest['channel']??'stable'),'package_hash_sha256'=>$sha,'package_size_bytes'=>(int)($item['package_size_bytes']??0),
                'release_notes'=>(string)($item['release_notes']??''),'repair_bridge'=>false,'download_status'=>'pending','install_status'=>'pending','downloaded_bytes'=>0,
            ];
            $planned[$releaseCode]=true;
        }
        if($steps===[])throw new RuntimeException('No hay actualizaciones aplicables para instalar.');
        $id=self::uuid();$dir=$this->batchDir($id);$this->ensureDir($dir.'/packages');
        $state=[
            'schema'=>'2cam-property-update-batch-v1','id'=>$id,'status'=>'running','phase'=>'downloading','created_at'=>date(DATE_ATOM),'updated_at'=>date(DATE_ATOM),
            'started_by'=>$userId,'installation_uuid'=>(string)config('deployment.installation_uuid',''),'installation_code'=>(string)config('app.instance_code',''),
            'source_role'=>InstallationMode::current(),'initial_version'=>$current,'target_version'=>$targetCore,'planned_release_codes'=>array_keys($planned),'component_refresh_done'=>false,
            'total_steps'=>count($steps),'downloaded_steps'=>0,'installed_steps'=>0,'total_bytes'=>array_sum(array_map(static fn(array $s):int=>(int)$s['package_size_bytes'],$steps)),
            'downloaded_bytes'=>0,'current_message'=>'Plan creado. Se descargarán todos los paquetes aplicables conocidos antes de instalar.','last_error'=>null,'steps'=>$steps,
        ];
        $this->save($state);$_SESSION['active_update_batch_id']=$id;
        ErrorReporter::runtimeLog('update_batch_created',['batch_id'=>$id,'initial_version'=>$current,'target_version'=>$state['target_version'],'steps'=>array_map(static fn(array $s):string=>(string)$s['component_code'].':'.(string)$s['version'],$steps)]);
        return $state;
    }

    /** @return array<string,mixed> */
    public function processNext(string $id,?int $userId=null): array
    {
        @set_time_limit(0);@ignore_user_abort(true);
        $state=$this->load($id);if(($state['status']??'')!=='running')return $state+['action'=>'noop'];
        try{
            if(($state['phase']??'')==='refreshing_components'){
                $added=$this->appendFreshComponentSteps($state);
                if($added>0){$state['phase']='downloading';$state['current_message']='Se detectaron '.$added.' componente(s) adicional(es) que quedaron habilitados por la actualización del Core. Se descargarán automáticamente.';$this->save($state);return $this->publicState($state)+['action'=>'expanded','added_steps'=>$added];}
                $state['status']='completed';$state['phase']='completed';$state['completed_at']=date(DATE_ATOM);$state['current_message']='Actualización completa. Core y componentes aplicables quedaron conciliados.';$this->save($state);unset($_SESSION['active_update_batch_id']);
                return $this->publicState($state)+['action'=>'completed'];
            }
            if(($state['phase']??'')==='downloading'){
                foreach((array)$state['steps'] as $i=>$step){
                    if(!is_array($step)||($step['download_status']??'pending')==='downloaded')continue;
                    $path=$this->packagePath($id,(int)$i,(string)$step['release_code']);
                    $sourceLabel=InstallationMode::isEdge()?'Property Node':'Property Hub Central';
                    $state['current_message']='Descargando '.(string)$step['version'].' desde '.$sourceLabel.'…';$this->save($state);
                    if(InstallationMode::isEdge())$download=EdgeNodeClient::downloadUpdatePackage((string)$step['release_code'],$path);
                    else{
                        $conn=RepositoryClient::connectionForTransfer();
                        $download=RepositoryTransport::downloadPackage((string)($conn['endpoint_url']??''),(string)$step['release_code'],$path,[
                            'X-Installation-Key: '.(string)($conn['credential']??''),'X-Installation-Uuid: '.(string)config('deployment.installation_uuid','')
                        ],300);
                    }
                    $actual=strtolower((string)(hash_file('sha256',$path)?:''));$expected=strtolower((string)$step['package_hash_sha256']);
                    if($actual===''||!hash_equals($expected,$actual)){@unlink($path);throw new RuntimeException('SHA-256 inválido al descargar '.(string)$step['release_code'].'.');}
                    $bytes=(int)(filesize($path)?:0);$state['steps'][$i]['download_status']='downloaded';$state['steps'][$i]['downloaded_bytes']=$bytes;$state['steps'][$i]['downloaded_at']=date(DATE_ATOM);
                    $state['downloaded_steps']=(int)$state['downloaded_steps']+1;$state['downloaded_bytes']=(int)$state['downloaded_bytes']+$bytes;
                    if((int)$state['downloaded_steps']>=count((array)$state['steps'])){$state['phase']='installing';$state['current_message']='Todos los paquetes fueron descargados y verificados. Iniciando instalación secuencial.';}else{$state['current_message']='Descargado y verificado '.(string)$step['version'].'.';}
                    $this->save($state);
                    ErrorReporter::runtimeLog('update_batch_package_downloaded',['batch_id'=>$id,'release_code'=>$step['release_code'],'version'=>$step['version'],'bytes'=>$bytes,'mode'=>$download['mode']??'unknown']);
                    return $this->publicState($state)+['action'=>'downloaded','step'=>(int)$i];
                }
                $state['phase']='installing';$this->save($state);
            }

            foreach((array)$state['steps'] as $i=>$step){
                if(!is_array($step)||($step['install_status']??'pending')==='installed')continue;
                $componentCode=(string)($step['component_code']??'');$expectedFrom=(string)($step['from_version']??'');
                if($componentCode==='property-hub-core'){
                    $actualVersion=$this->diskVersion();
                    if($expectedFrom!==''&&!hash_equals($expectedFrom,$actualVersion))throw new RuntimeException('El lote esperaba Core '.$expectedFrom.' antes de '.(string)$step['version'].', pero la instalación reporta '.$actualVersion.'. Se detuvo para evitar saltos.');
                }
                $path=$this->packagePath($id,(int)$i,(string)$step['release_code']);if(!is_file($path))throw new RuntimeException('Falta el paquete predescargado '.(string)$step['release_code'].'.');
                $state['current_message']='Instalando '.(string)($step['display_name']??$step['component_code']).' '.(string)$step['version'].' ('.((int)$i+1).'/'.count((array)$state['steps']).')…';$this->save($state);
                $fresh=RepositoryClient::manifest();
                $manager=new UpdateManager(Connection::pdo(),$this->root);
                $result=$manager->installPreparedFromManifest($fresh,(string)$step['release_code'],$path,$userId);
                $state=$this->load($id);$state['steps'][$i]['install_status']='installed';$state['steps'][$i]['installed_at']=date(DATE_ATOM);$state['installed_steps']=(int)$state['installed_steps']+1;
                $state['current_message']='Instalado '.(string)($step['display_name']??$step['component_code']).' '.(string)$step['version'].'.';
                if((int)$state['installed_steps']>=count((array)$state['steps'])){
                    // La siguiente solicitud cargará el runtime/Core recién instalado y
                    // volverá a consultar Central para descubrir componentes que antes
                    // estaban bloqueados por min_core/dependencias. No se pierde un update
                    // por haber elegido actualizar primero el Core.
                    $state['phase']='refreshing_components';$state['current_message']='Ruta conocida instalada. Reconsultando componentes pendientes con el Core actualizado…';
                }
                $this->save($state);
                $needsFinalize=is_file($this->root.'/storage/updates/deferred/update-finalize.json');
                ErrorReporter::runtimeLog('update_batch_step_installed',['batch_id'=>$id,'release_code'=>$step['release_code'],'version'=>$step['version'],'needs_finalize'=>$needsFinalize,'result_to'=>$result['to_version']??'']);
                return $this->publicState($state)+['action'=>'installed','step'=>(int)$i,'needs_finalize'=>$needsFinalize];
            }
            $state['phase']='refreshing_components';$state['current_message']='Reconsultando componentes pendientes…';$this->save($state);
            return $this->publicState($state)+['action'=>'refreshing'];
        }catch(Throwable $e){
            $state=$this->load($id);$state['status']='failed';$state['phase']='failed';$state['last_error']=$e->getMessage();$state['current_message']='Lote detenido: '.$e->getMessage();$state['failed_at']=date(DATE_ATOM);$this->save($state);
            try{ErrorReporter::report($e,['operation'=>'update_batch','batch_id'=>$id]);}catch(Throwable){}
            return $this->publicState($state)+['action'=>'failed'];
        }
    }

    /** @return array<string,mixed> */
    public function resume(string $id): array
    {
        $state=$this->load($id);
        if(($state['status']??'')==='completed')return $state;
        $state['status']='running';$state['last_error']=null;$state['resumed_at']=date(DATE_ATOM);
        $allDownloaded=true;foreach((array)$state['steps'] as $step)if(is_array($step)&&($step['download_status']??'pending')!=='downloaded'){$allDownloaded=false;break;}
        $state['phase']=$allDownloaded?'installing':'downloading';$state['current_message']=$allDownloaded?'Reanudando instalación secuencial.':'Reanudando descarga de paquetes.';
        $this->save($state);$_SESSION['active_update_batch_id']=$id;
        return $state;
    }

    /** @return array<string,mixed>|null */
    public function latestActive(): ?array
    {
        if(!is_dir($this->baseDir))return null;$files=glob($this->baseDir.'/*/batch.json')?:[];usort($files,static fn(string $a,string $b):int=>(filemtime($b)?:0)<=>(filemtime($a)?:0));
        foreach($files as $file){$state=json_decode((string)@file_get_contents($file),true);if(is_array($state)&&in_array((string)($state['status']??''),['running','failed'],true))return $this->publicState($state);}return null;
    }

    /** @return array<string,mixed> */
    public function load(string $id): array
    {
        $id=$this->safeId($id);$path=$this->batchDir($id).'/batch.json';if(!is_file($path))throw new RuntimeException('Lote de actualización no encontrado.');$state=json_decode((string)file_get_contents($path),true);if(!is_array($state)||($state['schema']??'')!=='2cam-property-update-batch-v1')throw new RuntimeException('Estado de lote inválido.');
        if(!hash_equals((string)($state['installation_uuid']??''),(string)config('deployment.installation_uuid','')))throw new RuntimeException('Este lote pertenece a otra instalación.');return $state;
    }

    /** Appends non-Core releases that became applicable after earlier batch steps. */
    private function appendFreshComponentSteps(array &$state): int
    {
        $rounds=(int)($state['component_refresh_rounds']??0);if($rounds>=6)return 0;$state['component_refresh_rounds']=$rounds+1;
        $fresh=RepositoryClient::manifest();$analysis=(new UpdateManager(Connection::pdo(),$this->root))->analyzeManifest($fresh);
        $planned=[];foreach((array)($state['steps']??[]) as $step)if(is_array($step)&&trim((string)($step['release_code']??''))!=='')$planned[(string)$step['release_code']]=true;
        $added=0;
        foreach((array)($analysis['available']??[]) as $item){
            if(!is_array($item))continue;$componentCode=trim((string)($item['component_code']??''));if($componentCode===''||$componentCode==='property-hub-core')continue;
            $releaseCode=trim((string)($item['release_code']??''));$version=trim((string)($item['version']??''));$sha=strtolower(trim((string)($item['package_hash_sha256']??'')));
            if($releaseCode===''||$version===''||isset($planned[$releaseCode]))continue;if(!preg_match('/^[a-f0-9]{64}$/',$sha))continue;
            $index=count((array)$state['steps']);$state['steps'][]=[
                'index'=>$index,'release_code'=>$releaseCode,'component_code'=>$componentCode,'component_type'=>(string)($item['component_type']??'component'),
                'display_name'=>(string)($item['display_name']??$componentCode),'version'=>$version,'from_version'=>(string)($item['installed_version']??''),
                'channel'=>(string)($item['channel']??'stable'),'package_hash_sha256'=>$sha,'package_size_bytes'=>(int)($item['package_size_bytes']??0),
                'release_notes'=>(string)($item['release_notes']??''),'repair_bridge'=>false,'download_status'=>'pending','install_status'=>'pending','downloaded_bytes'=>0,
            ];
            $planned[$releaseCode]=true;$added++;$state['total_steps']=(int)($state['total_steps']??0)+1;$state['total_bytes']=(int)($state['total_bytes']??0)+(int)($item['package_size_bytes']??0);
        }
        $state['planned_release_codes']=array_keys($planned);return $added;
    }

    /** @return array<string,mixed> */
    public function publicState(array $state): array
    {
        return [
            'id'=>$state['id']??'','status'=>$state['status']??'','phase'=>$state['phase']??'','initial_version'=>$state['initial_version']??'',
            'target_version'=>$state['target_version']??'','total_steps'=>(int)($state['total_steps']??0),'downloaded_steps'=>(int)($state['downloaded_steps']??0),
            'installed_steps'=>(int)($state['installed_steps']??0),'total_bytes'=>(int)($state['total_bytes']??0),'downloaded_bytes'=>(int)($state['downloaded_bytes']??0),
            'current_message'=>$state['current_message']??'','last_error'=>$state['last_error']??null,'steps'=>$state['steps']??[],
        ];
    }

    private function save(array $state): void
    {
        $state['updated_at']=date(DATE_ATOM);$dir=$this->batchDir((string)$state['id']);$this->ensureDir($dir);$path=$dir.'/batch.json';$tmp=$path.'.tmp';
        $json=json_encode($state,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);if(@file_put_contents($tmp,$json,LOCK_EX)===false)throw new RuntimeException('No fue posible guardar el estado del lote de actualización.');@chmod($tmp,0640);if(is_file($path))@unlink($path);if(!@rename($tmp,$path)){@unlink($tmp);throw new RuntimeException('No fue posible activar el estado del lote de actualización.');}
    }
    private function packagePath(string $id,int $index,string $releaseCode): string{$safe=preg_replace('/[^A-Za-z0-9._-]/','_',$releaseCode)?:'release';return $this->batchDir($id).'/packages/'.str_pad((string)$index,3,'0',STR_PAD_LEFT).'-'.$safe.'.zip';}
    private function batchDir(string $id): string{return $this->baseDir.'/'.$this->safeId($id);}
    private function safeId(string $id): string{if(!preg_match('/^[a-f0-9-]{36}$/',$id))throw new InvalidArgumentException('Identificador de lote inválido.');return $id;}
    private function ensureDir(string $dir): void{if(!is_dir($dir)&&!@mkdir($dir,0775,true)&&!is_dir($dir))throw new RuntimeException('No fue posible crear '.$dir.'.');}
    private function diskVersion(): string{$path=$this->root.'/VERSION.json';$v=is_file($path)?json_decode((string)@file_get_contents($path),true):null;return is_array($v)?trim((string)($v['version']??'')):(string)config('app.version','');}
    private static function uuid(): string{$d=random_bytes(16);$d[6]=chr((ord($d[6])&0x0f)|0x40);$d[8]=chr((ord($d[8])&0x3f)|0x80);$h=bin2hex($d);return substr($h,0,8).'-'.substr($h,8,4).'-'.substr($h,12,4).'-'.substr($h,16,4).'-'.substr($h,20);}
}
