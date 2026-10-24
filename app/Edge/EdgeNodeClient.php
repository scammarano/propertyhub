<?php
declare(strict_types=1);

final class EdgeNodeClient
{
    private static function parentConnection(): array
    {
        $registry=new ConnectionRegistry();try{$registry->importLegacyIfNeeded();}catch(Throwable $e){ErrorReporter::report($e,['operation'=>'edge_parent_import']);}
        return $registry->parentNode();
    }

    private static function cacheLocalIntegrations(array $response,array $conn,string $stampKey='last_config_at'): void
    {
        if(!array_key_exists('local_integrations',$response))return;
        try{
            $registry=new ConnectionRegistry();$registry->save('parent_node',(string)($response['_base_url']??$conn['endpoint_url']??''),(string)($conn['credential']??''),(string)($response['property_code']??$conn['remote_code']??''),'property_node','online',[
                $stampKey=>date(DATE_ATOM),'local_integrations'=>(array)($response['local_integrations']??[]),'update_distribution'=>(array)($response['update_distribution']??[]),
            ]);
        }catch(Throwable $e){ErrorReporter::report($e,['operation'=>'edge_cache_local_integrations']);}
    }

    public static function probe(string $nodeUrl): array
    {
        if(!InstallationMode::isEdge())throw new RuntimeException('La prueba de Property Node solo corresponde a un Edge Agent.');
        $r=EdgeHttp::postJson($nodeUrl,'api/edge/v1/ping.php',['agent_code'=>(string)config('app.instance_code')],[],12);
        if(empty($r['ok']) || (string)($r['instance_role']??'')!=='building_node')throw new RuntimeException('La URL indicada no corresponde a un Property Node.');
        $actual=strtoupper(trim((string)($r['instance_code']??'')));if($actual==='')throw new RuntimeException('El Property Node no informó su código de instalación.');
        return $r;
    }

    public static function pair(string $nodeUrl,string $token): array
    {
        if(!InstallationMode::isEdge())throw new RuntimeException('El emparejamiento con un Property Node solo corresponde a un Edge Agent.');
        $probe=self::probe($nodeUrl);$agentKey=bin2hex(random_bytes(32));$nodeCode=strtoupper(trim((string)($probe['instance_code']??'')));
        $payload=['agent_uuid'=>(string)config('deployment.installation_uuid'),'agent_code'=>(string)config('app.instance_code'),'agent_name'=>(string)config('app.instance_name'),'agent_key'=>$agentKey,'base_url'=>(string)config('app.url'),'app_version'=>(string)config('app.version'),'parent_property_code'=>$nodeCode];
        $r=EdgeHttp::postJson($nodeUrl,'api/edge/v1/register.php',$payload,['X-Edge-Pairing-Token: '.trim($token)],20);if(empty($r['ok']))throw new RuntimeException((string)($r['error']??'El Property Node rechazó el emparejamiento.'));
        $parentCode=(string)($r['property_code']??$nodeCode);$registry=new ConnectionRegistry();
        $registry->save('parent_node',(string)$r['_base_url'],$agentKey,$parentCode,'self','online',['paired_at'=>date(DATE_ATOM),'distribution_chain'=>'central>node>edge']);
        $registry->retireDirectCentralForEdge('paired_to_property_node_distribution');
        try{self::refreshProvisioning();}catch(Throwable $e){ErrorReporter::report($e,['operation'=>'edge_post_pair_provisioning']);}
        return $r+['distribution_result'=>['ok'=>true,'source'=>'property_node','property_code'=>$parentCode]];
    }

