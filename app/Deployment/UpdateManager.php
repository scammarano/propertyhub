<?php
declare(strict_types=1);

/**
 * Property Hub update engine.
 *
 * Responsibilities:
 * - compare signed repository manifests with locally installed components;
 * - download packages from the immediate trusted source (Node -> Central, Edge -> Node);
 * - validate SHA-256 and ZIP safety;
 * - create an internal rollback snapshot before touching application files;
 * - create a .phbackup PRE_UPDATE when BackupManager is available;
 * - apply files and registered migrations;
 * - update component/version metadata;
 * - rollback files + database automatically when installation fails.
 */
final class UpdateManager
{
    private PDO $pdo;
    private string $root;
    private ?string $preparedPackagePath = null;

    public function __construct(?PDO $pdo=null, string $root='')
    {
        $this->pdo = $pdo ?? Connection::pdo();
        $this->root = $root !== '' ? rtrim($root, '/\\') : base_path();
    }

    /**
     * @return array{releases:list<array<string,mixed>>,available:list<array<string,mixed>>,current:array<string,string>}
     */
    public function analyzeManifest(array $repositoryResponse): array
    {
        $manifest = (array)($repositoryResponse['manifest'] ?? []);
        $releases = array_values(array_filter((array)($manifest['releases'] ?? []), 'is_array'));
        $distribution=(array)($repositoryResponse['node_distribution']??[]);
        $hasNodeAllowlist=array_key_exists('allowed_release_codes',$distribution);
        $allowed=array_values(array_filter(array_map('strval',(array)($distribution['allowed_release_codes']??[]))));
        if(InstallationMode::isEdge()&&$hasNodeAllowlist){$allow=array_fill_keys($allowed,true);$releases=array_values(array_filter($releases,static fn(array $r):bool=>isset($allow[(string)($r['release_code']??'')])));}
        $installed = $this->installedVersions();
        $availableByComponent = [];

        foreach ($releases as $release) {
            $code = trim((string)($release['component_code'] ?? ''));
            $version = trim((string)($release['version'] ?? ''));
            if ($code === '' || $version === '') continue;

            $current = $installed[$code] ?? null;
            $dependencyIssues=[];
            foreach((array)($release['dependencies']??[]) as $dependency){
                if(!is_array($dependency)||empty($dependency['required']))continue;
                $depCode=trim((string)($dependency['component_code']??''));$constraint=trim((string)($dependency['version_constraint']??''));if($depCode===''||$constraint==='')continue;
                $depVersion=$installed[$depCode]??null;
                if($depVersion===null||!DesiredConnectorPolicy::satisfiesConstraint((string)$depVersion,$constraint))$dependencyIssues[]=['component_code'=>$depCode,'required'=>$constraint,'installed'=>$depVersion];
            }
            $isAvailable = ($current === null || version_compare($version, $current, '>')) && $dependencyIssues===[];
            $release['installed_version'] = $current;
            $release['update_available'] = $isAvailable;
            $release['is_new_component'] = $current === null;
            $release['dependency_issues']=$dependencyIssues;

            if (!$isAvailable) continue;
            if (!isset($availableByComponent[$code]) || version_compare($version, (string)$availableByComponent[$code]['version'], '>')) {
                $availableByComponent[$code] = $release;
            }
        }

        return [
            'releases'=>$releases,
            'available'=>array_values($availableByComponent),
            'current'=>$installed,
        ];
    }

    /**
     * Reconciles Central's Edge-distribution candidates with the inventories actually
     * reported to this Property Node. Central can know that a release is eligible for
     * Edge distribution, but only the Node knows whether each paired Edge already has it.
     *
     * @return array{candidates:list<array<string,mixed>>,pending:list<array<string,mixed>>,current:list<array<string,mixed>>,pending_count:int,current_count:int}
     */
    public static function reconcileDownstreamEdgeReleases(array $releases,array $agents): array
    {
        $candidates=[];$pending=[];$current=[];
        foreach($releases as $release){
            if(!is_array($release))continue;
            $code=trim((string)($release['component_code']??''));$target=trim((string)($release['version']??''));
            if($code===''||$target==='')continue;
            $states=[];$pendingAgents=0;$applicableAgents=0;
            foreach($agents as $agent){
                if(!is_array($agent))continue;
                $agentStatus=strtolower(trim((string)($agent['status']??'active')));
                if(in_array($agentStatus,['retired','disabled'],true))continue;
                $applicableAgents++;
                $meta=json_decode((string)($agent['metadata_json']??''),true);if(!is_array($meta))$meta=[];
                $installedMap=is_array($meta['installed_components']??null)?$meta['installed_components']:[];
                $installed=trim((string)($installedMap[$code]??''));
                if($installed===''&&str_starts_with($code,'connector-'))$installed=trim((string)($installedMap[substr($code,10)]??''));
                $isPending=$installed===''||version_compare($installed,$target,'<');
                if($isPending)$pendingAgents++;
                $states[]=[
                    'agent_code'=>(string)($agent['agent_code']??''),'agent_name'=>(string)($agent['agent_name']??''),
                    'agent_status'=>$agentStatus,'installed_version'=>$installed,'target_version'=>$target,
                    'pending'=>$isPending,'status'=>$isPending?'pending':'current',
                ];
            }
            $release['edge_states']=$states;$release['applicable_agent_count']=$applicableAgents;$release['pending_agent_count']=$pendingAgents;
            $release['up_to_date_agent_count']=max(0,$applicableAgents-$pendingAgents);
            // No paired active Edge yet means the release remains pending for deployment.
            $isPending=$applicableAgents===0||$pendingAgents>0;$release['edge_update_state']=$isPending?'pending':'current';
            $candidates[]=$release;if($isPending)$pending[]=$release;else$current[]=$release;
        }
        return ['candidates'=>$candidates,'pending'=>$pending,'current'=>$current,'pending_count'=>count($pending),'current_count'=>count($current)];
    }

