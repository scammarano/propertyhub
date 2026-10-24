<?php
declare(strict_types=1);

final class BackupManager
{
    public function __construct(private PDO $pdo, private string $root='') { $this->root=$root!==''?$root:base_path(); }

    public function settings(): array
    {
        $id=$this->installationId();
        $stmt=$this->pdo->prepare('SELECT * FROM bh_backup_settings WHERE installation_id=? LIMIT 1');$stmt->execute([$id]);$row=$stmt->fetch();
        if(!is_array($row)){
            $this->pdo->prepare("INSERT INTO bh_backup_settings (installation_id,local_path) VALUES (?,'storage/backups')")->execute([$id]);
            $stmt->execute([$id]);$row=$stmt->fetch();
        }
        return is_array($row)?$row:[];
    }

    public function saveSettings(array $input): void
    {
        $frequency=in_array((string)($input['schedule_frequency']??''),['manual','daily','weekly'],true)?(string)$input['schedule_frequency']:'daily';
        $time=preg_match('/^\d{2}:\d{2}$/',(string)($input['schedule_time']??''))?(string)$input['schedule_time'].':00':'02:00:00';
        $weekday=min(6,max(0,(int)($input['schedule_weekday']??0)));
        $local=trim((string)($input['local_path']??'storage/backups'))?:'storage/backups';
        $network=trim((string)($input['network_path']??''));
        $stmt=$this->pdo->prepare('UPDATE bh_backup_settings SET enabled=?,schedule_frequency=?,schedule_time=?,schedule_weekday=?,retention_daily=?,retention_weekly=?,retention_monthly=?,local_path=?,network_path=?,central_copy_enabled=?,node_copy_enabled=? WHERE installation_id=?');
        $stmt->execute([
            !empty($input['enabled'])?1:0,$frequency,$time,$weekday,
            min(365,max(1,(int)($input['retention_daily']??7))),min(104,max(0,(int)($input['retention_weekly']??4))),min(120,max(0,(int)($input['retention_monthly']??6))),
            $local,$network!==''?$network:null,!empty($input['central_copy_enabled'])?1:0,!empty($input['node_copy_enabled'])?1:0,$this->installationId()
        ]);
    }

    public function recoveryKeyExists(): bool { return trim((string)env('BACKUP_RECOVERY_KEY',''))!==''; }
    public function generateRecoveryKey(bool $rotate=false): string
    {
        if($this->recoveryKeyExists()&&!$rotate) throw new RuntimeException('Ya existe una clave de recuperación. Solo rótela si ya guardó un respaldo reciente y entiende que los respaldos anteriores requerirán la clave anterior.');
        $key='PHRK-'.rtrim(strtr(base64_encode(random_bytes(36)),'+/','-_'),'=');
        EnvironmentEditor::set(['BACKUP_RECOVERY_KEY'=>$key]);
        return $key;
    }

    public function backups(int $limit=50): array
    {
        $stmt=$this->pdo->prepare("SELECT * FROM bh_backup_runs WHERE installation_id=? AND status<>'deleted' ORDER BY started_at DESC LIMIT ".max(1,min(200,$limit)));
        $stmt->execute([$this->installationId()]);return $stmt->fetchAll()?:[];
    }

