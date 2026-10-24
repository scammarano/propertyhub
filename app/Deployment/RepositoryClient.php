<?php
declare(strict_types=1);

final class RepositoryClient
{
    private static function centralConnection(): array
    {
        if(InstallationMode::isEdge())throw new RuntimeException('El Edge Agent no se conecta directamente a Property Hub Central. Su fuente de distribución es el Property Node emparejado.');
        $registry=new ConnectionRegistry();
        try{$registry->importLegacyIfNeeded();}catch(Throwable $e){ErrorReporter::report($e,['operation'=>'connection_registry_import']);}
        return $registry->central();
    }

    /** Conexión de transferencia según la jerarquía: Node -> Central, Edge -> Node. */
    public static function connectionForTransfer(bool $unused=true): array
    {
        $registry=new ConnectionRegistry();
        try{$registry->importLegacyIfNeeded();}catch(Throwable $e){ErrorReporter::report($e,['operation'=>'connection_registry_import_transfer']);}
        return InstallationMode::isEdge()?$registry->parentNode():$registry->central();
    }

    /** Compatibilidad con updater 0.14.x: ya no existe reparación de credenciales Edge contra Central. */
    public static function recoverEdgeCredentials(Throwable $e): bool
    {
        if(!InstallationMode::isEdge())return false;
        try{EdgeNodeClient::refreshProvisioning();}catch(Throwable $refreshError){ErrorReporter::report($refreshError,['operation'=>'edge_node_provisioning_refresh','original_error'=>$e->getMessage()]);}
        return false;
    }

    private static function authenticatedPost(string $action,array $payload,int $timeout): array
    {
        $conn=self::centralConnection();
        $url=(string)($conn['endpoint_url']??'');$key=(string)($conn['credential']??'');
        if($url==='')throw new RuntimeException('Property Hub Central no está configurado en este Property Node.');
        return RepositoryTransport::postJson($url,$action,$payload,['X-Installation-Key: '.$key],$timeout);
    }

    public static function probe(): array
    {
        if(InstallationMode::isEdge())return EdgeNodeClient::updateDistributionStatus();
        $conn=self::centralConnection();$url=(string)($conn['endpoint_url']??'');
        if($url==='')return ['configured'=>false,'online'=>false,'message'=>'Fuente central no configurada'];
        try{
            $result=RepositoryTransport::postJson($url,'ping',['node_code'=>(string)config('app.instance_code',''),'node_version'=>(string)config('app.version','')],[],12);
            self::persistCanonicalUrl((string)$result['base_url']);
            return ['configured'=>true,'online'=>true,'message'=>'Endpoint del Property Hub Central disponible','response'=>$result['data'],'endpoint'=>$result['endpoint'],'transport_mode'=>$result['mode']];
        }catch(Throwable $e){return ['configured'=>true,'online'=>false,'message'=>$e->getMessage()];}
    }

    public static function status(): array
    {
        if(InstallationMode::isEdge())return EdgeNodeClient::updateDistributionStatus();
        $conn=self::centralConnection();$url=(string)($conn['endpoint_url']??'');
        if($url==='')return ['configured'=>false,'online'=>false,'message'=>'Fuente central no configurada'];
        if(!extension_loaded('curl'))return ['configured'=>true,'online'=>false,'message'=>'Extensión cURL no disponible'];
        $payload=['installation_uuid'=>(string)config('deployment.installation_uuid','')]+(new NodeTelemetryService())->payload();
        try{
            $result=self::authenticatedPost('heartbeat',$payload,15);self::persistCanonicalUrl((string)$result['base_url']);$response=(array)$result['data'];$maintenance=[];
            try{$maintenance=RemoteMaintenanceAgent::process((array)($response['maintenance_tasks']??[]));}catch(Throwable $maintenanceError){ErrorReporter::report($maintenanceError,['operation'=>'remote_maintenance_agent']);}
            return ['configured'=>true,'online'=>true,'message'=>'Property Hub Central disponible','response'=>$response,'maintenance'=>$maintenance,'endpoint'=>$result['endpoint'],'transport_mode'=>$result['mode']];
        }catch(Throwable $e){return ['configured'=>true,'online'=>false,'message'=>$e->getMessage()];}
    }

