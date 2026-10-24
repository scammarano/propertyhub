<?php
declare(strict_types=1);

final class SchemaManager
{
    public function __construct(private PDO $pdo) {}

    public function files(): array
    {
        $files=[
            base_path('database/migrations/0001_functional_foundation.sql'),
            base_path('database/migrations/0002_deployment_foundation.sql'),
        ];
        if(InstallationMode::isCentral()) $files[]=base_path('database/migrations/0003_central_repository.sql');
        $files[]=base_path('database/migrations/0004_node_technology_inventory.sql');
        if(InstallationMode::isCentral()) $files[]=base_path('database/migrations/0005_central_portfolio.sql');
        $files[]=base_path('database/migrations/0006_property_site_topology_lorawan.sql');
        if(InstallationMode::isCentral()) $files[]=base_path('database/migrations/0007_central_property_types.sql');
        if(InstallationMode::isCentral()) $files[]=base_path('database/migrations/0008_installation_provisioning.sql');
        $files[]=base_path('database/migrations/0009_operational_core_crud.sql');
        $files[]=base_path('database/migrations/0010_platform_managed_assets.sql');
        if(InstallationMode::isCentral()) $files[]=base_path('database/migrations/0011_registration_token_lifecycle.sql');
        $files[]=base_path('database/migrations/0012_structure_generation_wizard.sql');
        $files[]=base_path('database/migrations/0013_structure_designer_assets.sql');
        $files[]=base_path('database/migrations/0014_topology_designer.sql');
        $files[]=base_path('database/migrations/0015_resident_app_real_data.sql');
        $files[]=base_path('database/migrations/0016_backup_recovery.sql');
        if(InstallationMode::isCentral()){ $files[]=base_path('database/migrations/0017_backup_ux_test_installations.sql'); $files[]=base_path('database/migrations/0018_central_backup_vault.sql'); }
        $files[]=base_path('database/migrations/0019_unified_roles_edge_agent.sql');
        if(InstallationMode::isCentral()) $files[]=base_path('database/migrations/0020_central_edge_registry.sql');
        if(!InstallationMode::isCentral()) $files[]=base_path('database/migrations/0021_edge_enrollment_onvif_manual.sql');
        if(!InstallationMode::isCentral()) $files[]=base_path('database/migrations/0022_edge_inventory_resident_camera_preview.sql');
        if(!InstallationMode::isCentral()) $files[]=base_path('database/migrations/0023_webrtc_media_gateway.sql');
        if(!InstallationMode::isCentral()) $files[]=base_path('database/migrations/0024_video_source_channel_lifecycle.sql');
        if(!InstallationMode::isCentral()) $files[]=base_path('database/migrations/0025_cpanel_direct_webrtc.sql');
        if(!InstallationMode::isCentral()) $files[]=base_path('database/migrations/0026_edge_camera_name_sync.sql');
        if(!InstallationMode::isCentral()) $files[]=base_path('database/migrations/0027_resident_identity_audit_vimar.sql');
        if(!InstallationMode::isCentral()) $files[]=base_path('database/migrations/0028_account_recovery_granular_permissions.sql');
        if(!InstallationMode::isCentral()) $files[]=base_path('database/migrations/0029_access_policy_engine_connector_adoption.sql');
        if(!InstallationMode::isCentral()) $files[]=base_path('database/migrations/0030_logical_asset_physical_binding.sql');
        if(!InstallationMode::isCentral()) $files[]=base_path('database/migrations/0031_multi_path_access_audit.sql');
        if(!InstallationMode::isCentral()) $files[]=base_path('database/migrations/0032_auth_schema_consistency_fix.sql');
        if(!InstallationMode::isCentral()) $files[]=base_path('database/migrations/0033_zkbio_api_lab.sql');
        if(!InstallationMode::isCentral()) $files[]=base_path('database/migrations/0034_zkbio_operational_bridge.sql');
        if(!InstallationMode::isCentral()) $files[]=base_path('database/migrations/0035_access_point_structural_placement.sql');
        if(!InstallationMode::isCentral()) $files[]=base_path('database/migrations/0036_person_vehicle_reclassification.sql');
        if(!InstallationMode::isCentral()) $files[]=base_path('database/migrations/0037_resident_resource_display_names.sql');
        if(!InstallationMode::isCentral()) $files[]=base_path('database/migrations/0038_vertical_stop_artifact_cleanup.sql');
        if(!InstallationMode::isCentral()) $files[]=base_path('database/migrations/0039_structure_area_model_separation.sql');
        if(!InstallationMode::isCentral()) $files[]=base_path('database/migrations/0040_vimarhub_local_bridge.sql');
        if(!InstallationMode::isCentral()) $files[]=base_path('database/migrations/0041_vimar_direct_edge_discovery.sql');
        if(InstallationMode::isCentral()){ $files[]=base_path('database/migrations/0042_component_registry_release_lifecycle.sql'); $files[]=base_path('database/migrations/0043_installation_deployment_enrollment.sql'); $files[]=base_path('database/migrations/0044_property_portfolio_token_history.sql'); $files[]=base_path('database/migrations/0045_property_deployment_file_pre_node_edges.sql'); }
        $files[]=base_path('database/migrations/0046_camera_access_contextual_controls.sql');
        return $files;
    }

