<?php
declare(strict_types=1);

final class EdgeNodeService
{
    public function __construct(private PDO $pdo) {}
    private function assertNode(): void { if(!InstallationMode::isNode())throw new RuntimeException('Esta función solo está disponible en un Property Node.'); }

    public function createPairingToken(?int $userId=null,string $allowedCode='',string $agentName=''): array
    {
        $this->assertNode();
        $allowedCode=strtoupper(trim($allowedCode));
        if($allowedCode==='')throw new InvalidArgumentException('Indique el código único del Edge Agent, por ejemplo '.(string)config('app.instance_code').'-EDGE-01.');
        if(!preg_match('/^[A-Z0-9][A-Z0-9_-]{2,79}$/',$allowedCode))throw new InvalidArgumentException('Código Edge no válido. Use letras, números, guion o guion bajo.');
        $agentName=trim($agentName)!==''?trim($agentName):$allowedCode;
        $plain='PHEDGE-'.strtoupper(bin2hex(random_bytes(16)));$expires=date('Y-m-d H:i:s',time()+86400);
        $this->pdo->prepare("INSERT INTO bh_edge_pairing_tokens (token_hash,token_name,allowed_agent_code,central_bootstrap_encrypted,central_repository_url,expires_at,created_by) VALUES (?,?,?,?,?,?,?)")
            ->execute([hash('sha256',$plain),'Emparejamiento Edge '.$allowedCode,$allowedCode,null,null,$expires,$userId]);
        return ['token'=>$plain,'expires_at'=>$expires,'agent_code'=>$allowedCode,'agent_name'=>$agentName,'distribution_source'=>'property_node','distribution_chain'=>'central>node>edge'];
    }

    /** Endpoint legado: desde 0.14.47 no se entregan URL ni credenciales Central a un Edge. */
    public function centralBootstrapForAgent(array $agent): array
    {
        $this->assertNode();
        return ['ok'=>true,'property_code'=>(string)config('app.instance_code'),'direct_central_registration'=>false,'distribution'=>['source'=>'property_node','chain'=>'central>node>edge'],'server_time'=>date(DATE_ATOM)];
    }

    public function agents(): array { $this->assertNode();return $this->pdo->query("SELECT a.*,(SELECT COUNT(*) FROM bh_edge_backup_archives b WHERE b.agent_id=a.id AND b.status='stored') backup_count,(SELECT b.received_at FROM bh_edge_backup_archives b WHERE b.agent_id=a.id AND b.status='stored' ORDER BY b.id DESC LIMIT 1) last_backup_at FROM bh_edge_agents a WHERE a.status<>'retired' ORDER BY a.agent_code")->fetchAll()?:[]; }
    public function archives(int $limit=100): array { $this->assertNode();$stmt=$this->pdo->query("SELECT b.*,a.agent_code,a.agent_name FROM bh_edge_backup_archives b JOIN bh_edge_agents a ON a.id=b.agent_id WHERE b.status<>'deleted' ORDER BY b.received_at DESC LIMIT ".max(1,min(300,$limit)));return $stmt->fetchAll()?:[]; }
    public function archivePath(int $id): string { $this->assertNode();$stmt=$this->pdo->prepare("SELECT storage_path FROM bh_edge_backup_archives WHERE id=? AND status='stored' LIMIT 1");$stmt->execute([$id]);$path=(string)($stmt->fetchColumn()?:'');if($path===''||!is_file($path))throw new RuntimeException('El respaldo del Agent no está disponible.');return $path; }

    public function register(array $input,string $token): array
    {
        $this->assertNode();foreach(['agent_uuid','agent_code','agent_name','agent_key'] as $f)if(trim((string)($input[$f]??''))==='')throw new InvalidArgumentException('Falta '.$f.'.');
        $stmt=$this->pdo->prepare("SELECT * FROM bh_edge_pairing_tokens WHERE token_hash=? AND status='active' AND (expires_at IS NULL OR expires_at>NOW()) LIMIT 1");$stmt->execute([hash('sha256',trim($token))]);$t=$stmt->fetch();
        $code=strtoupper(trim((string)$input['agent_code']));$centralClaim=null;
        if(!is_array($t)){
            try{$centralClaim=RepositoryClient::claimCentralEdgeEnrollment(trim($token),$input);}catch(Throwable $e){throw new RuntimeException('Token Edge inválido o vencido. Central también rechazó el enrolamiento: '.$e->getMessage());}
            $allowed=strtoupper(trim((string)($centralClaim['agent_code']??'')));
            if($allowed===''||!hash_equals($allowed,$code))throw new RuntimeException('El token emitido por Central corresponde al Edge '.$allowed.', no a '.$code.'.');
        } elseif(!empty($t['allowed_agent_code'])&&!hash_equals((string)$t['allowed_agent_code'],$code))throw new RuntimeException('El token no corresponde a este código de Agent.');
        $parent=(string)config('app.instance_code');$requested=strtoupper(trim((string)($input['parent_property_code']??'')));if($requested!==''&&!hash_equals($parent,$requested))throw new RuntimeException('El Agent intenta asociarse a otra propiedad.');
        $sql="INSERT INTO bh_edge_agents (agent_uuid,agent_code,agent_name,base_url,agent_key_hash,app_version,status,last_seen_at,metadata_json) VALUES (?,?,?,?,?,?,'active',NOW(),?) ON DUPLICATE KEY UPDATE agent_code=VALUES(agent_code),agent_name=VALUES(agent_name),base_url=VALUES(base_url),agent_key_hash=VALUES(agent_key_hash),app_version=VALUES(app_version),status='active',last_seen_at=NOW(),metadata_json=VALUES(metadata_json)";
        $this->pdo->prepare($sql)->execute([(string)$input['agent_uuid'],$code,(string)$input['agent_name'],(string)($input['base_url']??''),hash('sha256',(string)$input['agent_key']),(string)($input['app_version']??'0.0.0'),json_encode(['paired_at'=>date(DATE_ATOM),'distribution_chain'=>'central>node>edge'],JSON_UNESCAPED_SLASHES)]);
        if(is_array($t))$this->pdo->prepare("UPDATE bh_edge_pairing_tokens SET status='used',used_at=NOW(),central_bootstrap_encrypted=NULL,central_repository_url=NULL WHERE id=?")->execute([(int)$t['id']]);
        return [
            'ok'=>true,'property_code'=>$parent,'property_name'=>(string)config('app.instance_name'),'server_time'=>date(DATE_ATOM),
            'update_distribution'=>['source'=>'property_node','chain'=>'central>node>edge','direct_central_registration'=>false],
        ];
    }

