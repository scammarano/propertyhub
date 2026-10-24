<?php
declare(strict_types=1);

require_once __DIR__.'/Support/helpers.php';
date_default_timezone_set((string) config('app.timezone', 'America/Caracas'));
require_once __DIR__.'/Support/ErrorReporter.php';
require_once __DIR__.'/Support/SupportLogBundleService.php';
ErrorReporter::bootstrap();
require_once __DIR__.'/Auth/JwtVerifier.php';
require_once __DIR__.'/Database/Connection.php';
require_once __DIR__.'/Install/SqlStatementSplitter.php';
require_once __DIR__.'/Install/MigrationRunner.php';
require_once __DIR__.'/Install/SchemaManager.php';
require_once __DIR__.'/Install/DemoDataCleaner.php';
require_once __DIR__.'/Seeders/CatalogSeeder.php';
require_once __DIR__.'/Deployment/InstallationMode.php';
require_once __DIR__.'/Deployment/DesiredConnectorPolicy.php';
require_once __DIR__.'/Deployment/ComponentRegistryService.php';
require_once __DIR__.'/Deployment/ConnectionRegistry.php';
require_once __DIR__.'/Deployment/ManifestSigner.php';
require_once __DIR__.'/Deployment/PropertyDeploymentFileService.php';
require_once __DIR__.'/Deployment/RepositoryTransport.php';
require_once __DIR__.'/Deployment/RepositoryClient.php';
require_once __DIR__.'/Deployment/UpdateManager.php';
require_once __DIR__.'/Deployment/UpdateBatchManager.php';
require_once __DIR__.'/Deployment/ReleasePublisher.php';
require_once __DIR__.'/Deployment/CoreDistributionArtifactBuilder.php';
require_once __DIR__.'/Deployment/RepositorySynchronizer.php';
require_once __DIR__.'/Deployment/EnvironmentEditor.php';
require_once __DIR__.'/Deployment/NodeRegistrationService.php';
require_once __DIR__.'/Backup/BackupCrypto.php';
require_once __DIR__.'/Backup/DatabaseBackup.php';
require_once __DIR__.'/Backup/BackupManager.php';
require_once __DIR__.'/Backup/BackupVaultService.php';
require_once __DIR__.'/Backup/RemoteMaintenanceAgent.php';
require_once __DIR__.'/Edge/EdgeSecretStore.php';
require_once __DIR__.'/Edge/EdgeHttp.php';
require_once __DIR__.'/Edge/EdgeCommandWorkerService.php';
require_once __DIR__.'/Edge/ZkBioOperationalService.php';
require_once __DIR__.'/Edge/EdgeNodeClient.php';
require_once __DIR__.'/Edge/EdgeNodeService.php';
require_once __DIR__.'/Edge/LocalIntegrationProvisioning.php';
require_once __DIR__.'/Edge/ZkBioApiMatrixRunner.php';
require_once __DIR__.'/Edge/OnvifClient.php';
require_once __DIR__.'/Edge/VimarHubBridgeClient.php';
require_once __DIR__.'/Edge/VimarDirectDiscoveryClient.php';
require_once __DIR__.'/Edge/EdgeConnectorLab.php';
require_once __DIR__.'/Media/MediaRelayService.php';
require_once __DIR__.'/Media/EdgeMediaGatewayService.php';
require_once __DIR__.'/Repositories/RepositoryService.php';
require_once __DIR__.'/Repositories/CentralCleanupService.php';
require_once __DIR__.'/Repositories/TechnologyRepository.php';
require_once __DIR__.'/Repositories/NodeRepository.php';
require_once __DIR__.'/Repositories/ResidentRepository.php';
require_once __DIR__.'/Mail/SmtpMailer.php';
require_once __DIR__.'/Resident/ResidentSecurityService.php';
require_once __DIR__.'/Resident/AccessPolicyService.php';
require_once __DIR__.'/Core/AssetBindingService.php';
require_once __DIR__.'/Telemetry/NodeTelemetryService.php';
require_once __DIR__.'/Auth/AppleClientSecret.php';
require_once __DIR__.'/Auth/SocialAuth.php';
require_once __DIR__.'/Auth/AuthManager.php';
require_once __DIR__.'/Repositories/DemoRepository.php';
require_once __DIR__.'/Support/UiStandard/UiTheme.php';
require_once __DIR__.'/Support/UiStandard/SidebarRenderer.php';

start_app_session();

// Windows/XAMPP cannot reliably overwrite the front controller that is executing the
// current request. Update packages may therefore leave a deferred replacement marker.
// Finalization happens in a separate request whose entry point is update-finalize.php,
// so public/index.php is no longer open by the web server when it is replaced.
if (!defined('PROPERTY_HUB_UPDATE_FINALIZER')) {
    $pendingFinalize = base_path('storage/updates/deferred/update-finalize.json');
    if (is_file($pendingFinalize) && current_user() && can('system.manage')) {
        $batchId=trim((string)($_SESSION['active_update_batch_id']??''));
        header('Location: update-finalize.php'.($batchId!==''?'?batch='.rawurlencode($batchId):''));
        exit;
    }
}

DemoRepository::boot();