    public static function manifest(): array
    {
        if(InstallationMode::isEdge()){
            $response=EdgeNodeClient::updateManifest();
            self::verifyManifest($response);
            return $response;
        }
        $result=self::authenticatedPost('manifest',[
            'installation_uuid'=>(string)config('deployment.installation_uuid',''),
            'app_version'=>(string)config('app.version',''),
            'api_version'=>'v1','ui_version'=>(string)config('app.ui_standard_version',''),
            'channel'=>(string)config('deployment.update_channel','stable'),
        ],18);
        self::persistCanonicalUrl((string)$result['base_url']);$response=(array)$result['data'];self::verifyManifest($response);
        try{LocalIntegrationProvisioning::reconcileNodeDesired((array)($response['manifest']['desired_connectors']??[]));}
        catch(Throwable $e){ErrorReporter::report($e,['operation'=>'central_desired_connector_reconcile']);}
        return $response+['_endpoint'=>$result['endpoint'],'_transport_mode'=>$result['mode']];
    }

    /** Manifiesto firmado por Central solicitado por un Node en nombre de uno de sus Edge. */
    public static function claimCentralEdgeEnrollment(string $token,array $edge): array
    {
        if(!InstallationMode::isNode())throw new RuntimeException('Solo un Property Node puede reclamar un enrolamiento Edge emitido por Central.');
        $result=self::authenticatedPost('edge-enrollment-claim',[
            'installation_uuid'=>(string)config('deployment.installation_uuid',''),
            'token'=>$token,
            'agent_uuid'=>(string)($edge['agent_uuid']??''),
            'agent_code'=>(string)($edge['agent_code']??''),
            'agent_name'=>(string)($edge['agent_name']??''),
            'base_url'=>(string)($edge['base_url']??''),
            'app_version'=>(string)($edge['app_version']??''),
        ],20);
        self::persistCanonicalUrl((string)$result['base_url']);
        return (array)$result['data'];
    }

    public static function manifestForEdge(array $agent,array $input=[]): array
    {
        if(!InstallationMode::isNode())throw new RuntimeException('Solo un Property Node puede solicitar un manifiesto downstream para Edge.');
        $result=self::authenticatedPost('manifest',[
            'installation_uuid'=>(string)config('deployment.installation_uuid',''),
            'app_version'=>(string)($input['app_version']??$agent['app_version']??'0.0.0'),
            'api_version'=>(string)($input['api_version']??'v1'),
            'ui_version'=>(string)($input['ui_version']??''),
            'channel'=>(string)config('deployment.update_channel','stable'),
            'downstream_role'=>'edge_agent',
            'downstream_installation_uuid'=>(string)($agent['agent_uuid']??''),
            'downstream_installation_code'=>(string)($agent['agent_code']??''),
        ],20);
        self::persistCanonicalUrl((string)$result['base_url']);
        $response=(array)$result['data'];self::verifyManifest($response);
        try{LocalIntegrationProvisioning::reconcileNodeDesired((array)($response['manifest']['desired_connectors']??[]));}
        catch(Throwable $e){ErrorReporter::report($e,['operation'=>'central_desired_connector_reconcile_for_edge']);}
        return $response+['_endpoint'=>$result['endpoint'],'_transport_mode'=>'central_via_property_node'];
    }

    public static function repositoryPublicKey(): string
    {
        $stmt=Connection::pdo()->prepare('SELECT repository_public_key FROM bh_deployment_settings WHERE installation_uuid=? LIMIT 1');
        $stmt->execute([(string)config('deployment.installation_uuid','')]);return (string)($stmt->fetchColumn()?:'');
    }