    public function authenticate(string $uuid,string $key): array
    {
        $this->assertNode();$stmt=$this->pdo->prepare("SELECT * FROM bh_edge_agents WHERE agent_uuid=? AND status<>'retired' LIMIT 1");$stmt->execute([$uuid]);$a=$stmt->fetch();if(!is_array($a)||!hash_equals((string)$a['agent_key_hash'],hash('sha256',$key)))throw new RuntimeException('Credenciales Edge inválidas.');return $a;
    }
    public function queueZkBioReadCommand(int $agentId,string $preset,array $params,?int $userId=null): array
    {
        $this->assertNode();$allowed=['devices','doors','readers','departments','access_levels','persons','transactions'];if(!in_array($preset,$allowed,true))throw new InvalidArgumentException('Prueba ZKBio remota no permitida.');$pageNo=max(1,(int)($params['pageNo']??1));$pageSize=max(1,min(1000,(int)($params['pageSize']??20)));$payload=['preset'=>$preset,'params'=>['pageNo'=>$pageNo,'pageSize'=>$pageSize,'pins'=>trim((string)($params['pins']??'')),'deptCodes'=>trim((string)($params['deptCodes']??'')),'personPin'=>trim((string)($params['personPin']??'')),'startDate'=>trim((string)($params['startDate']??'')),'endDate'=>trim((string)($params['endDate']??''))]];return $this->queueZkBioCommand($agentId,'zkbio.read_preset',$payload,$userId);
    }