    public static function refreshProvisioning(): array
    {
        if(!InstallationMode::isEdge())return ['ok'=>false,'skipped'=>true];$conn=self::parentConnection();$url=(string)($conn['endpoint_url']??'');$key=(string)($conn['credential']??'');
        if($url===''||$key==='')return ['ok'=>false,'configured'=>false,'message'=>'Property Node no emparejado.'];
        $r=EdgeHttp::postJson($url,'api/edge/v1/config.php',['agent_uuid'=>(string)config('deployment.installation_uuid')],['X-Edge-Agent-Key: '.$key,'X-Edge-Agent-Uuid: '.(string)config('deployment.installation_uuid')],15);
        $registry=new ConnectionRegistry();$registry->save('parent_node',(string)$r['_base_url'],$key,(string)($r['property_code']??($conn['remote_code']??'')),'property_node','online',['last_config_at'=>date(DATE_ATOM),'local_integrations'=>(array)($r['local_integrations']??[]),'update_distribution'=>(array)($r['update_distribution']??[])]);
        $distribution=(array)($r['update_distribution']??[]);$publicKey=trim((string)($distribution['repository_public_key']??''));
        if($publicKey!==''){
            try{Connection::pdo()->prepare('UPDATE bh_deployment_settings SET repository_public_key=?,repository_url=NULL,repository_status=?,last_repository_check_at=NOW() WHERE installation_uuid=?')->execute([$publicKey,'registered',(string)config('deployment.installation_uuid','')]);}
            catch(Throwable $e){ErrorReporter::report($e,['operation'=>'edge_store_distribution_public_key']);}
        }
        $registry->retireDirectCentralForEdge('property_node_is_distribution_source');
        $media=(array)($r['media_relay']??[]);if($media!==[]){try{(new EdgeMediaGatewayService(Connection::pdo()))->receiveProvisioning($media,[]);}catch(Throwable $e){ErrorReporter::report($e,['operation'=>'edge_refresh_media_provisioning']);}}
        return $r;
    }

    public static function repairCentralRegistration(): array
    {
        if(!InstallationMode::isEdge())return ['ok'=>false,'skipped'=>true];$r=self::refreshProvisioning();
        return ['ok'=>true,'property_code'=>(string)($r['property_code']??''),'operation'=>'direct_central_retired','message'=>'El Edge usa exclusivamente su Property Node para distribución de versiones.'];
    }

    public static function updateDistributionStatus(): array
    {
        if(!InstallationMode::isEdge())return ['configured'=>false,'online'=>false,'message'=>'No es un Edge Agent.'];$conn=self::parentConnection();$url=(string)($conn['endpoint_url']??'');$key=(string)($conn['credential']??'');
        if($url===''||$key==='')return ['configured'=>false,'online'=>false,'message'=>'Property Node no emparejado'];
        try{$r=EdgeHttp::postJson($url,'api/edge/v1/config.php',['agent_uuid'=>(string)config('deployment.installation_uuid')],['X-Edge-Agent-Key: '.$key,'X-Edge-Agent-Uuid: '.(string)config('deployment.installation_uuid')],12);self::cacheLocalIntegrations($r,$conn,'last_update_distribution_probe_at');$dist=(array)($r['update_distribution']??[]);return ['configured'=>true,'online'=>true,'message'=>'Property Node disponible para distribución','endpoint'=>(string)($r['_endpoint']??$url),'transport_mode'=>'property_node_relay','response'=>$r,'distribution_chain'=>(string)($dist['chain']??'central>node>edge')];}
        catch(Throwable $e){return ['configured'=>true,'online'=>false,'message'=>$e->getMessage(),'endpoint'=>$url,'transport_mode'=>'property_node_relay'];}
    }

    public static function updateManifest(): array
    {
        if(!InstallationMode::isEdge())throw new RuntimeException('El manifiesto downstream solo corresponde a Edge Agent.');$conn=self::parentConnection();$url=(string)($conn['endpoint_url']??'');$key=(string)($conn['credential']??'');if($url===''||$key==='')throw new RuntimeException('El Edge Agent no está emparejado con un Property Node.');
        $r=EdgeHttp::postJson($url,'api/edge/v1/update-manifest.php',['agent_uuid'=>(string)config('deployment.installation_uuid'),'app_version'=>(string)config('app.version',''),'api_version'=>'v1','ui_version'=>(string)config('app.ui_standard_version','')],['X-Edge-Agent-Key: '.$key,'X-Edge-Agent-Uuid: '.(string)config('deployment.installation_uuid')],25);
        $publicKey=trim((string)($r['repository_public_key']??''));if($publicKey!=='')Connection::pdo()->prepare('UPDATE bh_deployment_settings SET repository_public_key=?,repository_url=NULL,repository_status=?,last_repository_check_at=NOW() WHERE installation_uuid=?')->execute([$publicKey,'registered',(string)config('deployment.installation_uuid','')]);(new ConnectionRegistry())->retireDirectCentralForEdge('update_manifest_via_property_node');unset($r['repository_public_key']);
        return $r+['_transport_mode'=>'property_node_relay'];
    }