    public function status(): array
    {
        $executed=[];
        if($this->tableExists('bh_schema_migrations')) {
            foreach($this->pdo->query('SELECT migration,checksum_sha256,executed_at FROM bh_schema_migrations ORDER BY id')->fetchAll() as $row) {
                $executed[(string)$row['migration']]=$row;
            }
        }
        $items=[];$ready=true;
        foreach($this->files() as $file){
            $name=basename($file);$checksum=is_file($file)?(hash_file('sha256',$file)?:''):'';
            $row=$executed[$name]??null;
            $state='pending';
            if($row){$state=hash_equals((string)$row['checksum_sha256'],$checksum)?'applied':'checksum_mismatch';}
            if($state!=='applied')$ready=false;
            $items[]=['migration'=>$name,'state'=>$state,'executed_at'=>$row['executed_at']??null];
        }
        return ['ready'=>$ready,'items'=>$items,'role'=>InstallationMode::current()];
    }

    public function repair(array $options=[]): array
    {
        $webSafe=!empty($options['web_safe']);
        if($webSafe){@set_time_limit(0);@ignore_user_abort(true);}
        $before=$this->status();$preBackup=null;$newRecoveryKey=null;$wasUntracked=false;$reusedPreBackup=false;
        if(empty($before['ready'])){
            $recoveryKey=trim((string)env('BACKUP_RECOVERY_KEY',''));
            if($recoveryKey===''){
                $recoveryKey='PHRK-'.rtrim(strtr(base64_encode(random_bytes(36)),'+/','-_'),'=');
                EnvironmentEditor::set(['BACKUP_RECOVERY_KEY'=>$recoveryKey]);putenv('BACKUP_RECOVERY_KEY='.$recoveryKey);$_ENV['BACKUP_RECOVERY_KEY']=$recoveryKey;$newRecoveryKey=$recoveryKey;
            }
            $wasUntracked=!$this->tableExists('bh_backup_settings');
            if($webSafe&&$this->tableExists('bh_backup_runs')){
                $recent=$this->pdo->query("SELECT filename,local_path,size_bytes,sha256,completed_at FROM bh_backup_runs WHERE backup_type='pre_update' AND status IN ('completed','warning') AND completed_at>=DATE_SUB(NOW(),INTERVAL 30 MINUTE) ORDER BY id DESC LIMIT 1")->fetch();
                if(is_array($recent)&&!empty($recent['local_path'])&&is_file((string)$recent['local_path'])){$preBackup=$recent+['reused'=>true];$reusedPreBackup=true;}
            }
            if(!$preBackup)$preBackup=(new BackupManager($this->pdo,base_path()))->create('pre_update',null,['local_only'=>true,'skip_prune'=>true,'untracked'=>$wasUntracked,'recovery_key'=>$recoveryKey,'schema_only'=>$webSafe]);
        }
        $results=(new MigrationRunner($this->pdo))->run($this->files());
        $catalog=new CatalogSeeder($this->pdo,base_path());
        $connectorCount=$catalog->seedConnectorDefinitions();
        $demoSeed=['installations'=>0,'plans'=>0,'connectors'=>0];
        $publishedCore=null;
        if(InstallationMode::isCentral()) {
            $catalog->seedVendorApis();
            // A web-triggered schema repair must stay bounded. Building/signing the
            // distributable Core can exceed LiteSpeed/Apache connection timeouts and
            // is independent from applying SQL migrations. Repository screens or the
            // explicit Sync action reconcile it immediately afterwards.
            if(!$webSafe){try{$publishedCore=(new RepositorySynchronizer($this->pdo))->syncOfficialCore();}
            catch(Throwable $e){ErrorReporter::report($e,['operation'=>'sync_official_core','version'=>(string)config('app.version')]);}}
            if(!$webSafe&&env_bool('APP_DEMO_MODE',false)) {
                $demoCount=(int)$this->pdo->query("SELECT COUNT(*) FROM bh_repo_installations WHERE is_demo=1")->fetchColumn();
                if($demoCount===0) {
                    require_once base_path('app/Seeders/CentralDemoSeeder.php');
                    $demoSeed=(new CentralDemoSeeder($this->pdo))->seed();
                }
            }
        }
        $uuid=(string)config('deployment.installation_uuid');
        $this->pdo->prepare("UPDATE bh_installations SET app_version=? WHERE public_uuid=?")->execute([(string)config('app.version'),$uuid]);
        $this->pdo->prepare("UPDATE bh_installed_components SET installed_version=?,display_name='2CAM Property Hub Core' WHERE installation_id=(SELECT id FROM bh_installations WHERE public_uuid=? LIMIT 1) AND component_type='core'")->execute([(string)config('app.version'),$uuid]);
        $this->pdo->prepare("UPDATE bh_installed_components SET installed_version=? WHERE installation_id=(SELECT id FROM bh_installations WHERE public_uuid=? LIMIT 1) AND component_type='module' AND component_code IN ('property-hub-central-console','resident-app','operations-console','property-edge-agent-console')")->execute([(string)config('app.version'),$uuid]);
        if($preBackup&&$wasUntracked&&$this->tableExists('bh_backup_runs')){
            try{$installationId=(int)$this->pdo->query('SELECT id FROM bh_installations ORDER BY id LIMIT 1')->fetchColumn();$this->pdo->prepare("INSERT INTO bh_backup_runs (public_uuid,installation_id,backup_type,status,filename,local_path,size_bytes,sha256,encrypted,destinations_json,manifest_json,completed_at) VALUES (?,?, 'pre_update','completed',?,?,?,?,1,?,?,NOW())")->execute([(string)$preBackup['uuid'],$installationId,(string)$preBackup['filename'],(string)$preBackup['path'],(int)$preBackup['size'],(string)$preBackup['sha256'],json_encode($preBackup['destinations'],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),json_encode($preBackup['manifest'],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]);}catch(Throwable $e){ErrorReporter::report($e,['operation'=>'register_bootstrap_pre_update_backup']);}
        }
        return ['migrations'=>$results,'connectors'=>$connectorCount,'demo'=>$demoSeed,'pre_update_backup'=>$preBackup,'pre_update_backup_reused'=>$reusedPreBackup,'new_backup_recovery_key'=>$newRecoveryKey,'published_core'=>$publishedCore,'repository_sync_deferred'=>$webSafe&&InstallationMode::isCentral()];
    }

    private function tableExists(string $table): bool
    {
        $stmt=$this->pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=? LIMIT 1');
        $stmt->execute([$table]);return $stmt->fetchColumn()!==false;
    }
}
