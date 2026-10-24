<?php
declare(strict_types=1);

final class InstallerService
{
    public const EDGE_ENROLLMENT_CONTRACT = 'property-node-url-plus-token-v2';

    public function __construct(private string $root) {}

    public function install(array $input): array
    {
        $appVersion=$this->packageVersion();
        $mode = in_array(($input['instance_role'] ?? ''), ['central_repository','building_node','edge_agent'], true)
            ? (string)$input['instance_role'] : 'building_node';
        $this->validate($input, $mode);
        $this->ensureRuntimeDirectories();

        $dbHost = trim((string)$input['db_host']);
        $dbPort = (int)$input['db_port'];
        $dbName = trim((string)$input['db_database']);
        $dbUser = trim((string)$input['db_username']);
        $dbPassword = (string)($input['db_password'] ?? '');
        $createDatabase = !empty($input['create_database']);

        $serverPdo = new PDO(
            sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $dbHost, $dbPort),
            $dbUser,
            $dbPassword,
            [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::MYSQL_ATTR_USE_BUFFERED_QUERY=>true]
        );
        if ($createDatabase) {
            $quoted = '`'.str_replace('`', '``', $dbName).'`';
            $serverPdo->exec("CREATE DATABASE IF NOT EXISTS {$quoted} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        }
        $pdo = new PDO(
            sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $dbHost, $dbPort, $dbName),
            $dbUser,
            $dbPassword,
            [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false, PDO::MYSQL_ATTR_USE_BUFFERED_QUERY=>true]
        );

        $migrations = [
            $this->root.'/database/migrations/0001_functional_foundation.sql',
            $this->root.'/database/migrations/0002_deployment_foundation.sql',
        ];
        if ($mode === 'central_repository') $migrations[] = $this->root.'/database/migrations/0003_central_repository.sql';
        $migrations[] = $this->root.'/database/migrations/0004_node_technology_inventory.sql';
        if ($mode === 'central_repository') $migrations[] = $this->root.'/database/migrations/0005_central_portfolio.sql';
        $migrations[] = $this->root.'/database/migrations/0006_property_site_topology_lorawan.sql';
        if ($mode === 'central_repository') $migrations[] = $this->root.'/database/migrations/0007_central_property_types.sql';
        if ($mode === 'central_repository') $migrations[] = $this->root.'/database/migrations/0008_installation_provisioning.sql';
        $migrations[] = $this->root.'/database/migrations/0009_operational_core_crud.sql';
        $migrations[] = $this->root.'/database/migrations/0010_platform_managed_assets.sql';
        if ($mode === 'central_repository') $migrations[] = $this->root.'/database/migrations/0011_registration_token_lifecycle.sql';
        $migrations[] = $this->root.'/database/migrations/0012_structure_generation_wizard.sql';
        $migrations[] = $this->root.'/database/migrations/0013_structure_designer_assets.sql';
        $migrations[] = $this->root.'/database/migrations/0014_topology_designer.sql';
        $migrations[] = $this->root.'/database/migrations/0015_resident_app_real_data.sql';
        $migrations[] = $this->root.'/database/migrations/0016_backup_recovery.sql';
        if ($mode === 'central_repository') { $migrations[] = $this->root.'/database/migrations/0017_backup_ux_test_installations.sql'; $migrations[] = $this->root.'/database/migrations/0018_central_backup_vault.sql'; }
        $migrations[] = $this->root.'/database/migrations/0019_unified_roles_edge_agent.sql';
        if ($mode === 'central_repository') $migrations[] = $this->root.'/database/migrations/0020_central_edge_registry.sql';
        if ($mode !== 'central_repository') { $migrations[] = $this->root.'/database/migrations/0021_edge_enrollment_onvif_manual.sql'; $migrations[] = $this->root.'/database/migrations/0022_edge_inventory_resident_camera_preview.sql'; }
        if ($mode !== 'central_repository') {
            foreach (['0023_webrtc_media_gateway.sql','0024_video_source_channel_lifecycle.sql','0025_cpanel_direct_webrtc.sql','0026_edge_camera_name_sync.sql','0027_resident_identity_audit_vimar.sql','0028_account_recovery_granular_permissions.sql','0029_access_policy_engine_connector_adoption.sql','0030_logical_asset_physical_binding.sql','0031_multi_path_access_audit.sql','0032_auth_schema_consistency_fix.sql','0033_zkbio_api_lab.sql','0034_zkbio_operational_bridge.sql','0035_access_point_structural_placement.sql','0036_person_vehicle_reclassification.sql','0037_resident_resource_display_names.sql','0038_vertical_stop_artifact_cleanup.sql','0039_structure_area_model_separation.sql','0040_vimarhub_local_bridge.sql','0041_vimar_direct_edge_discovery.sql'] as $migration) $migrations[]=$this->root.'/database/migrations/'.$migration;
        }
        if ($mode === 'central_repository') { $migrations[]=$this->root.'/database/migrations/0042_component_registry_release_lifecycle.sql'; $migrations[]=$this->root.'/database/migrations/0043_installation_deployment_enrollment.sql'; $migrations[]=$this->root.'/database/migrations/0044_property_portfolio_token_history.sql'; $migrations[]=$this->root.'/database/migrations/0045_property_deployment_file_pre_node_edges.sql'; }
        $migrations[]=$this->root.'/database/migrations/0046_camera_access_contextual_controls.sql';
        $migrationResults = (new MigrationRunner($pdo))->run($migrations);
        $catalogSeeder = new CatalogSeeder($pdo, $this->root);
        $catalogSeeder->seedConnectorDefinitions();

        $installationUuid = self::uuid();
        $installationKey = bin2hex(random_bytes(32));
        $installationCode = strtoupper(trim((string)$input['instance_code']));
        $installationName = trim((string)$input['instance_name']);
        $installationType = (string)$input['instance_type'];
        $timezone = trim((string)($input['app_timezone'] ?? 'America/Caracas'));
        $channel = (string)($input['update_channel'] ?? ($mode === 'central_repository' ? 'development' : 'stable'));
        $appUrl = rtrim(trim((string)$input['app_url']), '/');

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("INSERT INTO bh_installations (public_uuid,code,name,installation_type,timezone,status,update_channel,app_version,api_version) VALUES (?,?,?,?,?,'active',?,?,'v1')");
            $stmt->execute([$installationUuid,$installationCode,$installationName,$installationType,$timezone,$channel,$appVersion]);
            $installationId = (int)$pdo->lastInsertId();

            $adminEmail = strtolower(trim((string)$input['admin_email']));
            $adminName = trim((string)$input['admin_name']);
            $adminUsername = trim((string)($input['admin_username'] ?? 'admin'));
            $adminType = $mode === 'central_repository' ? 'superadmin' : 'admin';
            $stmt = $pdo->prepare('INSERT INTO bh_local_users (installation_id,email,username,password_hash,user_type,status) VALUES (?,?,?,?,?,\'active\')');
            $stmt->execute([$installationId,$adminEmail,$adminUsername,password_hash((string)$input['admin_password'], PASSWORD_DEFAULT),$adminType]);
            $adminUserId = (int)$pdo->lastInsertId();

            $repositoryUrl = $mode === 'central_repository'
                ? RepositoryTransport::normalizeBaseUrl((string)($input['repository_public_url'] ?? $appUrl))
                : RepositoryTransport::normalizeBaseUrl((string)($input['central_repository_url'] ?? ''));
            $repositoryStatus = $mode === 'central_repository' ? 'registered' : ($repositoryUrl === '' ? 'not_configured' : 'pending');
            $stmt = $pdo->prepare('INSERT INTO bh_deployment_settings (installation_id,instance_role,installation_uuid,repository_url,repository_installation_key_hash,repository_status,update_channel,allow_offline,metadata_json) VALUES (?,?,?,?,?,?,?,?,?)');
            $stmt->execute([
                $installationId,$mode,$installationUuid,$repositoryUrl,hash('sha256',$installationKey),$repositoryStatus,$channel,
                !empty($input['allow_offline']) ? 1 : 0,
                json_encode(['installed_by'=>$adminName,'installer_version'=>$appVersion,'deployment_bundle_id'=>(string)($input['deployment_bundle_id']??''),'deployment_bundle_fingerprint'=>(string)($input['deployment_bundle_fingerprint']??'')], JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),
            ]);

            $componentStmt = $pdo->prepare('INSERT INTO bh_installed_components (installation_id,component_type,component_code,display_name,installed_version,api_compatibility,channel,source) VALUES (?,?,?,?,?,?,?,?)');
            $components = [
                ['core','property-hub-core','2CAM Property Hub Core',$appVersion,'v1',$channel,'bundled'],
                ['ui','2cam-ui-standard','2CAM UI Standard','1.2.0',null,'vendor','bundled'],
                ['api','property-api','Property API','v1','v1',$channel,'bundled'],
            ];
            if ($mode === 'central_repository') {
                $components[]=['module','property-hub-central-console','Property Hub Central Console',$appVersion,'v1',$channel,'bundled'];
                $components[]=['api','repository-api','Repository API','v1','v1',$channel,'bundled'];
            } elseif ($mode === 'building_node') {
                $components[]=['module','resident-app','Resident App',$appVersion,'v1',$channel,'bundled'];
                $components[]=['module','operations-console','Operations Console',$appVersion,'v1',$channel,'bundled'];
                $components[]=['module','edge-agent-management','Gestión de Property Edge Agents',$appVersion,'v1',$channel,'bundled'];
            } else {
                $components[]=['module','property-edge-agent-console','Property Edge Agent Console',$appVersion,'v1',$channel,'bundled'];
                $components[]=['module','local-integration-lab','Laboratorio de integraciones locales',$appVersion,'v1',$channel,'bundled'];
            }
            foreach ($components as $component) $componentStmt->execute([$installationId,...$component]);

            if ($repositoryUrl !== '') {
                $stmt = $pdo->prepare('INSERT INTO bh_update_sources (installation_id,source_type,source_url,enabled,priority) VALUES (?,\'central_repository\',?,1,10)');
                $stmt->execute([$installationId,$repositoryUrl]);
            }

            $centralData = [];
            if ($mode === 'central_repository') {
                $keys = $this->generateRepositoryKeys();
                $registrationToken = 'BHR-'.strtoupper(bin2hex(random_bytes(12)));
                $stmt = $pdo->prepare("INSERT INTO bh_repo_registration_tokens (token_name,token_hash,allowed_channel,max_uses,status,created_by) VALUES (?,?,?,?,'active',?)");
                $stmt->execute(['Token inicial para primer Property Node',hash('sha256',$registrationToken),'stable',1,$adminUserId]);
                $stmt = $pdo->prepare("INSERT INTO bh_repo_api_versions (api_code,version,status,minimum_app_version,documentation_path,changelog,released_at) VALUES ('property-api','v1','development',?,'docs/API_REPOSITORY_V1.md','API de identidad, operación y aplicaciones Property Hub.',NOW())");
                $stmt->execute([$appVersion]);
                $stmt = $pdo->prepare("INSERT INTO bh_repo_api_versions (api_code,version,status,minimum_app_version,documentation_path,changelog,released_at) VALUES ('repository-api','v1','development',?,'docs/API_REPOSITORY_V1.md','API privada de registro, heartbeat, telemetría agregada, catálogo y manifiestos.',NOW()) ON DUPLICATE KEY UPDATE status=VALUES(status),minimum_app_version=VALUES(minimum_app_version),changelog=VALUES(changelog)");
                $stmt->execute([$appVersion]);
                $catalogSeeder->seedVendorApis();
                $demoSeed = !empty($input['demo_mode']) ? (new CentralDemoSeeder($pdo))->seed() : ['installations'=>0,'plans'=>0,'connectors'=>0];
                $centralData = ['registration_token'=>$registrationToken,'repository_public_key'=>$keys['public'],'demo_seed'=>$demoSeed];
            }
            if ($mode === 'building_node' && !empty($input['demo_mode'])) {
                (new NodeDemoSeeder($pdo))->seed($installationId);
            }
            if ($mode !== 'central_repository') {
                $pdo->prepare('UPDATE bh_backup_settings SET central_copy_enabled=1,node_copy_enabled=? WHERE installation_id=?')->execute([$mode === 'edge_agent' ? 1 : 0,$installationId]);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $e;
        }

        $backupRecoveryKey='PHRK-'.rtrim(strtr(base64_encode(random_bytes(36)),'+/','-_'),'=');

        $env = [
            'APP_NAME'=>'2CAM Property Hub',
            'APP_ENV'=>'production',
            'APP_DEBUG'=>'false',
            'APP_DIAGNOSTICS'=>'true',
            'APP_LOG_LEVEL'=>'debug',
            'APP_LOG_REQUESTS'=>'true',
            'DIAGNOSTIC_TOKEN'=>bin2hex(random_bytes(24)),
            'APP_URL'=>$appUrl,
            'APP_TIMEZONE'=>$timezone,
            'APP_DEMO_MODE'=>!empty($input['demo_mode']) ? 'true' : 'false',
            'APP_SESSION_NAME'=>'property_hub_'.strtolower(preg_replace('/[^A-Z0-9]+/i','_',$installationCode)),
            'APP_KEY'=>bin2hex(random_bytes(32)),
            'INSTANCE_ROLE'=>$mode,
            'INSTANCE_UUID'=>$installationUuid,
            'INSTANCE_CODE'=>$installationCode,
            'INSTANCE_NAME'=>$installationName,
            'INSTANCE_TYPE'=>$installationType,
            'INSTALLATION_API_KEY'=>$installationKey,
            'DB_HOST'=>$dbHost,
            'DB_PORT'=>(string)$dbPort,
            'DB_DATABASE'=>$dbName,
            'DB_USERNAME'=>$dbUser,
            'DB_PASSWORD'=>$dbPassword,
            'UPDATE_CHANNEL'=>$channel,
            'CENTRAL_REPOSITORY_URL'=>$mode === 'building_node' ? RepositoryTransport::normalizeBaseUrl((string)($input['central_repository_url'] ?? '')) : '',
            'CENTRAL_REGISTRATION_TOKEN'=>'',
            'REPOSITORY_ENABLED'=>$mode === 'central_repository' ? 'true' : 'false',
            'REPOSITORY_PUBLIC_URL'=>$mode === 'central_repository' ? RepositoryTransport::normalizeBaseUrl((string)($input['repository_public_url'] ?? $appUrl)) : '',
            'REPOSITORY_PRIVATE_KEY_PATH'=>$mode === 'central_repository' ? 'storage/keys/repository-private.pem' : '',
            'REPOSITORY_PUBLIC_KEY_PATH'=>$mode === 'central_repository' ? 'storage/keys/repository-public.pem' : '',
            'ALLOW_OFFLINE'=>!empty($input['allow_offline']) ? 'true' : 'false',
            'BACKUP_RECOVERY_KEY'=>$backupRecoveryKey,
            'PARENT_PROPERTY_CODE'=>'',
            'PROPERTY_NODE_URL'=>'',
            'PROPERTY_NODE_AGENT_KEY'=>'',
            'GOOGLE_AUTH_ENABLED'=>'false','GOOGLE_CLIENT_ID'=>'','GOOGLE_CLIENT_SECRET'=>'','GOOGLE_REDIRECT_URI'=>$appUrl.'/oauth/google/callback.php',
            'APPLE_AUTH_ENABLED'=>'false','APPLE_CLIENT_ID'=>'','APPLE_CLIENT_SECRET'=>'','APPLE_TEAM_ID'=>'','APPLE_KEY_ID'=>'','APPLE_PRIVATE_KEY_PATH'=>'storage/keys/AuthKey_XXXXXXXXXX.p8','APPLE_REDIRECT_URI'=>$appUrl.'/oauth/apple/callback.php',
            'NOTIFY_EMAIL_ENABLED'=>'false','NOTIFY_PUSH_ENABLED'=>'false','NOTIFY_WHATSAPP_ENABLED'=>'false','NOTIFY_SMS_ENABLED'=>'false',
        ];
        EnvironmentWriter::write($this->root.'/.env', $env);

        // En el Central el paquete oficial incluye el ZIP distribuible para nodos.
        // Se publica automáticamente para que una instalación recién creada pueda
        // servir actualizaciones sin un segundo paso manual.
        if($mode==='central_repository'){
            foreach(['INSTANCE_ROLE'=>'central_repository','INSTANCE_UUID'=>$installationUuid,'REPOSITORY_ENABLED'=>'true','REPOSITORY_PRIVATE_KEY_PATH'=>'storage/keys/repository-private.pem','REPOSITORY_PUBLIC_KEY_PATH'=>'storage/keys/repository-public.pem','REPOSITORY_PUBLIC_URL'=>RepositoryTransport::normalizeBaseUrl((string)($input['repository_public_url'] ?? $appUrl))] as $k=>$v){putenv($k.'='.$v);$_ENV[$k]=$v;}
            try{
                $official=(new RepositorySynchronizer($pdo))->syncOfficialComponents($adminUserId);
                $centralData['published_core']=(array)($official['core']??[]);
                $centralData['published_connectors']=(array)($official['connectors']??[]);
            }catch(Throwable $publishError){$centralData['published_core_error']=$publishError->getMessage();}
        }

        $registrationResult = null;
        if ($mode === 'building_node' && trim((string)($input['central_repository_url'] ?? '')) !== '' && trim((string)($input['central_registration_token'] ?? '')) !== '') {
            try {
                $registrationResult = $this->registerNode([
                    'repository_url'=>(string)$input['central_repository_url'],
                    'registration_token'=>(string)$input['central_registration_token'],
                    'installation_uuid'=>$installationUuid,
                    'installation_key'=>$installationKey,
                    'installation_code'=>$installationCode,
                    'installation_name'=>$installationName,
                    'installation_type'=>$installationType,
                    'base_url'=>$appUrl,
                    'update_channel'=>$channel,
                    'instance_role'=>$mode,
                    'parent_property_code'=>'',
                ]);
                $repoPublicKey=(string)($registrationResult['repository_public_key'] ?? '');
                $expectedBundleFingerprint=strtolower(trim((string)($input['deployment_bundle_fingerprint']??'')));
                if($expectedBundleFingerprint!==''){
                    $actualFingerprint=strtolower(PropertyDeploymentFileService::publicKeyFingerprint($repoPublicKey));
                    if(!hash_equals($expectedBundleFingerprint,$actualFingerprint))throw new RuntimeException('La clave pública presentada por Central no coincide con el archivo de despliegue. Genere un archivo nuevo desde Central antes de continuar.');
                }
                $canonicalRepositoryUrl=(string)($registrationResult['_base_url'] ?? RepositoryTransport::normalizeBaseUrl((string)$input['central_repository_url']));
                $stmt = $pdo->prepare("UPDATE bh_deployment_settings SET repository_url=?, repository_status='registered', repository_public_key=?, last_repository_check_at=NOW(), metadata_json=? WHERE installation_id=?");
                $stmt->execute([$canonicalRepositoryUrl,$repoPublicKey,json_encode(['last_endpoint'=>$registrationResult['_endpoint']??null,'transport_mode'=>$registrationResult['_transport_mode']??null,'deployment_bundle_id'=>(string)($input['deployment_bundle_id']??''),'deployment_bundle_fingerprint'=>(string)($input['deployment_bundle_fingerprint']??''),'deployment_bundle_verified'=>trim((string)($input['deployment_bundle_id']??''))!=='' ],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),$installationId]);
                $stmt = $pdo->prepare("UPDATE bh_update_sources SET source_url=?, public_key=? WHERE installation_id=? AND source_type='central_repository'");
                $stmt->execute([$canonicalRepositoryUrl,$repoPublicKey,$installationId]);
                EnvironmentEditor::setFile($this->root.'/.env', ['CENTRAL_REPOSITORY_URL'=>$canonicalRepositoryUrl]);
            } catch (Throwable $e) {
                $stmt = $pdo->prepare('UPDATE bh_deployment_settings SET repository_status=\'error\', metadata_json=? WHERE installation_id=?');
                $stmt->execute([json_encode(['registration_error'=>$e->getMessage(),'deployment_bundle_id'=>(string)($input['deployment_bundle_id']??'')],JSON_UNESCAPED_UNICODE),$installationId]);
                if(trim((string)($input['deployment_bundle_id']??''))!=='')throw new RuntimeException('No fue posible completar el enrolamiento automático del Property Node desde el archivo de despliegue. '.$e->getMessage(),0,$e);
                $registrationResult = ['ok'=>false,'error'=>$e->getMessage()];
            }
        }

        $edgePairingResult = null;
        if ($mode === 'edge_agent' && trim((string)($input['property_node_url'] ?? '')) !== '' && trim((string)($input['edge_pairing_token'] ?? '')) !== '') {
            foreach(['INSTANCE_ROLE'=>$mode,'INSTANCE_UUID'=>$installationUuid,'INSTANCE_CODE'=>$installationCode,'INSTANCE_NAME'=>$installationName,'APP_URL'=>$appUrl,'UPDATE_CHANNEL'=>$channel,'INSTALLATION_API_KEY'=>$installationKey,'DB_HOST'=>$dbHost,'DB_PORT'=>(string)$dbPort,'DB_DATABASE'=>$dbName,'DB_USERNAME'=>$dbUser,'DB_PASSWORD'=>$dbPassword] as $k=>$v){putenv($k.'='.$v);$_ENV[$k]=$v;}
            try { $edgePairingResult=EdgeNodeClient::pair((string)$input['property_node_url'],(string)$input['edge_pairing_token']); $parentCode=strtoupper(trim((string)($edgePairingResult['property_code']??''))); if($parentCode!==''){EnvironmentEditor::setFile($this->root.'/.env',['PARENT_PROPERTY_CODE'=>$parentCode]);} $expectedBundleFingerprint=strtolower(trim((string)($input['deployment_bundle_fingerprint']??''))); if($expectedBundleFingerprint!==''){ $provisioning=EdgeNodeClient::refreshProvisioning(); $distribution=(array)($provisioning['update_distribution']??[]); $repositoryPublicKey=trim((string)($distribution['repository_public_key']??'')); if($repositoryPublicKey==='')throw new RuntimeException('El Property Node no entregó la clave pública de Central para verificar el archivo de despliegue.'); $actualFingerprint=strtolower(PropertyDeploymentFileService::publicKeyFingerprint($repositoryPublicKey)); if(!hash_equals($expectedBundleFingerprint,$actualFingerprint))throw new RuntimeException('El Property Node está vinculado a una Central cuya clave no coincide con el archivo de despliegue.'); $edgePairingResult['deployment_file_verified']=true; } }
            catch(Throwable $e){ throw new RuntimeException('No fue posible enrolar el Property Edge Agent. Verifique que el código local coincida exactamente con el Edge preparado en Central, que el token no haya vencido y que el Property Node pueda comunicarse con Central. Detalle: '.$e->getMessage(),0,$e); }
        }

        $lock = [
            'installed_at'=>date(DATE_ATOM),
            'installer_version'=>$appVersion,
            'instance_role'=>$mode,
            'installation_uuid'=>$installationUuid,
            'installation_code'=>$installationCode,
            'database'=>$dbName,
            'app_version'=>$appVersion,
            'ui_version'=>'1.2.0',
        ];
        if (file_put_contents($this->root.'/storage/installed.lock', json_encode($lock, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES), LOCK_EX) === false) {
            throw new RuntimeException('La instalación terminó, pero no fue posible escribir storage/installed.lock.');
        }
        @chmod($this->root.'/storage/installed.lock', 0600);

        return [
            'mode'=>$mode,
            'installation_uuid'=>$installationUuid,
            'installation_code'=>$installationCode,
            'installation_id'=>$installationId,
            'admin_email'=>(string)$input['admin_email'],
            'app_url'=>$appUrl,
            'migrations'=>$migrationResults,
            'registration'=>$registrationResult,
            'backup_recovery_key'=>$backupRecoveryKey,
            'edge_pairing'=>$edgePairingResult,
        ] + $centralData;
    }