    public static function downloadUpdatePackage(string $releaseCode,string $destination): array
    {
        if(!InstallationMode::isEdge())throw new RuntimeException('La descarga downstream solo corresponde a Edge Agent.');$conn=self::parentConnection();$base=rtrim((string)($conn['endpoint_url']??''),'/');$key=(string)($conn['credential']??'');if($base===''||$key==='')throw new RuntimeException('Property Node no emparejado.');if(!extension_loaded('curl'))throw new RuntimeException('La extensión cURL no está disponible.');
        $dir=dirname($destination);if(!is_dir($dir)&&!@mkdir($dir,0775,true)&&!is_dir($dir))throw new RuntimeException('No fue posible crear la carpeta de descargas.');$url=$base.'/api/edge/v1/update-package.php?release='.rawurlencode(trim($releaseCode));$fp=@fopen($destination,'wb');if(!$fp)throw new RuntimeException('No fue posible crear el archivo temporal de actualización.');$headers=[];
        $ch=curl_init($url);if($ch===false){fclose($fp);throw new RuntimeException('No fue posible iniciar cURL.');}curl_setopt_array($ch,[CURLOPT_HTTPGET=>true,CURLOPT_FILE=>$fp,CURLOPT_TIMEOUT=>300,CURLOPT_CONNECTTIMEOUT=>12,CURLOPT_HTTPHEADER=>['Accept: application/zip','X-Edge-Agent-Key: '.$key,'X-Edge-Agent-Uuid: '.(string)config('deployment.installation_uuid'),'X-Edge-App-Version: '.(string)config('app.version','')],CURLOPT_FOLLOWLOCATION=>false,CURLOPT_HEADERFUNCTION=>static function($ch,$line)use(&$headers){$len=strlen($line);$pos=strpos($line,':');if($pos!==false)$headers[strtolower(trim(substr($line,0,$pos)))]=trim(substr($line,$pos+1));return $len;}]);$ok=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$error=curl_error($ch);curl_close($ch);fclose($fp);
        if($ok===false||$status<200||$status>=300){$preview=is_file($destination)?mb_substr(trim((string)@file_get_contents($destination)),0,280):'';@unlink($destination);throw new RuntimeException('Property Node no pudo entregar el paquete. HTTP '.$status.($error!==''?' · '.$error:'').($preview!==''?' · '.$preview:''));}
        $bytes=(int)(filesize($destination)?:0);$sha=(string)(hash_file('sha256',$destination)?:'');$expected=strtolower(trim((string)($headers['x-checksum-sha256']??'')));if($bytes<1)throw new RuntimeException('El Property Node respondió con un paquete vacío.');if($expected!==''&&!hash_equals($expected,strtolower($sha))){@unlink($destination);throw new RuntimeException('El ZIP recibido del Property Node no coincide con su SHA-256.');}
        return ['endpoint'=>$url,'base_url'=>$base,'mode'=>'property_node_cache_relay','http_status'=>$status,'bytes'=>$bytes,'sha256'=>$sha,'response_sha256'=>$expected];
    }

    public static function reportMediaEndpoint(string $publicBaseUrl,string $endpointType='quick_tunnel',string $hlsPublicBaseUrl=''): array
    {
        if(!InstallationMode::isEdge())return ['ok'=>false,'skipped'=>true];
        $conn=self::parentConnection();$url=(string)($conn['endpoint_url']??'');$key=(string)($conn['credential']??'');
        if($url===''||$key==='')throw new RuntimeException('Property Node no emparejado.');
        return EdgeHttp::postJson($url,'api/edge/v1/media-endpoint.php',[
            'agent_uuid'=>(string)config('deployment.installation_uuid'),
            'public_base_url'=>rtrim(trim($publicBaseUrl),'/'),
            'endpoint_type'=>$endpointType,
            'agent_version'=>(string)config('app.version'),
            'hls_public_base_url'=>rtrim(trim($hlsPublicBaseUrl),'/'),
        ],['X-Edge-Agent-Key: '.$key,'X-Edge-Agent-Uuid: '.(string)config('deployment.installation_uuid')],20);
    }