    public function create(string $type='manual',?int $userId=null,array $options=[]): array
    {
        if(!in_array($type,['manual','scheduled','pre_update','pre_restore'],true))$type='manual';
        $recoveryKey=(string)($options['recovery_key']??env('BACKUP_RECOVERY_KEY',''));if($recoveryKey==='')throw new RuntimeException('Genere primero la clave de recuperación. Sin esa clave un respaldo automático no sería recuperable después de perder el servidor.');
        $untracked=!empty($options['untracked']);
        if(!class_exists('ZipArchive'))throw new RuntimeException('La extensión PHP zip es obligatoria para generar respaldos completos. Active extension=zip en PHP.');
        $installation=$this->installation();$settings=$untracked?['local_path'=>'storage/backups','network_path'=>null,'central_copy_enabled'=>0,'retention_daily'=>7,'retention_weekly'=>4,'retention_monthly'=>6]:$this->settings();$uuid=self::uuid();
        $runId=0;if(!$untracked){$this->pdo->prepare("INSERT INTO bh_backup_runs (public_uuid,installation_id,backup_type,status,created_by) VALUES (?,?,?,'running',?)")->execute([$uuid,(int)$installation['id'],$type,$userId]);$runId=(int)$this->pdo->lastInsertId();}
        $safeCode=preg_replace('/[^A-Z0-9_-]+/i','-',(string)$installation['code'])?:'PROPERTY';
        $tag=match($type){'pre_update'=>'PRE_UPDATE','pre_restore'=>'PRE_RESTORE','scheduled'=>'AUTO',default=>'MANUAL'};
        $filename=$safeCode.'_'.$tag.'_'.date('Ymd_His').'.phbackup';
        $localDir=$this->resolveBackupDirectory((string)($settings['local_path']??'storage/backups'));$this->ensureDir($localDir);
        $finalPath=$localDir.DIRECTORY_SEPARATOR.$filename;
        $tmpBase=$this->root.'/storage/backups/tmp/'.$uuid;$this->ensureDir($tmpBase);
        $dbPath=$tmpBase.'/database.sql';$zipPath=$tmpBase.'/payload.zip';
        $destinations=[];$warnings=[];$localOnly=!empty($options['local_only']);$schemaOnly=!empty($options['schema_only']);
        try{
            $dbStats=DatabaseBackup::dump($this->pdo,$dbPath);
            $envData=$this->readEnv();
            // La clave que descifra el respaldo nunca viaja dentro del propio .phbackup.
            unset($envData['BACKUP_RECOVERY_KEY']);
            $manifest=[
                'format'=>'2CAM Property Hub Complete Backup','format_version'=>1,'backup_uuid'=>$uuid,'backup_type'=>$type,'created_at'=>date(DATE_ATOM),
                'app_version'=>(string)config('app.version'),'ui_version'=>(string)config('app.ui_standard_version'),'instance_role'=>(string)config('app.instance_role'),'parent_property_code'=>(string)config('deployment.parent_property_code',''),
                'installation'=>['id'=>(int)$installation['id'],'uuid'=>(string)$installation['public_uuid'],'code'=>(string)$installation['code'],'name'=>(string)$installation['name'],'type'=>(string)$installation['installation_type'],'timezone'=>(string)$installation['timezone']],
                'source_server'=>['app_url'=>(string)config('app.url'),'db_host'=>(string)config('database.host'),'db_port'=>(int)config('database.port'),'db_database'=>(string)config('database.database')],
                'database'=>['name'=>(string)config('database.database'),'server_version'=>(string)$this->pdo->getAttribute(PDO::ATTR_SERVER_VERSION)]+$dbStats,
                'external_systems'=>$this->externalSystems((int)$installation['id']),
                'storage_policy'=>[
                    'included'=>$schemaOnly?'ninguno; snapshot rápido de seguridad para migración web':(InstallationMode::isCentral()?'storage persistente, repositorio oficial y Backup Vault remoto cifrado':'storage persistente necesario para recuperar la instalación'),
                    'excluded'=>$schemaOnly?['storage/** completo; la migración solo requiere proteger BD y configuración']:(InstallationMode::isCentral()?['storage/backups excepto storage/backups/offsite','storage/logs','storage/tmp','storage/cache','storage/sessions','storage/updates','storage/run','storage/uploads/edge-camera-cache','storage/installed.lock']:['storage/backups','storage/logs','storage/tmp','storage/cache','storage/sessions','storage/updates','storage/run','storage/uploads/edge-camera-cache','storage/installed.lock','storage/repository']),
                ],
                'backup_scope'=>$schemaOnly?'schema_safety_snapshot':'complete',
                'restore_policy'=>['same_installation'=>'preserve identity and external credentials; keep new server DB/URL settings','safe_clone'=>'keep new identity; disable external integrations and remote actions'],
            ];
            $checksums=['database.sql'=>hash_file('sha256',$dbPath)?:''];
            $zip=new ZipArchive();if($zip->open($zipPath,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)throw new RuntimeException('No fue posible crear el contenedor ZIP temporal.');
            if(!$zip->addFile($dbPath,'database.sql'))throw new RuntimeException('No fue posible agregar database.sql al ZIP temporal.');
            $envJson=json_encode($envData,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);$zip->addFromString('config/env-export.json',(string)$envJson);$checksums['config/env-export.json']=hash('sha256',(string)$envJson);
            $storageFiles=$schemaOnly?[]:$this->persistentStorageFiles();
            $storageSummary=$this->summarizeStorageFiles($storageFiles);
            foreach($storageFiles as [$full,$relative]){
                if(!is_file($full)||!is_readable($full))throw new RuntimeException('Archivo de storage no disponible durante el respaldo: '.$relative);
                if(!$zip->addFile($full,$relative))throw new RuntimeException('No fue posible agregar al respaldo: '.$relative);
                $checksums[$relative]=hash_file('sha256',$full)?:'';
            }
            $manifest['database_dump_bytes']=is_file($dbPath)?(int)filesize($dbPath):0;
            $manifest['storage_files']=$storageSummary['files'];
            $manifest['storage_bytes']=$storageSummary['bytes'];
            $manifest['storage_categories']=$storageSummary['categories'];
            $manifest['content_summary']=[
                'database_dump_bytes'=>$manifest['database_dump_bytes'],
                'storage_files'=>$storageSummary['files'],
                'storage_bytes'=>$storageSummary['bytes'],
                'external_systems'=>count((array)$manifest['external_systems']),
            ];
            $manifest['checksums_count']=count($checksums);
            $manifestJson=json_encode($manifest,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);$zip->addFromString('manifest.json',(string)$manifestJson);
            if(!$zip->addFromString('checksums.json',(string)json_encode($checksums,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)))throw new RuntimeException('No fue posible agregar checksums.json al ZIP temporal.');
            if(!$zip->close())throw new RuntimeException('No fue posible cerrar el ZIP temporal del respaldo. Revise espacio libre, permisos o archivos runtime en uso.');
            clearstatcache(true,$zipPath);
            if(!is_file($zipPath)||(int)@filesize($zipPath)<=0)throw new RuntimeException('El ZIP temporal del respaldo no fue creado correctamente.');
            $crypto=BackupCrypto::encryptFile($zipPath,$finalPath,$recoveryKey,['installation_code'=>$installation['code'],'installation_uuid'=>$installation['public_uuid'],'backup_type'=>$type,'app_version'=>config('app.version')]);
            $destinations[]=['type'=>'local','status'=>'ok','path'=>$finalPath];
            $network=trim((string)($settings['network_path']??''));if(!$localOnly&&$network!==''){
                try{$networkDir=$this->resolveBackupDirectory($network);$this->ensureDir($networkDir);$target=rtrim($networkDir,'/\\').DIRECTORY_SEPARATOR.$filename;if(!@copy($finalPath,$target))throw new RuntimeException('No fue posible copiar al destino de red.');$destinations[]=['type'=>'network','status'=>'ok','path'=>$target];}
                catch(Throwable $e){$warnings[]='Red: '.$e->getMessage();$destinations[]=['type'=>'network','status'=>'warning','message'=>$e->getMessage()];}
            }
            if(!$localOnly&&(!empty($options['force_central_copy'])||!empty($settings['central_copy_enabled']))&&!InstallationMode::isCentral()){
                try{$remote=RepositoryClient::uploadEncryptedBackup($finalPath,$manifest,(string)($options['central_request_uuid']??''));$destinations[]=['type'=>'central','status'=>'ok','response'=>$remote];}
                catch(Throwable $e){$warnings[]='Central: '.$e->getMessage();$destinations[]=['type'=>'central','status'=>'warning','message'=>$e->getMessage()];}
            }
            if(!$localOnly&&InstallationMode::isEdge()&&(!empty($options['force_node_copy'])||!empty($settings['node_copy_enabled']))){
                try{$remote=EdgeNodeClient::uploadEncryptedBackup($finalPath,$manifest);$destinations[]=['type'=>'property_node','status'=>'ok','response'=>$remote];}
                catch(Throwable $e){$warnings[]='Property Node: '.$e->getMessage();$destinations[]=['type'=>'property_node','status'=>'warning','message'=>$e->getMessage()];}
            }
            $status=$warnings===[]?'completed':'warning';$sha=hash_file('sha256',$finalPath)?:'';$size=(int)filesize($finalPath);
            if(!$untracked){$this->pdo->prepare('UPDATE bh_backup_runs SET status=?,filename=?,local_path=?,size_bytes=?,sha256=?,destinations_json=?,manifest_json=?,completed_at=NOW() WHERE id=?')->execute([$status,$filename,$finalPath,$size,$sha,json_encode($destinations,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),$manifestJson,$runId]);
            $this->pdo->prepare('UPDATE bh_backup_settings SET last_backup_at=NOW(),last_status=?,last_message=? WHERE installation_id=?')->execute([$warnings===[]?'ok':'warning',$warnings===[]?'Respaldo completado.':implode(' | ',$warnings),(int)$installation['id']]);
            if(empty($options['skip_prune']))$this->prune();}
            return ['id'=>$runId,'uuid'=>$uuid,'filename'=>$filename,'path'=>$finalPath,'size'=>$size,'sha256'=>$sha,'status'=>$status,'warnings'=>$warnings,'manifest'=>$manifest,'crypto'=>$crypto,'destinations'=>$destinations];
        }catch(Throwable $e){
            if(!$untracked){$this->pdo->prepare("UPDATE bh_backup_runs SET status='failed',error_message=?,completed_at=NOW() WHERE id=?")->execute([$e->getMessage(),$runId]);
            $this->pdo->prepare("UPDATE bh_backup_settings SET last_status='failed',last_message=? WHERE installation_id=?")->execute([$e->getMessage(),(int)$installation['id']]);}
            throw $e;
        }finally{$this->removeTree($tmpBase);}
    }

    public function inspect(string $encryptedPath,string $recoveryKey): array
    {
        if(!class_exists('ZipArchive'))throw new RuntimeException('La extensión PHP zip es obligatoria para leer respaldos.');
        $tmp=$this->root.'/storage/backups/tmp/inspect-'.bin2hex(random_bytes(8));$this->ensureDir($tmp);$zipPath=$tmp.'/payload.zip';
        try{
            $crypto=BackupCrypto::decryptFile($encryptedPath,$zipPath,$recoveryKey);
            $zip=new ZipArchive();if($zip->open($zipPath)!==true)throw new RuntimeException('El contenido cifrado no contiene un ZIP válido.');
            $manifest=json_decode((string)$zip->getFromName('manifest.json'),true);$env=json_decode((string)$zip->getFromName('config/env-export.json'),true);
            $extract=$tmp.'/extract';$this->ensureDir($extract);if(!$zip->extractTo($extract)){$zip->close();throw new RuntimeException('No fue posible extraer el respaldo para validar su integridad.');}$zip->close();
            if(!is_array($manifest)||!is_array($env))throw new RuntimeException('El respaldo no contiene manifiesto/configuración válida.');
            $integrity=$this->verifyExtractedChecksums($extract);
            return ['manifest'=>$manifest,'env'=>$env,'header'=>$crypto['header'],'integrity'=>$integrity,'sha256'=>hash_file('sha256',$encryptedPath)?:'','size'=>(int)filesize($encryptedPath)];
        }finally{$this->removeTree($tmp);}
    }

    public function restore(string $encryptedPath,string $recoveryKey,string $mode,?int $userId=null): array
    {
        if(!in_array($mode,['same_installation','safe_clone'],true))throw new InvalidArgumentException('Modo de recuperación inválido.');
        if(!class_exists('ZipArchive'))throw new RuntimeException('La extensión PHP zip es obligatoria para restaurar respaldos.');
        // Punto de retorno automático antes de una restauración destructiva.
        $safety=$this->create('pre_restore',$userId,['skip_prune'=>true,'local_only'=>true]);
        $currentEnv=$this->readEnv();$currentInstallation=$this->installation();$currentAdmin=$this->captureAdmin($userId);$currentBackupSettings=$this->settings();
        $tmp=$this->root.'/storage/backups/tmp/restore-'.bin2hex(random_bytes(8));$this->ensureDir($tmp);$zipPath=$tmp.'/payload.zip';$extract=$tmp.'/extract';$this->ensureDir($extract);
        $restoreUuid=self::uuid();$sourceName=basename($encryptedPath);$sourceSha=hash_file('sha256',$encryptedPath)?:'';
        try{
            BackupCrypto::decryptFile($encryptedPath,$zipPath,$recoveryKey);
            $zip=new ZipArchive();if($zip->open($zipPath)!==true)throw new RuntimeException('El respaldo descifrado no es un ZIP válido.');
            $manifest=json_decode((string)$zip->getFromName('manifest.json'),true);$backupEnv=json_decode((string)$zip->getFromName('config/env-export.json'),true);
            if(!is_array($manifest)||!is_array($backupEnv))throw new RuntimeException('El respaldo no contiene un manifiesto completo.');
            if((string)($manifest['instance_role']??'')!==(string)config('app.instance_role'))throw new RuntimeException('El respaldo pertenece a un rol diferente. Instale primero el mismo perfil (Central, Property Node o Edge Agent).');
            if(!$zip->extractTo($extract))throw new RuntimeException('No fue posible extraer el respaldo temporal.');$zip->close();
            $integrity=$this->verifyExtractedChecksums($extract);
            $dbFile=$extract.'/database.sql';if(!is_file($dbFile))throw new RuntimeException('Falta database.sql en el respaldo.');
            DatabaseBackup::restore($this->pdo,$dbFile);
            // Llevar la base restaurada hasta el schema de esta versión sin depender del UUID anterior del .env.
            $schema=new SchemaManager($this->pdo);(new MigrationRunner($this->pdo))->run($schema->files());
            (new CatalogSeeder($this->pdo,$this->root))->seedConnectorDefinitions();
            if(InstallationMode::isCentral())(new CatalogSeeder($this->pdo,$this->root))->seedVendorApis();
            $imported=$this->pdo->query('SELECT * FROM bh_installations ORDER BY id LIMIT 1')->fetch();if(!is_array($imported))throw new RuntimeException('La base restaurada no contiene una instalación válida.');
            $installationId=(int)$imported['id'];
            if($mode==='safe_clone'){
                $this->pdo->prepare("UPDATE bh_installations SET public_uuid=?,code=?,name=?,installation_type=?,app_version=?,updated_at=NOW() WHERE id=?")->execute([(string)$currentInstallation['public_uuid'],(string)$currentInstallation['code'],(string)$currentInstallation['name'],(string)$currentInstallation['installation_type'],(string)config('app.version'),$installationId]);
                $this->pdo->prepare("UPDATE bh_deployment_settings SET installation_uuid=?,repository_url=NULL,repository_status='not_configured',auto_check_updates=0,auto_download_updates=0,auto_install_updates=0 WHERE installation_id=?")->execute([(string)$currentInstallation['public_uuid'],$installationId]);
                $this->pdo->exec("UPDATE bh_connector_instances SET status='disabled'");
                $this->pdo->exec("UPDATE bh_access_points SET remote_policy='disabled'");
                $this->pdo->exec("UPDATE bh_cameras SET status='disabled'");
                $this->pdo->exec("UPDATE bh_update_sources SET enabled=0");
                $this->pdo->exec("UPDATE bh_backup_settings SET enabled=0,central_copy_enabled=0,network_path=NULL,local_path='storage/backups'");
                $this->restoreAdmin($installationId,$currentAdmin);
            } else {
                $this->pdo->prepare("UPDATE bh_installations SET app_version=?,updated_at=NOW() WHERE id=?")->execute([(string)config('app.version'),$installationId]);
                $this->pdo->prepare("UPDATE bh_backup_settings SET local_path=? WHERE installation_id=?")->execute([(string)($currentBackupSettings['local_path']??'storage/backups'),$installationId]);
            }
            $this->pdo->exec("UPDATE bh_backup_runs SET status='warning',error_message=COALESCE(error_message,'Snapshot capturado mientras el respaldo estaba en ejecución.') WHERE status='running'");
            $this->restorePersistentStorage($extract.'/storage',$mode);
            if(InstallationMode::isCentral())$this->reconcileCentralStoragePaths();
            $changes=$this->reconciledEnvironment($backupEnv,$currentEnv,$mode);
            EnvironmentEditor::set($changes);
            $identity=$mode==='safe_clone'?$currentInstallation:(array)($manifest['installation']??[]);
            $lock=['installed_at'=>date(DATE_ATOM),'installer_version'=>(string)config('app.version'),'instance_role'=>(string)config('app.instance_role'),'installation_uuid'=>(string)($identity['public_uuid']??$identity['uuid']??$currentInstallation['public_uuid']),'installation_code'=>(string)($identity['code']??$currentInstallation['code']),'database'=>(string)($currentEnv['DB_DATABASE']??config('database.database')),'app_version'=>(string)config('app.version'),'ui_version'=>(string)config('app.ui_standard_version'),'restored_from'=>$sourceName,'restore_mode'=>$mode];
            file_put_contents($this->root.'/storage/installed.lock',json_encode($lock,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES),LOCK_EX);
            $this->pdo->prepare("INSERT INTO bh_restore_runs (public_uuid,installation_id,restore_mode,status,source_filename,source_sha256,source_manifest_json,reconciliation_json,created_by,completed_at) VALUES (?,?,?,'completed',?,?,?,?,?,NOW())")->execute([$restoreUuid,$installationId,$mode,$sourceName,$sourceSha,json_encode($manifest,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),json_encode(['preserved_server_keys'=>self::localEnvironmentKeys(),'safe_clone'=>$mode==='safe_clone','safety_backup'=>$safety['filename'],'integrity'=>$integrity],JSON_UNESCAPED_SLASHES),null]);
            return ['restore_uuid'=>$restoreUuid,'mode'=>$mode,'manifest'=>$manifest,'safety_backup'=>$safety,'installation_id'=>$installationId,'message'=>$mode==='safe_clone'?'Copia restaurada en modo seguro. Conectores, cámaras, aperturas remotas, actualizaciones y notificaciones quedaron deshabilitados.':'La misma instalación fue recuperada conservando su identidad y credenciales externas; la configuración propia del nuevo servidor se mantuvo.'];
        }catch(Throwable $e){ErrorReporter::report($e,['operation'=>'backup_restore','source'=>$sourceName,'mode'=>$mode,'safety_backup'=>$safety['filename'],'integrity'=>$integrity??null]);throw new RuntimeException($e->getMessage().' Respaldo de seguridad previo: '.($safety['filename']??'no disponible'),0,$e);}finally{$this->removeTree($tmp);}
    }

    public function due(): bool
    {
        $s=$this->settings();if(empty($s['enabled'])||($s['schedule_frequency']??'manual')==='manual')return false;
        $now=new DateTimeImmutable('now');$scheduled=(string)($s['schedule_time']??'02:00:00');[$h]=array_map('intval',explode(':',$scheduled));if((int)$now->format('G')!==$h)return false;
        if(($s['schedule_frequency']??'daily')==='weekly'&&(int)$now->format('w')!==(int)($s['schedule_weekday']??0))return false;
        $last=!empty($s['last_backup_at'])?new DateTimeImmutable((string)$s['last_backup_at']):null;if(!$last)return true;
        return $last->format('Y-m-d')!==$now->format('Y-m-d');
    }


    public function offsiteArchives(int $limit=50): array
    {
        if(!InstallationMode::isCentral())return [];
        $stmt=$this->pdo->query('SELECT * FROM bh_backup_offsite_archives WHERE status<>\'deleted\' ORDER BY received_at DESC LIMIT '.max(1,min(200,$limit)));
        return $stmt->fetchAll()?:[];
    }


    public function pathForOffsite(int $id): string
    {
        if(!InstallationMode::isCentral())throw new RuntimeException('Solo el Property Hub Central almacena copias externas.');
        $stmt=$this->pdo->prepare("SELECT storage_path FROM bh_backup_offsite_archives WHERE id=? AND status='stored' LIMIT 1");$stmt->execute([$id]);$path=(string)($stmt->fetchColumn()?:'');
        if($path===''||!is_file($path))throw new RuntimeException('La copia externa ya no está disponible en el almacenamiento central.');return $path;
    }

    public function schedulerInfo(): array
    {
        $marker=$this->root.'/storage/backups/scheduler-installed.json';
        $data=is_file($marker)?json_decode((string)file_get_contents($marker),true):null;
        $runMarker=$this->root.'/storage/backups/scheduler-last-run.json';
        $run=is_file($runMarker)?json_decode((string)file_get_contents($runMarker),true):null;
        return ['installed'=>is_array($data),'details'=>is_array($data)?$data:[],'marker'=>$marker,'last_run'=>is_array($run)?$run:[],'last_run_marker'=>$runMarker];
    }

    public function deleteBackup(int $id): void
    {
        $stmt=$this->pdo->prepare('SELECT * FROM bh_backup_runs WHERE id=? AND installation_id=? LIMIT 1');$stmt->execute([$id,$this->installationId()]);$row=$stmt->fetch();if(!is_array($row))throw new RuntimeException('Respaldo no encontrado.');
        $path=(string)($row['local_path']??'');if($path!==''&&is_file($path))@unlink($path);$this->pdo->prepare("UPDATE bh_backup_runs SET status='deleted' WHERE id=?")->execute([$id]);
    }

    public function pathForRun(int $id): string
    {
        $stmt=$this->pdo->prepare("SELECT local_path FROM bh_backup_runs WHERE id=? AND installation_id=? AND status IN ('completed','warning') LIMIT 1");$stmt->execute([$id,$this->installationId()]);$path=(string)($stmt->fetchColumn()?:'');if($path===''||!is_file($path))throw new RuntimeException('El archivo de respaldo ya no está disponible localmente.');return $path;
    }

    public function acceptIncomingUpload(array $file): string
    {
        if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new RuntimeException('No se recibió correctamente el archivo de respaldo.');
        $name=(string)($file['name']??'backup.phbackup');if(!str_ends_with(strtolower($name),'.phbackup'))throw new InvalidArgumentException('El archivo debe tener extensión .phbackup.');
        $dir=$this->root.'/storage/backups/incoming';$this->ensureDir($dir);$target=$dir.'/'.date('Ymd_His').'_'.preg_replace('/[^A-Za-z0-9_.-]/','_',basename($name));
        if(!move_uploaded_file((string)$file['tmp_name'],$target))throw new RuntimeException('No fue posible guardar temporalmente el respaldo subido.');return $target;
    }

    private function verifyExtractedChecksums(string $extract): array
    {
        $checksumsPath=$extract.'/checksums.json';
        if(!is_file($checksumsPath))throw new RuntimeException('El respaldo no contiene checksums.json.');
        $checksums=json_decode((string)file_get_contents($checksumsPath),true);
        if(!is_array($checksums)||$checksums===[])throw new RuntimeException('checksums.json no es válido.');
        $verified=0;
        foreach($checksums as $relative=>$expected){
            $relative=ltrim(str_replace('\\','/',(string)$relative),'/');
            if($relative===''||preg_match('#(^|/)\.\.(/|$)#',$relative))throw new RuntimeException('El respaldo contiene una ruta inválida en checksums: '.$relative);
            $path=$extract.'/'.str_replace('/',DIRECTORY_SEPARATOR,$relative);
            if(!is_file($path))throw new RuntimeException('Falta un archivo declarado por el respaldo: '.$relative);
            $actual=hash_file('sha256',$path)?:'';
            if($actual===''||!hash_equals(strtolower((string)$expected),strtolower($actual)))throw new RuntimeException('Falló la integridad del respaldo en: '.$relative);
            $verified++;
        }
        return ['ok'=>true,'verified_files'=>$verified];
    }

    private function prune(): void
    {
        $s=$this->settings();$runs=$this->backups(200);$daily=[];$weekly=[];$monthly=[];$keep=[];
        foreach($runs as $r){if(!in_array($r['status'],['completed','warning'],true)||empty($r['local_path']))continue;$dt=new DateTimeImmutable((string)$r['started_at']);$daily[$dt->format('Y-m-d')][]=$r;$weekly[$dt->format('o-W')][]=$r;$monthly[$dt->format('Y-m')][]=$r;}
        foreach(array_slice(array_keys($daily),0,(int)$s['retention_daily']) as $k)foreach($daily[$k] as $r)$keep[(int)$r['id']]=true;
        foreach(array_slice(array_keys($weekly),0,(int)$s['retention_weekly']) as $k)if(isset($weekly[$k][0]))$keep[(int)$weekly[$k][0]['id']]=true;
        foreach(array_slice(array_keys($monthly),0,(int)$s['retention_monthly']) as $k)if(isset($monthly[$k][0]))$keep[(int)$monthly[$k][0]['id']]=true;
        foreach($runs as $r){$id=(int)$r['id'];if(isset($keep[$id])||!in_array($r['backup_type'],['manual','scheduled'],true))continue;$path=(string)($r['local_path']??'');if($path!==''&&is_file($path))@unlink($path);$this->pdo->prepare("UPDATE bh_backup_runs SET status='deleted' WHERE id=?")->execute([$id]);}
    }

    private function installation(): array
    { $stmt=$this->pdo->prepare('SELECT * FROM bh_installations WHERE public_uuid=? LIMIT 1');$stmt->execute([(string)config('app.installation_uuid')]);$row=$stmt->fetch();if(!is_array($row)){$row=$this->pdo->query('SELECT * FROM bh_installations ORDER BY id LIMIT 1')->fetch();}if(!is_array($row))throw new RuntimeException('No existe instalación local.');return $row; }
    private function installationId(): int{return (int)$this->installation()['id'];}
    private function resolveBackupDirectory(string $path): string
    {
        $path=trim($path);if($path==='')$path='storage/backups';
        $windowsDrive=strlen($path)>=3&&ctype_alpha($path[0])&&$path[1]===':'&&($path[2]==='\\'||$path[2]==='/');
        $unc=str_starts_with($path,'\\\\');
        $unix=str_starts_with($path,'/');
        if($windowsDrive||$unc||$unix)return rtrim($path,'/\\');
        return $this->root.DIRECTORY_SEPARATOR.str_replace(['/', '\\'],DIRECTORY_SEPARATOR,trim($path,'/\\'));
    }
    private function ensureDir(string $dir): void{if(!is_dir($dir)&&!@mkdir($dir,0775,true)&&!is_dir($dir))throw new RuntimeException('No fue posible crear el directorio: '.$dir);}
    private function readEnv(): array{ $file=$this->root.'/.env';$out=[];foreach(file($file,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES)?:[] as $line){$line=trim($line);if($line===''||str_starts_with($line,'#')||!str_contains($line,'='))continue;[$k,$v]=array_map('trim',explode('=',$line,2));if(strlen($v)>=2&&(($v[0]==='"'&&$v[-1]==='"')||($v[0]==="'"&&$v[-1]==="'")))$v=stripcslashes(substr($v,1,-1));$out[$k]=$v;}return $out; }
    private function persistentStorageFiles(): array
    {
        $base=$this->root.'/storage';$out=[];if(!is_dir($base))return $out;
        $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base,FilesystemIterator::SKIP_DOTS));
        foreach($it as $file){
            if(!$file->isFile())continue;
            $rel=str_replace('\\','/',substr($file->getPathname(),strlen($this->root)+1));
            $isCentral=InstallationMode::isCentral();
            if($rel==='storage/installed.lock'||preg_match('#^storage/(logs|tmp|cache|sessions|updates|run)/#i',$rel))continue;
            // Snapshots de descubrimiento/preview del Edge son caché efímero. Pueden ser creados y borrados
            // mientras corre un PRE_UPDATE; nunca deben formar parte de un respaldo recuperable.
            if(preg_match('#^storage/uploads/edge-camera-cache/#i',$rel))continue;
            if(preg_match('#^storage/backups/#i',$rel)){
                // El respaldo global del Central incluye el Vault remoto, pero nunca sus propios .phbackup ni temporales.
                if(!($isCentral&&preg_match('#^storage/backups/offsite/#i',$rel)))continue;
            }
            // Los paquetes del repositorio son patrimonio del Central, no del Property Node.
            if(!$isCentral&&preg_match('#^storage/repository/#i',$rel))continue;
            $out[]=[$file->getPathname(),$rel,(int)$file->getSize()];
        }
        return $out;
    }

    private function summarizeStorageFiles(array $files): array
    {
        $bytes=0;$categories=[];
        foreach($files as $item){
            $relative=(string)($item[1]??'');
            $size=(int)($item[2]??((isset($item[0])&&is_file((string)$item[0]))?filesize((string)$item[0]):0));
            $bytes+=$size;
            $trim=preg_replace('#^storage/#i','',$relative)??$relative;
            $category=explode('/',$trim,2)[0]?:'root';
            if(!isset($categories[$category]))$categories[$category]=['files'=>0,'bytes'=>0];
            $categories[$category]['files']++;
            $categories[$category]['bytes']+=$size;
        }
        ksort($categories);
        return ['files'=>count($files),'bytes'=>$bytes,'categories'=>$categories];
    }
    private function restorePersistentStorage(string $source,string $mode): void{if(!is_dir($source))return;$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source,FilesystemIterator::SKIP_DOTS));foreach($it as $file){if(!$file->isFile())continue;$rel=str_replace('\\','/',substr($file->getPathname(),strlen($source)+1));if(preg_match('#^(run/|uploads/edge-camera-cache/)#i',$rel))continue;if($mode==='safe_clone'&&preg_match('#^keys/repository-(private|public)\.pem$#i',$rel))continue;$target=$this->root.'/storage/'.str_replace('/',DIRECTORY_SEPARATOR,$rel);$this->ensureDir(dirname($target));copy($file->getPathname(),$target);} }

    private function reconcileCentralStoragePaths(): void
    {
        if(!InstallationMode::isCentral())return;
        try{
            $rows=$this->pdo->query("SELECT id,installation_code,filename FROM bh_backup_offsite_archives WHERE status='stored'")->fetchAll()?:[];
            $stmt=$this->pdo->prepare('UPDATE bh_backup_offsite_archives SET storage_path=? WHERE id=?');
            foreach($rows as $r){
                $code=preg_replace('/[^A-Za-z0-9_-]/','_', (string)$r['installation_code'])?:'node';
                $dir=$this->root.'/storage/backups/offsite/'.$code;
                $candidate=$dir.'/'.basename((string)$r['filename']);
                if(!is_file($candidate)&&is_dir($dir)){
                    $matches=glob($dir.'/*_'.basename((string)$r['filename']))?:[];
                    if($matches)$candidate=(string)end($matches);
                }
                if(is_file($candidate))$stmt->execute([$candidate,(int)$r['id']]);
            }
        }catch(Throwable $e){ErrorReporter::report($e,['operation'=>'reconcile_central_vault_paths']);}
    }

    private function externalSystems(int $installationId): array
    {
        try{$stmt=$this->pdo->prepare('SELECT cd.vendor,cd.name product,ci.name,ci.endpoint_url,ci.status,ci.integration_mode,ci.api_family,ci.api_version FROM bh_connector_instances ci JOIN bh_connector_definitions cd ON cd.id=ci.connector_definition_id WHERE ci.installation_id=? ORDER BY ci.id');$stmt->execute([$installationId]);return $stmt->fetchAll()?:[];}catch(Throwable $e){return [];}
    }
    private function captureAdmin(?int $userId): array{if(!$userId)return [];try{$stmt=$this->pdo->prepare('SELECT email,username,password_hash,user_type,status FROM bh_local_users WHERE id=? LIMIT 1');$stmt->execute([$userId]);return (array)($stmt->fetch()?:[]);}catch(Throwable $e){return [];} }
    private function restoreAdmin(int $installationId,array $admin): void{if($admin===[])return;$email=(string)($admin['email']??'');$username=(string)($admin['username']??'admin');$stmt=$this->pdo->prepare('SELECT id FROM bh_local_users WHERE installation_id=? AND (email=? OR username=?) LIMIT 1');$stmt->execute([$installationId,$email,$username]);$id=(int)($stmt->fetchColumn()?:0);if($id)$this->pdo->prepare("UPDATE bh_local_users SET email=?,username=?,password_hash=?,user_type='admin',status='active' WHERE id=?")->execute([$email,$username,(string)$admin['password_hash'],$id]);else $this->pdo->prepare("INSERT INTO bh_local_users (installation_id,email,username,password_hash,user_type,status) VALUES (?,?,?,?,'admin','active')")->execute([$installationId,$email,$username,(string)$admin['password_hash']]); }
    private function reconciledEnvironment(array $backup,array $current,string $mode): array{ $changes=$backup;foreach(self::localEnvironmentKeys() as $k)if(array_key_exists($k,$current))$changes[$k]=$current[$k];if($mode==='safe_clone'){foreach(['INSTANCE_UUID','INSTANCE_CODE','INSTANCE_NAME','INSTANCE_TYPE','INSTALLATION_API_KEY','APP_SESSION_NAME','BACKUP_RECOVERY_KEY'] as $k)if(array_key_exists($k,$current))$changes[$k]=$current[$k];$changes['CENTRAL_REPOSITORY_URL']='';$changes['REPOSITORY_ENABLED']='false';$changes['NOTIFY_EMAIL_ENABLED']='false';$changes['NOTIFY_PUSH_ENABLED']='false';$changes['NOTIFY_WHATSAPP_ENABLED']='false';$changes['NOTIFY_SMS_ENABLED']='false';$changes['GOOGLE_AUTH_ENABLED']='false';$changes['APPLE_AUTH_ENABLED']='false';$changes['ALLOW_OFFLINE']='true';}return $changes; }
    private static function localEnvironmentKeys(): array{return ['APP_URL','DB_HOST','DB_PORT','DB_DATABASE','DB_USERNAME','DB_PASSWORD','APP_DIAGNOSTICS','APP_LOG_LEVEL','APP_LOG_REQUESTS','DIAGNOSTIC_TOKEN','REPOSITORY_PUBLIC_URL','GOOGLE_REDIRECT_URI','APPLE_REDIRECT_URI'];}
    private static function uuid(): string { $data=random_bytes(16);$data[6]=chr((ord($data[6])&0x0f)|0x40);$data[8]=chr((ord($data[8])&0x3f)|0x80);return vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($data),4)); }
    private function removeTree(string $dir): void{if(!is_dir($dir))return;$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($it as $f){$f->isDir()?@rmdir($f->getPathname()):@unlink($f->getPathname());}@rmdir($dir);}
}
