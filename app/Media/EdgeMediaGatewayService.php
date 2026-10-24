<?php
declare(strict_types=1);

final class EdgeMediaGatewayService
{
    private const PROVIDER_VERSION='1.20.0';
    private const CLOUDFLARED_VERSION='2026.8.2';
    private const CLOUDFLARED_WINDOWS_AMD64_SHA256='c29eee2b121f5436a642eed69fd9767da7e7b8c510fa50aaa130337f931357b5';
    private ?int $resolvedInstallationId=null;

    public function __construct(private PDO $pdo) {}

    private function assertEdge(): void
    {
        if(!InstallationMode::isEdge())throw new RuntimeException('El Media Gateway local solo está disponible en Property Edge Agent.');
    }

    private function installationId(): int
    {
        if(($this->resolvedInstallationId??0)>0)return (int)$this->resolvedInstallationId;

        // En Edge históricos el INSTANCE_UUID/código del proceso puede diferir de la
        // única fila bh_installations conservada en la base. El runtime de media debe
        // resolver exactamente igual que el laboratorio de conectores para que un
        // reinicio de Windows no deje cámaras/túneles sin instalación local.
        $uuid=trim((string)config('deployment.installation_uuid'));
        if($uuid!==''){
            $stmt=$this->pdo->prepare('SELECT id FROM bh_installations WHERE public_uuid=? LIMIT 1');
            $stmt->execute([$uuid]);$id=(int)$stmt->fetchColumn();
            if($id>0)return $this->resolvedInstallationId=$id;
        }
        $code=trim((string)config('app.instance_code'));
        if($code!==''){
            $stmt=$this->pdo->prepare('SELECT id FROM bh_installations WHERE code=? LIMIT 1');
            $stmt->execute([$code]);$id=(int)$stmt->fetchColumn();
            if($id>0)return $this->resolvedInstallationId=$id;
        }
        $rows=$this->pdo->query('SELECT id,public_uuid,code FROM bh_installations ORDER BY id')->fetchAll()?:[];
        if(count($rows)===1&&(int)($rows[0]['id']??0)>0){
            ErrorReporter::runtimeLog('edge_media_installation_fallback',[
                'agent_uuid'=>$uuid,'agent_code'=>$code,
                'database_installation_uuid'=>(string)($rows[0]['public_uuid']??''),
                'database_installation_code'=>(string)($rows[0]['code']??''),
            ]);
            return $this->resolvedInstallationId=(int)$rows[0]['id'];
        }
        $q=$this->pdo->query("SELECT i.id FROM bh_installations i WHERE EXISTS(SELECT 1 FROM bh_edge_cameras c WHERE c.installation_id=i.id) OR EXISTS(SELECT 1 FROM bh_connector_instances ci WHERE ci.installation_id=i.id) ORDER BY i.id");
        $ids=array_values(array_unique(array_map('intval',$q->fetchAll(PDO::FETCH_COLUMN)?:[])));
        if(count($ids)===1&&$ids[0]>0)return $this->resolvedInstallationId=$ids[0];
        throw new RuntimeException('No se encontró una instalación Edge local inequívoca para Media Gateway.');
    }

    private function mediaDir(): string
    {
        return base_path('storage/media');
    }

    public function state(): array
    {
        $this->assertEdge();$iid=$this->installationId();$this->ensureRow();
        $stmt=$this->pdo->prepare('SELECT * FROM bh_edge_media_gateway WHERE installation_id=? LIMIT 1');$stmt->execute([$iid]);$row=(array)($stmt->fetch()?:[]);
        $binary=$this->binaryPath();$config=$this->configPath();
        $row['binary_exists']=is_file($binary);$row['config_exists']=is_file($config);$row['binary_path']=$binary;$row['config_path']=$config;
        $row['cloudflared_exists']=is_file($this->cloudflaredPath());$row['cloudflared_path']=$this->cloudflaredPath();
        $row['relay']=$this->relayProvisioning();
        $meta=json_decode((string)($row['metadata_json']??''),true);if(!is_array($meta))$meta=[];
        $row['hls_tunnel_public_url']=rtrim(trim((string)($meta['hls_tunnel_public_url']??'')),'/');
        $row['watchdog']=(array)($meta['watchdog']??[]);
        $row['whep_health']=(array)($meta['whep_health']??[]);
        $row['hls_health']=(array)($meta['hls_health']??[]);
        $row['last_media_endpoint_report_at']=(string)($meta['last_media_endpoint_report_at']??'');
        $row['camera_counts']=$this->cameraCounts();
        $row['local_test_streams']=[];
        foreach($this->mediaRows() as $mediaRow){
            if(empty($mediaRow['webrtc_enabled']))continue;
            $path=trim((string)($mediaRow['webrtc_path']??''),'/');if($path==='')continue;
            $row['local_test_streams'][]=[
                'name'=>(string)($mediaRow['media_name']??'Cámara'),
                'path'=>$path,
                'codec'=>(string)($mediaRow['webrtc_codec']??''),
                'status'=>(string)($mediaRow['webrtc_status']??'pending'),
                'url'=>'http://127.0.0.1:8889/'.$path,
                'hls_url'=>'http://127.0.0.1:8888/'.$path,
            ];
        }
        return $row;
    }

    public function receiveProvisioning(array $relay,array $cameraAssignments=[]): array
    {
        $this->assertEdge();$this->ensureRow();$iid=$this->installationId();
        $safe=[
            'enabled'=>!empty($relay['enabled']),'media_mode'=>(string)($relay['media_mode']??'cpanel_test'),
            'relay_name'=>(string)($relay['relay_name']??'Property Hub Media Relay'),
            'webrtc_base_url'=>rtrim(trim((string)($relay['webrtc_base_url']??'')),'/'),
            'publish_rtsp_base_url'=>rtrim(trim((string)($relay['publish_rtsp_base_url']??'')),'/'),
            'publish_user'=>(string)($relay['publish_user']??''),
            'publish_password'=>(string)($relay['publish_password']??''),
            'viewer_user'=>(string)($relay['viewer_user']??''),
            'viewer_password'=>(string)($relay['viewer_password']??''),
            'status'=>(string)($relay['status']??'not_configured'),
            'received_at'=>date(DATE_ATOM),
        ];
        $enc=EdgeSecretStore::encrypt($safe);
        $this->pdo->prepare("UPDATE bh_edge_media_gateway SET relay_configuration_encrypted=?,status=CASE WHEN status='not_installed' THEN status ELSE 'configured' END,last_error=NULL WHERE installation_id=?")->execute([$enc,$iid]);
        foreach($cameraAssignments as $assignment){
            if(!is_array($assignment))continue;$uuid=trim((string)($assignment['camera_uuid']??''));if($uuid==='')continue;
            $enabled=!empty($assignment['live_enabled'])?1:0;$path=trim((string)($assignment['live_path']??''));
            $stmt=$this->pdo->prepare("UPDATE bh_edge_video_channels SET webrtc_enabled=?,webrtc_path=NULLIF(?,''),webrtc_status=CASE WHEN ?=0 THEN 'disabled' WHEN webrtc_status='ready' THEN 'ready' ELSE 'pending' END WHERE installation_id=? AND channel_uuid=?");
            $stmt->execute([$enabled,$path,$enabled,$iid,$uuid]);
            if($stmt->rowCount()===0){$this->pdo->prepare("UPDATE bh_edge_cameras SET webrtc_enabled=?,webrtc_path=NULLIF(?,''),webrtc_status=CASE WHEN ?=0 THEN 'disabled' WHEN webrtc_status='ready' THEN 'ready' ELSE 'pending' END WHERE installation_id=? AND camera_uuid=? AND deleted_at IS NULL")->execute([$enabled,$path,$enabled,$iid,$uuid]);}
        }
        return ['ok'=>true,'relay_enabled'=>$safe['enabled'],'camera_assignments'=>count($cameraAssignments)];
    }