    public static function heartbeat(): array
    {
        if(!InstallationMode::isEdge())return ['ok'=>false,'skipped'=>true];
        $conn=self::parentConnection();$url=(string)($conn['endpoint_url']??'');$key=(string)($conn['credential']??'');
        if($url===''||$key==='')return ['ok'=>false,'configured'=>false];
        try{
            $lab=new EdgeConnectorLab(Connection::pdo());
            $zkTelemetry=['configured'=>false,'status'=>'unknown','token_present'=>false];
            try{$zkTelemetry=$lab->zkBioTelemetry();}catch(Throwable $telemetryError){ErrorReporter::report($telemetryError,['operation'=>'edge_zkbio_telemetry']);$zkTelemetry['last_error']=$telemetryError->getMessage();}
            $payload=[
                'agent_uuid'=>(string)config('deployment.installation_uuid'),
                'app_version'=>(string)config('app.version'),
                'status'=>'online',
                'backup'=>(new NodeTelemetryService())->payload()['backup']??[],
                // Inventario de versiones sin credenciales: permite al Node mostrar qué
                // componentes Edge siguen pendientes sin instalar ni copiar secretos.
                'installed_components'=>(new UpdateManager(Connection::pdo()))->installedVersions(),
                // Solo telemetría no sensible. El endpoint LAN y el access_token nunca salen del Edge.
                'connectors'=>['zkbio'=>$zkTelemetry],
            ];
            $headers=['X-Edge-Agent-Key: '.$key,'X-Edge-Agent-Uuid: '.(string)config('deployment.installation_uuid')];
            $r=EdgeHttp::postJson($url,'api/edge/v1/heartbeat.php',$payload,$headers,15);
            self::cacheLocalIntegrations($r,$conn,'last_heartbeat_provisioning_at');
            $media=(array)($r['media_relay']??[]);
            if($media!==[]){
                try{(new EdgeMediaGatewayService(Connection::pdo()))->receiveProvisioning($media,[]);}
                catch(Throwable $mediaError){ErrorReporter::report($mediaError,['operation'=>'edge_heartbeat_media_provisioning']);}
            }

            // Heartbeat conserva compatibilidad como canal de respaldo. El worker 0.14.17
            // utiliza el mismo procesador, pero consulta al Node cada 1-2 segundos.
            $command=is_array($r['command']??null)?(array)$r['command']:null;
            if($command!==null)$r['command_processed']=self::processCommand($command,$url,$key,$lab);
            (new ConnectionRegistry())->mark('parent_node',true);return $r;
        }catch(Throwable $e){(new ConnectionRegistry())->mark('parent_node',false,$e->getMessage());throw $e;}
    }

    /** Consulta liviana para el worker de baja latencia. */
    public static function pollCommand(): array
    {
        if(!InstallationMode::isEdge())return ['ok'=>false,'skipped'=>true];$conn=self::parentConnection();$url=(string)($conn['endpoint_url']??'');$key=(string)($conn['credential']??'');if($url===''||$key==='')return ['ok'=>false,'configured'=>false];$headers=['X-Edge-Agent-Key: '.$key,'X-Edge-Agent-Uuid: '.(string)config('deployment.installation_uuid')];$r=EdgeHttp::postJson($url,'api/edge/v1/command-poll.php',['agent_uuid'=>(string)config('deployment.installation_uuid')],$headers,12);$command=is_array($r['command']??null)?(array)$r['command']:null;if($command!==null)$r['command_processed']=self::processCommand($command,$url,$key,new EdgeConnectorLab(Connection::pdo()));return $r;
    }

    private static function processCommand(array $command,string $url,string $key,EdgeConnectorLab $lab): array
    {
        $commandUuid=trim((string)($command['command_uuid']??''));$type=trim((string)($command['command_type']??''));$body=(array)($command['payload']??[]);if($commandUuid==='')return ['ok'=>false,'error'=>'missing_command_uuid'];$resultPayload=['agent_uuid'=>(string)config('deployment.installation_uuid'),'command_uuid'=>$commandUuid,'ok'=>false];try{if($type==='zkbio.read_preset'){$result=$lab->runZkBioPreset(trim((string)($body['preset']??'')),(array)($body['params']??[]),'node_remote',null);}elseif(str_starts_with($type,'zkbio.')){$result=$lab->executeOperationalCommand($type,$body);}else{throw new RuntimeException('Tipo de comando Edge no soportado: '.$type);}$resultPayload['ok']=true;$resultPayload['result']=$result;}catch(Throwable $e){$resultPayload['error']=$e->getMessage();ErrorReporter::report($e,['operation'=>'edge_command_execute','command_uuid'=>$commandUuid,'command_type'=>$type]);}
        $headers=['X-Edge-Agent-Key: '.$key,'X-Edge-Agent-Uuid: '.(string)config('deployment.installation_uuid')];try{EdgeHttp::postJson($url,'api/edge/v1/command-result.php',$resultPayload,$headers,45);return ['command_uuid'=>$commandUuid,'command_type'=>$type,'ok'=>!empty($resultPayload['ok']),'error'=>$resultPayload['error']??null];}catch(Throwable $reportError){ErrorReporter::report($reportError,['operation'=>'edge_command_result_report','command_uuid'=>$commandUuid]);return ['command_uuid'=>$commandUuid,'command_type'=>$type,'ok'=>false,'report_error'=>$reportError->getMessage()];}
    }