    public function queueZkBioCommand(int $agentId,string $commandType,array $payload,?int $userId=null): array
    {
        $this->assertNode();if(!LocalIntegrationProvisioning::nodeEnabled('zkteco',$this->pdo))throw new RuntimeException('ZKBio no está habilitado en Sistemas conectados de este Property Node.');$allowed=['zkbio.read_preset','zkbio.inventory','zkbio.remote_open','zkbio.create_person','zkbio.create_visitor','zkbio.create_vehicle','zkbio.bio_read','zkbio.bio_upload'];if(!in_array($commandType,$allowed,true))throw new InvalidArgumentException('Comando ZKBio no permitido.');$q=$this->pdo->prepare("SELECT * FROM bh_edge_agents WHERE id=? AND status<>'retired' LIMIT 1");$q->execute([$agentId]);$agent=$q->fetch();if(!is_array($agent))throw new InvalidArgumentException('Edge Agent no encontrado.');$uuid=self::uuid();$this->pdo->prepare("INSERT INTO bh_edge_commands (command_uuid,agent_id,command_type,payload_json,status,queued_by) VALUES (?,?,?,?, 'queued',?)")->execute([$uuid,$agentId,$commandType,json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),$userId]);return ['ok'=>true,'command_uuid'=>$uuid,'agent_code'=>(string)$agent['agent_code'],'command_type'=>$commandType,'preset'=>$payload['preset']??null,'status'=>'queued'];
    }

    public function zkbioCommands(int $limit=60): array
    {
        $this->assertNode();$limit=max(1,min(200,$limit));return $this->pdo->query("SELECT c.*,a.agent_code,a.agent_name FROM bh_edge_commands c JOIN bh_edge_agents a ON a.id=c.agent_id WHERE c.command_type LIKE 'zkbio.%' ORDER BY c.id DESC LIMIT {$limit}")->fetchAll()?:[];
    }

    private function claimNextCommand(array $agent): ?array
    {
        $this->pdo->beginTransaction();try{
            // Si un Agent recibió un comando pero cayó antes de reportar resultado, el
            // comando vuelve a cola después de cinco minutos para no quedar bloqueado.
            $this->pdo->prepare("UPDATE bh_edge_commands SET status='queued',claimed_at=NULL WHERE agent_id=? AND command_type IN ('zkbio.read_preset','zkbio.inventory','zkbio.bio_read') AND status='claimed' AND claimed_at<DATE_SUB(NOW(),INTERVAL 5 MINUTE)")->execute([(int)$agent['id']]);
            $this->pdo->prepare("UPDATE bh_edge_commands SET status='failed',completed_at=NOW(),error_message='Timeout del Edge; no se reintenta automáticamente una operación mutante o física.' WHERE agent_id=? AND command_type LIKE 'zkbio.%' AND command_type NOT IN ('zkbio.read_preset','zkbio.inventory','zkbio.bio_read') AND status='claimed' AND claimed_at<DATE_SUB(NOW(),INTERVAL 2 MINUTE)")->execute([(int)$agent['id']]);
            $q=$this->pdo->prepare("SELECT * FROM bh_edge_commands WHERE agent_id=? AND command_type LIKE 'zkbio.%' AND status='queued' ORDER BY id LIMIT 1 FOR UPDATE");$q->execute([(int)$agent['id']]);$row=$q->fetch();
            if(!is_array($row)){$this->pdo->commit();return null;}
            $this->pdo->prepare("UPDATE bh_edge_commands SET status='claimed',claimed_at=NOW() WHERE id=? AND status='queued'")->execute([(int)$row['id']]);
            $this->pdo->commit();$payload=json_decode((string)($row['payload_json']??''),true);
            return ['command_uuid'=>(string)$row['command_uuid'],'command_type'=>(string)$row['command_type'],'payload'=>is_array($payload)?$payload:[]];
        }catch(Throwable $e){if($this->pdo->inTransaction())$this->pdo->rollBack();throw $e;}
    }

    public function nextCommand(array $agent): array
    {
        $this->assertNode();$command=$this->claimNextCommand($agent);return ['ok'=>true,'command'=>$command,'server_time'=>date(DATE_ATOM)];
    }

    public function completeCommand(array $agent,array $input): array
    {
        $this->assertNode();$uuid=trim((string)($input['command_uuid']??''));if($uuid==='')throw new InvalidArgumentException('Falta command_uuid.');$q=$this->pdo->prepare("SELECT * FROM bh_edge_commands WHERE command_uuid=? AND agent_id=? LIMIT 1");$q->execute([$uuid,(int)$agent['id']]);$row=$q->fetch();if(!is_array($row))throw new RuntimeException('Comando Edge no reconocido.');$ok=!empty($input['ok']);$result=$input['result']??null;$error=trim((string)($input['error']??''));$this->pdo->prepare("UPDATE bh_edge_commands SET status=?,completed_at=NOW(),result_json=?,error_message=? WHERE id=?")->execute([$ok?'completed':'failed',$result===null?null:json_encode($result,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),$error!==''?$error:null,(int)$row['id']]);$extra=[];try{$extra=(new ZkBioOperationalService($this->pdo))->handleCommandCompletion($row,$ok,$result,$error);}catch(Throwable $sideEffect){ErrorReporter::report($sideEffect,['operation'=>'zkbio_command_completion_side_effect','command_uuid'=>$uuid,'command_type'=>$row['command_type']]);$extra['side_effect_error']=$sideEffect->getMessage();}return ['ok'=>true,'command_uuid'=>$uuid,'status'=>$ok?'completed':'failed']+$extra;
    }

    private static function uuid(): string
    {
        $d=random_bytes(16);$d[6]=chr((ord($d[6])&0x0f)|0x40);$d[8]=chr((ord($d[8])&0x3f)|0x80);return vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($d),4));
    }

    public function heartbeat(array $agent,array $input): array
    {
        $metadata=['backup'=>$input['backup']??[],'connectors'=>$input['connectors']??[],'installed_components'=>is_array($input['installed_components']??null)?$input['installed_components']:[],'heartbeat_at'=>date(DATE_ATOM)];
        $this->pdo->prepare("UPDATE bh_edge_agents SET status='active',app_version=?,last_seen_at=NOW(),last_error=NULL,metadata_json=? WHERE id=?")->execute([(string)($input['app_version']??$agent['app_version']),json_encode($metadata,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),(int)$agent['id']]);
        $media=['enabled'=>false];try{$media=(new MediaRelayService($this->pdo))->provisioning();}catch(Throwable $e){ErrorReporter::report($e,['operation'=>'edge_heartbeat_media_provisioning_node']);}
        $command=null;try{$command=$this->claimNextCommand($agent);}catch(Throwable $e){ErrorReporter::report($e,['operation'=>'edge_command_claim']);}
        return ['ok'=>true,'property_code'=>(string)config('app.instance_code'),'media_relay'=>$media,'local_integrations'=>LocalIntegrationProvisioning::nodeIntegrations($this->pdo),'command'=>$command,'server_time'=>date(DATE_ATOM)];
    }

    public function configurationForAgent(array $agent): array
    {
        $this->assertNode();$media=['enabled'=>false];
        try{$media=(new MediaRelayService($this->pdo))->provisioning();}catch(Throwable $e){ErrorReporter::report($e,['operation'=>'edge_media_provisioning_config']);}
        return [
            'ok'=>true,'property_code'=>(string)config('app.instance_code'),'property_name'=>(string)config('app.instance_name'),'agent_code'=>(string)$agent['agent_code'],
            'update_distribution'=>[
                'source'=>'property_node','chain'=>'central>node>edge','direct_central_access'=>false,
                'repository_public_key'=>RepositoryClient::repositoryPublicKey(),
                'manifest_path'=>'api/edge/v1/update-manifest.php','package_path'=>'api/edge/v1/update-package.php',
            ],
            'media_relay'=>$media,'local_integrations'=>LocalIntegrationProvisioning::nodeIntegrations($this->pdo),'server_time'=>date(DATE_ATOM),
        ];
    }

    /** Manifiesto Edge firmado por Central y filtrado por las integraciones activas del Node. */
    public function updateManifestForAgent(array $agent,array $input): array
    {
        $this->assertNode();
        $version=trim((string)($input['app_version']??$agent['app_version']??'0.0.0'));if($version==='')$version='0.0.0';
        $this->pdo->prepare("UPDATE bh_edge_agents SET app_version=?,last_seen_at=NOW(),status='active' WHERE id=?")->execute([$version,(int)$agent['id']]);
        $response=RepositoryClient::manifestForEdge($agent,['app_version'=>$version,'api_version'=>(string)($input['api_version']??'v1'),'ui_version'=>(string)($input['ui_version']??'')]);
        $manifest=(array)($response['manifest']??[]);$releases=array_values(array_filter((array)($manifest['releases']??[]),'is_array'));$filtered=[];$excluded=[];
        foreach($releases as $release){if($this->releaseAllowedForAgent($release))$filtered[]=$release;else $excluded[]=['release_code'=>(string)($release['release_code']??''),'component_code'=>(string)($release['component_code']??''),'reason'=>'connector_not_enabled_on_property_node'];}
        $manifest['releases']=$filtered;$manifest['distribution_chain']=['central'=>(string)($manifest['repository_version']??''),'node_code'=>(string)config('app.instance_code'),'edge_code'=>(string)$agent['agent_code']];
        $diag=(array)($manifest['diagnostics']??[]);$diag['node_distribution_filter']=['before'=>count($releases),'after'=>count($filtered),'excluded'=>$excluded,'local_integrations'=>LocalIntegrationProvisioning::nodeIntegrations($this->pdo)];$manifest['diagnostics']=$diag;
        $batchCodes=[];foreach((array)($diag['core_update_path']??[]) as $step){if(!is_array($step))continue;$rc=trim((string)($step['release_code']??''));if($rc!==''&&$this->releaseAllowedForAgent($step))$batchCodes[]=$rc;}$batchCodes=array_values(array_unique($batchCodes));
        // El filtrado del Node cambia el contenido firmado; por ello el Node NO altera el manifiesto Central.
        // En su lugar adjuntamos allowlists separadas y conservamos intacta la firma original. La lista batch
        // autoriza sólo los Core que Central incluyó dentro de la ruta progresiva firmada.
        $response['node_distribution']=['property_code'=>(string)config('app.instance_code'),'agent_code'=>(string)$agent['agent_code'],'allowed_release_codes'=>array_values(array_map(static fn(array $r):string=>(string)($r['release_code']??''),$filtered)),'batch_allowed_release_codes'=>$batchCodes,'excluded'=>$excluded,'local_integrations'=>LocalIntegrationProvisioning::nodeIntegrations($this->pdo),'chain'=>'central>node>edge'];
        $response['repository_public_key']=RepositoryClient::repositoryPublicKey();
        $this->storeDistributionAuthorizationCache($agent,$response);
        return $response;
    }

    /** Devuelve un paquete autorizado para el Edge, cacheándolo en el Node. */
    public function updatePackagePathForAgent(array $agent,string $releaseCode): array
    {
        $this->assertNode();$releaseCode=trim($releaseCode);if($releaseCode==='')throw new InvalidArgumentException('Falta release.');
        $release=null;$authorizationSource='central_manifest';$centralError=null;$response=null;
        try{$response=RepositoryClient::manifestForEdge($agent,['app_version'=>(string)($agent['app_version']??'0.0.0'),'api_version'=>'v1']);}
        catch(Throwable $e){
            // Si Central está temporalmente fuera de línea, un Node puede seguir sirviendo un ZIP
            // YA cacheado siempre que conserve una autorización firmada previamente para este Agent.
            // No descargamos releases nuevos ni saltamos el filtro de conectores en modo offline.
            $centralError=$e;$release=$this->cachedAuthorizedRelease($agent,$releaseCode);
            if(!is_array($release))throw $e;
            $authorizationSource='cached_signed_manifest';
        }
        if(is_array($response)){
            foreach((array)($response['manifest']['releases']??[]) as $candidate){if(is_array($candidate)&&hash_equals((string)($candidate['release_code']??''),$releaseCode)&&$this->releaseAllowedForAgent($candidate)){$release=$candidate;break;}}
            if(!is_array($release))foreach((array)($response['manifest']['diagnostics']['core_update_path']??[]) as $candidate){if(is_array($candidate)&&hash_equals((string)($candidate['release_code']??''),$releaseCode)&&$this->releaseAllowedForAgent($candidate)){$release=$candidate;break;}}
            // Si Central respondió, su decisión actual prevalece: no resucitamos desde caché un release
            // retirado, revocado o ya no autorizado para este Edge.
            if(!is_array($release))throw new RuntimeException('La versión solicitada no está autorizada para este Edge o para las integraciones habilitadas en el Node.');
        }
        $expected=strtolower(trim((string)($release['package_hash_sha256']??'')));if(!preg_match('/^[a-f0-9]{64}$/',$expected))throw new RuntimeException('El release no contiene SHA-256 válido.');
        $dir=base_path('storage/updates/node-distribution-cache');if(!is_dir($dir)&&!@mkdir($dir,0775,true)&&!is_dir($dir))throw new RuntimeException('No fue posible crear la caché de distribución del Node.');
        $safe=preg_replace('/[^A-Za-z0-9._-]/','_',$releaseCode)?:'release';$path=$dir.'/'.$safe.'.zip';
        $cached=is_file($path)&&hash_equals($expected,strtolower((string)(hash_file('sha256',$path)?:'')));
        if(!$cached){
            @unlink($path);
            if($authorizationSource==='cached_signed_manifest')throw new RuntimeException('Property Hub Central no está disponible y el paquete autorizado todavía no existe en la caché del Property Node. No se intentará una descarga insegura.',0,$centralError);
            $central=(new ConnectionRegistry())->central();$url=(string)($central['endpoint_url']??'');$key=(string)($central['credential']??'');if($url===''||$key==='')throw new RuntimeException('El Property Node no está conectado a Property Hub Central.');
            RepositoryTransport::downloadPackage($url,$releaseCode,$path,['X-Installation-Key: '.$key,'X-Installation-Uuid: '.(string)config('deployment.installation_uuid','')],300);$actual=strtolower((string)(hash_file('sha256',$path)?:''));if(!hash_equals($expected,$actual)){@unlink($path);throw new RuntimeException('El paquete cacheado por el Node no coincide con el SHA-256 firmado por Central.');}
        }
        return ['path'=>$path,'sha256'=>$expected,'release'=>$release,'cached'=>$cached,'authorization_source'=>$authorizationSource,'chain'=>'central>node>edge'];
    }

    private function storeDistributionAuthorizationCache(array $agent,array $response): void
    {
        $dir=base_path('storage/updates/node-distribution-cache/authorization');if(!is_dir($dir)&&!@mkdir($dir,0775,true)&&!is_dir($dir))return;
        $uuid=trim((string)($agent['agent_uuid']??''));if($uuid==='')return;$path=$dir.'/'.hash('sha256',$uuid).'.json';
        $payload=['schema'=>'2cam-edge-distribution-authorization-v1','cached_at'=>date(DATE_ATOM),'agent_uuid'=>$uuid,'agent_code'=>(string)($agent['agent_code']??''),'repository_response'=>$response];
        @file_put_contents($path,json_encode($payload,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),LOCK_EX);@chmod($path,0640);
    }

    private function cachedAuthorizedRelease(array $agent,string $releaseCode): ?array
    {
        $uuid=trim((string)($agent['agent_uuid']??''));if($uuid==='')return null;$path=base_path('storage/updates/node-distribution-cache/authorization/'.hash('sha256',$uuid).'.json');if(!is_file($path))return null;
        $cache=json_decode((string)@file_get_contents($path),true);if(!is_array($cache)||($cache['schema']??'')!=='2cam-edge-distribution-authorization-v1'||!hash_equals((string)($cache['agent_uuid']??''),$uuid))return null;
        $response=(array)($cache['repository_response']??[]);$manifest=(array)($response['manifest']??[]);$dist=(array)($response['node_distribution']??[]);$allowedCodes=array_merge((array)($dist['allowed_release_codes']??[]),(array)($dist['batch_allowed_release_codes']??[]));$allowed=array_fill_keys(array_map('strval',$allowedCodes),true);
        if(!isset($allowed[$releaseCode]))return null;
        // The signed Central manifest is verified when cached by RepositoryClient::manifestForEdge().
        // On fallback we still require canonical hash/signature fields to be present and enforce the
        // Node's CURRENT connector policy before serving a locally cached ZIP.
        if(trim((string)($response['signature']??''))===''||trim((string)($response['canonical_sha256']??''))==='')return null;
        foreach((array)($manifest['releases']??[]) as $candidate){if(is_array($candidate)&&hash_equals((string)($candidate['release_code']??''),$releaseCode)&&$this->releaseAllowedForAgent($candidate))return $candidate;}
        foreach((array)($manifest['diagnostics']['core_update_path']??[]) as $candidate){if(is_array($candidate)&&hash_equals((string)($candidate['release_code']??''),$releaseCode)&&$this->releaseAllowedForAgent($candidate))return $candidate;}
        return null;
    }

    private function releaseAllowedForAgent(array $release): bool
    {
        $componentType=strtolower(trim((string)($release['component_type']??'')));$code=strtolower(trim((string)($release['component_code']??'')));
        if($componentType!=='connector'&&!str_starts_with($code,'connector-')&&!str_contains($code,'-connector-'))return true;
        $integration=DesiredConnectorPolicy::canonical((string)($release['connector_code']??''));
        if($integration==='')$integration=ComponentRegistryService::derivedConnectorCode($code);
        // Legacy manifests from an older Central may not carry connector_code; keep the
        // final heuristic only as backwards compatibility, never as the primary identity.
        if($integration===''||$integration===$code){
            foreach(['vimar'=>'vimar','zkteco'=>'zkteco','zkbio'=>'zkteco','onvif'=>'onvif','rtsp'=>'onvif','hikvision'=>'onvif','dahua'=>'onvif','unifi'=>'unifi','lorawan'=>'lorawan','ttlock'=>'ttlock','sonoff'=>'sonoff'] as $needle=>$canon){if(str_contains($code,$needle)){$integration=$canon;break;}}
        }
        if($integration==='')return false;return LocalIntegrationProvisioning::nodeEnabled($integration,$this->pdo);
    }

    public function updateMediaEndpoint(array $agent,array $input): array
    {
        $this->assertNode();
        $mode=(string)((new MediaRelayService($this->pdo))->settings()['media_mode']??'cpanel_test');
        if($mode!=='edge_direct_webrtc')throw new RuntimeException('El Property Node no está en modo WebRTC directo Edge.');
        $url=rtrim(trim((string)($input['public_base_url']??'')),'/');
        if($url===''||!preg_match('#^https://#i',$url)||parse_url($url,PHP_URL_HOST)===null)throw new InvalidArgumentException('El endpoint WebRTC del Edge debe ser una URL HTTPS válida.');
        $type=strtolower(trim((string)($input['endpoint_type']??'quick_tunnel')));if(!in_array($type,['quick_tunnel','named_tunnel','manual'],true))$type='manual';
        $hlsUrl=rtrim(trim((string)($input['hls_public_base_url']??'')),'/');
        if($hlsUrl!==''&&(!preg_match('#^https://#i',$hlsUrl)||parse_url($hlsUrl,PHP_URL_HOST)===null))throw new InvalidArgumentException('El endpoint HLS del Edge debe ser una URL HTTPS válida.');
        $agentCode=(string)$agent['agent_code'];$installationId=$this->installationId();
        $meta=json_encode(['source'=>'edge_agent','reported_at'=>date(DATE_ATOM),'agent_version'=>(string)($input['agent_version']??''),'hls_public_base_url'=>$hlsUrl],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        $sql="INSERT INTO bh_edge_media_endpoints (installation_id,edge_agent_id,agent_code,endpoint_type,public_base_url,status,last_seen_at,last_error,metadata_json) VALUES (?,?,?,?,?,'online',NOW(),NULL,?) ON DUPLICATE KEY UPDATE edge_agent_id=VALUES(edge_agent_id),endpoint_type=VALUES(endpoint_type),public_base_url=VALUES(public_base_url),status='online',last_seen_at=NOW(),last_error=NULL,metadata_json=VALUES(metadata_json)";
        $this->pdo->prepare($sql)->execute([$installationId,(int)$agent['id'],$agentCode,$type,$url,$meta]);
        $this->pdo->prepare("UPDATE bh_edge_agents SET last_seen_at=NOW(),status='active',last_error=NULL WHERE id=?")->execute([(int)$agent['id']]);
        return ['ok'=>true,'agent_code'=>$agentCode,'public_base_url'=>$url,'endpoint_type'=>$type,'server_time'=>date(DATE_ATOM)];
    }

    public function syncInventory(array $agent,array $input): array
    {
        $this->assertNode();$cameras=(array)($input['cameras']??[]);if(count($cameras)>1000)throw new RuntimeException('El inventario Edge excede el máximo de 1000 canales de video por sincronización.');
        $devices=(array)($input['devices']??[]);if(count($devices)>2000)throw new RuntimeException('El inventario Edge excede el máximo de 2000 equipos físicos por sincronización.');
        $deleted=array_values(array_unique(array_filter(array_map(static fn($v)=>strtolower(trim((string)$v)),(array)($input['deleted_camera_uuids']??[])))));
        $installationId=$this->installationId();$connectorId=$this->edgeOnvifConnectorId($installationId,$agent);$seen=[];$seenSources=[];$created=0;$updated=0;$archived=0;$mediaAssignments=[];
        $deviceCreated=0;$deviceUpdated=0;$deviceArchived=0;$deviceSeenByConnector=[];
        $mediaRelay=new MediaRelayService($this->pdo);

        foreach($devices as $device){
            if(!is_array($device))continue;$connectorCode=strtolower(trim((string)($device['connector_code']??'')));if($connectorCode===''||!preg_match('/^[a-z0-9_-]{2,80}$/',$connectorCode))continue;
            $externalId=trim((string)($device['external_id']??''));$sourceUuid=strtolower(trim((string)($device['source_uuid']??'')));if($sourceUuid===''||!preg_match('/^[0-9a-f-]{36}$/',$sourceUuid))$sourceUuid=$this->stableEdgeDeviceUuid($connectorCode.'|'.$externalId.'|'.(string)($device['name']??''));
            $name=trim((string)($device['name']??''));if($name==='')continue;$genericConnectorId=$this->edgeConnectorId($installationId,$agent,$connectorCode);$deviceSeenByConnector[$connectorCode][]=$sourceUuid;
            $deviceType=$this->nodeDeviceType((string)($device['device_type']??'other'));$status=$this->nodeDeviceStatus((string)($device['status']??'unknown'));
            $vendor=trim((string)($device['vendor']??''));$model=trim((string)($device['model']??''));$serial=trim((string)($device['serial_number']??''));$ip=trim((string)($device['ip_address']??''));$managedBy=trim((string)($device['managed_by']??(string)$agent['agent_code']));
            $capabilities=(array)($device['capabilities']??[]);$capabilities['source']='edge_agent';$capabilities['agent_code']=(string)$agent['agent_code'];$capabilities['connector_code']=$connectorCode;$capabilities['edge_name']=$name;
            $q=$this->pdo->prepare("SELECT id FROM bh_devices WHERE installation_id=? AND source_scope='edge' AND edge_agent_id=? AND source_uuid=? LIMIT 1");$q->execute([$installationId,(int)$agent['id'],$sourceUuid]);$id=(int)($q->fetchColumn()?:0);
            if($id>0){
                // Node owns business naming/location. Keep the current name and placement while refreshing technical metadata.
                $this->pdo->prepare("UPDATE bh_devices SET connector_instance_id=?,device_type=?,vendor=NULLIF(?,''),model=NULLIF(?,''),serial_number=NULLIF(?,''),ip_address=NULLIF(?,''),external_id=NULLIF(?,''),capabilities_json=?,status=?,last_seen_at=CASE WHEN ?='online' THEN NOW() ELSE last_seen_at END,managed_by=?,source_updated_at=NOW(),source_deleted_at=NULL WHERE id=?")
                    ->execute([$genericConnectorId,$deviceType,$vendor,$model,$serial,$ip,$externalId,json_encode($capabilities,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),$status,$status,$managedBy,$id]);$deviceUpdated++;
            }else{
                $this->pdo->prepare("INSERT INTO bh_devices (installation_id,connector_instance_id,device_type,name,vendor,model,serial_number,ip_address,external_id,capabilities_json,status,last_seen_at,managed_by,source_scope,edge_agent_id,source_uuid,source_updated_at,source_deleted_at) VALUES (?,?,?,?,NULLIF(?,''),NULLIF(?,''),NULLIF(?,''),NULLIF(?,''),NULLIF(?,''),?,?,CASE WHEN ?='online' THEN NOW() ELSE NULL END,?,'edge',?,?,NOW(),NULL)")
                    ->execute([$installationId,$genericConnectorId,$deviceType,$name,$vendor,$model,$serial,$ip,$externalId,json_encode($capabilities,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),$status,$status,$managedBy,(int)$agent['id'],$sourceUuid]);$deviceCreated++;
            }
        }
        if(!empty($input['full_device_inventory'])){
            $requested=array_values(array_unique(array_filter(array_map(static fn($v)=>strtolower(trim((string)$v)),(array)($input['device_connectors']??array_keys($deviceSeenByConnector))))));
            foreach($requested as $connectorCode){if(!preg_match('/^[a-z0-9_-]{2,80}$/',$connectorCode))continue;$genericConnectorId=$this->edgeConnectorId($installationId,$agent,$connectorCode);$seenDeviceUuids=array_values(array_unique($deviceSeenByConnector[$connectorCode]??[]));
                if($seenDeviceUuids===[]){continue;} // An empty bridge response must never erase a previously discovered site inventory.
                $marks=implode(',',array_fill(0,count($seenDeviceUuids),'?'));$args=[$installationId,(int)$agent['id'],$genericConnectorId,...$seenDeviceUuids];$stmt=$this->pdo->prepare("UPDATE bh_devices SET status='disabled',source_deleted_at=COALESCE(source_deleted_at,NOW()) WHERE installation_id=? AND source_scope='edge' AND edge_agent_id=? AND connector_instance_id=? AND source_uuid NOT IN ({$marks}) AND source_deleted_at IS NULL");$stmt->execute($args);$deviceArchived+=$stmt->rowCount();
                $count=$this->pdo->prepare("SELECT COUNT(*) FROM bh_devices WHERE installation_id=? AND connector_instance_id=? AND source_scope='edge' AND edge_agent_id=? AND status<>'disabled' AND source_deleted_at IS NULL");$count->execute([$installationId,$genericConnectorId,(int)$agent['id']]);$this->pdo->prepare("UPDATE bh_connector_instances SET device_count=?,status='online',last_health_at=NOW() WHERE id=?")->execute([(int)$count->fetchColumn(),$genericConnectorId]);
            }
        }

        if($deleted!==[]){
            $marks=implode(',',array_fill(0,count($deleted),'?'));$args=[$installationId,(int)$agent['id'],...$deleted];
            $stmt=$this->pdo->prepare("UPDATE bh_cameras SET status='disabled',live_enabled=0,live_transport='none',live_status='disabled',source_deleted_at=NOW(),edge_last_seen_at=NOW() WHERE installation_id=? AND source_scope='edge' AND edge_agent_id=? AND edge_camera_uuid IN ({$marks}) AND source_deleted_at IS NULL");$stmt->execute($args);$archived+=$stmt->rowCount();
        }

        foreach($cameras as $camera){
            if(!is_array($camera))continue;
            $uuid=strtolower(trim((string)($camera['camera_uuid']??'')));$name=trim((string)($camera['name']??''));if($uuid===''||$name==='')continue;$seen[]=$uuid;
            $sourceUuid=strtolower(trim((string)($camera['source_uuid']??$uuid)));if($sourceUuid==='')$sourceUuid=$uuid;$seenSources[]=$sourceUuid;
            $sourceName=trim((string)($camera['source_name']??$name));if($sourceName==='')$sourceName=$name;
            $sourceKind=strtolower(trim((string)($camera['source_kind']??'camera')));if(!in_array($sourceKind,['camera','nvr','dvr','encoder','unknown'],true))$sourceKind='unknown';
            $channelKey=trim((string)($camera['channel_key']??'default'));$channelIndex=max(1,(int)($camera['channel_index']??1));
            $status=$this->nodeCameraStatus((string)($camera['status']??'offline'));$host=trim((string)($camera['host']??''));$vendor=trim((string)($camera['manufacturer']??''));$model=trim((string)($camera['model']??''));$serial=trim((string)($camera['serial_number']??''));
            $edgeLiveStatus=strtolower(trim((string)($camera['webrtc_status']??'disabled')));$edgeCodec=trim((string)($camera['webrtc_codec']??''));$edgeLiveError=trim((string)($camera['webrtc_error']??''));
            if(!in_array($edgeLiveStatus,['disabled','pending','ready','incompatible','error'],true))$edgeLiveStatus='pending';
            $deviceType=in_array($sourceKind,['nvr','dvr'],true)?'nvr':'camera';
            $capabilities=['source'=>'edge_agent','agent_code'=>(string)$agent['agent_code'],'source_kind'=>$sourceKind,'onvif'=>!empty($camera['onvif_url']),'rtsp'=>!empty($camera['rtsp_available']),'snapshot'=>!empty($camera['snapshot_available']),'profile_count'=>(int)($camera['profile_count']??0),'profiles'=>$camera['profiles']??[],'webrtc_codec'=>$edgeCodec,'webrtc_status'=>$edgeLiveStatus];

            // Un NVR/DVR es un solo dispositivo físico aunque exponga muchos canales.
            $deviceStmt=$this->pdo->prepare("SELECT id FROM bh_devices WHERE installation_id=? AND source_scope='edge' AND edge_agent_id=? AND source_uuid=? LIMIT 1");$deviceStmt->execute([$installationId,(int)$agent['id'],$sourceUuid]);$deviceId=(int)($deviceStmt->fetchColumn()?:0);
            if($deviceId>0){
                $this->pdo->prepare("UPDATE bh_devices SET connector_instance_id=?,device_type=?,name=?,vendor=NULLIF(?,''),model=NULLIF(?,''),serial_number=NULLIF(?,''),ip_address=NULLIF(?,''),external_id=?,capabilities_json=?,status=?,last_seen_at=CASE WHEN ?='online' THEN NOW() ELSE last_seen_at END,managed_by=?,source_updated_at=NOW(),source_deleted_at=NULL WHERE id=?")
                    ->execute([$connectorId,$deviceType,$sourceName,$vendor,$model,$serial,$host,$sourceUuid,json_encode($capabilities,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),$status,$status,(string)$agent['agent_code'],$deviceId]);
            }else{
                $this->pdo->prepare("INSERT INTO bh_devices (installation_id,connector_instance_id,device_type,name,vendor,model,serial_number,ip_address,external_id,capabilities_json,status,last_seen_at,managed_by,source_scope,edge_agent_id,source_uuid,source_updated_at,source_deleted_at) VALUES (?,?,?,?,NULLIF(?,''),NULLIF(?,''),NULLIF(?,''),NULLIF(?,''),?,?,?,CASE WHEN ?='online' THEN NOW() ELSE NULL END,?,'edge',?,?,NOW(),NULL)")
                    ->execute([$installationId,$connectorId,$deviceType,$sourceName,$vendor,$model,$serial,$host,$sourceUuid,json_encode($capabilities,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),$status,$status,(string)$agent['agent_code'],(int)$agent['id'],$sourceUuid]);$deviceId=(int)$this->pdo->lastInsertId();
            }

            $camStmt=$this->pdo->prepare("SELECT id,live_enabled,live_path,name,edge_name,name_sync_mode FROM bh_cameras WHERE installation_id=? AND source_scope='edge' AND edge_agent_id=? AND edge_camera_uuid=? LIMIT 1");$camStmt->execute([$installationId,(int)$agent['id'],$uuid]);$existing=$camStmt->fetch();$cameraId=is_array($existing)?(int)$existing['id']:0;
            $streamMode=in_array($sourceKind,['nvr','dvr'],true)?'nvr_channel':(!empty($camera['onvif_url'])?'onvif':'rtsp');
            $sourceMeta=json_encode(['agent_code'=>(string)$agent['agent_code'],'source_uuid'=>$sourceUuid,'source_name'=>$sourceName,'edge_name'=>$name,'source_kind'=>$sourceKind,'channel_key'=>$channelKey,'channel_index'=>$channelIndex,'host'=>$host,'manufacturer'=>$vendor,'model'=>$model,'serial_number'=>$serial,'firmware_version'=>$camera['firmware_version']??null,'hardware_id'=>$camera['hardware_id']??null,'profile_count'=>(int)($camera['profile_count']??0),'rtsp_available'=>!empty($camera['rtsp_available']),'snapshot_available'=>!empty($camera['snapshot_available']),'last_test_at'=>$camera['last_test_at']??null,'webrtc_error'=>$edgeLiveError],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
            if($cameraId>0){
                $liveEnabled=!empty($existing['live_enabled']);$nodeLiveStatus=$liveEnabled?$this->nodeLiveStatus($edgeLiveStatus,$status):'disabled';
                $syncMode=(string)($existing['name_sync_mode']??'edge');if(!in_array($syncMode,['edge','node'],true))$syncMode='edge';
                $this->pdo->prepare("UPDATE bh_cameras SET device_id=?,name=CASE WHEN name_sync_mode='edge' THEN ? ELSE name END,edge_name=?,stream_mode=?,status=?,edge_source_uuid=?,edge_channel_key=?,edge_last_seen_at=NOW(),source_metadata_json=?,source_deleted_at=NULL,live_codec=NULLIF(?,''),live_status=?,live_updated_at=NOW() WHERE id=?")
                    ->execute([$deviceId,$name,$name,$streamMode,$status,$sourceUuid,$channelKey,$sourceMeta,$edgeCodec,$nodeLiveStatus,$cameraId]);$updated++;
            }else{
                $this->pdo->prepare("INSERT INTO bh_cameras (installation_id,device_id,name,edge_name,name_sync_mode,stream_mode,resident_visibility,allow_recordings,allow_audio,allow_ptz,status,source_scope,edge_agent_id,edge_camera_uuid,edge_source_uuid,edge_channel_key,edge_last_seen_at,source_metadata_json,source_deleted_at,live_enabled,live_transport,live_codec,live_status,live_updated_at) VALUES (?,?,?,?,'edge',?,'none',0,0,0,?,'edge',?,?,?,?,NOW(),?,NULL,0,'none',NULLIF(?,''),'disabled',NOW())")
                    ->execute([$installationId,$deviceId,$name,$name,$streamMode,$status,(int)$agent['id'],$uuid,$sourceUuid,$channelKey,$sourceMeta,$edgeCodec]);$cameraId=(int)$this->pdo->lastInsertId();$created++;
            }
            $camState=$this->pdo->prepare('SELECT live_enabled,live_path FROM bh_cameras WHERE id=? AND installation_id=? LIMIT 1');$camState->execute([$cameraId,$installationId]);$live=(array)($camState->fetch()?:[]);$liveEnabled=!empty($live['live_enabled']);$path=trim((string)($live['live_path']??''));if($liveEnabled&&$path==='')$path=$mediaRelay->ensureLivePath($cameraId,$uuid);
            $mediaAssignments[]=['camera_uuid'=>$uuid,'live_enabled'=>$liveEnabled,'live_path'=>$path];
        }

        if(!empty($input['full_inventory'])){
            if($seen===[]){
                $stmt=$this->pdo->prepare("UPDATE bh_cameras SET status='disabled',live_enabled=0,live_transport='none',live_status='disabled',source_deleted_at=COALESCE(source_deleted_at,NOW()) WHERE installation_id=? AND source_scope='edge' AND edge_agent_id=? AND source_deleted_at IS NULL");$stmt->execute([$installationId,(int)$agent['id']]);$archived+=$stmt->rowCount();
            }else{
                $marks=implode(',',array_fill(0,count($seen),'?'));$args=[$installationId,(int)$agent['id'],...$seen];$stmt=$this->pdo->prepare("UPDATE bh_cameras SET status='disabled',live_enabled=0,live_transport='none',live_status='disabled',source_deleted_at=COALESCE(source_deleted_at,NOW()) WHERE installation_id=? AND source_scope='edge' AND edge_agent_id=? AND edge_camera_uuid NOT IN ({$marks}) AND source_deleted_at IS NULL");$stmt->execute($args);$archived+=$stmt->rowCount();
            }
            $seenSources=array_values(array_unique($seenSources));
            if($seenSources===[]){$this->pdo->prepare("UPDATE bh_devices SET status='disabled',source_deleted_at=COALESCE(source_deleted_at,NOW()) WHERE installation_id=? AND source_scope='edge' AND edge_agent_id=? AND connector_instance_id=? AND source_deleted_at IS NULL")->execute([$installationId,(int)$agent['id'],$connectorId]);}
            else{$marks=implode(',',array_fill(0,count($seenSources),'?'));$args=[$installationId,(int)$agent['id'],$connectorId,...$seenSources];$this->pdo->prepare("UPDATE bh_devices SET status='disabled',source_deleted_at=COALESCE(source_deleted_at,NOW()) WHERE installation_id=? AND source_scope='edge' AND edge_agent_id=? AND connector_instance_id=? AND source_uuid NOT IN ({$marks}) AND source_deleted_at IS NULL")->execute($args);}
        }
        $countStmt=$this->pdo->prepare("SELECT COUNT(*) FROM bh_devices WHERE installation_id=? AND connector_instance_id=? AND source_scope='edge' AND edge_agent_id=? AND status<>'disabled' AND source_deleted_at IS NULL");$countStmt->execute([$installationId,$connectorId,(int)$agent['id']]);
        $this->pdo->prepare("UPDATE bh_connector_instances SET device_count=?,status='online',last_health_at=NOW() WHERE id=?")->execute([(int)$countStmt->fetchColumn(),$connectorId]);$this->pdo->prepare("UPDATE bh_edge_agents SET last_seen_at=NOW(),status='active' WHERE id=?")->execute([(int)$agent['id']]);
        return ['ok'=>true,'created'=>$created,'updated'=>$updated,'archived'=>$archived,'received'=>count($seen),'devices_received'=>count($devices),'devices_created'=>$deviceCreated,'devices_updated'=>$deviceUpdated,'devices_archived'=>$deviceArchived,'property_code'=>(string)config('app.instance_code'),'media_relay'=>$mediaRelay->provisioning(),'camera_media'=>$mediaAssignments];
    }

    private function nodeLiveStatus(string $edgeStatus,string $cameraStatus): string
    {
        if($cameraStatus!=='online')return 'offline';
        return match($edgeStatus){'ready'=>'ready','incompatible'=>'incompatible','error'=>'error','disabled'=>'pending',default=>'pending'};
    }

    public function storeCameraSnapshot(array $agent,string $cameraUuid,string $tmp,string $sha,string $declaredMime=''): array
    {
        $this->assertNode();$cameraUuid=strtolower(trim($cameraUuid));if($cameraUuid==='')throw new InvalidArgumentException('Falta UUID de cámara Edge.');
        $actual=(string)(hash_file('sha256',$tmp)?:'');if($sha===''||!hash_equals(strtolower($sha),strtolower($actual)))throw new RuntimeException('SHA-256 del snapshot no coincide.');$size=(int)filesize($tmp);if($size<1||$size>10*1024*1024)throw new RuntimeException('Snapshot vacío o mayor de 10 MB.');
        $stmt=$this->pdo->prepare("SELECT id FROM bh_cameras WHERE installation_id=? AND source_scope='edge' AND edge_agent_id=? AND edge_camera_uuid=? LIMIT 1");$stmt->execute([$this->installationId(),(int)$agent['id'],$cameraUuid]);$cameraId=(int)($stmt->fetchColumn()?:0);if($cameraId<1)throw new RuntimeException('La cámara Edge todavía no existe en el inventario del Node. Sincronice primero el catálogo.');
        $mime=$declaredMime;if(class_exists('finfo')){$f=new finfo(FILEINFO_MIME_TYPE);$det=(string)$f->file($tmp);if($det!=='')$mime=$det;}if(!in_array($mime,['image/jpeg','image/png','image/webp'],true))throw new RuntimeException('Formato de snapshot no permitido: '.($mime?:'desconocido'));
        $ext=$mime==='image/png'?'png':($mime==='image/webp'?'webp':'jpg');$agentDir=preg_replace('/[^A-Za-z0-9_-]/','_', (string)$agent['agent_code']);$rel='storage/uploads/edge-cameras/'.$agentDir.'/'.$cameraUuid.'.'.$ext;$target=base_path($rel);$dir=dirname($target);if(!is_dir($dir)&&!@mkdir($dir,0775,true)&&!is_dir($dir))throw new RuntimeException('No se pudo crear el almacén de snapshots Edge.');
        foreach(['jpg','png','webp'] as $oldExt){$old=base_path('storage/uploads/edge-cameras/'.$agentDir.'/'.$cameraUuid.'.'.$oldExt);if($old!==$target&&is_file($old))@unlink($old);}if(!@rename($tmp,$target)){if(!@copy($tmp,$target))throw new RuntimeException('No se pudo guardar el snapshot recibido.');@unlink($tmp);}
        $this->pdo->prepare('UPDATE bh_cameras SET snapshot_path=?,snapshot_mime=?,snapshot_at=NOW(),edge_last_seen_at=NOW() WHERE id=?')->execute([$rel,$mime,$cameraId]);
        return ['ok'=>true,'camera_id'=>$cameraId,'snapshot_at'=>date(DATE_ATOM),'size_bytes'=>$size,'sha256'=>$actual];
    }

    private function installationId(): int
    {
        $stmt=$this->pdo->prepare('SELECT id FROM bh_installations WHERE public_uuid=? LIMIT 1');$stmt->execute([(string)config('deployment.installation_uuid')]);$id=(int)$stmt->fetchColumn();if($id<1)throw new RuntimeException('No se encontró la instalación local del Property Node.');return $id;
    }

    private function nodeCameraStatus(string $status): string
    {
        return match(strtolower($status)){'online'=>'online','disabled'=>'disabled','configured','warning'=>'maintenance',default=>'offline'};
    }

    private function nodeDeviceStatus(string $status): string
    {
        return match(strtolower(trim($status))){'online','ready','active'=>'online','offline'=>'offline','disabled'=>'disabled','maintenance','error'=>'maintenance',default=>'unknown'};
    }

    private function nodeDeviceType(string $type): string
    {
        $allowed=['access_controller','reader','lock','relay','camera','nvr','intercom','phone','speaker','network_controller','network_switch','poe_switch','access_point','patch_panel','fiber_distributor','rack','ups','gateway','elevator_controller','turnstile','barrier','door_operator','fire_panel','pump','meter','sensor','iot_actuator','other'];$type=strtolower(trim($type));return in_array($type,$allowed,true)?$type:'other';
    }

    private function stableEdgeDeviceUuid(string $seed): string
    {
        $hex=substr(hash('sha256',$seed),0,32);$hex[12]='4';$hex[16]=dechex((hexdec($hex[16])&0x3)|0x8);return substr($hex,0,8).'-'.substr($hex,8,4).'-'.substr($hex,12,4).'-'.substr($hex,16,4).'-'.substr($hex,20,12);
    }

    private function edgeConnectorId(int $installationId,array $agent,string $code): int
    {
        $code=strtolower(trim($code));if($code==='onvif')return $this->edgeOnvifConnectorId($installationId,$agent);
        $def=$this->pdo->prepare('SELECT id,name FROM bh_connector_definitions WHERE code=? LIMIT 1');$def->execute([$code]);$definition=$def->fetch();if(!is_array($definition))throw new RuntimeException('El catálogo del conector '.$code.' no está disponible en el Property Node.');$definitionId=(int)$definition['id'];
        $label=$code==='vimar'?'Vimar':(string)($definition['name']??strtoupper($code));$name=$label.' · '.(string)$agent['agent_code'];$stmt=$this->pdo->prepare('SELECT id FROM bh_connector_instances WHERE installation_id=? AND connector_definition_id=? AND name=? LIMIT 1');$stmt->execute([$installationId,$definitionId,$name]);$id=(int)($stmt->fetchColumn()?:0);if($id>0)return $id;
        $caps=json_encode(['agent_code'=>(string)$agent['agent_code'],'source'=>'edge_agent','local_first'=>true,'connector_code'=>$code],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        $this->pdo->prepare("INSERT INTO bh_connector_instances (installation_id,connector_definition_id,name,target_type,manages_downstream_assets,direct_device_policy,platform_product,integration_mode,api_family,api_version,authentication_mode,endpoint_url,device_count,capabilities_json,environment,status,last_health_at) VALUES (?,?,?,'platform_server',1,'required','Property Edge Agent','edge_proxy',?,'auto','edge_managed',?,0,?,'production','online',NOW())")
            ->execute([$installationId,$definitionId,$name,$label,'edge://'.(string)$agent['agent_code'].'/'.$code,$caps]);return (int)$this->pdo->lastInsertId();
    }

    private function edgeOnvifConnectorId(int $installationId,array $agent): int
    {
        $def=$this->pdo->prepare("SELECT id FROM bh_connector_definitions WHERE code='onvif' LIMIT 1");$def->execute();$definitionId=(int)$def->fetchColumn();if($definitionId<1)throw new RuntimeException('El catálogo ONVIF no está disponible en el Property Node.');$name='ONVIF · '.(string)$agent['agent_code'];
        $stmt=$this->pdo->prepare('SELECT id FROM bh_connector_instances WHERE installation_id=? AND connector_definition_id=? AND name=? LIMIT 1');$stmt->execute([$installationId,$definitionId,$name]);$id=(int)($stmt->fetchColumn()?:0);if($id>0)return $id;
        $this->pdo->prepare("INSERT INTO bh_connector_instances (installation_id,connector_definition_id,name,target_type,manages_downstream_assets,direct_device_policy,platform_product,integration_mode,api_family,api_version,authentication_mode,endpoint_url,device_count,capabilities_json,environment,status,last_health_at) VALUES (?,?,?,'protocol_service',1,'required','Property Edge Agent','edge_proxy','ONVIF','Profile T/S','edge_managed',?,0,?,'production','online',NOW())")
            ->execute([$installationId,$definitionId,$name,'edge://'.(string)$agent['agent_code'],json_encode(['agent_code'=>(string)$agent['agent_code'],'source'=>'edge_agent'],JSON_UNESCAPED_SLASHES)]);return (int)$this->pdo->lastInsertId();
    }
    public function storeBackup(array $agent,string $tmp,string $filename,string $sha,string $backupUuid='',string $createdAt=''): array
    {
        $safe=preg_replace('/[^A-Za-z0-9_.-]/','_',basename(rawurldecode($filename)))?:'edge.phbackup';if(!str_ends_with(strtolower($safe),'.phbackup'))throw new RuntimeException('Formato de respaldo Edge inválido.');$actual=(string)(hash_file('sha256',$tmp)?:'');if($sha===''||!hash_equals(strtolower($sha),strtolower($actual)))throw new RuntimeException('SHA-256 del respaldo Edge no coincide.');
        $dir=base_path('storage/backups/agents/'.preg_replace('/[^A-Za-z0-9_-]/','_', (string)$agent['agent_code']));if(!is_dir($dir)&&!@mkdir($dir,0775,true)&&!is_dir($dir))throw new RuntimeException('No se pudo crear el almacén de respaldos del Agent.');$target=$dir.'/'.$safe;if(!@rename($tmp,$target)){if(!@copy($tmp,$target))throw new RuntimeException('No se pudo custodiar el respaldo del Agent.');@unlink($tmp);}
        $this->pdo->prepare("INSERT INTO bh_edge_backup_archives (agent_id,backup_uuid,filename,size_bytes,sha256,storage_path,backup_created_at,metadata_json) VALUES (?,?,?,?,?,?,?,?)")->execute([(int)$agent['id'],$backupUuid!==''?$backupUuid:null,$safe,(int)filesize($target),$actual,$target,$createdAt!==''?date('Y-m-d H:i:s',strtotime($createdAt)):null,json_encode(['source'=>'edge_agent'],JSON_UNESCAPED_SLASHES)]);$this->pdo->prepare("UPDATE bh_edge_agents SET last_seen_at=NOW(),status='active' WHERE id=?")->execute([(int)$agent['id']]);return ['ok'=>true,'archive_id'=>(int)$this->pdo->lastInsertId(),'sha256'=>$actual,'size_bytes'=>(int)filesize($target)];
    }
}