    /** @return array<string,string> */
    public function installedVersions(): array
    {
        $result = ['property-hub-core'=>(string)config('app.version','0.0.0')];
        try {
            $installationId = $this->installationId();
            $stmt = $this->pdo->prepare("SELECT component_code,installed_version FROM bh_installed_components WHERE installation_id=? AND status<>'removed'");
            $stmt->execute([$installationId]);
            foreach ($stmt->fetchAll() ?: [] as $row) {
                $result[(string)$row['component_code']] = (string)$row['installed_version'];
            }
        } catch (Throwable $e) {
            $this->reportSafely($e, ['operation'=>'update_installed_versions']);
        }
        return $result;
    }

    /** Installs a release from a package already downloaded and SHA-validated by UpdateBatchManager. */
    public function installPreparedFromManifest(array $repositoryResponse,string $releaseCode,string $preparedPackagePath,?int $userId=null): array
    {
        if(!is_file($preparedPackagePath))throw new RuntimeException('El paquete preparado para el lote ya no existe.');
        $this->preparedPackagePath=$preparedPackagePath;
        try{return $this->installFromManifest($repositoryResponse,$releaseCode,$userId);}
        finally{$this->preparedPackagePath=null;}
    }

    /**
     * Installs one release already returned by a verified RepositoryClient::manifest() call.
     *
     * @return array<string,mixed>
     */
    public function installFromManifest(array $repositoryResponse, string $releaseCode, ?int $userId=null): array
    {
        // Update installation can legitimately exceed PHP's default execution limit on
        // shared hosting while backup/snapshot work is performed. The web server may
        // still enforce its own hard timeout, but PHP must not terminate the operation.
        @set_time_limit(0);
        @ignore_user_abort(true);

        if (InstallationMode::isCentral()) {
            throw new RuntimeException('El Property Hub Central no se actualiza desde sí mismo. Publique la versión para los nodos y actualice el Central por el paquete oficial.');
        }
        if (!class_exists('ZipArchive')) throw new RuntimeException('La extensión PHP ZIP es obligatoria para instalar actualizaciones.');
        if (!extension_loaded('curl')) throw new RuntimeException('La extensión PHP cURL es obligatoria para descargar actualizaciones.');

        $analysis = $this->analyzeManifest($repositoryResponse);
        $release = null;
        foreach ($analysis['available'] as $candidate) {
            if (hash_equals((string)($candidate['release_code'] ?? ''), $releaseCode)) {
                $release = $candidate;
                break;
            }
        }
        if (!is_array($release)) throw new RuntimeException('La versión seleccionada ya no está disponible o no es superior a la instalada. Vuelva a buscar actualizaciones.');

        $componentCode = (string)$release['component_code'];
        $toVersion = (string)$release['version'];
        $fromVersion = $analysis['current'][$componentCode] ?? null;
        $jobUuid = self::uuid();
        $jobId = $this->createJob($jobUuid, $release, $fromVersion);
        $downloadDir = $this->root.'/storage/updates/downloads';
        $stagingDir = $this->root.'/storage/updates/staging/'.$jobUuid;
        $rollbackDir = $this->root.'/storage/updates/rollback/'.$jobUuid;
        $packagePath = $downloadDir.'/'.$jobUuid.'.zip';
        $preUpdateBackup = null;
        $snapshot = null;
        $payloadApplyStarted = false;
        $edgeWorkerPaused = false;
        $edgeWorkerRestartWarning = null;

        $this->ensureDir($downloadDir);
        $this->ensureDir(dirname($stagingDir));
        $this->ensureDir(dirname($rollbackDir));

        try {
            $sourceLabel=InstallationMode::isEdge()?'Property Node':'Property Hub Central';
            $this->updateJob($jobId, 'downloading', 10, 'Descargando paquete firmado desde '.$sourceLabel.'.');
            if($this->preparedPackagePath!==null){
                if(!@copy($this->preparedPackagePath,$packagePath))throw new RuntimeException('No fue posible activar el paquete predescargado del lote.');
                $download=['mode'=>'batch_preloaded','bytes'=>(int)(filesize($packagePath)?:0),'sha256'=>(string)(hash_file('sha256',$packagePath)?:'')];
            }elseif(InstallationMode::isEdge()){
                $download=EdgeNodeClient::downloadUpdatePackage((string)$release['release_code'],$packagePath);
            }else{
                $centralConnection=RepositoryClient::connectionForTransfer();
                $download=RepositoryTransport::downloadPackage(
                    (string)($centralConnection['endpoint_url']??''),(string)$release['release_code'],$packagePath,
                    ['X-Installation-Key: '.(string)($centralConnection['credential']??''),'X-Installation-Uuid: '.(string)config('deployment.installation_uuid','')],300
                );
            }

            $this->updateJob($jobId, 'downloaded', 25, 'Paquete descargado por '.$download['mode'].'.', ['package_path'=>$packagePath]);
            $expectedHash = strtolower(trim((string)($release['package_hash_sha256'] ?? '')));
            $actualHash = strtolower((string)(hash_file('sha256', $packagePath) ?: ''));
            if ($expectedHash === '' || $actualHash === '' || !hash_equals($expectedHash, $actualHash)) {
                throw new RuntimeException('SHA-256 del paquete no coincide con el manifiesto firmado. La actualización fue cancelada.');
            }

            $this->validateZip($packagePath);
            $this->removeTree($stagingDir);
            $this->ensureDir($stagingDir);
            $zip = new ZipArchive();
            if ($zip->open($packagePath) !== true) throw new RuntimeException('No fue posible abrir el paquete ZIP descargado.');
            if (!$zip->extractTo($stagingDir)) { $zip->close(); throw new RuntimeException('No fue posible extraer el paquete en staging.'); }
            $zip->close();
            $payloadRoot = $this->resolvePayloadRoot($stagingDir);
            $packageMeta = $this->readPackageMetadata($payloadRoot);
            $this->validatePackageMetadata($packageMeta, $release);
            $this->updateJob($jobId, 'validated', 35, 'Firma de manifiesto, SHA-256, ZIP y metadatos validados.');

            // Internal rollback snapshot is mandatory, even for nodes upgraded from versions
            // that do not yet have the complete .phbackup subsystem.
            $this->updateJob($jobId, 'backing_up', 42, 'Creando punto de reversión interno.');
            $snapshot = $this->createRollbackSnapshot($rollbackDir, $payloadRoot, $packageMeta);

            // From 0.10.2 onward we also produce the normal encrypted PRE_UPDATE backup.
            if (class_exists('BackupManager')) {
                try {
                    $manager = new BackupManager($this->pdo, $this->root);
                    if ($manager->recoveryKeyExists()) {
                        $preUpdateBackup = $manager->create('pre_update', $userId, ['local_only'=>true]);
                    }
                } catch (Throwable $backupError) {
                    // A failed complete backup must stop a normal update. The rollback snapshot
                    // remains available, but we do not silently continue.
                    throw new RuntimeException('No fue posible crear el respaldo PRE_UPDATE: '.$backupError->getMessage(), 0, $backupError);
                }
            }

            // En Windows el worker Edge es persistente y su script principal puede quedar
            // bloqueado mientras PHP lo ejecuta. Sólo lo pausamos si el payload realmente cambia
            // archivos de su runtime; archivos byte-idénticos no justifican detener nada.
            $edgeWorkerPaused = $this->pauseEdgeWorkerForUpdateIfNeeded($payloadRoot, $packageMeta);

            $this->updateJob($jobId, 'installing', 55, 'Aplicando archivos del componente.');
            // Desde este punto un fallo sí puede haber dejado archivos parcialmente aplicados.
            $payloadApplyStarted = true;
            $applied = $this->applyPayload($payloadRoot, $packageMeta);

            $migrationResults = [];
            $migrationNames = $this->effectiveMigrationNames($packageMeta);
            if ($migrationNames !== []) {
                $migrationFiles = [];
                foreach ($migrationNames as $migration) {
                    $path = $this->root.'/'.ltrim(str_replace('\\','/',$migration),'/');
                    if (!is_file($path)) throw new RuntimeException('La actualización declara una migración que no fue instalada: '.$migration);
                    $migrationFiles[] = $path;
                }
                $migrationResults = (new MigrationRunner($this->pdo))->run($migrationFiles);
            }

            $this->updateComponentMetadata($release);
            // Connector manifests are versioned application metadata. Refresh them after
            // every installed component so a Core update with no new SQL migration still
            // exposes the current connector versions (for example Vimar 0.3.0).
            try { (new CatalogSeeder($this->pdo,$this->root))->seedConnectorDefinitions(); }
            catch (Throwable $catalogError) { $this->reportSafely($catalogError,['operation'=>'update_connector_catalog_refresh','release_code'=>$releaseCode]); }
            $this->healthCheck($packageMeta, $release);
            if ($edgeWorkerPaused) {
                try {
                    $restart = $this->resumeEdgeWorkerAfterUpdate();
                    if (empty($restart['running'])) {
                        $edgeWorkerRestartWarning = (string)($restart['message'] ?? 'El worker Edge no confirmó heartbeat después de la actualización.');
                        $this->reportSafely(new RuntimeException($edgeWorkerRestartWarning), ['operation'=>'update_edge_worker_restart','release_code'=>$releaseCode,'job_uuid'=>$jobUuid]);
                    }
                } catch (Throwable $workerRestartError) {
                    $edgeWorkerRestartWarning = $workerRestartError->getMessage();
                    $this->reportSafely($workerRestartError, ['operation'=>'update_edge_worker_restart','release_code'=>$releaseCode,'job_uuid'=>$jobUuid]);
                }
            }
            $installMessage = 'Actualización instalada correctamente.'.($edgeWorkerRestartWarning ? ' Advertencia worker Edge: '.$edgeWorkerRestartWarning : '');
            $this->updateJob($jobId, 'installed', 100, $installMessage, [
                'package_path'=>$packagePath,
                'backup_path'=>is_array($preUpdateBackup)?(string)($preUpdateBackup['path']??''):(string)($snapshot['database']??''),
                'details'=>json_encode(['applied_files'=>$applied,'migrations'=>$migrationResults,'pre_update_backup'=>$preUpdateBackup], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),
            ]);

            $this->removeTree($stagingDir);
            return [
                'job_id'=>$jobId,
                'job_uuid'=>$jobUuid,
                'release'=>$release,
                'from_version'=>$fromVersion,
                'to_version'=>$toVersion,
                'applied_files'=>$applied,
                'migrations'=>$migrationResults,
                'pre_update_backup'=>$preUpdateBackup,
                'rollback_snapshot'=>$snapshot,
                'download'=>$download,
                'edge_worker_restart_warning'=>$edgeWorkerRestartWarning,
            ];
        } catch (Throwable $e) {
            $rollbackMessage = '';
            if (is_array($snapshot) && $payloadApplyStarted) {
                try {
                    $this->rollbackSnapshot($snapshot);
                    $rollbackMessage = ' Se restauraron automáticamente archivos y base de datos al estado previo.';
                } catch (Throwable $rollbackError) {
                    $rollbackMessage = ' ATENCIÓN: el rollback automático también falló: '.$rollbackError->getMessage();
                    $this->reportSafely($rollbackError, ['operation'=>'update_rollback','release_code'=>$releaseCode,'job_uuid'=>$jobUuid]);
                }
            } elseif (is_array($snapshot)) {
                // El snapshot puede existir aunque PRE_UPDATE falle. Si todavía no comenzó applyPayload,
                // no se tocó la instalación y un rollback sólo puede introducir un segundo fallo innecesario.
                $rollbackMessage = ' La instalación no fue modificada; no se requirió rollback.';
            }
            if ($edgeWorkerPaused) {
                try { $this->resumeEdgeWorkerAfterUpdate(); }
                catch (Throwable $resumeError) {
                    $rollbackMessage .= ' Advertencia: no se pudo reiniciar el worker Edge después del fallo/rollback: '.$resumeError->getMessage();
                    $this->reportSafely($resumeError, ['operation'=>'update_edge_worker_restart_after_failure','release_code'=>$releaseCode,'job_uuid'=>$jobUuid]);
                }
            }
            try { $this->updateJob($jobId, 'failed', 100, $e->getMessage().$rollbackMessage); } catch (Throwable $ignore) {}
            $this->reportSafely($e, ['operation'=>'update_install','release_code'=>$releaseCode,'job_uuid'=>$jobUuid,'rollback'=>$rollbackMessage]);
            throw new RuntimeException('La actualización no pudo completarse. '.$e->getMessage().$rollbackMessage, 0, $e);
        }
    }