    public static function syncCameras(bool $includeSnapshots=true): array
    {
        if(!InstallationMode::isEdge())return ['ok'=>false,'skipped'=>true];
        $conn=self::parentConnection();$url=(string)($conn['endpoint_url']??'');$key=(string)($conn['credential']??'');
        if($url===''||$key==='')return ['ok'=>false,'configured'=>false,'message'=>'Property Node no emparejado.'];
        $lab=new EdgeConnectorLab(Connection::pdo());$items=$lab->activeVideoInventory();$deleted=$lab->deletedVideoUuids();
        $postInventory=static function(array $cameraItems,bool $fullInventory,array $deletedUuids=[]) use ($url,$key): array {
            $payload=[];
            foreach($cameraItems as $c){
                $payload[]=[
                    'camera_uuid'=>(string)$c['camera_uuid'],'source_uuid'=>(string)($c['source_uuid']??$c['camera_uuid']),'source_name'=>(string)($c['source_name']??$c['name']),'source_kind'=>(string)($c['source_kind']??'camera'),
                    'channel_key'=>(string)($c['channel_key']??'default'),'channel_index'=>(int)($c['channel_index']??1),'name'=>(string)$c['name'],'host'=>(string)$c['host'],'onvif_url'=>(string)$c['onvif_url'],
                    'rtsp_available'=>trim((string)($c['rtsp_url']??''))!=='','snapshot_available'=>trim((string)($c['snapshot_url']??''))!=='',
                    'manufacturer'=>(string)($c['manufacturer']??''),'model'=>(string)($c['model']??''),'firmware_version'=>(string)($c['firmware_version']??''),
                    'serial_number'=>(string)($c['serial_number']??''),'hardware_id'=>(string)($c['hardware_id']??''),'profile_count'=>(int)($c['profile_count']??0),
                    'status'=>(string)($c['status']??'offline'),'last_test_at'=>(string)($c['last_test_at']??''),'profiles'=>(array)($c['profiles']??[]),
                    'webrtc_codec'=>(string)($c['webrtc_codec']??''),'webrtc_status'=>(string)($c['webrtc_status']??'disabled'),'webrtc_error'=>(string)($c['webrtc_error']??''),
                ];
            }
            return EdgeHttp::postJson(
                $url,'api/edge/v1/inventory-sync.php',
                ['agent_uuid'=>(string)config('deployment.installation_uuid'),'full_inventory'=>$fullInventory,'cameras'=>$payload,'deleted_camera_uuids'=>array_values($deletedUuids)],
                ['X-Edge-Agent-Key: '.$key,'X-Edge-Agent-Uuid: '.(string)config('deployment.installation_uuid')],30
            );
        };

        $result=$postInventory($items,true,$deleted);
        if(!empty($result['ok'])&&$deleted!==[])$lab->markDeletedVideoSynced();
        $gatewayResult=['ok'=>false,'skipped'=>true];
        try{
            $gateway=new EdgeMediaGatewayService(Connection::pdo());
            $gateway->receiveProvisioning((array)($result['media_relay']??[]),(array)($result['camera_media']??[]));
            $state=$gateway->state();
            if(!empty($state['binary_exists'])){
                $generated=$gateway->generateConfiguration();$status=$gateway->checkStatus(false);
                if(empty($status['ok'])&&!empty((array)($result['media_relay']??[])['enabled']))$status=$gateway->start();
                $gatewayResult=['ok'=>!empty($status['ok']),'generated'=>$generated,'status'=>$status];
                $items=$lab->activeVideoInventory();$result2=$postInventory($items,false,[]);$result=array_replace($result,$result2);
            }
        }catch(Throwable $e){$gatewayResult=['ok'=>false,'error'=>$e->getMessage()];ErrorReporter::report($e,['operation'=>'edge_media_gateway_sync']);}

        $snapshotOk=0;$snapshotErrors=[];$sourceResults=[];
        foreach($items as $item){$sourceId=(int)($item['source_id']??0);$uuid=(string)$item['camera_uuid'];try{
            $snapshotUploaded=false;
            if($includeSnapshots&&trim((string)($item['snapshot_url']??''))!==''){
                $snap=$lab->captureSnapshotForInventoryItem($item);$file=(string)$snap['path'];$mime=(string)$snap['mime'];
                EdgeHttp::postFile($url,'api/edge/v1/camera-snapshot-upload.php',$file,['X-Edge-Agent-Key: '.$key,'X-Edge-Agent-Uuid: '.(string)config('deployment.installation_uuid'),'X-Edge-Camera-Uuid: '.$uuid,'X-Snapshot-Sha256: '.(string)(hash_file('sha256',$file)?:''),'X-Snapshot-Mime: '.$mime],45);@unlink($file);$snapshotUploaded=true;$snapshotOk++;
            }
            $sourceResults[$sourceId]['ok']=true;$sourceResults[$sourceId]['snapshot']=($sourceResults[$sourceId]['snapshot']??false)||$snapshotUploaded;
        }catch(Throwable $e){$sourceResults[$sourceId]['ok']=false;$sourceResults[$sourceId]['error']=$e->getMessage();$snapshotErrors[]=['camera_uuid'=>$uuid,'message'=>$e->getMessage()];ErrorReporter::report($e,['operation'=>'edge_camera_snapshot_sync','camera_uuid'=>$uuid]);}}
        foreach($sourceResults as $sourceId=>$state)$lab->markCameraSync((int)$sourceId,!empty($state['ok']),(string)($state['error']??''),!empty($state['snapshot']));
        return $result+['snapshots_uploaded'=>$snapshotOk,'snapshot_errors'=>$snapshotErrors,'media_gateway'=>$gatewayResult,'logical_cameras'=>count($items),'deleted_synced'=>count($deleted)];
    }