    private function packageVersion(): string
    {
        $meta=json_decode((string)@file_get_contents($this->root.'/VERSION.json'),true);
        $version=is_array($meta)?trim((string)($meta['version']??'')):'';
        return $version!==''?$version:'0.14.60';
    }

    private function ensureRuntimeDirectories(): void
    {
        foreach ([
            'storage/keys',
            'storage/repository/packages',
            'storage/repository/manifests',
            'storage/repository/signatures',
            'storage/backups',
            'storage/backups/tmp',
            'storage/backups/incoming',
            'storage/backups/agents',
            'storage/updates/downloads',
            'storage/updates/staging',
            'storage/updates/rollback',
            'storage/logs',
        ] as $relative) {
            $dir=$this->root.'/'.$relative;
            if(!is_dir($dir) && !@mkdir($dir,0775,true) && !is_dir($dir)) {
                throw new RuntimeException('No fue posible crear el directorio requerido: '.$relative);
            }
            if(!is_writable($dir)) throw new RuntimeException('El directorio no es escribible: '.$relative);
        }
    }

    private function validate(array $input, string $mode): void
    {
        foreach (['app_url','instance_code','instance_name','instance_type','db_host','db_port','db_database','db_username','admin_name','admin_email','admin_password'] as $field) {
            if (trim((string)($input[$field] ?? '')) === '') throw new InvalidArgumentException('Falta el campo obligatorio: '.$field);
        }
        if (!filter_var((string)$input['app_url'], FILTER_VALIDATE_URL)) throw new InvalidArgumentException('APP URL no es válida.');
        if (!str_ends_with(rtrim((string)$input['app_url'],'/'), '/public')) throw new InvalidArgumentException('APP URL debe terminar en /public para esta versión.');
        if (!filter_var((string)$input['admin_email'], FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('El correo del administrador no es válido.');
        if (strlen((string)$input['admin_password']) < 10) throw new InvalidArgumentException('La contraseña debe tener al menos 10 caracteres.');
        if (($input['admin_password'] ?? '') !== ($input['admin_password_confirmation'] ?? '')) throw new InvalidArgumentException('Las contraseñas no coinciden.');
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{2,39}$/', (string)$input['instance_code'])) throw new InvalidArgumentException('El código de instalación debe tener 3 a 40 caracteres alfanuméricos, guion o guion bajo.');
        if ($mode === 'central_repository' && !filter_var((string)($input['repository_public_url'] ?? $input['app_url']), FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('La URL pública del repositorio no es válida.');
        }
        if ($mode === 'central_repository' && !str_ends_with(rtrim((string)($input['repository_public_url'] ?? $input['app_url']),'/'), '/public')) {
            throw new InvalidArgumentException('La URL del repositorio debe terminar en /public.');
        }
        if ($mode === 'edge_agent') {
            // 0.14.53+: the parent property is never typed by the technician.
            // It is authoritative data returned by the Property Node after consuming
            // the enrollment token. Keep this validator deliberately limited to the
            // two values that the Edge installer actually owns.
            $nodeUrl=trim((string)($input['property_node_url'] ?? ''));
            if ($nodeUrl==='' || !filter_var(EdgeHttp::normalizeBaseUrl($nodeUrl),FILTER_VALIDATE_URL)) throw new InvalidArgumentException('Indique la URL válida del Property Node.');
            if(trim((string)($input['edge_pairing_token']??''))==='') throw new InvalidArgumentException('Indique el token de enrolamiento Edge generado por Central o por el Property Node.');
        }
    }

    private function generateRepositoryKeys(): array
    {
        $privatePath = $this->root.'/storage/keys/repository-private.pem';
        $publicPath = $this->root.'/storage/keys/repository-public.pem';
        $resource = openssl_pkey_new(['private_key_bits'=>3072,'private_key_type'=>OPENSSL_KEYTYPE_RSA]);
        if ($resource === false) throw new RuntimeException('OpenSSL no pudo generar la clave del repositorio.');
        if (!openssl_pkey_export($resource, $private)) throw new RuntimeException('No fue posible exportar la clave privada del repositorio.');
        $details = openssl_pkey_get_details($resource);
        if (!is_array($details) || empty($details['key'])) throw new RuntimeException('No fue posible obtener la clave pública del repositorio.');
        if (file_put_contents($privatePath, $private, LOCK_EX) === false || file_put_contents($publicPath, $details['key'], LOCK_EX) === false) {
            throw new RuntimeException('No fue posible guardar las claves del repositorio.');
        }
        @chmod($privatePath, 0600); @chmod($publicPath, 0644);
        return ['private'=>$private,'public'=>(string)$details['key']];
    }

    private function registerNode(array $payload): array
    {
        $repositoryUrl = (string)$payload['repository_url'];
        $token = (string)$payload['registration_token'];
        unset($payload['repository_url'],$payload['registration_token']);
        $result = RepositoryTransport::postJson(
            $repositoryUrl,
            'register',
            $payload,
            ['X-Registration-Token: '.$token],
            18
        );
        $decoded = (array)$result['data'];
        if (empty($decoded['ok'])) throw new RuntimeException('El repositorio devolvió una respuesta de registro inválida.');
        return $decoded + [
            '_endpoint'=>(string)$result['endpoint'],
            '_transport_mode'=>(string)$result['mode'],
            '_base_url'=>(string)$result['base_url'],
        ];
    }

    public static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