    public static function uploadEncryptedBackup(string $filePath,array $manifest=[],string $requestUuid=''): array
    {
        if(InstallationMode::isEdge())return EdgeNodeClient::uploadEncryptedBackup($filePath,$manifest);
        $conn=self::centralConnection();$url=(string)($conn['endpoint_url']??'');if($url==='')throw new RuntimeException('Fuente central no configurada para copia externa.');
        $sha=hash_file('sha256',$filePath)?:'';$filename=basename($filePath);
        $result=RepositoryTransport::postFile($url,'backup-upload',$filePath,[
            'X-Installation-Key: '.(string)($conn['credential']??''),'X-Installation-Uuid: '.(string)config('deployment.installation_uuid',''),
            'X-Backup-Filename: '.rawurlencode($filename),'X-Backup-Sha256: '.$sha,'X-Backup-Created-At: '.rawurlencode((string)($manifest['created_at']??date(DATE_ATOM))),
            'X-Backup-Type: '.rawurlencode((string)($manifest['backup_type']??'manual')),'X-Backup-App-Version: '.rawurlencode((string)($manifest['app_version']??config('app.version',''))),
            'X-Backup-Request-Uuid: '.rawurlencode($requestUuid),
        ],300);
        self::persistCanonicalUrl((string)$result['base_url']);return (array)$result['data'];
    }

    public static function completeMaintenanceTask(string $requestUuid,string $status,array $details=[]): array
    {
        if(InstallationMode::isEdge())throw new RuntimeException('Las tareas remotas del Edge se coordinan por su Property Node.');
        $result=self::authenticatedPost('maintenance-complete',['installation_uuid'=>(string)config('deployment.installation_uuid',''),'request_uuid'=>$requestUuid,'status'=>$status,'details'=>$details],30);
        self::persistCanonicalUrl((string)$result['base_url']);return (array)$result['data'];
    }

    public static function downloadRecoveryBackup(string $repositoryUrl,string $recoveryToken,string $destination): array
    {
        $result=RepositoryTransport::downloadRecoveryBackup($repositoryUrl,$recoveryToken,$destination,300);
        if(!InstallationMode::isEdge())self::persistCanonicalUrl((string)$result['base_url']);return $result;
    }

    private static function persistCanonicalUrl(string $baseUrl): void
    {
        if(InstallationMode::isEdge())return;
        try{
            $registry=new ConnectionRegistry();$existing=$registry->central();$current=RepositoryTransport::normalizeBaseUrl((string)($existing['endpoint_url']??''));
            if($baseUrl===''||$baseUrl===$current){if($baseUrl!==''&&$registry->available())$registry->mark('central',true);return;}
            $registry->save('central',$baseUrl,(string)($existing['credential']??''),(string)($existing['remote_code']??''),'self','online',['canonicalized_at'=>date(DATE_ATOM)]);
            $pdo=Connection::pdo();$uuid=(string)config('deployment.installation_uuid','');$pdo->prepare('UPDATE bh_deployment_settings SET repository_url=?,last_repository_check_at=NOW() WHERE installation_uuid=?')->execute([$baseUrl,$uuid]);
            $pdo->prepare("UPDATE bh_update_sources SET source_url=? WHERE installation_id=(SELECT id FROM bh_installations WHERE public_uuid=? LIMIT 1) AND source_type='central_repository'")->execute([$baseUrl,$uuid]);
        }catch(Throwable $e){ErrorReporter::report($e,['operation'=>'repository_url_persist','base_url'=>$baseUrl]);}
    }

    private static function verifyManifest(array $response): void
    {
        if(!is_array($response['manifest']??null)||trim((string)($response['signature']??''))==='')throw new RuntimeException('El repositorio devolvió un manifiesto sin firma.');
        $public=self::repositoryPublicKey();
        if($public==='')throw new RuntimeException(InstallationMode::isEdge()?'El Property Node todavía no provisionó la clave pública de distribución. Refresque el vínculo con el Node.':'No se dispone de la clave pública de la fuente central. Registre nuevamente la instalación.');
        $signature=base64_decode((string)$response['signature'],true);if($signature===false)throw new RuntimeException('La firma del manifiesto no tiene un formato válido.');
        $valid=openssl_verify(ManifestSigner::canonicalJson((array)$response['manifest']),$signature,$public,OPENSSL_ALGO_SHA256);if($valid!==1)throw new RuntimeException('La firma del manifiesto no es válida. La actualización fue rechazada.');
        $expected=(string)($response['canonical_sha256']??'');if($expected!==''&&!hash_equals($expected,hash('sha256',ManifestSigner::canonicalJson((array)$response['manifest']))))throw new RuntimeException('El checksum canónico del manifiesto no coincide.');
    }
}
