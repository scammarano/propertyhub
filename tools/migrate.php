<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit("CLI only\n");}
$root=dirname(__DIR__);
require_once $root.'/app/Support/helpers.php';
require_once $root.'/app/Database/Connection.php';
require_once $root.'/app/Deployment/InstallationMode.php';
require_once $root.'/app/Deployment/DesiredConnectorPolicy.php';
require_once $root.'/app/Deployment/ComponentRegistryService.php';
require_once $root.'/app/Deployment/EnvironmentEditor.php';
require_once $root.'/app/Deployment/ManifestSigner.php';
require_once $root.'/app/Deployment/ReleasePublisher.php';
require_once $root.'/app/Deployment/RepositorySynchronizer.php';
require_once $root.'/app/Install/SqlStatementSplitter.php';
require_once $root.'/app/Install/MigrationRunner.php';
require_once $root.'/app/Seeders/CatalogSeeder.php';
require_once $root.'/app/Seeders/CentralDemoSeeder.php';
require_once $root.'/app/Seeders/NodeDemoSeeder.php';
require_once $root.'/app/Backup/BackupCrypto.php';
require_once $root.'/app/Backup/DatabaseBackup.php';
require_once $root.'/app/Backup/BackupManager.php';
if(!is_file($root.'/.env')){fwrite(STDERR,"No existe .env. Ejecute el instalador web.\n");exit(1);}
$pdo=Connection::pdo();
$files=[
 $root.'/database/migrations/0001_functional_foundation.sql',
 $root.'/database/migrations/0002_deployment_foundation.sql',
];
if(InstallationMode::isCentral())$files[]=$root.'/database/migrations/0003_central_repository.sql';
$files[]=$root.'/database/migrations/0004_node_technology_inventory.sql';
if(InstallationMode::isCentral())$files[]=$root.'/database/migrations/0005_central_portfolio.sql';
$files[]=$root.'/database/migrations/0006_property_site_topology_lorawan.sql';
if(InstallationMode::isCentral())$files[]=$root.'/database/migrations/0007_central_property_types.sql';
if(InstallationMode::isCentral())$files[]=$root.'/database/migrations/0008_installation_provisioning.sql';
$files[]=$root.'/database/migrations/0009_operational_core_crud.sql';
$files[]=$root.'/database/migrations/0010_platform_managed_assets.sql';
if(InstallationMode::isCentral())$files[]=$root.'/database/migrations/0011_registration_token_lifecycle.sql';
$files[]=$root.'/database/migrations/0012_structure_generation_wizard.sql';
$files[]=$root.'/database/migrations/0013_structure_designer_assets.sql';
$files[]=$root.'/database/migrations/0014_topology_designer.sql';
$files[]=$root.'/database/migrations/0015_resident_app_real_data.sql';
$files[]=$root.'/database/migrations/0016_backup_recovery.sql';
if(InstallationMode::isCentral()){ $files[]=$root.'/database/migrations/0017_backup_ux_test_installations.sql'; $files[]=$root.'/database/migrations/0018_central_backup_vault.sql'; }
$files[]=$root.'/database/migrations/0019_unified_roles_edge_agent.sql';
if(InstallationMode::isCentral())$files[]=$root.'/database/migrations/0020_central_edge_registry.sql';
if(!InstallationMode::isCentral())$files[]=$root.'/database/migrations/0021_edge_enrollment_onvif_manual.sql';
if(!InstallationMode::isCentral())$files[]=$root.'/database/migrations/0022_edge_inventory_resident_camera_preview.sql';
if(!InstallationMode::isCentral())$files[]=$root.'/database/migrations/0023_webrtc_media_gateway.sql';
if(!InstallationMode::isCentral())$files[]=$root.'/database/migrations/0024_video_source_channel_lifecycle.sql';
if(!InstallationMode::isCentral())$files[]=$root.'/database/migrations/0025_cpanel_direct_webrtc.sql';
if(!InstallationMode::isCentral())$files[]=$root.'/database/migrations/0026_edge_camera_name_sync.sql';
if(!InstallationMode::isCentral()){ foreach(['0027_resident_identity_audit_vimar.sql','0028_account_recovery_granular_permissions.sql','0029_access_policy_engine_connector_adoption.sql','0030_logical_asset_physical_binding.sql','0031_multi_path_access_audit.sql','0032_auth_schema_consistency_fix.sql','0033_zkbio_api_lab.sql','0034_zkbio_operational_bridge.sql','0035_access_point_structural_placement.sql','0036_person_vehicle_reclassification.sql','0037_resident_resource_display_names.sql','0038_vertical_stop_artifact_cleanup.sql','0039_structure_area_model_separation.sql','0040_vimarhub_local_bridge.sql','0041_vimar_direct_edge_discovery.sql'] as $migration)$files[]=$root.'/database/migrations/'.$migration; }
if(InstallationMode::isCentral()){ $files[]=$root.'/database/migrations/0042_component_registry_release_lifecycle.sql'; $files[]=$root.'/database/migrations/0043_installation_deployment_enrollment.sql'; $files[]=$root.'/database/migrations/0044_property_portfolio_token_history.sql'; }
try{
 // Toda actualización con migraciones pendientes genera primero un respaldo local completo.
 // En una actualización 0.9.x -> 0.10.6 las tablas de backup todavía no existen,
 // por lo que el respaldo se crea en modo bootstrap/untracked y se registra después de 0016.
 $hasMigrationTable=(bool)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='bh_schema_migrations'")->fetchColumn();
 $executed=[];
 if($hasMigrationTable){
   foreach($pdo->query('SELECT migration,checksum_sha256 FROM bh_schema_migrations')->fetchAll() as $row)$executed[(string)$row['migration']]=(string)$row['checksum_sha256'];
 }
 $pending=false;
 foreach($files as $file){
   $name=basename($file);$sha=hash_file('sha256',$file)?:'';
   if(!isset($executed[$name])||!hash_equals($executed[$name],$sha)){$pending=true;break;}
 }
 $hasBackupTable=(bool)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name='bh_backup_settings'")->fetchColumn();
 $pre=null;$bootstrapPre=false;$newRecoveryKey=null;
 if($pending){
   $recoveryKey=trim((string)env('BACKUP_RECOVERY_KEY',''));
   if($recoveryKey===''){
     $recoveryKey='PHRK-'.rtrim(strtr(base64_encode(random_bytes(36)),'+/','-_'),'=');
     EnvironmentEditor::set(['BACKUP_RECOVERY_KEY'=>$recoveryKey]);
     putenv('BACKUP_RECOVERY_KEY='.$recoveryKey);$_ENV['BACKUP_RECOVERY_KEY']=$recoveryKey;$newRecoveryKey=$recoveryKey;
     echo PHP_EOL."============================================================".PHP_EOL;
     echo "CLAVE DE RECUPERACION GENERADA (GUARDELA FUERA DEL SERVIDOR)".PHP_EOL;
     echo $recoveryKey.PHP_EOL;
     echo "============================================================".PHP_EOL.PHP_EOL;
   }
   $bootstrapPre=!$hasBackupTable;
   try{
     $pre=(new BackupManager($pdo,$root))->create('pre_update',null,[
       'local_only'=>true,'skip_prune'=>true,'untracked'=>$bootstrapPre,'recovery_key'=>$recoveryKey
     ]);
     echo "Respaldo PRE_UPDATE creado: {$pre['filename']}".PHP_EOL;
     echo "Ruta: {$pre['path']}".PHP_EOL;
   }catch(Throwable $backupError){
     fwrite(STDERR,'ERROR: no se ejecutarán migraciones porque no fue posible crear el respaldo PRE_UPDATE: '.$backupError->getMessage().PHP_EOL);
     throw $backupError;
   }
 }

 $results=(new MigrationRunner($pdo))->run($files);
 if($pre&&$bootstrapPre){
   try{
     $installationId=(int)$pdo->query('SELECT id FROM bh_installations ORDER BY id LIMIT 1')->fetchColumn();
     $stmt=$pdo->prepare("INSERT INTO bh_backup_runs (public_uuid,installation_id,backup_type,status,filename,local_path,size_bytes,sha256,encrypted,destinations_json,manifest_json,completed_at) VALUES (?,?, 'pre_update','completed',?,?,?,?,1,?,?,NOW())");
     $stmt->execute([
       (string)$pre['uuid'],$installationId,(string)$pre['filename'],(string)$pre['path'],(int)$pre['size'],(string)$pre['sha256'],
       json_encode($pre['destinations'],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE),
       json_encode($pre['manifest'],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)
     ]);
   }catch(Throwable $registerError){
     fwrite(STDERR,'ADVERTENCIA: el PRE_UPDATE existe en disco, pero no pudo registrarse en el historial: '.$registerError->getMessage().PHP_EOL);
   }
 }
 $catalog=new CatalogSeeder($pdo,$root);$connectorCount=$catalog->seedConnectorDefinitions();
 $demoSeed=['installations'=>0,'plans'=>0,'connectors'=>0];
 $publishedCore=null;
 if(InstallationMode::isCentral()){
  $catalog->seedVendorApis();
  $publishedCore=(new RepositorySynchronizer($pdo))->syncOfficialCore();
  if(env_bool('APP_DEMO_MODE',false)){
   $demoCount=(int)$pdo->query("SELECT COUNT(*) FROM bh_repo_installations WHERE is_demo=1")->fetchColumn();
   if($demoCount===0)$demoSeed=(new CentralDemoSeeder($pdo))->seed();
  }
 }
 $uuid=(string)config('deployment.installation_uuid');
 $pdo->prepare("UPDATE bh_installations SET app_version=? WHERE public_uuid=?")->execute([(string)config('app.version'),$uuid]);
 $pdo->prepare("UPDATE bh_installed_components SET component_code='property-hub-core',display_name='2CAM Property Hub Core',installed_version=? WHERE installation_id=(SELECT id FROM bh_installations WHERE public_uuid=? LIMIT 1) AND component_type='core'")->execute([(string)config('app.version'),$uuid]);
 $pdo->prepare("UPDATE bh_installed_components SET installed_version=? WHERE installation_id=(SELECT id FROM bh_installations WHERE public_uuid=? LIMIT 1) AND component_type='module' AND component_code IN ('property-hub-central-console','resident-app','operations-console')")->execute([(string)config('app.version'),$uuid]);
 $pdo->prepare("UPDATE bh_installed_components SET component_code='property-api',display_name='Property API',installed_version='v1' WHERE installation_id=(SELECT id FROM bh_installations WHERE public_uuid=? LIMIT 1) AND component_type='api' AND component_code IN ('building-api','property-api')")->execute([$uuid]);
 foreach($results as $result)echo $result['migration'].': '.$result['status'].PHP_EOL;
 echo "Conectores sincronizados: {$connectorCount}".PHP_EOL;
 if(is_array($publishedCore))echo "Core publicado para nodos: {$publishedCore['release_code']} · {$publishedCore['hash']}".PHP_EOL;
 echo "Demo central cargado: {$demoSeed['installations']} instalaciones, {$demoSeed['plans']} preparaciones.".PHP_EOL;
 echo "Sincronización/migración Property Hub ".(string)config('app.version')." completada.".PHP_EOL;
}catch(Throwable $e){fwrite(STDERR,'ERROR: '.$e->getMessage().PHP_EOL);exit(1);}