    public static function syncEquipment(string $connectorCode='vimar'): array
    {
        if(!InstallationMode::isEdge())return ['ok'=>false,'skipped'=>true];$connectorCode=strtolower(trim($connectorCode));if($connectorCode!=='vimar')throw new InvalidArgumentException('Sincronización genérica Edge no habilitada para '.$connectorCode.'.');
        $conn=self::parentConnection();$url=(string)($conn['endpoint_url']??'');$key=(string)($conn['credential']??'');if($url===''||$key==='')return ['ok'=>false,'configured'=>false,'message'=>'Property Node no emparejado.'];
        $lab=new EdgeConnectorLab(Connection::pdo());$items=$lab->vimarSyncInventoryPayload();if($items===[])return ['ok'=>false,'skipped'=>true,'message'=>'No existe inventario Vimar descubierto todavía. Ejecute Descubrir Vimar antes de sincronizar.','devices_received'=>0];
        $result=EdgeHttp::postJson($url,'api/edge/v1/inventory-sync.php',[
            'agent_uuid'=>(string)config('deployment.installation_uuid'),'full_inventory'=>false,'cameras'=>[],
            'devices'=>$items,'full_device_inventory'=>true,'device_connectors'=>[$connectorCode],
        ],['X-Edge-Agent-Key: '.$key,'X-Edge-Agent-Uuid: '.(string)config('deployment.installation_uuid')],30);
        ErrorReporter::runtimeLog('vimar_inventory_sync',['ok'=>!empty($result['ok']),'devices'=>count($items),'node_devices_created'=>(int)($result['devices_created']??0),'node_devices_updated'=>(int)($result['devices_updated']??0)]);
        return $result+['sent_devices'=>count($items),'connector_code'=>$connectorCode];
    }

    public static function uploadEncryptedBackup(string $file,array $manifest=[]): array
    {
        if(!InstallationMode::isEdge())throw new RuntimeException('Solo un Edge Agent envía su respaldo al Property Node.');$conn=self::parentConnection();$url=(string)($conn['endpoint_url']??'');$key=(string)($conn['credential']??'');if($url===''||$key==='')throw new RuntimeException('El Edge Agent no está emparejado con un Property Node.');
        return EdgeHttp::postFile($url,'api/edge/v1/backup-upload.php',$file,['X-Edge-Agent-Key: '.$key,'X-Edge-Agent-Uuid: '.(string)config('deployment.installation_uuid'),'X-Backup-Filename: '.rawurlencode(basename($file)),'X-Backup-Sha256: '.(string)(hash_file('sha256',$file)?:''),'X-Backup-Created-At: '.rawurlencode((string)($manifest['created_at']??date(DATE_ATOM))),'X-Backup-Uuid: '.rawurlencode((string)($manifest['backup_uuid']??''))],300);
    }
}
