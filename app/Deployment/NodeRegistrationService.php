<?php
declare(strict_types=1);

final class NodeRegistrationService
{
    public function register(string $repositoryUrl,string $token): array
    {
        if(!InstallationMode::isNode())throw new RuntimeException('Solo un Property Node se registra directamente en Property Hub Central. Los Edge Agents se distribuyen exclusivamente a través de su Node.');
        $token=trim($token);
        if($token==='')throw new InvalidArgumentException('Indique un token de registro o reconexión.');

        $registry=new ConnectionRegistry();
        try{$registry->importLegacyIfNeeded();}catch(Throwable $e){ErrorReporter::report($e,['operation'=>'connection_registry_import_registration']);}
        $existingCentral=$registry->central();
        $oldInstallationKey=(string)($existingCentral['credential']??'');
        if($oldInstallationKey==='')$oldInstallationKey=(string)config('deployment.installation_api_key');
        $isRelink=str_starts_with(strtoupper($token),'BHR-RELINK-');
        // La credencial permanente se genera/rota localmente. Un Agent reparado vía
        // Property Node no debe depender de un INSTALLATION_API_KEY histórico en .env.
        $installationKey=$isRelink?bin2hex(random_bytes(32)):($oldInstallationKey!==''?$oldInstallationKey:bin2hex(random_bytes(32)));

        $payload=[
            'installation_uuid'=>(string)config('deployment.installation_uuid'),
            'installation_key'=>$installationKey,
            'installation_code'=>(string)config('app.instance_code'),
            'installation_name'=>(string)config('app.instance_name'),
            'installation_type'=>(string)config('app.instance_type'),
            'base_url'=>(string)config('app.url'),
            'update_channel'=>(string)config('deployment.update_channel'),
            'app_version'=>(string)config('app.version','0.0.0'),
            'api_version'=>'v1',
            'ui_version'=>(string)config('app.ui_standard_version',''),
            'instance_role'=>InstallationMode::current(),
            'parent_property_code'=>'',
        ];

        try {
            $result = RepositoryTransport::postJson(
                $repositoryUrl,
                'register',
                $payload,
                ['X-Registration-Token: '.$token],
                18
            );
        } catch(Throwable $e) { throw $e; }

        $response=(array)$result['data'];
        if(empty($response['ok']))throw new RuntimeException('Respuesta de registro inválida.');

        $canonicalUrl=(string)$result['base_url'];
        $pdo=Connection::pdo();
        $public=(string)($response['repository_public_key']??'');
        $pdo->beginTransaction();
        try{
            $stmt=$pdo->prepare("UPDATE bh_deployment_settings SET repository_url=?,repository_status='registered',repository_public_key=?,repository_installation_key_hash=?,last_repository_check_at=NOW(),metadata_json=? WHERE installation_uuid=?");
            $stmt->execute([
                $canonicalUrl,$public,hash('sha256',$installationKey),
                json_encode(['last_endpoint'=>$result['endpoint'],'transport_mode'=>$result['mode'],'last_operation'=>$response['operation']??'registered'],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),
                (string)config('deployment.installation_uuid')
            ]);
            // Mantener el mismo source_id al renovar credenciales. El historial
            // de bh_update_jobs puede referenciar esta fuente por FK.
            $installationUuid=(string)config('deployment.installation_uuid');
            $source=$pdo->prepare("SELECT s.id FROM bh_update_sources s JOIN bh_installations i ON i.id=s.installation_id WHERE i.public_uuid=? AND s.source_type='central_repository' ORDER BY s.priority ASC,s.id ASC LIMIT 1 FOR UPDATE");
            $source->execute([$installationUuid]);
            $sourceId=(int)($source->fetchColumn()?:0);
            if($sourceId>0){
                $stmt=$pdo->prepare("UPDATE bh_update_sources SET source_url=?,public_key=?,enabled=1,priority=10,last_error=NULL,updated_at=NOW() WHERE id=?");
                $stmt->execute([$canonicalUrl,$public,$sourceId]);
            } else {
                $stmt=$pdo->prepare("INSERT INTO bh_update_sources (installation_id,source_type,source_url,public_key,enabled,priority) SELECT id,'central_repository',?,?,1,10 FROM bh_installations WHERE public_uuid=? LIMIT 1");
                $stmt->execute([$canonicalUrl,$public,$installationUuid]);
            }
            $pdo->commit();
        }catch(Throwable $e){
            if($pdo->inTransaction())$pdo->rollBack();
            // El Central ya aceptó la clave nueva. No se restaura aquí, porque eso
            // dejaría al nodo con una credencial distinta a la registrada.
            throw $e;
        }
        $registry->save('central',$canonicalUrl,$installationKey,'','self','online',['registration_operation'=>$response['operation']??'registered','registered_at'=>date(DATE_ATOM)]);
        return $response + ['_endpoint'=>$result['endpoint'],'_transport_mode'=>$result['mode'],'_base_url'=>$canonicalUrl];
    }
}