    public function installOfficialBinary(): array
    {
        $this->assertEdge();
        if(PHP_OS_FAMILY!=='Windows')throw new RuntimeException('La descarga automática de Property Hub '.(string)config('app.version','0.14.60').' está preparada para Edge Agent Windows/XAMPP.');
        if(!extension_loaded('curl'))throw new RuntimeException('La extensión PHP cURL es necesaria para descargar MediaMTX.');
        if(!class_exists('ZipArchive'))throw new RuntimeException('La extensión PHP ZIP es necesaria para instalar MediaMTX.');
        $dir=$this->mediaDir();$binDir=$dir.'/bin';if(!is_dir($binDir)&&!@mkdir($binDir,0775,true)&&!is_dir($binDir))throw new RuntimeException('No se pudo crear storage/media/bin.');
        $url='https://github.com/bluenviron/mediamtx/releases/download/v'.self::PROVIDER_VERSION.'/mediamtx_v'.self::PROVIDER_VERSION.'_windows_amd64.zip';
        $zipPath=$dir.'/mediamtx-'.self::PROVIDER_VERSION.'.zip';$fp=fopen($zipPath,'wb');if(!$fp)throw new RuntimeException('No se pudo crear el archivo temporal de MediaMTX.');
        $ch=curl_init($url);if($ch===false){fclose($fp);throw new RuntimeException('No se pudo iniciar la descarga de MediaMTX.');}
        curl_setopt_array($ch,[CURLOPT_FILE=>$fp,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>180,CURLOPT_CONNECTTIMEOUT=>15,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_USERAGENT=>'2CAM-Property-Hub-Edge/0.14.32']);
        $ok=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$err=curl_error($ch);curl_close($ch);fclose($fp);
        if(!$ok||$status<200||$status>=300){@unlink($zipPath);throw new RuntimeException('No se pudo descargar MediaMTX desde el release oficial: HTTP '.$status.($err!==''?' · '.$err:''));}
        $filename=basename($url);$checksUrl='https://github.com/bluenviron/mediamtx/releases/download/v'.self::PROVIDER_VERSION.'/checksums.sha256';
        $cc=curl_init($checksUrl);if($cc===false){@unlink($zipPath);throw new RuntimeException('No se pudo iniciar la verificación SHA-256 de MediaMTX.');}
        curl_setopt_array($cc,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>30,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_USERAGENT=>'2CAM-Property-Hub-Edge/0.14.32']);
        $checksRaw=curl_exec($cc);$checksStatus=(int)curl_getinfo($cc,CURLINFO_RESPONSE_CODE);$checksErr=curl_error($cc);curl_close($cc);
        if(!is_string($checksRaw)||$checksStatus<200||$checksStatus>=300){@unlink($zipPath);throw new RuntimeException('No se pudo obtener checksums.sha256 del release oficial: HTTP '.$checksStatus.($checksErr!==''?' · '.$checksErr:''));}
        $expected='';foreach(preg_split('/\R/',trim($checksRaw))?:[] as $line){if(preg_match('/^([a-f0-9]{64})\s+\*?'.preg_quote($filename,'/').'$/i',trim((string)$line),$m)){$expected=strtolower((string)$m[1]);break;}}
        $archiveSha=strtolower((string)(hash_file('sha256',$zipPath)?:''));if($expected===''||$archiveSha===''||!hash_equals($expected,$archiveSha)){@unlink($zipPath);throw new RuntimeException('La descarga de MediaMTX no pasó la verificación SHA-256 oficial.');}
        $zip=new ZipArchive();if($zip->open($zipPath)!==true){@unlink($zipPath);throw new RuntimeException('El ZIP descargado de MediaMTX no se pudo abrir.');}
        $found=false;
        for($i=0;$i<$zip->numFiles;$i++){
            $name=(string)$zip->getNameIndex($i);if(strtolower(basename($name))==='mediamtx.exe'){$raw=$zip->getFromIndex($i);if($raw===false)continue;if(file_put_contents($binDir.'/mediamtx.exe',$raw)===false)throw new RuntimeException('No se pudo escribir mediamtx.exe.');$found=true;break;}
        }
        $zip->close();@unlink($zipPath);if(!$found)throw new RuntimeException('El release oficial no contenía mediamtx.exe.');
        $hash=(string)(hash_file('sha256',$binDir.'/mediamtx.exe')?:'');
        $this->ensureRow();$this->pdo->prepare("UPDATE bh_edge_media_gateway SET provider='mediamtx',provider_version=?,binary_path=?,status='stopped',last_error=NULL,metadata_json=? WHERE installation_id=?")
            ->execute([self::PROVIDER_VERSION,$this->relativePath($binDir.'/mediamtx.exe'),json_encode(['binary_sha256'=>$hash,'archive_sha256'=>$archiveSha,'archive_sha256_verified'=>true,'source'=>$url,'installed_at'=>date(DATE_ATOM)],JSON_UNESCAPED_SLASHES),$this->installationId()]);
        return ['ok'=>true,'version'=>self::PROVIDER_VERSION,'binary'=>$binDir.'/mediamtx.exe','sha256'=>$hash,'archive_sha256'=>$archiveSha,'verified'=>true,'source'=>$url];
    }

    public function generateConfiguration(): array
    {
        $this->assertEdge();$this->ensureRow();$iid=$this->installationId();$relay=$this->relayProvisioning();
        $mode=(string)($relay['media_mode']??'cpanel_test');
        $cameras=$this->mediaRows();$paths=[];$ready=0;$incompatible=0;$pending=0;
        foreach($cameras as $camera){
            $id=(int)$camera['media_id'];$type=(string)$camera['media_type'];$enabled=!empty($camera['webrtc_enabled']);$path=trim((string)($camera['webrtc_path']??''));
            if(!$enabled){$this->markMedia($type,$id,'disabled',(string)($camera['webrtc_codec']??''),'');continue;}
            if($path===''){$this->markMedia($type,$id,'pending',(string)($camera['webrtc_codec']??''),'El Property Node todavía no asignó un path de video.');$pending++;continue;}
            $rtsp=trim((string)($camera['rtsp_url']??''));if($rtsp===''){$this->markMedia($type,$id,'error','', 'El canal no tiene URI RTSP.');$pending++;continue;}
            $meta=json_decode((string)($camera['metadata_json']??''),true);if(!is_array($meta))$meta=[];
            $codec=trim((string)($camera['webrtc_codec']??''));if($codec==='')$codec=$this->detectCodec($meta);$codecUpper=strtoupper($codec);
            if($codecUpper==='H265'||$codecUpper==='HEVC'){$this->markMedia($type,$id,'incompatible',$codec,'La fuente seleccionada usa H265/HEVC. Seleccione un perfil H264 para máxima compatibilidad WebRTC.');$incompatible++;continue;}
            if($mode==='cpanel_test'){$this->markMedia($type,$id,'pending',$codec,'Modo snapshot cPanel activo. Cambie el Node a WebRTC directo Edge para video en vivo sin VPS.');$pending++;continue;}
            $source=$this->cameraSourceUrl($camera);
            if($mode==='edge_direct_webrtc'){
                $paths[$path]=['source'=>$source,'dest'=>'','media_id'=>$id,'media_type'=>$type,'codec'=>$codec];$ready++;continue;
            }
            if(empty($relay['enabled'])||trim((string)($relay['publish_rtsp_base_url']??''))===''){$this->markMedia($type,$id,'pending',$codec,'Property Node todavía no tiene Media Relay público configurado.');$pending++;continue;}
            $dest=$this->publishDestination($path,$relay);if($dest===''){$this->markMedia($type,$id,'error',$codec,'No se pudo construir el destino RTSP del Media Relay.');$pending++;continue;}
            $paths[$path]=['source'=>$source,'dest'=>$dest,'media_id'=>$id,'media_type'=>$type,'codec'=>$codec];$ready++;
        }

        $dir=$this->mediaDir();if(!is_dir($dir)&&!@mkdir($dir,0775,true)&&!is_dir($dir))throw new RuntimeException('No se pudo crear storage/media.');
        $log=str_replace('\\','/',base_path('storage/logs/mediamtx.log'));
        $lines=['# Generated by 2CAM Property Hub Edge 0.14.32 - do not edit manually.','logLevel: info','logDestinations: [file]','logFile: '.$this->yaml($log),'readTimeout: 10s','writeTimeout: 10s'];
        if($mode==='edge_direct_webrtc'){
            $viewerPass=(string)($relay['viewer_password']??'');
            if($viewerPass==='')throw new RuntimeException('El Node todavía no entregó la clave maestra de lectura. Guarde el modo WebRTC directo Edge en el Node y sincronice otra vez.');
            // Seguridad: ningún path de cámara queda con lectura anónima. Cada cámara/canal
            // recibe credenciales derivadas exclusivamente para su live_path. Conocer el URL
            // trycloudflare por sí solo no permite leer ningún stream.
            $lines[]='authMethod: internal';$lines[]='authInternalUsers:';
            foreach(array_keys($paths) as $authPath){
                $cred=$this->cameraViewerCredentials((string)$authPath,$viewerPass);
                $lines[]='  - user: '.$this->yaml($cred['user']);
                $lines[]='    pass: '.$this->yaml($cred['pass']);
                $lines[]='    ips: []';
                $lines[]='    permissions:';
                $lines[]='      - action: read';
                $lines[]='        path: '.$this->yaml((string)$authPath);
            }
            // La API de control solo escucha en localhost:9997 y no se expone por cloudflared.
            $lines[]='  - user: any';$lines[]='    pass:';$lines[]='    ips: ["127.0.0.1", "::1"]';$lines[]='    permissions:';$lines[]='      - action: api';
            $lines=array_merge($lines,[
                'rtsp: true','rtspTransports: [tcp]','rtspAddress: 127.0.0.1:8554','rtmp: false',
                'hls: true','hlsAddress: 127.0.0.1:8888','hlsEncryption: false','hlsAllowOrigins: ["*"]','hlsAlwaysRemux: false','hlsVariant: mpegts','hlsSegmentCount: 4','hlsSegmentDuration: 1s',
                'srt: false','moq: false','playback: false',
                'webrtc: true','webrtcAddress: 127.0.0.1:8889','webrtcEncryption: false','webrtcAllowOrigins: ["*"]',
                'webrtcLocalUDPAddress: :8189','webrtcLocalTCPAddress: :8190','webrtcIPsFromInterfaces: true','webrtcAdditionalHosts: []',
                'webrtcICEServers2:','  - url: stun:stun.l.google.com:19302',
                'api: true','apiAddress: 127.0.0.1:9997','apiEncryption: false','metrics: false','pprof: false','paths:'
            ]);
        }else{
            $lines=array_merge($lines,[
                'authMethod: internal','authInternalUsers:','  - user: any','    pass:','    ips: ["127.0.0.1", "::1"]','    permissions:','      - action: read','      - action: publish','      - action: api',
                'rtsp: true','rtspTransports: [tcp]','rtspAddress: 127.0.0.1:8554','rtmp: false',
                'hls: true','hlsAddress: 127.0.0.1:8888','hlsEncryption: false','hlsAllowOrigins: ["*"]','hlsAlwaysRemux: false','hlsVariant: mpegts','hlsSegmentCount: 4','hlsSegmentDuration: 1s',
                'srt: false','moq: false','playback: false',
                'webrtc: true','webrtcAddress: 127.0.0.1:8889','webrtcEncryption: false','webrtcLocalUDPAddress: 127.0.0.1:8189','webrtcLocalTCPAddress: 127.0.0.1:8190','webrtcIPsFromInterfaces: false','webrtcAdditionalHosts: [127.0.0.1]',
                'api: true','apiAddress: 127.0.0.1:9997','apiEncryption: false','metrics: false','pprof: false','paths:'
            ]);
        }
        if($paths===[]){$lines[]='  all_others:';$lines[]='    source: publisher';}
        foreach($paths as $path=>$cfg){
            $lines[]='  '.$this->yamlKey($path).':';$lines[]='    source: '.$this->yaml($cfg['source']);$lines[]='    sourceOnDemand: false';$lines[]='    rtspTransport: tcp';
            if($mode==='external_webrtc'&&$cfg['dest']!==''){$lines[]='    forward:';$lines[]='      - dest: '.$this->yaml($cfg['dest']);}
        }
        $config=implode("\r\n",$lines)."\r\n";$path=$this->configPath();if(file_put_contents($path,$config,LOCK_EX)===false)throw new RuntimeException('No se pudo escribir mediamtx.yml.');
        foreach($paths as $cfg)$this->markMedia((string)$cfg['media_type'],(int)$cfg['media_id'],'pending',(string)$cfg['codec'],'Gateway configurado; esperando fuente RTSP.');
        // No reemplazar metadata_json completo: allí viven también los endpoints HLS,
        // estado del watchdog y datos de recuperación. 0.14.24 podía borrar la URL HLS
        // al regenerar mediamtx.yml durante una sincronización de cámaras.
        $this->mergeGatewayMetadata(['paths'=>array_keys($paths),'generated_at'=>date(DATE_ATOM),'ready'=>$ready,'incompatible'=>$incompatible,'pending'=>$pending,'media_mode'=>$mode]);
        $this->pdo->prepare("UPDATE bh_edge_media_gateway SET provider='mediamtx',provider_version=?,binary_path=?,config_path=?,status=CASE WHEN status='running' THEN status ELSE 'configured' END,last_error=NULL WHERE installation_id=?")
            ->execute([self::PROVIDER_VERSION,$this->relativePath($this->binaryPath()),$this->relativePath($path),$iid]);
        $this->writeRunnerFiles();
        return ['ok'=>true,'config_path'=>$path,'ready'=>$ready,'incompatible'=>$incompatible,'pending'=>$pending,'paths'=>array_keys($paths),'relay_enabled'=>!empty($relay['enabled']),'media_mode'=>$mode];
    }

    public function start(): array
    {
        $this->assertEdge();if(!is_file($this->binaryPath()))throw new RuntimeException('MediaMTX todavía no está instalado. Use Instalar Media Gateway.');
        if(!is_file($this->configPath()))$this->generateConfiguration();
        $current=$this->checkStatus(false);if(!empty($current['ok']))return $current;
        if(PHP_OS_FAMILY==='Windows'){
            $exe=str_replace('/','\\',$this->binaryPath());$cfg=str_replace('/','\\',$this->configPath());
            $cmd='cmd /c start "Property Hub MediaMTX" /MIN "'.$exe.'" "'.$cfg.'"';
            @pclose(@popen($cmd,'r'));
        }else{
            @exec('nohup '.escapeshellarg($this->binaryPath()).' '.escapeshellarg($this->configPath()).' >/dev/null 2>&1 &');
        }
        $last=[];for($i=0;$i<10;$i++){usleep(500000);$last=$this->checkStatus(true);if(!empty($last['ok']))break;}
        return $last?:$this->checkStatus(true);
    }

    private function restartAfterConfiguration(): array
    {
        // En laboratorio necesitamos estar seguros de que el proceso activo tomó
        // los listeners ICE nuevos. start() por sí solo retorna si ya hay un
        // MediaMTX vivo, por lo que una config recién generada podía quedar sin
        // aplicarse hasta el siguiente reinicio del servidor.
        if(PHP_OS_FAMILY==='Windows'){
            @exec('taskkill /F /IM mediamtx.exe >NUL 2>&1');
            usleep(700000);
        }
        return $this->start();
    }

    public function checkStatus(bool $persist=true): array
    {
        $this->assertEdge();$this->ensureRow();$binary=is_file($this->binaryPath());$config=is_file($this->configPath());$running=false;$error='';$paths=[];$readyNames=[];
        if($binary&&$config&&extension_loaded('curl')){
            $ch=curl_init('http://127.0.0.1:9997/v3/paths/list');
            if($ch!==false){
                curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>3,CURLOPT_CONNECTTIMEOUT=>2]);$raw=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$err=curl_error($ch);curl_close($ch);
                if($status>=200&&$status<300){
                    $running=true;$decoded=json_decode((string)$raw,true);if(is_array($decoded))$paths=(array)($decoded['items']??[]);
                    foreach($paths as $item){if(!is_array($item))continue;$name=trim((string)($item['name']??''));if($name!==''&&!empty($item['ready']))$readyNames[$name]=true;}
                }elseif($err!=='')$error=$err;else $error='MediaMTX Control API respondió HTTP '.$status;
            }
        }elseif(!$binary)$error='MediaMTX no está instalado.';elseif(!$config)$error='MediaMTX todavía no tiene configuración.';elseif(!extension_loaded('curl'))$error='PHP cURL no está activo.';
        $state=!$binary?'not_installed':($running?'running':($config?'stopped':'configured'));
        if($running){
            foreach($this->mediaRows() as $camera){
                if(empty($camera['webrtc_enabled']))continue;$path=trim((string)($camera['webrtc_path']??''));if($path==='')continue;$type=(string)$camera['media_type'];$id=(int)$camera['media_id'];
                if(isset($readyNames[$path]))$this->markMedia($type,$id,'ready',(string)($camera['webrtc_codec']??''),'');
                else $this->markMedia($type,$id,'pending',(string)($camera['webrtc_codec']??''),'MediaMTX está activo, pero la fuente RTSP todavía no está lista.');
            }
        }
        if($persist)$this->pdo->prepare("UPDATE bh_edge_media_gateway SET status=?,last_check_at=NOW(),last_error=NULLIF(?,'') WHERE installation_id=?")->execute([$state,$error,$this->installationId()]);
        return ['ok'=>$running,'status'=>$state,'binary_exists'=>$binary,'config_exists'=>$config,'path_count'=>count($paths),'ready_paths'=>array_keys($readyNames),'error'=>$error];
    }

    public function prepareCpanelDirectWebrtc(): array
    {
        $this->assertEdge();
        try{EdgeNodeClient::refreshProvisioning();}catch(Throwable $e){ErrorReporter::report($e,['operation'=>'edge_media_refresh_before_direct']);}
        // Recibir también las asignaciones por cámara (live_enabled/live_path) antes
        // de generar mediamtx.yml. Así el primer clic funciona aunque el scheduler
        // todavía no haya corrido después de cambiar el modo en el Node.
        try{EdgeNodeClient::syncCameras(false);}catch(Throwable $e){ErrorReporter::report($e,['operation'=>'edge_media_assignments_before_direct']);}
        $relay=$this->relayProvisioning();if((string)($relay['media_mode']??'')!=='edge_direct_webrtc')throw new RuntimeException('En PREMIER-LM cambie Video remoto a “WebRTC directo desde Edge · sin VPS” y guarde antes de iniciar esta prueba.');
        if(!is_file($this->binaryPath()))$this->installOfficialBinary();
        if(!is_file($this->cloudflaredPath()))$this->installCloudflared();
        $this->generateConfiguration();$media=$this->restartAfterConfiguration();if(empty($media['ok']))throw new RuntimeException('MediaMTX no pudo iniciar con la configuración nueva: '.(string)($media['error']??'sin detalle'));
        $tunnel=$this->startQuickTunnel();
        $hlsTunnel=$this->startHlsQuickTunnel();
        $reported=EdgeNodeClient::reportMediaEndpoint((string)$tunnel['public_base_url'],'quick_tunnel',(string)$hlsTunnel['public_base_url']);
        try{EdgeNodeClient::syncCameras(false);}catch(Throwable $e){ErrorReporter::report($e,['operation'=>'edge_media_direct_sync']);}
        return ['ok'=>true,'media'=>$media,'tunnel'=>$tunnel,'hls_tunnel'=>$hlsTunnel,'node'=>$reported];
    }


    /**
     * Supervisor autocurativo para el runtime de video del Edge.
     *
     * - MediaMTX debe volver a iniciar después de un reboot.
     * - Los Quick Tunnels son efímeros: si DNS/Cloudflare/origen deja de responder,
     *   se crea una URL nueva y se reporta inmediatamente al Property Node.
     * - Si todo está sano, refresca periódicamente el endpoint en el Node para que
     *   nunca quede apuntando durante horas a una URL histórica.
     */
    public function watchdog(bool $allowRecovery=true): array
    {
        $this->assertEdge();$this->ensureRow();
        $relay=$this->relayProvisioning();$mode=(string)($relay['media_mode']??'cpanel_test');
        if($mode!=='edge_direct_webrtc')return ['ok'=>true,'skipped'=>true,'reason'=>'media_mode_'.$mode];

        $runDir=base_path('storage/run');if(!is_dir($runDir))@mkdir($runDir,0775,true);
        $lock=@fopen($runDir.'/edge-media-watchdog.lock','c+');
        if(!$lock||!@flock($lock,LOCK_EX|LOCK_NB)){if(is_resource($lock))@fclose($lock);return ['ok'=>true,'busy'=>true,'message'=>'Media watchdog ya está ejecutándose.'];}

        $started=microtime(true);$recovered=[];$errors=[];$media=['ok'=>false];$whepHealth=[];$hlsHealth=[];$reported=false;
        try{
            if(!is_file($this->binaryPath())){
                if(!$allowRecovery)throw new RuntimeException('MediaMTX no está instalado.');
                $this->installOfficialBinary();$recovered[]='mediamtx_installed';
            }
            if(!is_file($this->configPath())){
                $this->generateConfiguration();$recovered[]='configuration_generated';
            }
            $media=$this->checkStatus(true);
            if(empty($media['ok'])&&$allowRecovery){$media=$this->start();if(!empty($media['ok']))$recovered[]='mediamtx_started';}
            if(empty($media['ok']))throw new RuntimeException('MediaMTX no está operativo: '.(string)($media['error']??'sin detalle'));

            if(!is_file($this->cloudflaredPath())){
                if(!$allowRecovery)throw new RuntimeException('cloudflared no está instalado.');
                $this->installCloudflared();$recovered[]='cloudflared_installed';
            }

            $row=$this->gatewayRow();$meta=$this->gatewayMetadata($row);
            $whepUrl=rtrim(trim((string)($row['tunnel_public_url']??'')),'/');
            $hlsUrl=rtrim(trim((string)($meta['hls_tunnel_public_url']??'')),'/');
            $whepHealth=$this->probePublicEndpoint($whepUrl,'webrtc');
            $hlsHealth=$this->probePublicEndpoint($hlsUrl,'hls');

            if(empty($whepHealth['ok'])&&$allowRecovery){
                try{$new=$this->startQuickTunnel();$whepUrl=(string)$new['public_base_url'];$recovered[]='whep_tunnel_restarted';$whepHealth=$this->probePublicEndpoint($whepUrl,'webrtc',true);}
                catch(Throwable $tunnelError){$errors[]='WHEP: '.$tunnelError->getMessage();$whepHealth['recovery_error']=$tunnelError->getMessage();ErrorReporter::report($tunnelError,['operation'=>'edge_media_watchdog_whep_recover']);}
            }
            if(empty($hlsHealth['ok'])&&$allowRecovery){
                try{$new=$this->startHlsQuickTunnel();$hlsUrl=(string)$new['public_base_url'];$recovered[]='hls_tunnel_restarted';$hlsHealth=$this->probePublicEndpoint($hlsUrl,'hls',true);}
                catch(Throwable $tunnelError){$errors[]='HLS: '.$tunnelError->getMessage();$hlsHealth['recovery_error']=$tunnelError->getMessage();ErrorReporter::report($tunnelError,['operation'=>'edge_media_watchdog_hls_recover']);}
            }

            $meta=$this->gatewayMetadata();
            $lastReport=(string)($meta['last_media_endpoint_report_at']??'');$lastReportTs=$lastReport!==''?(strtotime($lastReport)?:0):0;
            $mustReport=$whepUrl!==''&&($recovered!==[]||$lastReportTs===0||(time()-$lastReportTs)>=90);
            if($mustReport){
                // Si HLS no pudo recuperarse, se limpia en el Node en vez de dejar una
                // URL histórica que fuerce al móvil a esperar contra un hostname muerto.
                EdgeNodeClient::reportMediaEndpoint($whepUrl,'quick_tunnel',!empty($hlsHealth['ok'])?$hlsUrl:'');$reported=true;
                $this->mergeGatewayMetadata(['last_media_endpoint_report_at'=>date(DATE_ATOM)]);
            }

            $allOk=!empty($media['ok'])&&!empty($whepHealth['ok'])&&!empty($hlsHealth['ok']);
            $watch=['ok'=>$allOk,'checked_at'=>date(DATE_ATOM),'recovered'=>$recovered,'reported_to_node'=>$reported,'latency_ms'=>(int)round((microtime(true)-$started)*1000)];
            if($recovered!==[])$watch['last_recovery_at']=date(DATE_ATOM);
            $this->mergeGatewayMetadata(['watchdog'=>$watch,'whep_health'=>$whepHealth,'hls_health'=>$hlsHealth]);
            $this->pdo->prepare("UPDATE bh_edge_media_gateway SET tunnel_status=?,last_error=NULL WHERE installation_id=?")->execute([$allOk?'online':'offline',$this->installationId()]);
            ErrorReporter::runtimeLog('edge_media_watchdog',['ok'=>$allOk,'recovered'=>$recovered,'reported_to_node'=>$reported,'whep_host'=>(string)(parse_url($whepUrl,PHP_URL_HOST)?:''),'hls_host'=>(string)(parse_url($hlsUrl,PHP_URL_HOST)?:''),'latency_ms'=>$watch['latency_ms']]);
            return ['ok'=>$allOk,'media'=>$media,'whep'=>$whepHealth,'hls'=>$hlsHealth,'recovered'=>$recovered,'errors'=>$errors,'reported_to_node'=>$reported,'watchdog'=>$watch];
        }catch(Throwable $e){
            $errors[]=$e->getMessage();$watch=['ok'=>false,'checked_at'=>date(DATE_ATOM),'recovered'=>$recovered,'error'=>$e->getMessage(),'latency_ms'=>(int)round((microtime(true)-$started)*1000)];
            try{$this->mergeGatewayMetadata(['watchdog'=>$watch,'whep_health'=>$whepHealth,'hls_health'=>$hlsHealth]);$this->pdo->prepare("UPDATE bh_edge_media_gateway SET tunnel_status='error',last_error=? WHERE installation_id=?")->execute([$e->getMessage(),$this->installationId()]);}catch(Throwable){}
            ErrorReporter::report($e,['operation'=>'edge_media_watchdog','recovered'=>$recovered]);
            return ['ok'=>false,'media'=>$media,'whep'=>$whepHealth,'hls'=>$hlsHealth,'recovered'=>$recovered,'errors'=>$errors,'watchdog'=>$watch];
        }finally{
            @flock($lock,LOCK_UN);@fclose($lock);
        }
    }

    private function probePublicEndpoint(string $url,string $kind,bool $fresh=false): array
    {
        $url=rtrim(trim($url),'/');$checked=date(DATE_ATOM);
        if($url==='')return ['ok'=>false,'kind'=>$kind,'status'=>'missing','http_status'=>0,'checked_at'=>$checked,'error'=>'Sin URL pública.'];
        $host=(string)(parse_url($url,PHP_URL_HOST)?:'');
        if($host==='')return ['ok'=>false,'kind'=>$kind,'status'=>'invalid','http_status'=>0,'checked_at'=>$checked,'error'=>'URL pública inválida.'];
        if(!extension_loaded('curl'))return ['ok'=>false,'kind'=>$kind,'status'=>'unknown','http_status'=>0,'checked_at'=>$checked,'error'=>'PHP cURL no disponible.'];
        $ch=curl_init($url.'/');if($ch===false)return ['ok'=>false,'kind'=>$kind,'status'=>'error','http_status'=>0,'checked_at'=>$checked,'error'=>'No se pudo iniciar cURL.'];
        curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_TIMEOUT=>$fresh?8:5,CURLOPT_CONNECTTIMEOUT=>$fresh?5:3,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_HTTPHEADER=>['Cache-Control: no-cache','Pragma: no-cache'],CURLOPT_USERAGENT=>'2CAM-Property-Hub-Media-Watchdog/'.(string)config('app.version')]);
        $body=curl_exec($ch);$http=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$err=curl_error($ch);curl_close($ch);
        // 401/404 son válidos aquí: demuestran que Cloudflare llegó al MediaMTX local.
        // 5xx/530, fallo DNS o conexión 0 indican que el Quick Tunnel ya no sirve.
        $ok=$body!==false&&$http>=200&&$http<500;
        return ['ok'=>$ok,'kind'=>$kind,'status'=>$ok?'online':'offline','http_status'=>$http,'checked_at'=>$checked,'host'=>$host,'error'=>$ok?'':($err!==''?$err:'HTTP '.$http)];
    }

    private function gatewayRow(): array
    {
        $this->ensureRow();$stmt=$this->pdo->prepare('SELECT * FROM bh_edge_media_gateway WHERE installation_id=? LIMIT 1');$stmt->execute([$this->installationId()]);return (array)($stmt->fetch()?:[]);
    }

    private function gatewayMetadata(?array $row=null): array
    {
        $row=$row??$this->gatewayRow();$meta=json_decode((string)($row['metadata_json']??''),true);return is_array($meta)?$meta:[];
    }


    private function cameraViewerCredentials(string $path,string $masterPass): array
    {
        $path=trim($path,'/');
        if($path===''||$masterPass==='')return ['user'=>'','pass'=>''];
        $user='phcam_'.substr(hash('sha256',$path),0,16);
        $raw=hash_hmac('sha256','property-hub-media-read|'.$path,$masterPass,true);
        $pass=rtrim(strtr(base64_encode($raw),'+/','-_'),'=');
        return ['user'=>$user,'pass'=>$pass];
    }

    public function installCloudflared(): array
    {
        $this->assertEdge();if(PHP_OS_FAMILY!=='Windows')throw new RuntimeException('La instalación automática de cloudflared está preparada para Edge Windows/XAMPP.');if(!extension_loaded('curl'))throw new RuntimeException('PHP cURL es necesario para descargar cloudflared.');
        $dir=dirname($this->cloudflaredPath());if(!is_dir($dir)&&!@mkdir($dir,0775,true)&&!is_dir($dir))throw new RuntimeException('No se pudo crear storage/media/bin.');
        $url='https://github.com/cloudflare/cloudflared/releases/download/'.self::CLOUDFLARED_VERSION.'/cloudflared-windows-amd64.exe';$tmp=$this->cloudflaredPath().'.download';$fp=fopen($tmp,'wb');if(!$fp)throw new RuntimeException('No se pudo crear la descarga temporal de cloudflared.');
        $ch=curl_init($url);if($ch===false){fclose($fp);throw new RuntimeException('No se pudo iniciar cURL para cloudflared.');}curl_setopt_array($ch,[CURLOPT_FILE=>$fp,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_TIMEOUT=>180,CURLOPT_CONNECTTIMEOUT=>15,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_USERAGENT=>'2CAM-Property-Hub-Edge/0.14.32']);$ok=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$err=curl_error($ch);curl_close($ch);fclose($fp);
        if(!$ok||$status<200||$status>=300){@unlink($tmp);throw new RuntimeException('No se pudo descargar cloudflared desde el release oficial: HTTP '.$status.($err!==''?' · '.$err:''));}
        if(filesize($tmp)<1000000){@unlink($tmp);throw new RuntimeException('La descarga de cloudflared es demasiado pequeña y fue rechazada.');}
        $sha=strtolower((string)(hash_file('sha256',$tmp)?:''));if($sha===''||!hash_equals(self::CLOUDFLARED_WINDOWS_AMD64_SHA256,$sha)){@unlink($tmp);throw new RuntimeException('La descarga de cloudflared '.self::CLOUDFLARED_VERSION.' no pasó la verificación SHA-256 oficial.');}
        if(!@rename($tmp,$this->cloudflaredPath())){if(!@copy($tmp,$this->cloudflaredPath()))throw new RuntimeException('No se pudo instalar cloudflared.exe.');@unlink($tmp);}
        return ['ok'=>true,'binary'=>$this->cloudflaredPath(),'version'=>self::CLOUDFLARED_VERSION,'sha256'=>$sha,'sha256_verified'=>true,'source'=>$url];
    }

    public function startQuickTunnel(): array
    {
        $r=$this->startQuickTunnelForPort(8889,'webrtc','cloudflared-media.log','media-webrtc-quick-tunnel.cmd');
        $this->pdo->prepare("UPDATE bh_edge_media_gateway SET tunnel_provider='cloudflare_quick',tunnel_public_url=?,tunnel_status='online',tunnel_last_started_at=NOW(),last_error=NULL WHERE installation_id=?")->execute([(string)$r['public_base_url'],$this->installationId()]);
        return $r;
    }

    public function startHlsQuickTunnel(): array
    {
        $r=$this->startQuickTunnelForPort(8888,'hls','cloudflared-hls.log','media-hls-quick-tunnel.cmd');
        $this->mergeGatewayMetadata(['hls_tunnel_public_url'=>(string)$r['public_base_url'],'hls_tunnel_provider'=>'cloudflare_quick','hls_tunnel_started_at'=>date(DATE_ATOM)]);
        return $r;
    }

    private function startQuickTunnelForPort(int $port,string $kind,string $logName,string $runnerName): array
    {
        $this->assertEdge();if(!is_file($this->cloudflaredPath()))throw new RuntimeException('cloudflared todavía no está instalado.');
        $log=base_path('storage/logs/'.$logName);@file_put_contents($log,'');$runner=base_path('tools/'.$runnerName);$exe=str_replace('/','\\',$this->cloudflaredPath());$logWin=str_replace('/','\\',$log);
        $body="@echo off\r\nsetlocal\r\n\"{$exe}\" tunnel --no-autoupdate --url http://127.0.0.1:{$port} > \"{$logWin}\" 2>&1\r\n";@file_put_contents($runner,$body,LOCK_EX);$runnerWin=str_replace('/','\\',$runner);@pclose(@popen('cmd /c start "" /MIN "'.$runnerWin.'"','r'));
        $url='';for($i=0;$i<30;$i++){usleep(500000);$raw=is_file($log)?(string)@file_get_contents($log):'';if(preg_match('#https://[a-z0-9-]+\\.trycloudflare\\.com#i',$raw,$m)){$url=rtrim((string)$m[0],'/');break;}}
        if($url===''){$tail=is_file($log)?substr((string)@file_get_contents($log),-1200):'';throw new RuntimeException('Cloudflare Quick Tunnel '.$kind.' no entregó una URL pública. Revise '.$logName.'. '.$tail);}
        return ['ok'=>true,'public_base_url'=>$url,'provider'=>'cloudflare_quick','kind'=>$kind,'port'=>$port,'log'=>$log];
    }

    private function mergeGatewayMetadata(array $changes): void
    {
        $stmt=$this->pdo->prepare('SELECT metadata_json FROM bh_edge_media_gateway WHERE installation_id=? LIMIT 1');$stmt->execute([$this->installationId()]);$meta=json_decode((string)($stmt->fetchColumn()?:''),true);if(!is_array($meta))$meta=[];
        foreach($changes as $k=>$v)$meta[$k]=$v;
        $this->pdo->prepare('UPDATE bh_edge_media_gateway SET metadata_json=? WHERE installation_id=?')->execute([json_encode($meta,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),$this->installationId()]);
    }

    public function taskCommand(): string
    {
        return base_path('tools/install-media-gateway-task.bat');
    }

    private function ensureRow(): void
    {
        $iid=$this->installationId();$this->pdo->prepare("INSERT INTO bh_edge_media_gateway (installation_id,provider,provider_version,binary_path,config_path,status) VALUES (?,'mediamtx',?,?,?,'not_installed') ON DUPLICATE KEY UPDATE installation_id=VALUES(installation_id)")
            ->execute([$iid,self::PROVIDER_VERSION,$this->relativePath($this->binaryPath()),$this->relativePath($this->configPath())]);
    }

    private function relayProvisioning(): array
    {
        $this->ensureRow();$stmt=$this->pdo->prepare('SELECT relay_configuration_encrypted FROM bh_edge_media_gateway WHERE installation_id=? LIMIT 1');$stmt->execute([$this->installationId()]);
        return EdgeSecretStore::decrypt((string)($stmt->fetchColumn()?:''));
    }

    private function cameraCounts(): array
    {
        $rows=$this->mediaRows();$out=['total'=>count($rows),'enabled'=>0,'ready'=>0,'incompatible'=>0,'errors'=>0];
        foreach($rows as $row){if(!empty($row['webrtc_enabled']))$out['enabled']++;if(($row['webrtc_status']??'')==='ready')$out['ready']++;if(($row['webrtc_status']??'')==='incompatible')$out['incompatible']++;if(($row['webrtc_status']??'')==='error')$out['errors']++;}return $out;
    }

    private function mediaRows(): array
    {
        $iid=$this->installationId();$rows=[];
        $sql="SELECT ch.id media_id,'channel' media_type,ch.channel_uuid media_uuid,ch.channel_name media_name,ch.stream_uri rtsp_url,ch.metadata_json,ch.webrtc_enabled,ch.webrtc_path,ch.webrtc_codec,ch.webrtc_status,ch.webrtc_error,c.configuration_encrypted FROM bh_edge_video_channels ch JOIN bh_edge_cameras c ON c.id=ch.edge_camera_id WHERE ch.installation_id=? AND c.deleted_at IS NULL AND ch.enabled=1 AND ch.status<>'disabled' ORDER BY c.name,ch.channel_index";
        $stmt=$this->pdo->prepare($sql);$stmt->execute([$iid]);$rows=$stmt->fetchAll()?:[];
        $legacy=$this->pdo->prepare("SELECT c.id media_id,'source' media_type,c.camera_uuid media_uuid,c.name media_name,c.rtsp_url,c.metadata_json,c.webrtc_enabled,c.webrtc_path,c.webrtc_codec,c.webrtc_status,c.webrtc_error,c.configuration_encrypted FROM bh_edge_cameras c WHERE c.installation_id=? AND c.deleted_at IS NULL AND NOT EXISTS (SELECT 1 FROM bh_edge_video_channels ch WHERE ch.edge_camera_id=c.id)");$legacy->execute([$iid]);
        return array_merge($rows,$legacy->fetchAll()?:[]);
    }

    private function binaryPath(): string { return $this->mediaDir().'/bin/mediamtx.exe'; }
    private function cloudflaredPath(): string { return $this->mediaDir().'/bin/cloudflared.exe'; }
    private function tunnelLogPath(): string { return base_path('storage/logs/cloudflared-media.log'); }
    private function configPath(): string { return $this->mediaDir().'/mediamtx.yml'; }

    private function markMedia(string $type,int $id,string $status,string $codec,string $error): void
    {
        $table=$type==='channel'?'bh_edge_video_channels':'bh_edge_cameras';
        $this->pdo->prepare("UPDATE {$table} SET webrtc_status=?,webrtc_codec=NULLIF(?,''),webrtc_last_test_at=NOW(),webrtc_error=NULLIF(?,'') WHERE id=? AND installation_id=?")
            ->execute([$status,$codec,$error,$id,$this->installationId()]);
    }

    private function detectCodec(array $meta): string
    {
        $selected=(array)($meta['webrtc_selected_profile']??[]);$codec=trim((string)($selected['encoding']??''));if($codec!=='')return $codec;
        foreach((array)($meta['profiles']??[]) as $profile){if(!is_array($profile))continue;$enc=trim((string)($profile['encoding']??''));if($enc!=='')return $enc;}
        return '';
    }

    private function cameraSourceUrl(array $camera): string
    {
        $url=trim((string)($camera['rtsp_url']??''));$secret=EdgeSecretStore::decrypt((string)($camera['configuration_encrypted']??''));$user=(string)($secret['username']??'');$pass=(string)($secret['password']??'');
        $parts=parse_url($url);if(!is_array($parts)||empty($parts['host'])||isset($parts['user'])||$user==='')return $url;
        $scheme=(string)($parts['scheme']??'rtsp');$host=(string)$parts['host'];$port=isset($parts['port'])?':'.(int)$parts['port']:'';$path=(string)($parts['path']??'');$query=isset($parts['query'])?'?'.$parts['query']:'';
        return $scheme.'://'.rawurlencode($user).':'.rawurlencode($pass).'@'.$host.$port.$path.$query;
    }

    private function publishDestination(string $path,array $relay): string
    {
        $base=rtrim((string)($relay['publish_rtsp_base_url']??''),'/');$parts=parse_url($base);if(!is_array($parts)||empty($parts['host']))return '';
        $scheme=(string)($parts['scheme']??'rtsp');$host=(string)$parts['host'];$port=isset($parts['port'])?':'.(int)$parts['port']:'';$basePath=trim((string)($parts['path']??''),'/');
        $user=(string)($relay['publish_user']??'');$pass=(string)($relay['publish_password']??'');$auth=$user!==''?rawurlencode($user).':'.rawurlencode($pass).'@':'';
        return $scheme.'://'.$auth.$host.$port.'/'.trim($basePath.'/'.$path,'/');
    }

    private function writeRunnerFiles(): void
    {
        $tools=base_path('tools');$runner=$tools.'/media-gateway-runner.cmd';$task=$tools.'/install-media-gateway-task.bat';$exe=str_replace('/','\\',$this->binaryPath());$cfg=str_replace('/','\\',$this->configPath());
        $cmd="@echo off\r\nsetlocal\r\n\"{$exe}\" \"{$cfg}\"\r\n";@file_put_contents($runner,$cmd,LOCK_EX);
        $taskName='2CAM Property Hub Media Gateway - '.preg_replace('/[^A-Za-z0-9_-]/','-',(string)config('app.instance_code'));
        $runnerWin=str_replace('/','\\',$runner);
        $bat="@echo off\r\nsetlocal\r\nnet session >nul 2>&1\r\nif errorlevel 1 (echo EJECUTE ESTE ARCHIVO COMO ADMINISTRADOR.& pause & exit /b 1)\r\nschtasks /Delete /TN \"{$taskName}\" /F >nul 2>&1\r\nschtasks /Create /TN \"{$taskName}\" /SC ONSTART /RU SYSTEM /RL HIGHEST /TR \"cmd.exe /c \\\"{$runnerWin}\\\"\" /F\r\nif errorlevel 1 (echo No se pudo crear la tarea del Media Gateway.& pause & exit /b 1)\r\nschtasks /Run /TN \"{$taskName}\"\r\necho.\r\necho Media Gateway instalado: {$taskName}\r\npause\r\n";@file_put_contents($task,$bat,LOCK_EX);
        $firewall=$tools.'/configure-media-gateway-firewall.bat';
        $fw="@echo off\r\nsetlocal\r\nnet session >nul 2>&1\r\nif errorlevel 1 (echo EJECUTE ESTE ARCHIVO COMO ADMINISTRADOR.& pause & exit /b 1)\r\nnetsh advfirewall firewall delete rule name=\"2CAM Property Hub WebRTC UDP 8189\" >nul 2>&1\r\nnetsh advfirewall firewall delete rule name=\"2CAM Property Hub WebRTC TCP 8190\" >nul 2>&1\r\nnetsh advfirewall firewall add rule name=\"2CAM Property Hub WebRTC UDP 8189\" dir=in action=allow program=\"{$exe}\" protocol=UDP localport=8189 profile=any\r\nnetsh advfirewall firewall add rule name=\"2CAM Property Hub WebRTC TCP 8190\" dir=in action=allow program=\"{$exe}\" protocol=TCP localport=8190 profile=any\r\necho.\r\necho Firewall WebRTC configurado para MediaMTX.\r\npause\r\n";@file_put_contents($firewall,$fw,LOCK_EX);
    }

    private function yaml(string $value): string { return json_encode($value,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?: '""'; }
    private function yamlKey(string $value): string { return $this->yaml($value); }
    private function relativePath(string $path): string { $base=rtrim(str_replace('\\','/',base_path()),'/').'/';$norm=str_replace('\\','/',$path);return str_starts_with($norm,$base)?substr($norm,strlen($base)):$norm; }
}