    /** @return list<array<string,mixed>> */
    public function jobs(int $limit=20): array
    {
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM bh_update_jobs WHERE installation_id=? ORDER BY created_at DESC LIMIT '.max(1,min(100,$limit)));
            $stmt->execute([$this->installationId()]);
            return $stmt->fetchAll() ?: [];
        } catch (Throwable $e) { return []; }
    }

    private function createJob(string $uuid, array $release, ?string $fromVersion): int
    {
        $sourceId = null;
        try {
            $stmt=$this->pdo->prepare("SELECT id FROM bh_update_sources WHERE installation_id=? AND enabled=1 ORDER BY priority,id LIMIT 1");
            $stmt->execute([$this->installationId()]);
            $sourceId=(int)($stmt->fetchColumn()?:0) ?: null;
        } catch(Throwable $e) {}
        $stmt=$this->pdo->prepare("INSERT INTO bh_update_jobs (public_uuid,installation_id,source_id,release_code,component_code,from_version,to_version,manifest_json,package_hash_sha256,signature,status,progress_percent,details,started_at) VALUES (?,?,?,?,?,?,?,?,?,?,'checking',1,?,NOW())");
        $stmt->execute([
            $uuid,$this->installationId(),$sourceId,(string)$release['release_code'],(string)$release['component_code'],$fromVersion,(string)$release['version'],
            json_encode($release,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),(string)($release['package_hash_sha256']??''),(string)($release['signature']??''),'Validando versión seleccionada.'
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    private function updateJob(int $jobId,string $status,int $progress,string $details,array $extra=[]): void
    {
        $allowed=['checking','downloading','downloaded','validated','backing_up','installing','installed','rolling_back','rolled_back','failed','cancelled'];
        if(!in_array($status,$allowed,true))$status='failed';
        $sets=['status=?','progress_percent=?','details=?'];$params=[$status,max(0,min(100,$progress)),$details];
        foreach(['package_path','backup_path'] as $field)if(array_key_exists($field,$extra)){$sets[]=$field.'=?';$params[]=$extra[$field];}
        if(isset($extra['details'])){$sets[2]='details=?';$params[2]=(string)$extra['details'];}
        if(in_array($status,['installed','failed','rolled_back','cancelled'],true))$sets[]='finished_at=NOW()';
        $params[]=$jobId;
        $this->pdo->prepare('UPDATE bh_update_jobs SET '.implode(',',$sets).' WHERE id=?')->execute($params);
    }

    /** @return array<string,mixed> */
    private function createRollbackSnapshot(string $rollbackDir,string $payloadRoot,array $packageMeta): array
    {
        $this->removeTree($rollbackDir);$this->ensureDir($rollbackDir);
        $dbPath=$rollbackDir.'/database.sql';
        if(!class_exists('DatabaseBackup'))throw new RuntimeException('El motor de respaldo de base de datos no está disponible para crear el punto de reversión.');
        $dbStats=DatabaseBackup::dump($this->pdo,$dbPath);

        $filesZip=$rollbackDir.'/application-files.zip';
        $zip=new ZipArchive();if($zip->open($filesZip,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)throw new RuntimeException('No fue posible crear el respaldo interno de archivos.');
        $existing=[];
        foreach($this->applicationFiles() as [$full,$relative]){$zip->addFile($full,$relative);$existing[$relative]=true;}
        $zip->close();
        $targets=$this->payloadTargets($payloadRoot,$packageMeta);
        $snapshot=['root'=>$rollbackDir,'database'=>$dbPath,'database_stats'=>$dbStats,'files'=>$filesZip,'existing_files'=>array_keys($existing),'payload_targets'=>$targets,'created_at'=>date(DATE_ATOM)];
        file_put_contents($rollbackDir.'/snapshot.json',json_encode($snapshot,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),LOCK_EX);
        return $snapshot;
    }

    private function rollbackSnapshot(array $snapshot): void
    {
        $existing=array_fill_keys((array)($snapshot['existing_files']??[]),true);
        foreach((array)($snapshot['payload_targets']??[]) as $relative){
            if(isset($existing[$relative]))continue;
            $target=$this->root.'/'.str_replace('/',DIRECTORY_SEPARATOR,(string)$relative);
            if(is_file($target))@unlink($target);
        }
        $files=(string)($snapshot['files']??'');
        if(!is_file($files))throw new RuntimeException('No se encontró el ZIP interno de archivos para rollback.');
        $zip=new ZipArchive();if($zip->open($files)!==true)throw new RuntimeException('No se pudo abrir el ZIP interno de rollback.');
        try {
            for($i=0;$i<$zip->numFiles;$i++){
                $name=(string)$zip->getNameIndex($i);
                if($name===''||str_ends_with($name,'/'))continue;
                $relative=$this->safeRelative(str_replace('\\','/',$name));
                if($relative==='')continue;
                $contents=$zip->getFromIndex($i);
                if($contents===false)throw new RuntimeException('No se pudo leer '.$relative.' desde el ZIP de rollback.');
                if($this->mustDeferLiveTarget($relative)){
                    $this->queueDeferredContents($contents,$relative,'rollback');
                    continue;
                }
                $this->replaceTargetContents($relative,$contents,'rollback');
            }
        } finally {
            $zip->close();
        }
        $db=(string)($snapshot['database']??'');if(!is_file($db))throw new RuntimeException('No se encontró database.sql del rollback.');
        try {
            DatabaseBackup::restore($this->pdo,$db);
        } catch (Throwable $restoreError) {
            // A failed migration can poison the current PDO connection with an unread
            // MySQL result set (error 2014). Rollback must not depend on that same
            // connection: retry once with a clean, buffered connection.
            $message=strtolower($restoreError->getMessage());
            if(!str_contains($message,'2014')&&!str_contains($message,'unbuffered'))throw $restoreError;
            $fresh=Connection::fresh();
            DatabaseBackup::restore($fresh,$db);
            $this->pdo=$fresh;
        }
    }

    /** @return int number of files copied */
    private function applyPayload(string $payloadRoot,array $packageMeta): int
    {
        $targets=$this->payloadTargets($payloadRoot,$packageMeta);$count=0;
        foreach($targets as $relative){
            $source=$payloadRoot.'/'.str_replace('/',DIRECTORY_SEPARATOR,$relative);
            if(!is_file($source))continue;
            $target=$this->root.'/'.str_replace('/',DIRECTORY_SEPARATOR,$relative);
            // No reemplazar un archivo que ya es exactamente el mismo. Además de reducir I/O,
            // esto evita convertir archivos ejecutados por procesos persistentes en falsos fallos
            // de actualización bajo Windows.
            if(is_file($target)){
                $sourceHash=@hash_file('sha256',$source);$targetHash=@hash_file('sha256',$target);
                if(is_string($sourceHash)&&$sourceHash!==''&&is_string($targetHash)&&$targetHash!==''&&hash_equals($sourceHash,$targetHash))continue;
            }
            if($this->mustDeferLiveTarget($relative)){
                $this->queueDeferredFile($source,$relative,'update');
                $count++;
                continue;
            }
            $this->ensureDir(dirname($target));
            $tmp=$target.'.phupdate.'.bin2hex(random_bytes(4));
            if(!@copy($source,$tmp))throw new RuntimeException('No se pudo preparar el archivo '.$relative.'.');
            @chmod($tmp,0644);
            if(is_file($target)&&!@unlink($target)){
                // Some Windows web-server combinations keep the active entry point open.
                // If we discover another live target in the future, defer it rather than
                // converting a harmless file lock into a failed update.
                @unlink($tmp);
                if($this->canDeferTarget($relative)){
                    $this->queueDeferredFile($source,$relative,'update-lock');
                    $count++;
                    continue;
                }
                throw new RuntimeException('No se pudo reemplazar '.$relative.'. Revise permisos del servidor web.');
            }
            if(!@rename($tmp,$target)){@unlink($tmp);throw new RuntimeException('No se pudo activar '.$relative.'.');}
            $count++;
        }
        foreach((array)($packageMeta['delete_paths']??[]) as $relative){
            $relative=$this->safeRelative((string)$relative);
            if($relative===''||$this->isProtectedPath($relative))throw new RuntimeException('El paquete intentó eliminar una ruta protegida: '.$relative);
            $target=$this->root.'/'.str_replace('/',DIRECTORY_SEPARATOR,$relative);
            if(is_file($target)&&!@unlink($target))throw new RuntimeException('No fue posible eliminar el archivo obsoleto '.$relative.'.');
        }
        return $count;
    }

    private function pauseEdgeWorkerForUpdateIfNeeded(string $payloadRoot,array $packageMeta): bool
    {
        if(PHP_OS_FAMILY!=='Windows'||!InstallationMode::isEdge())return false;
        $sensitive=[
            'tools/edge-command-worker.php',
            'tools/edge-command-worker-runner.cmd',
            'tools/install-edge-command-worker.bat',
            'app/Edge/EdgeCommandWorkerService.php',
        ];
        $needsPause=false;
        foreach($this->payloadTargets($payloadRoot,$packageMeta) as $relative){
            if(!in_array($relative,$sensitive,true))continue;
            $source=$payloadRoot.'/'.str_replace('/',DIRECTORY_SEPARATOR,$relative);
            $target=$this->root.'/'.str_replace('/',DIRECTORY_SEPARATOR,$relative);
            if(!is_file($source))continue;
            if(is_file($target)){
                $a=@hash_file('sha256',$source);$b=@hash_file('sha256',$target);
                if(is_string($a)&&$a!==''&&is_string($b)&&$b!==''&&hash_equals($a,$b))continue;
            }
            $needsPause=true;break;
        }
        if(!$needsPause)return false;
        $runDir=$this->root.'/storage/run';$this->ensureDir($runDir);
        @file_put_contents($runDir.'/edge-command-worker-supervisor.stop',date(DATE_ATOM),LOCK_EX);
        @file_put_contents($runDir.'/edge-command-worker.stop',date(DATE_ATOM),LOCK_EX);
        if($this->waitForEdgeWorkerLockRelease(15))return true;
        if(function_exists('exec')){
            $out=[];$code=1;@exec('schtasks /End /TN "PropertyHub Edge Command Worker" 2>NUL',$out,$code);
            if($this->waitForEdgeWorkerLockRelease(4))return true;
        }
        throw new RuntimeException('No fue posible pausar el worker Edge para actualizar sus archivos. El updater no modificó el payload.');
    }

    private function waitForEdgeWorkerLockRelease(int $seconds): bool
    {
        $lockPath=$this->root.'/storage/run/edge-command-worker.lock';
        $deadline=microtime(true)+max(1,$seconds);
        do{
            $h=@fopen($lockPath,'c+');
            if($h!==false){
                $free=@flock($h,LOCK_EX|LOCK_NB);
                if($free){@flock($h,LOCK_UN);@fclose($h);return true;}
                @fclose($h);
            }
            usleep(250000);
        }while(microtime(true)<$deadline);
        return false;
    }

    /** @return array<string,mixed> */
    private function resumeEdgeWorkerAfterUpdate(): array
    {
        if(PHP_OS_FAMILY!=='Windows'||!InstallationMode::isEdge())return ['running'=>false,'message'=>'No aplica'];
        @unlink($this->root.'/storage/run/edge-command-worker.stop');
        @unlink($this->root.'/storage/run/edge-command-worker-supervisor.stop');
        if(class_exists('EdgeCommandWorkerService')){
            $r=EdgeCommandWorkerService::startDetached();
            return is_array($r)?$r:['running'=>false,'message'=>'El servicio no devolvió estado.'];
        }
        if(function_exists('exec')){
            $out=[];$code=1;@exec('schtasks /Run /TN "PropertyHub Edge Command Worker" 2>NUL',$out,$code);
            if($code===0)return ['running'=>true,'launch_method'=>'scheduled_task'];
        }
        return ['running'=>false,'message'=>'No se pudo solicitar el reinicio del worker Edge.'];
    }

    private function canDeferTarget(string $relative): bool
    {
        $p=strtolower(str_replace('\\','/',$relative));
        return $p==='public/index.php';
    }

    private function mustDeferLiveTarget(string $relative): bool
    {
        return PHP_OS_FAMILY==='Windows' && $this->canDeferTarget($relative);
    }

    private function queueDeferredFile(string $source,string $relative,string $reason): void
    {
        $contents=@file_get_contents($source);
        if($contents===false)throw new RuntimeException('No se pudo preparar el reemplazo diferido de '.$relative.'.');
        $this->queueDeferredContents($contents,$relative,$reason);
    }

    private function queueDeferredContents(string $contents,string $relative,string $reason): void
    {
        $relative=$this->safeRelative($relative);
        if($relative===''||!$this->canDeferTarget($relative))throw new RuntimeException('Ruta no admitida para reemplazo diferido: '.$relative);
        $dir=$this->root.'/storage/updates/deferred';$this->ensureDir($dir);
        $sourceRelative='storage/updates/deferred/'.str_replace('/','-',strtolower($relative)).'.new';
        $source=$this->root.'/'.str_replace('/',DIRECTORY_SEPARATOR,$sourceRelative);
        if(@file_put_contents($source,$contents,LOCK_EX)===false)throw new RuntimeException('No se pudo guardar el reemplazo diferido de '.$relative.'.');
        @chmod($source,0644);
        $hash=hash_file('sha256',$source);if($hash===false)throw new RuntimeException('No se pudo verificar el reemplazo diferido de '.$relative.'.');
        $markerPath=$dir.'/update-finalize.json';
        $marker=['schema'=>'2cam-property-deferred-update-v1','created_at'=>date(DATE_ATOM),'replacements'=>[]];
        if(is_file($markerPath)){
            $loaded=json_decode((string)@file_get_contents($markerPath),true);
            if(is_array($loaded)&&($loaded['schema']??'')==='2cam-property-deferred-update-v1')$marker=$loaded;
        }
        $replacements=[];
        foreach((array)($marker['replacements']??[]) as $item){
            if(is_array($item)&&($item['target']??'')!==$relative)$replacements[]=$item;
        }
        $replacements[]=['source'=>$sourceRelative,'target'=>$relative,'sha256'=>$hash,'reason'=>$reason];
        $marker['replacements']=$replacements;$marker['updated_at']=date(DATE_ATOM);
        if(@file_put_contents($markerPath,json_encode($marker,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),LOCK_EX)===false){
            throw new RuntimeException('No se pudo registrar la finalización diferida de la actualización.');
        }
    }

    private function replaceTargetContents(string $relative,string $contents,string $operation): void
    {
        $target=$this->root.'/'.str_replace('/',DIRECTORY_SEPARATOR,$relative);$this->ensureDir(dirname($target));
        $tmp=$target.'.ph'.$operation.'.'.bin2hex(random_bytes(4));
        if(@file_put_contents($tmp,$contents,LOCK_EX)===false)throw new RuntimeException('No se pudo preparar '.$relative.' durante '.$operation.'.');
        @chmod($tmp,0644);
        if(is_file($target)&&!@unlink($target)){
            @unlink($tmp);
            if($this->canDeferTarget($relative)){
                $this->queueDeferredContents($contents,$relative,$operation.'-lock');
                return;
            }
            throw new RuntimeException('No se pudo reemplazar '.$relative.' durante '.$operation.'.');
        }
        if(!@rename($tmp,$target)){@unlink($tmp);throw new RuntimeException('No se pudo activar '.$relative.' durante '.$operation.'.');}
    }

    /** @return list<string> */
    private function payloadTargets(string $payloadRoot,array $packageMeta): array
    {
        $targets=[];$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($payloadRoot,FilesystemIterator::SKIP_DOTS));
        foreach($it as $file){
            if(!$file->isFile())continue;
            $rel=str_replace('\\','/',substr($file->getPathname(),strlen($payloadRoot)+1));
            if($rel==='update-package.json')continue;
            $rel=$this->safeRelative($rel);
            if($rel===''||$this->isProtectedPath($rel))throw new RuntimeException('El paquete contiene una ruta protegida: '.$rel);
            $targets[]=$rel;
        }
        return array_values(array_unique($targets));
    }

    /** @return list<array{0:string,1:string}> */
    private function applicationFiles(): array
    {
        $out=[];$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root,FilesystemIterator::SKIP_DOTS));
        foreach($it as $file){
            if(!$file->isFile())continue;
            $rel=str_replace('\\','/',substr($file->getPathname(),strlen($this->root)+1));
            if($rel==='.env'||str_starts_with($rel,'storage/')||str_starts_with($rel,'.git/'))continue;
            $out[]=[$file->getPathname(),$rel];
        }
        return $out;
    }

    private function healthCheck(array $packageMeta,array $release): void
    {
        foreach((array)($packageMeta['health_files']??['VERSION.json','public/index.php','app/bootstrap.php']) as $relative){
            $relative=$this->safeRelative((string)$relative);
            if($relative===''||str_starts_with(strtolower($relative),'storage/updates/deferred/')){
                throw new RuntimeException('Health check rechazó una ruta runtime inválida declarada por el paquete: '.$relative.'.');
            }
            if(!is_file($this->root.'/'.$relative))throw new RuntimeException('Health check falló: falta '.$relative.'.');
        }
        $versionFile=$this->root.'/VERSION.json';$versionData=is_file($versionFile)?json_decode((string)file_get_contents($versionFile),true):null;
        if(($release['component_code']??'')==='property-hub-core' && (!is_array($versionData)||!hash_equals((string)$release['version'],(string)($versionData['version']??'')))){
            throw new RuntimeException('Health check falló: VERSION.json no corresponde a '.$release['version'].'.');
        }
        foreach($this->effectiveMigrationNames($packageMeta) as $migration){
            $name=basename((string)$migration);$stmt=$this->pdo->prepare('SELECT 1 FROM bh_schema_migrations WHERE migration=? LIMIT 1');$stmt->execute([$name]);if($stmt->fetchColumn()===false)throw new RuntimeException('Health check falló: migración '.$name.' no registrada.');
        }
    }

    /** @return list<string> */
    private function effectiveMigrationNames(array $packageMeta): array
    {
        $names=array_values(array_filter((array)($packageMeta['migrations']??[]),'is_string'));
        $role=InstallationMode::current();$roleMigrations=(array)($packageMeta['role_migrations']??[]);
        $aliases=match($role){InstallationMode::CENTRAL=>['central_repository','central'],InstallationMode::EDGE=>['edge_agent','edge'],default=>['building_node','node']};
        foreach($aliases as $alias)foreach((array)($roleMigrations[$alias]??[]) as $migration)if(is_string($migration)&&trim($migration)!=='')$names[]=$migration;
        return array_values(array_unique($names));
    }

    private function updateComponentMetadata(array $release): void
    {
        $installationId=$this->installationId();$type=(string)($release['component_type']??'other');$code=(string)$release['component_code'];$version=(string)$release['version'];$display=(string)($release['display_name']??$code);$channel=(string)($release['channel']??config('deployment.update_channel','stable'));
        $stmt=$this->pdo->prepare("INSERT INTO bh_installed_components (installation_id,component_type,component_code,display_name,installed_version,channel,status,source) VALUES (?,?,?,?,?,?,'active','central_repository') ON DUPLICATE KEY UPDATE display_name=VALUES(display_name),installed_version=VALUES(installed_version),channel=VALUES(channel),status='active',source='central_repository',updated_at=NOW()");
        $stmt->execute([$installationId,$type,$code,$display,$version,$channel]);
        if($code==='property-hub-core'){
            $this->pdo->prepare('UPDATE bh_installations SET app_version=? WHERE id=?')->execute([$version,$installationId]);
            // Resident App y Operations Console forman parte del mismo paquete monolítico
            // del Core en esta etapa; sus metadatos deben avanzar junto con el código.
            $this->pdo->prepare("UPDATE bh_installed_components SET installed_version=?,updated_at=NOW() WHERE installation_id=? AND component_type='module' AND component_code IN ('resident-app','operations-console','property-edge-agent-console')")
                ->execute([$version,$installationId]);
        }
    }

    private function readPackageMetadata(string $payloadRoot): array
    {
        $path=$payloadRoot.'/update-package.json';if(!is_file($path))throw new RuntimeException('El paquete no contiene update-package.json.');
        $data=json_decode((string)file_get_contents($path),true);if(!is_array($data))throw new RuntimeException('update-package.json no es JSON válido.');return $data;
    }

    private function validatePackageMetadata(array $meta,array $release): void
    {
        if((string)($meta['schema']??'')!=='2cam-property-update-v1')throw new RuntimeException('Contrato de paquete de actualización no soportado.');
        foreach(['component_code','version'] as $field)if(trim((string)($meta[$field]??''))==='')throw new RuntimeException('El paquete no declara '.$field.'.');
        if(!hash_equals((string)$release['component_code'],(string)$meta['component_code']))throw new RuntimeException('El componente del ZIP no coincide con el manifiesto firmado.');
        if(!hash_equals((string)$release['version'],(string)$meta['version']))throw new RuntimeException('La versión del ZIP no coincide con el manifiesto firmado.');
        $min=(string)($meta['min_updater_version']??'');if($min!==''&&version_compare((string)self::engineVersion(),$min,'<'))throw new RuntimeException('Este paquete requiere Update Engine '.$min.' o superior.');
    }

    private function resolvePayloadRoot(string $stagingDir): string
    {
        if(is_file($stagingDir.'/update-package.json'))return $stagingDir;
        $entries=array_values(array_filter(scandir($stagingDir)?:[],fn($v)=>!in_array($v,['.','..'],true)));
        if(count($entries)===1&&is_dir($stagingDir.'/'.$entries[0])&&is_file($stagingDir.'/'.$entries[0].'/update-package.json'))return $stagingDir.'/'.$entries[0];
        throw new RuntimeException('No se encontró update-package.json en la raíz del paquete de actualización. El ZIP publicado no cumple el contrato del updater.');
    }

    private function validateZip(string $path): void
    {
        $zip=new ZipArchive();if($zip->open($path)!==true)throw new RuntimeException('El paquete descargado no es un ZIP válido.');
        for($i=0;$i<$zip->numFiles;$i++){
            $name=(string)$zip->getNameIndex($i);$normalized=str_replace('\\','/',$name);
            if($normalized===''||str_starts_with($normalized,'/')||preg_match('#(^|/)\.\.(/|$)#',$normalized)){$zip->close();throw new RuntimeException('El paquete contiene una ruta insegura: '.$name);}
            // Preserve a leading dot so `.env.example` remains protected.
            $trim=ltrim($normalized,'/');
            // Package can have one harmless root directory; check protected segments both ways.
            $parts=explode('/',$trim);if(count($parts)>1&&$parts[0]!==''&&!str_starts_with($parts[0],'.')){$withoutRoot=implode('/',array_slice($parts,1));}else{$withoutRoot=$trim;}
            if($this->isProtectedPath($trim)||($withoutRoot!==''&&$this->isProtectedPath($withoutRoot))){$zip->close();throw new RuntimeException('El paquete intenta modificar una ruta protegida: '.$name);}
        }
        $zip->close();
    }

    private function isProtectedPath(string $relative): bool
    {
        $p=strtolower(ltrim(str_replace('\\','/',$relative),'/'));
        if($p==='.env'||str_starts_with($p,'.env.'))return true;
        foreach(['storage/keys/','storage/uploads/','storage/backups/','storage/logs/','storage/repository/','storage/updates/rollback/','storage/installed.lock'] as $protected){
            if($p===$protected||str_starts_with($p,$protected))return true;
        }
        return false;
    }

    private function safeRelative(string $path): string
    {
        $path=ltrim(str_replace('\\','/',$path),'/');if($path===''||preg_match('#(^|/)\.\.(/|$)#',$path))return '';return $path;
    }

    private function installationId(): int
    {
        $uuid=(string)config('deployment.installation_uuid','');$stmt=$this->pdo->prepare('SELECT id FROM bh_installations WHERE public_uuid=? LIMIT 1');$stmt->execute([$uuid]);$id=(int)($stmt->fetchColumn()?:0);if($id<=0){$id=(int)($this->pdo->query('SELECT id FROM bh_installations ORDER BY id LIMIT 1')->fetchColumn()?:0);}if($id<=0)throw new RuntimeException('No se encontró la instalación local para el actualizador.');return $id;
    }


    /**
     * Incident logging must never replace the update exception being handled.
     * This also keeps future update failures diagnosable if the reporter itself
     * has a compatibility problem.
     */
    private function reportSafely(Throwable $e, string|array $context='update'): void
    {
        try {
            ErrorReporter::report($e,$context);
        } catch (Throwable $reportError) {
            try {
                ErrorReporter::runtimeLog('update_error_reporter_failure',[
                    'original_exception'=>get_class($e),
                    'original_message'=>$e->getMessage(),
                    'report_exception'=>get_class($reportError),
                    'report_message'=>$reportError->getMessage(),
                ]);
            } catch (Throwable) {
                // Preserve the original update exception even if all logging fails.
            }
        }
    }

    private function ensureDir(string $dir): void{if(!is_dir($dir)&&!@mkdir($dir,0775,true)&&!is_dir($dir))throw new RuntimeException('No fue posible crear el directorio '.$dir.'.');}
    private function removeTree(string $dir): void{if(!is_dir($dir))return;$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($it as $f){$f->isDir()?@rmdir($f->getPathname()):@unlink($f->getPathname());}@rmdir($dir);}
    public static function engineVersion(): string{return '1.2.1';}
    private static function uuid(): string{$data=random_bytes(16);$data[6]=chr((ord($data[6])&0x0f)|0x40);$data[8]=chr((ord($data[8])&0x3f)|0x80);return vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($data),4));}
}
