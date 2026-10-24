<?php
declare(strict_types=1);
$root = dirname(__DIR__);
if (!is_file($root.'/storage/installed.lock') || !is_file($root.'/.env')) {
    header('Location: ../install/'); exit;
}
require_once $root.'/app/bootstrap.php';


$route = route_name();
$repositoryApiRoutes = [
    'repository-api-ping' => 'ping.php',
    'repository-api-register' => 'register.php',
    'repository-api-heartbeat' => 'heartbeat.php',
    'repository-api-edge-enrollment-claim' => 'edge-enrollment-claim.php',
    'repository-api-edge-bootstrap-token' => 'edge-bootstrap-token.php',
    'repository-api-manifest' => 'manifest.php',
    'repository-api-catalog' => 'catalog.php',
    'repository-api-package' => 'package.php',
    'repository-api-backup-upload' => 'backup-upload.php',
    'repository-api-maintenance-complete' => 'maintenance-complete.php',
    'repository-api-recovery-download' => 'recovery-download.php',
];
if (isset($repositoryApiRoutes[$route])) {
    require __DIR__.'/api/repository/v1/'.$repositoryApiRoutes[$route];
    exit;
}
if ($route === '') $route = current_user() ? AuthManager::landingRoute() : 'login';
if ($route === 'local-login' && !is_post()) redirect('login');
if ($route === 'asset-bindings' && !is_post()) { header('Location: '.url('technology-inventory',['tab'=>'relationships'])); exit; }

try {
    if ($route === 'login') {
        if (current_user()) redirect(AuthManager::landingRoute());
        render('auth/login', ['title' => 'Iniciar sesión'], false);
        exit;
    }
    if ($route === 'local-login' && is_post()) {
        verify_csrf();
        $identifier=post_string('identifier');
        try {
            AuthManager::localLogin($identifier, (string)($_POST['password'] ?? ''));
        } catch (DomainException $e) {
            ErrorReporter::authFailure($identifier, $e->getMessage(), $e);
            flash('danger', $e->getMessage());
            redirect('login');
        }
        try{(new ResidentSecurityService())->audit('auth.login','local_user',is_numeric(current_user()['id']??null)?(int)current_user()['id']:null,['email'=>(string)(current_user()['email']??''),'role'=>(string)(current_user()['role']??'')]);}catch(Throwable $ignore){}
        redirect(AuthManager::landingRoute());
    }
    if ($route === 'demo-login' && is_post()) {
        verify_csrf();
        AuthManager::demoLogin(post_string('profile'));
        redirect(AuthManager::landingRoute());
    }
    if ($route === 'logout') {
        try{(new ResidentSecurityService())->audit('auth.logout','local_user',is_numeric(current_user()['id']??null)?(int)current_user()['id']:null);}catch(Throwable $ignore){}
        AuthManager::logout();
        header('Location: index.php?route=login');
        exit;
    }
    if ($route === 'accept-invitation') {
        $token=trim((string)($_GET['token']??''));$inv=(new ResidentSecurityService())->invitationByToken($token);
        render('auth/accept-invitation',['title'=>'Activar cuenta','token'=>$token,'invitation'=>$inv],false);exit;
    }
    if ($route === 'accept-invitation-save' && is_post()) {
        verify_csrf();$token=post_string('token');try{(new ResidentSecurityService())->acceptInvitation($token,(string)($_POST['password']??''));flash('success','Cuenta activada. Ya puedes iniciar sesión con tu correo y nueva contraseña.');redirect('login');}catch(Throwable $e){flash('danger',$e->getMessage());header('Location: '.url('accept-invitation',['token'=>$token]));exit;}
    }


    if ($route === 'forgot-password') { if(current_user())redirect(AuthManager::landingRoute());render('auth/forgot-password',['title'=>'Recuperar contraseña'],false);exit; }
    if ($route === 'forgot-password-send' && is_post()) { verify_csrf();try{(new ResidentSecurityService())->requestPasswordReset(post_string('email'));}catch(Throwable $e){ErrorReporter::report($e,['operation'=>'password_reset_request']);}flash('success','Si el correo está registrado, recibirás un enlace para crear una nueva contraseña.');redirect('forgot-password'); }
    if ($route === 'reset-password') { $token=trim((string)($_GET['token']??''));$reset=(new ResidentSecurityService())->passwordResetByToken($token);render('auth/reset-password',['title'=>'Nueva contraseña','token'=>$token,'reset'=>$reset],false);exit; }
    if ($route === 'reset-password-save' && is_post()) { verify_csrf();$token=post_string('token');$p=(string)($_POST['password']??'');if($p!==(string)($_POST['password_confirmation']??'')){flash('danger','Las contraseñas no coinciden.');header('Location: '.url('reset-password',['token'=>$token]));exit;}try{(new ResidentSecurityService())->resetPassword($token,$p);flash('success','Contraseña actualizada. Ya puedes iniciar sesión.');redirect('login');}catch(Throwable $e){flash('danger',$e->getMessage());header('Location: '.url('reset-password',['token'=>$token]));exit;} }
    if ($route === 'register') { if(InstallationMode::isCentral())redirect('login');render('auth/register',['title'=>'Crear cuenta'],false);exit; }
    if ($route === 'register-save' && is_post()) { verify_csrf();$p=(string)($_POST['password']??'');if($p!==(string)($_POST['password_confirmation']??'')){flash('danger','Las contraseñas no coinciden.');redirect('register');}try{(new ResidentSecurityService())->registerResident(post_string('email'),$p);flash('success','Registro recibido. Revisa tu correo y confirma la cuenta antes de iniciar sesión.');redirect('login');}catch(Throwable $e){flash('danger',$e->getMessage());redirect('register');} }
    if ($route === 'verify-email') { try{(new ResidentSecurityService())->verifyEmail(trim((string)($_GET['token']??'')));flash('success','Correo confirmado. Tu cuenta ya está activa.');}catch(Throwable $e){flash('danger',$e->getMessage());}redirect('login'); }

    require_auth();
    try{(new ResidentSecurityService())->touchPresence();}catch(Throwable $ignore){}

    if ($route === 'presence-ping' && is_post()) {
        verify_csrf();(new ResidentSecurityService())->touchPresence();http_response_code(204);exit;
    }
    if ($route === 'mail-settings-save' && is_post()) {
        require_permission('system.manage');verify_csrf();(new ResidentSecurityService())->saveMailSettings($_POST);flash('success','Correo saliente guardado.');redirect('user-activity');
    }
    if ($route === 'mail-test' && is_post()) {
        require_permission('system.manage');verify_csrf();try{(new ResidentSecurityService())->sendTest(post_string('test_email'));flash('success','Correo de prueba enviado.');}catch(Throwable $e){ErrorReporter::report($e,['operation'=>'mail_test']);flash('danger','Correo: '.$e->getMessage());}redirect('user-activity');
    }
    if ($route === 'resident-account-invite' && is_post()) {
        require_permission('people.manage');verify_csrf();try{$r=(new ResidentSecurityService())->invitePerson(post_int('person_id'));flash('success','Invitación enviada a '.$r['email'].'.');}catch(Throwable $e){ErrorReporter::report($e,['operation'=>'resident_account_invite']);flash('danger','Invitación: '.$e->getMessage());}redirect('people');
    }

    if ($route === 'client-log' && is_post()) {
        verify_csrf();
        $payload = [
            'timestamp' => date(DATE_ATOM),
            'request_id' => ErrorReporter::requestId(),
            'installation_code' => (string)config('app.instance_code'),
            'user_id' => (int)(current_user()['id'] ?? 0),
            'user_role' => (string)(current_user()['role'] ?? ''),
            'source' => substr(post_string('source','browser'),0,80),
            'phase' => substr(post_string('phase','unknown'),0,80),
            'message' => substr(post_string('message','Error de interfaz'),0,2000),
            'stack' => substr(post_string('stack',''),0,12000),
            'url' => substr(post_string('url',''),0,2000),
            'user_agent' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''),0,500),
            'remote_ip' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
        ];
        $dir=base_path('storage/logs');
        if(!is_dir($dir))@mkdir($dir,0775,true);
        @file_put_contents($dir.'/frontend-'.date('Y-m-d').'.jsonl',json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE)."\n",FILE_APPEND|LOCK_EX);
        http_response_code(204);
        exit;
    }

    if ($route === 'system-repair') {
        require_permission('system.manage');
        $schema=(new SchemaManager(Connection::pdo()))->status();$migrationRecoveryKey=$_SESSION['migration_backup_recovery_key']??null;$migrationPreBackup=$_SESSION['migration_pre_update_backup']??null;unset($_SESSION['migration_backup_recovery_key'],$_SESSION['migration_pre_update_backup']);
        render('admin/system-repair',['title'=>'Reparación y migraciones','schema'=>$schema,'migrationRecoveryKey'=>$migrationRecoveryKey,'migrationPreBackup'=>$migrationPreBackup],'admin');
        exit;
    }
    if ($route === 'system-run-migrations' && is_post()) {
        require_permission('system.manage'); verify_csrf();
        $result=(new SchemaManager(Connection::pdo()))->repair(['web_safe'=>true]);
        if(!empty($result['new_backup_recovery_key']))$_SESSION['migration_backup_recovery_key']=$result['new_backup_recovery_key'];
        if(!empty($result['pre_update_backup']))$_SESSION['migration_pre_update_backup']=$result['pre_update_backup'];
        flash('success','Migraciones verificadas. Conectores sincronizados: '.(int)$result['connectors'].'. Respaldo de seguridad: '.(!empty($result['pre_update_backup']['filename'])?$result['pre_update_backup']['filename']:'no requerido').'.'.(!empty($result['repository_sync_deferred'])?' La sincronización del repositorio se ejecutará fuera de esta migración para evitar timeouts.':''));
        redirect('system-repair');
    }


    if ($route === 'system-demo-cleanup') {
        require_permission('system.manage');
        if(InstallationMode::isCentral()) redirect('repository-central-cleanup');
        $cleaner=new DemoDataCleaner(Connection::pdo());
        render('admin/demo-cleanup',['title'=>'Limpiar datos de demostración','preview'=>$cleaner->preview()],'admin');
        exit;
    }
    if ($route === 'system-demo-cleanup-run' && is_post()) {
        require_permission('system.manage'); verify_csrf();
        if(post_string('confirmation')!==(string)config('app.instance_code')) throw new InvalidArgumentException('El código de confirmación no coincide con esta instalación.');
        $result=(new DemoDataCleaner(Connection::pdo()))->clean();
        $removed=array_sum(array_filter($result,'is_int'));
        flash('success','Datos de demostración eliminados: '.$removed.' registros o elementos. APP_DEMO_MODE quedó desactivado.');
        redirect(InstallationMode::isCentral()?'repository-dashboard':(InstallationMode::isEdge()?'edge-dashboard':'dashboard'));
    }


    if ($route === 'backup-recovery') {
        require_permission('system.manage');
        $manager=new BackupManager(Connection::pdo());
        $preview=$_SESSION['backup_restore_preview']??null;$newRecoveryKey=$_SESSION['new_backup_recovery_key']??null;unset($_SESSION['new_backup_recovery_key']);
        render('admin/backup-recovery',[
            'title'=>'Respaldo y recuperación','backupSettings'=>$manager->settings(),'backupRuns'=>$manager->backups(),
            'recoveryKeyExists'=>$manager->recoveryKeyExists(),'newRecoveryKey'=>$newRecoveryKey,'restorePreview'=>$preview,'schedulerInfo'=>$manager->schedulerInfo(),
            'offsiteArchives'=>$manager->offsiteArchives(),
        ],'admin');exit;
    }
    if ($route === 'backup-settings-save' && is_post()) {
        require_permission('system.manage');verify_csrf();(new BackupManager(Connection::pdo()))->saveSettings($_POST);
        flash('success','Política de respaldo actualizada.');redirect('backup-recovery');
    }
    if ($route === 'backup-key-generate' && is_post()) {
        require_permission('system.manage');verify_csrf();
        $key=(new BackupManager(Connection::pdo()))->generateRecoveryKey(!empty($_POST['rotate']));
        $_SESSION['new_backup_recovery_key']=$key;
        flash('warning','Clave de recuperación generada. Guárdela fuera del servidor: se mostrará en esta pantalla hasta que salga de ella.');redirect('backup-recovery');
    }
    if ($route === 'backup-create' && is_post()) {
        require_permission('system.manage');verify_csrf();
        $type=in_array(post_string('backup_type','manual'),['manual','pre_update'],true)?post_string('backup_type','manual'):'manual';
        $result=(new BackupManager(Connection::pdo()))->create($type,(int)(current_user()['id']??0));
        flash($result['status']==='completed'?'success':'warning','Respaldo '.$result['filename'].' creado. Tamaño '.number_format(((int)$result['size'])/1048576,1).' MB.'.(!empty($result['warnings'])?' '.implode(' | ',$result['warnings']):''));
        redirect('backup-recovery');
    }
    if ($route === 'support-logs-download') {
        require_permission('system.manage');
        $days=max(1,min(30,(int)($_GET['days']??7)));
        $incident=trim((string)($_GET['incident']??''));$requestId=trim((string)($_GET['request_id']??''));
        $bundle=(new SupportLogBundleService())->create($days,$incident,$requestId);
        ErrorReporter::runtimeLog('support_logs_download',[
            'days'=>$days,'incident'=>$incident,'request_id'=>$requestId,
            'file_count'=>(int)($bundle['file_count']??0),'bytes'=>(int)($bundle['bytes']??0),
        ]);
        $path=(string)$bundle['path'];$filename=(string)$bundle['filename'];
        header('Content-Type: application/zip');header('Content-Disposition: attachment; filename="'.basename($filename).'"');
        header('Content-Length: '.filesize($path));header('Cache-Control: no-store, no-cache, must-revalidate');header('Pragma: no-cache');
        readfile($path);@unlink($path);exit;
    }
    if ($route === 'backup-download') {
        require_permission('system.manage');$manager=new BackupManager(Connection::pdo());$path=$manager->pathForRun(max(1,(int)($_GET['id']??0)));
        header('Content-Type: application/octet-stream');header('Content-Disposition: attachment; filename="'.basename($path).'"');header('Content-Length: '.filesize($path));header('Cache-Control: no-store');readfile($path);exit;
    }
    if ($route === 'backup-offsite-download') {
        require_permission('system.manage');if(!InstallationMode::isCentral())throw new RuntimeException('Solo disponible en el Property Hub Central.');$path=(new BackupManager(Connection::pdo()))->pathForOffsite(max(1,(int)($_GET['id']??0)));
        header('Content-Type: application/octet-stream');header('Content-Disposition: attachment; filename="'.basename($path).'"');header('Content-Length: '.filesize($path));header('Cache-Control: no-store');readfile($path);exit;
    }
    if ($route === 'backup-delete' && is_post()) {
        require_permission('system.manage');verify_csrf();(new BackupManager(Connection::pdo()))->deleteBackup(post_int('id'));flash('success','Copia local eliminada del historial activo.');redirect('backup-recovery');
    }
    if ($route === 'backup-restore-local-prepare' && is_post()) {
        require_permission('system.manage');verify_csrf();$manager=new BackupManager(Connection::pdo());$path=$manager->pathForRun(post_int('id'));
        $key=(string)env('BACKUP_RECOVERY_KEY','');if($key==='')throw new RuntimeException('No existe clave local de recuperación.');$info=$manager->inspect($path,$key);
        $_SESSION['backup_restore_preview']=['path'=>$path,'source'=>'local','info'=>$info];redirect('backup-recovery');
    }
    if ($route === 'backup-restore-upload' && is_post()) {
        require_permission('system.manage');verify_csrf();$key=(string)($_POST['recovery_key']??'');if($key==='')throw new InvalidArgumentException('Indique la clave de recuperación del respaldo.');
        $manager=new BackupManager(Connection::pdo());$path=$manager->acceptIncomingUpload($_FILES['backup_file']??[]);$info=$manager->inspect($path,$key);
        $_SESSION['backup_restore_preview']=['path'=>$path,'source'=>'upload','info'=>$info];$_SESSION['backup_restore_key']=$key;flash('success','Respaldo validado. La clave PHRK queda disponible solo durante esta sesión; no tendrá que escribirla otra vez.');redirect('backup-recovery');
    }

    if ($route === 'backup-restore-central' && is_post()) {
        require_permission('system.manage'); verify_csrf();
        if(InstallationMode::isCentral())throw new RuntimeException('El Central no se restaura a sí mismo usando un token de Node.');
        $url=post_string('central_repository_url');$token=post_string('recovery_token');$key=(string)($_POST['recovery_key']??'');
        if($url===''||$token===''||$key==='')throw new InvalidArgumentException('Indique URL del Central, token de recuperación y clave PHRK.');
        $dir=base_path('storage/backups/incoming');if(!is_dir($dir)&&!@mkdir($dir,0775,true)&&!is_dir($dir))throw new RuntimeException('No fue posible crear storage/backups/incoming.');
        $path=$dir.'/CENTRAL_RECOVERY_'.date('Ymd_His').'.phbackup';
        RepositoryClient::downloadRecoveryBackup($url,$token,$path);
        $info=(new BackupManager(Connection::pdo()))->inspect($path,$key);
        $_SESSION['backup_restore_preview']=['path'=>$path,'source'=>'central','info'=>$info];$_SESSION['backup_restore_key']=$key;
        flash('success','Copia recuperada desde Property Hub Central y validada. Revise el manifiesto antes de restaurar.');redirect('backup-recovery');
    }
    if ($route === 'backup-restore-cancel' && is_post()) {
        require_permission('system.manage');verify_csrf();$preview=$_SESSION['backup_restore_preview']??null;if(is_array($preview)&&in_array(($preview['source']??''),['upload','central'],true)&&is_file((string)($preview['path']??'')))@unlink((string)$preview['path']);unset($_SESSION['backup_restore_preview'],$_SESSION['backup_restore_key']);redirect('backup-recovery');
    }
    if ($route === 'backup-restore-run' && is_post()) {
        require_permission('system.manage');verify_csrf();$preview=$_SESSION['backup_restore_preview']??null;if(!is_array($preview)||!is_file((string)($preview['path']??'')))throw new RuntimeException('No existe un respaldo preparado para restaurar.');
        $mode=post_string('restore_mode');$confirm=post_string('confirmation');if($confirm!==(string)config('app.instance_code'))throw new InvalidArgumentException('Escriba el código actual de la instalación para confirmar la restauración destructiva.');
        $key=(string)($_POST['recovery_key']??'');if($key==='')$key=(string)($_SESSION['backup_restore_key']??'');if($key===''&&($preview['source']??'')==='local')$key=(string)env('BACKUP_RECOVERY_KEY','');if($key==='')throw new InvalidArgumentException('No hay una clave de recuperación validada en esta sesión. Vuelva a preparar el respaldo.');
        $result=(new BackupManager(Connection::pdo()))->restore((string)$preview['path'],$key,$mode,(int)(current_user()['id']??0));
        if(in_array(($preview['source']??''),['upload','central'],true)&&is_file((string)$preview['path']))@unlink((string)$preview['path']);unset($_SESSION['backup_restore_preview'],$_SESSION['backup_restore_key'],$_SESSION['auth_user']);
        flash('success',(string)$result['message'].' Punto de seguridad previo: '.(string)$result['safety_backup']['filename'].'.');redirect('login');
    }


    // Property Edge Agent: consola técnica y laboratorio de integraciones locales.
    if ($route === 'edge-node-pair' && is_post()) {
        require_permission('system.manage'); verify_csrf();
        if(!InstallationMode::isEdge())throw new RuntimeException('Esta acción corresponde a un Property Edge Agent.');
        $nodeUrl=post_string('property_node_url');$token=post_string('edge_pairing_token');
        $result=EdgeNodeClient::pair($nodeUrl,$token);$msg='Edge Agent emparejado con '.$result['property_code'].' correctamente. Actualizaciones, configuración e integraciones se recibirán exclusivamente desde este Property Node.';flash('success',$msg);redirect('edge-dashboard');
    }
    if ($route === 'edge-central-repair' && is_post()) {
        require_permission('system.manage'); verify_csrf();
        if(!InstallationMode::isEdge())throw new RuntimeException('Esta acción corresponde a un Property Edge Agent.');
        $r=EdgeNodeClient::repairCentralRegistration();
        flash('success','Distribución Edge refrescada desde '.((string)($r['property_code']??'Property Node')).'. El Agent no usa acceso directo a Central.');
        redirect('deployment-settings');
    }
    if ($route === 'edge-zkbio-save' && is_post()) {
        require_permission('system.manage'); verify_csrf(); if(!InstallationMode::isEdge())throw new RuntimeException('Solo disponible en Edge Agent.');
        $lab=new EdgeConnectorLab(Connection::pdo());$existing=$lab->zkBioConfig();$token=(string)($_POST['access_token']??'');
        if(trim($token)===''&&!empty($existing['configured'])){ $secret=EdgeSecretStore::decrypt((string)($existing['configuration_encrypted']??''));$token=(string)($secret['access_token']??''); }
        $lab->saveZkBio(post_string('endpoint_url'),$token);flash('success','Configuración ZKBio guardada localmente y cifrada.');redirect('edge-dashboard');
    }
    if ($route === 'edge-zkbio-test' && is_post()) {
        require_permission('system.manage'); verify_csrf();if(!InstallationMode::isEdge())throw new RuntimeException('Solo disponible en Edge Agent.');
        try{$_SESSION['edge_zkbio_test']=(new EdgeConnectorLab(Connection::pdo()))->testZkBio();flash('success','Prueba ZKBio completada.');}catch(Throwable $e){$_SESSION['edge_zkbio_test']=['ok'=>false,'message'=>$e->getMessage()];ErrorReporter::report($e,['operation'=>'edge_zkbio_test']);flash('danger','ZKBio: '.$e->getMessage());}redirect('edge-dashboard');
    }
    if ($route === 'edge-zkbio-discover' && is_post()) {
        require_permission('system.manage'); verify_csrf();if(!InstallationMode::isEdge())throw new RuntimeException('Solo disponible en Edge Agent.');$returnRoute=post_string('return_route','edge-dashboard');if(!in_array($returnRoute,['edge-dashboard','edge-zkbio-lab'],true))$returnRoute='edge-dashboard';
        try{$_SESSION['edge_zkbio_discovery']=(new EdgeConnectorLab(Connection::pdo()))->discoverZkBio();flash('success','Lectura de aprendizaje ZKBio completada sin modificar la plataforma.');}catch(Throwable $e){$_SESSION['edge_zkbio_discovery']=['ok'=>false,'message'=>$e->getMessage()];ErrorReporter::report($e,['operation'=>'edge_zkbio_discovery']);flash('danger','Aprendizaje ZKBio: '.$e->getMessage());}redirect($returnRoute);
    }
    if ($route === 'edge-zkbio-lab-save' && is_post()) {
        require_permission('system.manage');verify_csrf();if(!InstallationMode::isEdge())throw new RuntimeException('Solo disponible en Edge Agent.');$lab=new EdgeConnectorLab(Connection::pdo());$lab->saveZkBio(post_string('endpoint_url'),(string)($_POST['access_token']??''));flash('success','Configuración ZKBio guardada cifrada en Property Edge.');redirect('edge-zkbio-lab');
    }
    if ($route === 'edge-zkbio-lab-run' && is_post()) {
        require_permission('system.manage');verify_csrf();if(!InstallationMode::isEdge())throw new RuntimeException('Solo disponible en Edge Agent.');$params=['pageNo'=>post_int('pageNo',1),'pageSize'=>post_int('pageSize',20),'pins'=>post_string('pins'),'deptCodes'=>post_string('deptCodes'),'personPin'=>post_string('personPin'),'startDate'=>post_string('startDate'),'endDate'=>post_string('endDate')];
        try{$_SESSION['edge_zkbio_lab_result']=(new EdgeConnectorLab(Connection::pdo()))->runZkBioPreset(post_string('preset'),$params,'edge_local',is_int(current_user()['id']??null)?(int)current_user()['id']:null);flash('success','Prueba ZKBio ejecutada.');}catch(Throwable $e){$_SESSION['edge_zkbio_lab_result']=['ok'=>false,'error'=>true,'message'=>$e->getMessage()];ErrorReporter::report($e,['operation'=>'edge_zkbio_lab_run']);flash('danger','ZKBio: '.$e->getMessage());}redirect('edge-zkbio-lab');
    }
    if ($route === 'edge-zkbio-lab-custom' && is_post()) {
        require_permission('system.manage');verify_csrf();if(!InstallationMode::isEdge())throw new RuntimeException('Solo disponible en Edge Agent.');$method=strtoupper(post_string('method','GET'));if(in_array($method,['POST','DELETE'],true)&&!hash_equals('EJECUTAR',strtoupper(post_string('post_confirmation'))))throw new InvalidArgumentException('Para una solicitud POST/DELETE manual escriba EJECUTAR.');$payload=null;$raw=trim((string)($_POST['body_json']??''));if($raw!==''){$payload=json_decode($raw,true);if(!is_array($payload))throw new InvalidArgumentException('Body JSON inválido.');}
        try{$_SESSION['edge_zkbio_lab_result']=(new EdgeConnectorLab(Connection::pdo()))->runZkBioCustom($method,post_string('path'),post_string('query'),$payload,'edge_local',is_int(current_user()['id']??null)?(int)current_user()['id']:null);flash('success','Solicitud ZKBio ejecutada.');}catch(Throwable $e){$_SESSION['edge_zkbio_lab_result']=['ok'=>false,'error'=>true,'message'=>$e->getMessage()];ErrorReporter::report($e,['operation'=>'edge_zkbio_lab_custom']);flash('danger','ZKBio: '.$e->getMessage());}redirect('edge-zkbio-lab');
    }
    if ($route === 'edge-zkbio-matrix-start' && is_post()) {
        require_permission('system.manage');verify_csrf();if(!InstallationMode::isEdge())throw new RuntimeException('Solo disponible en Edge Agent.');
        if(!LocalIntegrationProvisioning::edgeEnabled('zkteco'))try{EdgeNodeClient::refreshProvisioning();}catch(Throwable $e){ErrorReporter::report($e,['operation'=>'edge_zkbio_matrix_refresh']);}
        header('Content-Type: application/json; charset=utf-8');
        try{$runner=new ZkBioApiMatrixRunner(Connection::pdo());$r=$runner->start(is_int(current_user()['id']??null)?(int)current_user()['id']:null);echo json_encode(['ok'=>true]+$r,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);}catch(Throwable $e){ErrorReporter::report($e,['operation'=>'edge_zkbio_matrix_start']);http_response_code(422);echo json_encode(['ok'=>false,'message'=>$e->getMessage()],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}exit;
    }
    if ($route === 'edge-zkbio-matrix-step' && is_post()) {
        require_permission('system.manage');verify_csrf();if(!InstallationMode::isEdge())throw new RuntimeException('Solo disponible en Edge Agent.');header('Content-Type: application/json; charset=utf-8');
        try{$runner=new ZkBioApiMatrixRunner(Connection::pdo());$r=$runner->step(post_string('run_id'),is_int(current_user()['id']??null)?(int)current_user()['id']:null);echo json_encode(['ok'=>true]+$r,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);}catch(Throwable $e){ErrorReporter::report($e,['operation'=>'edge_zkbio_matrix_step']);http_response_code(422);echo json_encode(['ok'=>false,'message'=>$e->getMessage()],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);}exit;
    }
    if ($route === 'edge-zkbio-matrix-download') {
        require_permission('system.manage');if(!InstallationMode::isEdge())throw new RuntimeException('Solo disponible en Edge Agent.');$runId=trim((string)($_GET['run_id']??''));$path=(new ZkBioApiMatrixRunner(Connection::pdo()))->zipPath($runId);header('Content-Type: application/zip');header('Content-Disposition: attachment; filename="'.basename($path).'"');header('Content-Length: '.filesize($path));readfile($path);exit;
    }

    if ($route === 'edge-zkbio-remote-open' && is_post()) {
        require_permission('system.manage');verify_csrf();if(!InstallationMode::isEdge())throw new RuntimeException('Solo disponible en Edge Agent.');if(!hash_equals('ABRIR',strtoupper(post_string('confirmation'))))throw new InvalidArgumentException('Confirme escribiendo ABRIR.');
        try{$_SESSION['edge_zkbio_lab_result']=(new EdgeConnectorLab(Connection::pdo()))->remoteOpenZkBio(post_string('door_id'),post_int('interval',3),is_int(current_user()['id']??null)?(int)current_user()['id']:null);flash('success','Comando de apertura enviado a ZKBio.');}catch(Throwable $e){$_SESSION['edge_zkbio_lab_result']=['ok'=>false,'error'=>true,'message'=>$e->getMessage()];ErrorReporter::report($e,['operation'=>'edge_zkbio_remote_open']);flash('danger','Apertura ZKBio: '.$e->getMessage());}redirect('edge-zkbio-lab');
    }
    if ($route === 'edge-vimar-save' && is_post()) {
        require_permission('system.manage');verify_csrf();if(!InstallationMode::isEdge())throw new RuntimeException('Solo disponible en Edge Agent.');(new EdgeConnectorLab(Connection::pdo()))->saveVimar($_POST);flash('success','Configuración Vimar guardada.');redirect('edge-vimar-lab');
    }
    if ($route === 'edge-vimar-test' && is_post()) {
        require_permission('system.manage');verify_csrf();if(!InstallationMode::isEdge())throw new RuntimeException('Solo disponible en Edge Agent.');try{$_SESSION['edge_vimar_test']=(new EdgeConnectorLab(Connection::pdo()))->testVimar();flash('success','Prueba del conector Vimar completada.');}catch(Throwable $e){$_SESSION['edge_vimar_test']=['ok'=>false,'message'=>$e->getMessage(),'entities'=>[]];ErrorReporter::report($e,['operation'=>'edge_vimar_test']);flash('danger','Vimar: '.$e->getMessage());}redirect('edge-vimar-lab');
    }
    if ($route === 'edge-vimar-discover' && is_post()) {
        require_permission('system.manage');verify_csrf();if(!InstallationMode::isEdge())throw new RuntimeException('Solo disponible en Edge Agent.');try{$_SESSION['edge_vimar_result']=(new EdgeConnectorLab(Connection::pdo()))->discoverVimar();flash('success','Discovery Vimar directo completado en modo solo lectura.');}catch(Throwable $e){$_SESSION['edge_vimar_result']=['ok'=>false,'message'=>$e->getMessage()];ErrorReporter::report($e,['operation'=>'edge_vimar_discover']);flash('danger','Vimar discovery: '.$e->getMessage());}redirect('edge-vimar-lab');
    }
    if ($route === 'edge-vimar-sync' && is_post()) {
        require_permission('system.manage');verify_csrf();if(!InstallationMode::isEdge())throw new RuntimeException('Solo disponible en Edge Agent.');try{$_SESSION['edge_vimar_sync']=EdgeNodeClient::syncEquipment('vimar');flash(!empty($_SESSION['edge_vimar_sync']['ok'])?'success':'warning',(string)($_SESSION['edge_vimar_sync']['message']??'Inventario Vimar sincronizado con el Node.'));}catch(Throwable $e){$_SESSION['edge_vimar_sync']=['ok'=>false,'message'=>$e->getMessage()];ErrorReporter::report($e,['operation'=>'edge_vimar_sync']);flash('danger','Sincronización Vimar: '.$e->getMessage());}redirect('edge-vimar-lab');
    }
    if ($route === 'edge-onvif-discover' && is_post()) {
        require_permission('system.manage'); verify_csrf();if(!InstallationMode::isEdge())throw new RuntimeException('Solo disponible en Edge Agent.');
        try{$_SESSION['edge_onvif_result']=(new EdgeConnectorLab(Connection::pdo()))->onvifDiscover(4,post_string('local_ip'));flash('info',(string)$_SESSION['edge_onvif_result']['message']);}catch(Throwable $e){$_SESSION['edge_onvif_result']=['ok'=>false,'message'=>$e->getMessage(),'devices'=>[]];ErrorReporter::report($e,['operation'=>'edge_onvif_discovery']);flash('danger',$e->getMessage());}redirect('edge-video-lab');
    }
    if ($route === 'edge-camera-save' && is_post()) {
        require_permission('system.manage');verify_csrf();if(!InstallationMode::isEdge())throw new RuntimeException('Solo disponible en Edge Agent.');$lab=new EdgeConnectorLab(Connection::pdo());
        $payload=['id'=>post_int('id',0),'name'=>post_string('name'),'source_kind'=>post_string('source_kind','unknown'),'host'=>post_string('host'),'onvif_url'=>post_string('onvif_url'),'onvif_port'=>post_int('onvif_port',80),'username'=>post_string('username'),'password'=>(string)($_POST['password']??''),'rtsp_url'=>post_string('rtsp_url'),'verify_tls'=>!empty($_POST['verify_tls'])];
        $id=$lab->saveCamera($payload);
        try{
            $_SESSION['edge_camera_test']=$lab->testCamera($id);
            $sync=EdgeNodeClient::syncCameras(true);flash('success','Cámara guardada, ONVIF validado y sincronizada con el Property Node'.(!empty($sync['snapshots_uploaded'])?' con snapshot de prueba.':'.'));
        }catch(Throwable $e){$_SESSION['edge_camera_test']=['ok'=>false,'camera_id'=>$id,'message'=>$e->getMessage()];ErrorReporter::report($e,['operation'=>'edge_camera_save_test_sync','camera_id'=>$id]);flash('warning','Cámara guardada localmente. La prueba o sincronización quedó pendiente: '.$e->getMessage());}
        redirect('edge-video-lab');
    }
    if ($route === 'edge-camera-channels-save' && is_post()) {
        require_permission('system.manage');verify_csrf();if(!InstallationMode::isEdge())throw new RuntimeException('Solo disponible en Edge Agent.');
        $cameraId=max(1,post_int('camera_id'));$lab=new EdgeConnectorLab(Connection::pdo());$r=$lab->saveVideoChannels($cameraId,$_POST);
        try{$sync=EdgeNodeClient::syncCameras(true);flash('success','Canales guardados y sincronizados con el Node: '.(int)($r['enabled']??0).' habilitado(s).');}
        catch(Throwable $e){ErrorReporter::report($e,['operation'=>'edge_camera_channels_sync','camera_id'=>$cameraId]);flash('warning','Canales guardados localmente; la sincronización con el Node quedó pendiente: '.$e->getMessage());}
        redirect('edge-video-lab');
    }
    if ($route === 'edge-camera-test' && is_post()) {
        require_permission('system.manage');verify_csrf();if(!InstallationMode::isEdge())throw new RuntimeException('Solo disponible en Edge Agent.');$id=max(1,post_int('id'));try{$_SESSION['edge_camera_test']=(new EdgeConnectorLab(Connection::pdo()))->testCamera($id);$sync=EdgeNodeClient::syncCameras(true);flash('success','Prueba ONVIF completada y cámara sincronizada con el Node'.(!empty($sync['snapshots_uploaded'])?' con snapshot.':'.'));}catch(Throwable $e){$_SESSION['edge_camera_test']=['ok'=>false,'camera_id'=>$id,'message'=>$e->getMessage()];ErrorReporter::report($e,['operation'=>'edge_camera_test','camera_id'=>$id]);flash('danger',$e->getMessage());}redirect('edge-video-lab');
    }
    if ($route === 'edge-camera-delete' && is_post()) {
        require_permission('system.manage');verify_csrf();if(!InstallationMode::isEdge())throw new RuntimeException('Solo disponible en Edge Agent.');(new EdgeConnectorLab(Connection::pdo()))->deleteCamera(max(1,post_int('id')));try{EdgeNodeClient::syncCameras(true);flash('success','Fuente eliminada del Agent y retirada del inventario activo del Node.');}catch(Throwable $e){ErrorReporter::report($e,['operation'=>'edge_camera_delete_sync']);flash('warning','Cámara eliminada localmente; el Node se actualizará en el próximo scheduler.');}redirect('edge-video-lab');
    }
    if ($route === 'edge-camera-sync' && is_post()) {
        require_permission('system.manage');verify_csrf();if(!InstallationMode::isEdge())throw new RuntimeException('Solo disponible en Edge Agent.');$r=EdgeNodeClient::syncCameras(true);flash('success','Inventario sincronizado con el Node: '.(int)($r['received']??0).' cámara(s), '.(int)($r['snapshots_uploaded']??0).' snapshot(s).');redirect('edge-video-lab');
    }
    if ($route === 'edge-media-refresh-node' && is_post()) {
        require_permission('system.manage');verify_csrf();if(!InstallationMode::isEdge())throw new RuntimeException('Solo disponible en Edge Agent.');
        $cfg=EdgeNodeClient::refreshProvisioning();
        $sync=null;try{$sync=EdgeNodeClient::syncCameras(false);}catch(Throwable $e){ErrorReporter::report($e,['operation'=>'edge_media_refresh_node_sync']);}
        $mode=(string)(((array)($cfg['media_relay']??[]))['media_mode']??'cpanel_test');
        flash('success','Configuración recibida del Property Node. Modo de video: '.$mode.'.'.(is_array($sync)?' Inventario y asignaciones WebRTC sincronizados.':''));
        redirect('edge-dashboard');
    }
    if ($route === 'edge-media-install' && is_post()) {
        require_permission('system.manage');verify_csrf();if(!InstallationMode::isEdge())throw new RuntimeException('Solo disponible en Edge Agent.');
        $r=(new EdgeMediaGatewayService(Connection::pdo()))->installOfficialBinary();flash('success','MediaMTX '.(string)$r['version'].' instalado desde el release oficial.');redirect('edge-dashboard');
    }
    if ($route === 'edge-media-configure' && is_post()) {
        require_permission('system.manage');verify_csrf();if(!InstallationMode::isEdge())throw new RuntimeException('Solo disponible en Edge Agent.');
        $r=(new EdgeMediaGatewayService(Connection::pdo()))->generateConfiguration();flash('success','Media Gateway configurado. Cámaras listas para publicar: '.(int)($r['ready']??0).'.');redirect('edge-dashboard');
    }
    if ($route === 'edge-media-start' && is_post()) {
        require_permission('system.manage');verify_csrf();if(!InstallationMode::isEdge())throw new RuntimeException('Solo disponible en Edge Agent.');
        $gateway=new EdgeMediaGatewayService(Connection::pdo());$r=$gateway->start();try{EdgeNodeClient::syncCameras(false);}catch(Throwable $syncError){ErrorReporter::report($syncError,['operation'=>'edge_media_start_sync']);}
        flash(!empty($r['ok'])?'success':'warning',!empty($r['ok'])?'Media Gateway activo.':'Media Gateway no respondió todavía: '.(string)($r['error']??'sin detalle'));redirect('edge-dashboard');
    }
    if ($route === 'edge-media-status' && is_post()) {
        require_permission('system.manage');verify_csrf();if(!InstallationMode::isEdge())throw new RuntimeException('Solo disponible en Edge Agent.');
        $r=(new EdgeMediaGatewayService(Connection::pdo()))->checkStatus(true);try{EdgeNodeClient::syncCameras(false);}catch(Throwable $syncError){ErrorReporter::report($syncError,['operation'=>'edge_media_status_sync']);}
        flash(!empty($r['ok'])?'success':'warning',!empty($r['ok'])?'MediaMTX está activo; paths listos: '.count((array)($r['ready_paths']??[])).'.':'MediaMTX no está activo: '.(string)($r['error']??'sin detalle'));redirect('edge-dashboard');
    }
    if ($route === 'edge-media-cpanel-direct' && is_post()) {
        require_permission('system.manage');verify_csrf();if(!InstallationMode::isEdge())throw new RuntimeException('Solo disponible en Edge Agent.');
        $r=(new EdgeMediaGatewayService(Connection::pdo()))->prepareCpanelDirectWebrtc();$url=(string)($r['tunnel']['public_base_url']??'');
        flash('success','WebRTC directo Edge listo para prueba cPanel'.($url!==''?' · '.$url:''));redirect('edge-dashboard');
    }
    if ($route === 'edge-rtsp-test' && is_post()) {
        require_permission('system.manage'); verify_csrf();if(!InstallationMode::isEdge())throw new RuntimeException('Solo disponible en Edge Agent.');
        $host=post_string('host');$port=max(1,min(65535,post_int('port',554)));$_SESSION['edge_rtsp_host']=$host;$_SESSION['edge_rtsp_port']=$port;
        try{$_SESSION['edge_rtsp_result']=(new EdgeConnectorLab(Connection::pdo()))->testRtsp($host,$port);flash(!empty($_SESSION['edge_rtsp_result']['ok'])?'success':'warning',(string)$_SESSION['edge_rtsp_result']['message']);}catch(Throwable $e){$_SESSION['edge_rtsp_result']=['ok'=>false,'message'=>$e->getMessage()];ErrorReporter::report($e,['operation'=>'edge_rtsp_test']);flash('danger',$e->getMessage());}redirect('edge-video-lab');
    }

    // Property Node: gestión y custodia de Edge Agents.
    if ($route === 'edge-agent-create-token' && is_post()) {
        require_permission('system.manage');verify_csrf();if(!InstallationMode::isNode())throw new RuntimeException('Solo un Property Node puede emparejar Edge Agents.');
        $_SESSION['new_edge_pairing_token']=(new EdgeNodeService(Connection::pdo()))->createPairingToken((int)(current_user()['id']??0),post_string('allowed_agent_code'),post_string('agent_name'));flash('warning','PHEDGE generado para este Property Node. No contiene credenciales de Central y se mostrará una sola vez.');redirect('edge-agents');
    }
    if ($route === 'edge-agent-backup-download') {
        require_permission('system.manage');if(!InstallationMode::isNode())throw new RuntimeException('Solo disponible en Property Node.');$path=(new EdgeNodeService(Connection::pdo()))->archivePath(max(1,(int)($_GET['id']??0)));
        header('Content-Type: application/octet-stream');header('Content-Disposition: attachment; filename="'.basename($path).'"');header('Content-Length: '.filesize($path));header('Cache-Control: no-store');readfile($path);exit;
    }
    if ($route === 'node-zkbio-queue' && is_post()) {
        require_permission('system.manage');verify_csrf();if(!InstallationMode::isNode())throw new RuntimeException('Solo un Property Node puede solicitar pruebas al Edge Agent.');
        try{
            $params=[
                'pageNo'=>post_int('pageNo',1),'pageSize'=>post_int('pageSize',20),'pins'=>post_string('pins'),'deptCodes'=>post_string('deptCodes'),
                'personPin'=>post_string('personPin'),'startDate'=>post_string('startDate'),'endDate'=>post_string('endDate'),
            ];
            $queued=(new EdgeNodeService(Connection::pdo()))->queueZkBioReadCommand(post_int('agent_id'),post_string('preset'),$params,is_int(current_user()['id']??null)?(int)current_user()['id']:null);
            flash('success','Prueba ZKBio encolada para '.(string)$queued['agent_code'].'. El Edge la ejecutará en su próximo heartbeat y devolverá la respuesta saneada.');
        }catch(Throwable $e){ErrorReporter::report($e,['operation'=>'node_zkbio_queue']);flash('danger','ZKBio remoto: '.$e->getMessage());}
        redirect('zkbio-lab');
    }


    if ($route === 'node-zkbio-inventory-sync' && is_post()) {
        require_permission('system.manage');verify_csrf();if(!InstallationMode::isNode())throw new RuntimeException('Solo disponible en Property Node.');
        try{$r=(new ZkBioOperationalService(Connection::pdo()))->queueInventory(is_int(current_user()['id']??null)?(int)current_user()['id']:null);flash('success','Inventario ZKBio solicitado al Edge. Comando: '.(string)($r['command_uuid']??''));}catch(Throwable $e){ErrorReporter::report($e,['operation'=>'node_zkbio_inventory']);flash('danger','Inventario ZKBio: '.$e->getMessage());}redirect('zkbio-lab');
    }
    if ($route === 'node-zkbio-adopt-door' && is_post()) {
        require_permission('integrations.manage');verify_csrf();if(!InstallationMode::isNode())throw new RuntimeException('Solo disponible en Property Node.');
        try{$placement=['property_id'=>post_int('property_id')?:null,'area_id'=>post_int('area_id')?:null,'new_area_name'=>post_string('new_area_name'),'new_area_scope'=>post_string('new_area_scope')?:'common','new_area_usage'=>post_string('new_area_usage')?:'circulation','side_a_area_id'=>post_int('side_a_area_id')?:null,'side_b_area_id'=>post_int('side_b_area_id')?:null,'side_a_new_area'=>post_string('side_a_new_area'),'side_b_new_area'=>post_string('side_b_new_area')];$id=(new ZkBioOperationalService(Connection::pdo()))->adoptDoor(post_int('external_object_id'),$placement);flash('success','Puerta ZKBio vinculada y ubicada como punto de acceso Property #'.$id.'.');}catch(Throwable $e){ErrorReporter::report($e,['operation'=>'node_zkbio_adopt_door']);flash('danger','Adopción/ubicación ZKBio: '.$e->getMessage());}redirect('zkbio-lab');
    }
    if ($route === 'node-zkbio-person-map' && is_post()) {
        require_permission('people.manage');verify_csrf();try{(new ZkBioOperationalService(Connection::pdo()))->setPersonPin(post_int('person_id'),post_string('zk_pin'));flash('success','Persona Property vinculada al PIN ZKBio.');}catch(Throwable $e){flash('danger',$e->getMessage());}redirect('zkbio-lab');
    }
    if ($route === 'node-zkbio-department-map' && is_post()) {
        require_permission('integrations.manage');verify_csrf();try{(new ZkBioOperationalService(Connection::pdo()))->mapDepartment(post_int('property_id'),post_string('department_code'));flash('success','Unidad Property vinculada al departamento ZKBio.');}catch(Throwable $e){flash('danger','Conciliación ZKBio: '.$e->getMessage());}redirect('zkbio-lab');
    }
    if ($route === 'node-zkbio-department-unmap' && is_post()) {
        require_permission('integrations.manage');verify_csrf();try{(new ZkBioOperationalService(Connection::pdo()))->unmapDepartment(post_int('property_id'));flash('success','Vínculo Unidad ↔ Departamento ZKBio eliminado.');}catch(Throwable $e){flash('danger','Conciliación ZKBio: '.$e->getMessage());}redirect('zkbio-lab');
    }
    if ($route === 'node-zkbio-department-bulk-map' && is_post()) {
        require_permission('integrations.manage');verify_csrf();try{$r=(new ZkBioOperationalService(Connection::pdo()))->bulkMapSuggestedDepartments();flash('success','Conciliación masiva ZKBio: '.(int)($r['linked']??0).' unidades vinculadas por coincidencia inequívoca; '.(int)($r['skipped']??0).' quedaron sin tocar.');}catch(Throwable $e){flash('danger','Conciliación masiva ZKBio: '.$e->getMessage());}redirect('zkbio-lab');
    }
    if ($route === 'node-zkbio-adopt-all-found' && is_post()) {
        require_permission('people.manage');verify_csrf();
        try{
            if(function_exists('set_time_limit'))@set_time_limit(0);
            $normalize=!empty($_POST['normalize_names']);$mapSuggested=!empty($_POST['map_suggested']);
            $r=(new ZkBioOperationalService(Connection::pdo()))->adoptAllFoundSubjects($mapSuggested,post_string('relationship_mode')?:'infer',post_int('access_profile_id')?:null,$normalize);
            $msg='Adopción masiva ZKBio: '.(int)($r['units_processed']??0).' unidad(es) procesadas, '.(int)($r['departments_linked']??0).' departamento(s) vinculados, '.(int)($r['persons_created']??0).' persona(s) creadas, '.(int)($r['persons_linked']??0).' relación(es), '.(int)($r['vehicles_created']??0).' vehículo(s), '.(int)($r['reclassified']??0).' reclasificado(s), '.(int)($r['already']??0).' ya conciliado(s).';
            if($normalize)$msg.=' Nombres normalizados: '.(int)($r['names_normalized']??0).'.';
            if(!empty($r['errors']))$msg.=' Omitidos: '.implode(' | ',array_slice((array)$r['errors'],0,8));
            flash((int)($r['skipped']??0)>0?'warning':'success',$msg);
        }catch(Throwable $e){ErrorReporter::report($e,['operation'=>'node_zkbio_adopt_all_found']);flash('danger','Adopción masiva ZKBio: '.$e->getMessage());}
        redirect('zkbio-lab');
    }
    if ($route === 'node-zkbio-adopt-department-all' && is_post()) {
        require_permission('people.manage');verify_csrf();try{$normalize=!empty($_POST['normalize_names']);$r=(new ZkBioOperationalService(Connection::pdo()))->adoptDepartmentSubjects(post_int('property_id'),post_string('relationship_mode')?:'infer',post_int('access_profile_id')?:null,$normalize);$msg='Contenido ZKBio adoptado: '.(int)($r['persons_created']??0).' persona(s) creadas, '.(int)($r['persons_linked']??0).' relación(es), '.(int)($r['vehicles_created']??0).' vehículo(s) creados, '.(int)($r['reclassified']??0).' reclasificado(s), '.(int)($r['already']??0).' ya conciliado(s).';if($normalize)$msg.=' Nombres normalizados: '.(int)($r['names_normalized']??0).' cambio(s); '.(int)($r['names_unchanged']??0).' ya estaban correctos.';if(!empty($r['errors']))$msg.=' Omitidos: '.implode(' | ',array_slice((array)$r['errors'],0,5));flash((int)($r['skipped']??0)>0?'warning':'success',$msg);}catch(Throwable $e){flash('danger','Adopción completa ZKBio: '.$e->getMessage());}redirect('zkbio-lab');
    }
    if ($route === 'node-zkbio-adopt-department-people' && is_post()) {
        require_permission('people.manage');verify_csrf();try{$r=(new ZkBioOperationalService(Connection::pdo()))->adoptDepartmentPeople(post_int('property_id'),post_string('relationship_mode')?:'infer',post_int('access_profile_id')?:null);$msg='Personas ZKBio conciliadas: '.(int)($r['created']??0).' creadas, '.(int)($r['linked']??0).' vinculadas, '.(int)($r['already']??0).' ya existentes.';if((int)($r['vehicles_skipped']??0)>0)$msg.=' '.(int)$r['vehicles_skipped'].' identidad(es) vehiculares se dejaron fuera de Personas.';flash('success',$msg);}catch(Throwable $e){flash('danger','Adopción de personas ZKBio: '.$e->getMessage());}redirect('zkbio-lab');
    }
    if ($route === 'node-zkbio-adopt-selected' && is_post()) {
        require_permission('people.manage');verify_csrf();try{$pins=$_POST['pins']??[];if(!is_array($pins))$pins=[];$normalize=!empty($_POST['normalize_names']);$r=(new ZkBioOperationalService(Connection::pdo()))->adoptSelectedSubjects(post_int('property_id'),$pins,post_string('target_mode')?:'auto',post_string('relationship_mode')?:'infer',post_int('access_profile_id')?:null,$normalize);$msg='Selección ZKBio procesada: '.(int)($r['persons_created']??0).' persona(s) creadas, '.(int)($r['persons_linked']??0).' relación(es) creadas, '.(int)($r['vehicles_created']??0).' vehículo(s), '.(int)($r['reclassified']??0).' reclasificado(s), '.(int)($r['already']??0).' ya conciliado(s).';if($normalize)$msg.=' Nombres normalizados: '.(int)($r['names_normalized']??0).' cambio(s); '.(int)($r['names_unchanged']??0).' ya estaban correctos.';if(!empty($r['errors']))$msg.=' Omitidos: '.implode(' | ',array_slice((array)$r['errors'],0,5));flash((int)($r['skipped']??0)>0?'warning':'success',$msg);}catch(Throwable $e){flash('danger','Adopción seleccionada ZKBio: '.$e->getMessage());}redirect('zkbio-lab');
    }
    if ($route === 'node-zkbio-normalize-adopted-names' && is_post()) {
        require_permission('people.manage');verify_csrf();try{$r=(new ZkBioOperationalService(Connection::pdo()))->normalizeAdoptedPersonNamesForProperty(post_int('property_id'));flash('success','Normalización completada: '.(int)($r['updated']??0).' persona(s) actualizadas, '.(int)($r['unchanged']??0).' ya estaban normalizadas, '.(int)($r['skipped']??0).' omitidas.');}catch(Throwable $e){flash('danger','Normalización de nombres ZKBio: '.$e->getMessage());}redirect('zkbio-lab');
    }

    if ($route === 'membership-pending') {
        render('auth/membership-pending', ['title' => 'Vinculación pendiente'], 'resident');
        exit;
    }
    if ((current_user()['membership_status'] ?? 'approved') !== 'approved') redirect('membership-pending');
    if (!in_array($route,['logout','system-repair','system-run-migrations','system-demo-cleanup','system-demo-cleanup-run'],true)) {
        $schemaStatus=(new SchemaManager(Connection::pdo()))->status();
        if(empty($schemaStatus['ready'])) redirect('system-repair');
    }
    if (InstallationMode::isCentral() && str_starts_with($route, 'resident-')) redirect('repository-dashboard');
    $managedInstallationWrites = [
        'installation-profile-save','property-save','property-delete','property-bulk-action','property-duplicate','area-save','area-delete','area-bulk-action','area-context-links-save','vertical-transport-save','vertical-transport-delete','structure-wizard-generate','structure-wizard-rollback',
        'person-save','person-delete','person-reclassify-vehicle','people-bulk-reclassify-vehicles','membership-save','membership-delete','membership-bulk-relationship','vehicle-save','vehicle-zkbio-sync','node-zkbio-open-access','site-location-save',
        'site-location-delete','connectivity-save','connectivity-delete','connector-instance-save',
        'connector-instance-delete','connector-instance-create','device-save','device-delete','node-privacy-save',
        'deployment-register','deployment-test-repository','deployment-heartbeat','check-updates','apply-update','start-update-batch','update-batch-step','update-batch-resume',
        'resident-account-save','resident-app-settings-save','emergency-contact-save','emergency-contact-delete',
        'access-point-save','access-point-delete','camera-save','camera-delete','media-relay-save','media-relay-test'
    ];
    if (InstallationMode::isCentral() && is_post() && in_array($route,$managedInstallationWrites,true)) {
        throw new RuntimeException('Esta operación pertenece a una instalación administrada (Property Node o Edge Agent), no al Property Hub Central.');
    }

    // El Edge Agent no es una segunda aplicación administrativa de la propiedad. Solo admite
    // operaciones técnicas, respaldo, vínculo y actualización. Los datos maestros viven en el Property Node.
    if (InstallationMode::isEdge() && is_post()) {
        $edgeAllowedWrites=[
            'backup-settings-save','backup-key-generate','backup-create','backup-delete','backup-restore-local-prepare','backup-restore-upload','backup-restore-central','backup-restore-cancel','backup-restore-run',
            'edge-node-pair','edge-central-repair','edge-zkbio-save','edge-zkbio-test','edge-zkbio-discover','edge-zkbio-lab-save','edge-zkbio-lab-run','edge-zkbio-lab-custom','edge-zkbio-remote-open','edge-command-worker-start','edge-zkbio-bio-read','edge-zkbio-bio-upload','edge-vimar-save','edge-vimar-test','edge-vimar-discover','edge-vimar-sync','edge-onvif-discover','edge-camera-save','edge-camera-channels-save','edge-camera-test','edge-camera-delete','edge-camera-sync','edge-rtsp-test','edge-media-refresh-node','edge-media-install','edge-media-configure','edge-media-start','edge-media-status','edge-media-cpanel-direct',
            'deployment-save','deployment-test-repository','deployment-register','deployment-heartbeat','check-updates','apply-update','start-update-batch','update-batch-step','update-batch-resume'
        ];
        if(!in_array($route,$edgeAllowedWrites,true))throw new RuntimeException('Esta operación administrativa pertenece al Property Node. El Edge Agent solo ejecuta funciones técnicas locales.');
    }

    if ($route === 'switch-context' && is_post()) {
        verify_csrf();
        (new ResidentRepository())->setContext(post_int('property_id'));
        flash('success', 'Propiedad activa actualizada.');
        redirect(post_string('return_route', 'resident-home'));
    }
    if ($route === 'resident-preview-start') {
        require_permission('people.view');
        if (!InstallationMode::isNode()) throw new RuntimeException('La vista móvil pertenece exclusivamente a un Property Node.');
        $personId=max(1,(int)($_GET['person_id'] ?? 0));
        $check=Connection::pdo()->prepare('SELECT COUNT(*) FROM bh_people WHERE id=? AND installation_id=(SELECT id FROM bh_installations WHERE code=? LIMIT 1)');
        $check->execute([$personId,(string)config('app.instance_code')]);
        if((int)$check->fetchColumn()!==1) throw new InvalidArgumentException('La persona no pertenece a esta instalación.');
        $_SESSION['resident_preview_person_id']=$personId;
        unset($_SESSION['resident_active_property_id']);
        redirect('resident-home');
    }
    if ($route === 'resident-preview-stop') {
        unset($_SESSION['resident_preview_person_id'],$_SESSION['resident_active_property_id']);
        redirect('people');
    }
    if ($route === 'create-reservation' && is_post()) {
        require_permission('reservations.create'); verify_csrf();
        (new ResidentRepository())->createReservation($_POST);
        try{(new ResidentSecurityService())->audit('reservation.create','reservation',null,['route'=>$route], 'success',null,(int)($_SESSION['resident_active_property_id']??0)?:null);}catch(Throwable $ignore){}
        flash('success', 'La solicitud de reserva fue registrada.');
        redirect(post_string('return_route', 'resident-reservations'));
    }
    if ($route === 'reservation-status' && is_post()) {
        require_permission('reservations.manage'); verify_csrf();
        (new ResidentRepository())->updateReservationStatus(post_int('id'), post_string('status'));
        flash('success', 'Estado de la reserva actualizado.');
        redirect('reservations');
    }
    if ($route === 'create-bulletin' && is_post()) {
        require_permission('bulletin.manage'); verify_csrf();
        (new ResidentRepository())->createBulletin($_POST);
        flash('success', 'Publicación agregada a la cartelera.');
        redirect('bulletin');
    }
    if ($route === 'create-notification' && is_post()) {
        require_permission('notifications.manage'); verify_csrf();
        (new ResidentRepository())->createNotification($_POST);
        flash('success', 'Notificación registrada.');
        redirect('notifications');
    }
    if ($route === 'create-invitation' && is_post()) {
        require_permission(can('visitors.manage') ? 'visitors.manage' : 'visitors.manage.own'); verify_csrf();
        $invitationId=(new ResidentRepository())->createInvitation($_POST);
        $zkMessage='';
        if(InstallationMode::isNode()&&LocalIntegrationProvisioning::nodeEnabled('zkteco',Connection::pdo())){try{(new ZkBioOperationalService(Connection::pdo()))->queueVisitorProvision($invitationId,is_int(current_user()['id']??null)?(int)current_user()['id']:null);$zkMessage=' Se está enviando también a ZKBio.';}catch(Throwable $e){ErrorReporter::report($e,['operation'=>'zkbio_visitor_provision','invitation_id'=>$invitationId]);$zkMessage=' La invitación Property quedó creada; ZKBio requiere revisión: '.$e->getMessage();}}
        try{(new ResidentSecurityService())->audit('visitor.invitation.create','invitation',$invitationId,['route'=>$route], 'success',null,(int)($_SESSION['resident_active_property_id']??0)?:null);}catch(Throwable $ignore){}
        flash('success', 'Invitación creada y código temporal generado.'.$zkMessage);
        redirect(post_string('return_route', 'resident-visitors'));
    }
    if ($route === 'notification-read' && is_post()) {
        require_permission('notifications.view'); verify_csrf();
        (new ResidentRepository())->markNotificationRead(post_int('id'));
        try{(new ResidentSecurityService())->audit('notification.read','notification',post_int('id'));}catch(Throwable $ignore){}
        redirect(post_string('return_route', 'resident-notifications'));
    }

    if ($route === 'access-profile-save' && is_post()) {
        require_permission('people.manage');verify_csrf();$id=post_int('id')?:null;(new AccessPolicyService(Connection::pdo()))->saveProfile($_POST,$id);flash('success',$id?'Perfil de acceso actualizado.':'Perfil de acceso creado.');redirect('access-profiles');
    }
    if ($route === 'access-profile-delete' && is_post()) {
        require_permission('people.manage');verify_csrf();(new AccessPolicyService(Connection::pdo()))->deleteProfile(post_int('id'));flash('success','Perfil de acceso eliminado.');redirect('access-profiles');
    }
    if ($route === 'membership-profile-assign' && is_post()) {
        require_permission('people.manage');verify_csrf();(new AccessPolicyService(Connection::pdo()))->assignMembership(post_int('membership_id'),post_int('access_profile_id'));flash('success','Perfil aplicado a la relación persona/unidad.');redirect('people');
    }
    if ($route === 'resident-account-save' && is_post()) {
        require_permission('people.manage'); verify_csrf();
        $result=(new ResidentRepository())->saveResidentAccount($_POST);
        flash('success','Acceso móvil guardado para '.$result['person'].'. Usuario: '.$result['login'].'.');
        redirect('people');
    }
    if ($route === 'resident-app-settings-save' && is_post()) {
        require_permission('system.manage'); verify_csrf();
        (new ResidentRepository())->saveSettings($_POST);
        flash('success','Configuración visual de Property App actualizada.');
        redirect('resident-app-settings');
    }
    if ($route === 'emergency-contact-save' && is_post()) {
        require_permission('emergency.manage'); verify_csrf();
        $id=post_int('id')?:null;(new ResidentRepository())->saveEmergencyContact($_POST,$id);
        flash('success',$id?'Contacto actualizado.':'Contacto de emergencia creado.');redirect('emergency');
    }
    if ($route === 'emergency-contact-delete' && is_post()) {
        require_permission('emergency.manage'); verify_csrf();
        (new ResidentRepository())->deleteEmergencyContact(post_int('id'));flash('success','Contacto eliminado.');redirect('emergency');
    }
    if ($route === 'asset-binding-save' && is_post()) {
        require_permission('integrations.manage'); verify_csrf();
        (new \App\Core\AssetBindingService(Connection::pdo()))->saveBinding($_POST);
        flash('success','Activo Property Hub vinculado al recurso físico.');header('Location: '.url('technology-inventory',['tab'=>'relationships']));exit;
    }
    if ($route === 'asset-binding-delete' && is_post()) {
        require_permission('integrations.manage'); verify_csrf();
        (new \App\Core\AssetBindingService(Connection::pdo()))->deleteBinding(post_int('id'));
        flash('success','Binding físico eliminado; el activo lógico se conserva.');header('Location: '.url('technology-inventory',['tab'=>'relationships']));exit;
    }
    if ($route === 'access-authenticator-save' && is_post()) {
        require_permission('integrations.manage'); verify_csrf();
        (new \App\Core\AssetBindingService(Connection::pdo()))->saveAuthenticator($_POST);
        flash('success','Autenticador agregado al punto de acceso.');header('Location: '.url('technology-inventory',['tab'=>'relationships']));exit;
    }
    if ($route === 'access-execution-path-save' && is_post()) {
        require_permission('integrations.manage'); verify_csrf();
        (new \App\Core\AssetBindingService(Connection::pdo()))->saveExecutionPath($_POST);
        flash('success','Ruta de apertura agregada al punto de acceso.');header('Location: '.url('technology-inventory',['tab'=>'relationships']));exit;
    }
    if ($route === 'access-execution-path-delete' && is_post()) {
        require_permission('integrations.manage'); verify_csrf();
        (new \App\Core\AssetBindingService(Connection::pdo()))->deleteExecutionPath(post_int('id'));
        flash('success','Ruta de apertura eliminada.');header('Location: '.url('technology-inventory',['tab'=>'relationships']));exit;
    }
    if ($route === 'access-point-save' && is_post()) {
        require_permission('integrations.manage'); verify_csrf();
        $id=post_int('id')?:null;$repo=new ResidentRepository();$savedId=$repo->saveAccessPoint($_POST,$id);$repo->saveAccessPointCameraLinks($savedId,is_array($_POST['camera_ids']??null)?$_POST['camera_ids']:[]);
        flash('success',$id?'Punto de acceso actualizado.':'Punto de acceso creado.');redirect('access');
    }
    if ($route === 'access-point-delete' && is_post()) {
        require_permission('integrations.manage'); verify_csrf();
        (new ResidentRepository())->deleteAccessPoint(post_int('id'));flash('success','Punto de acceso eliminado.');redirect('access');
    }
    if ($route === 'camera-save' && is_post()) {
        require_permission('integrations.manage'); verify_csrf();
        $id=post_int('id')?:null;$repo=new ResidentRepository();$savedId=$repo->saveCamera($_POST,$id);$repo->saveCameraAccessLinks($savedId,is_array($_POST['access_point_ids']??null)?$_POST['access_point_ids']:[]);
        flash('success',$id?'Cámara actualizada.':'Cámara registrada.');redirect('cameras');
    }
    if ($route === 'camera-delete' && is_post()) {
        require_permission('technical.delete'); verify_csrf();
        (new ResidentRepository())->deleteCamera(post_int('id'));flash('success','Cámara retirada del inventario activo. Si proviene de un Edge Agent y todavía está publicada allí, reaparecerá en la próxima sincronización.');redirect('cameras');
    }
    if ($route === 'media-relay-save' && is_post()) {
        require_permission('integrations.manage');verify_csrf();if(!InstallationMode::isNode())throw new RuntimeException('El Media Relay se administra desde el Property Node.');
        $svc=new MediaRelayService(Connection::pdo());$svc->save($_POST);$mode=post_string('media_mode','cpanel_test');flash('success',$mode==='edge_direct_webrtc'?'WebRTC directo Edge habilitado. Ahora abra el Agent y pulse “Preparar WebRTC cPanel”.':'Configuración de video remoto guardada.');redirect('cameras');
    }
    if ($route === 'media-relay-test' && is_post()) {
        require_permission('integrations.manage');verify_csrf();if(!InstallationMode::isNode())throw new RuntimeException('El Media Relay se prueba desde el Property Node.');
        $r=(new MediaRelayService(Connection::pdo()))->test();flash(!empty($r['ok'])?'success':'warning',(string)$r['message']);redirect('cameras');
    }
    if ($route === 'media-relay-config-download') {
        require_permission('integrations.manage');if(!InstallationMode::isNode())throw new RuntimeException('El Media Relay se administra desde el Property Node.');
        $service=new MediaRelayService(Connection::pdo());$content=$service->publicRelayConfig();header('Content-Type: text/yaml; charset=utf-8');header('Content-Disposition: attachment; filename="mediamtx-property-hub.yml"');header('Cache-Control: no-store');echo $content;exit;
    }
    if ($route === 'repository-create-installation' && is_post()) {
        require_permission('system.manage'); verify_csrf();
        if (!InstallationMode::isCentral()) throw new RuntimeException('Esta acción pertenece al Property Hub Central.');
        $result=(new RepositoryService())->createInstallationPlan([
            'installation_name'=>post_string('installation_name'),
            'installation_code'=>post_string('installation_code'),
            'installation_type'=>post_string('installation_type','mixed'),
            'planned_base_url'=>post_string('planned_base_url'),
            'update_channel'=>post_string('update_channel','stable'),
            'data_scope'=>post_string('data_scope','aggregates'),
            'environment_type'=>post_string('environment_type','production'),
            'technology'=>(array)($_POST['technology']??[]),
            'notes'=>post_string('notes'),
        ],is_int(current_user()['id']??null)?(int)current_user()['id']:null);
        $_SESSION['new_installation_plan']=$result;
        flash('success','Instalación preparada. Copie el token y complete la instalación del Property Node.');
        redirect('repository-new-installation');
    }
    if ($route === 'repository-cancel-installation-plan' && is_post()) {
        require_permission('system.manage'); verify_csrf();
        if (!InstallationMode::isCentral()) throw new RuntimeException('Esta acción pertenece al Property Hub Central.');
        (new RepositoryService())->cancelInstallationPlan(post_int('id'));
        flash('success','Preparación cancelada y token revocado.');
        redirect('repository-installations');
    }

    if ($route === 'repository-set-installation-environment' && is_post()) {
        require_permission('system.manage'); verify_csrf();
        if (!InstallationMode::isCentral()) throw new RuntimeException('Esta acción pertenece al Property Hub Central.');
        $installationId=post_int('installation_id');
        (new RepositoryService())->setInstallationEnvironment($installationId,post_string('environment_type','production'));
        flash('success','Entorno de la instalación actualizado.');
        redirect('repository-installation',['id'=>$installationId]);
    }
    if ($route === 'repository-installation-connectors-save' && is_post()) {
        require_permission('system.manage'); verify_csrf();
        if (!InstallationMode::isCentral()) throw new RuntimeException('Esta acción pertenece al Property Hub Central.');
        $installationId=post_int('installation_id');$desired=(new RepositoryService())->saveInstallationDesiredConnectors($installationId,(array)($_POST['connector_codes']??[]),(array)($_POST['connector_policy']??[]),(array)($_POST['connector_version']??[]),(array)($_POST['connector_constraint']??[]));
        flash('success','Conectores deseados actualizados: '.count($desired).'. El Node los recibirá en su próximo chequeo de actualizaciones y los propagará a sus Edge.');
        redirect('repository-installation',['id'=>$installationId]);
    }

    if ($route === 'repository-duplicate-installation' && is_post()) {
        require_permission('system.manage'); verify_csrf();
        if (!InstallationMode::isCentral()) throw new RuntimeException('Esta acción pertenece al Property Hub Central.');
        $result=(new RepositoryService())->duplicateInstallationAsPlan(
            post_int('installation_id'),
            post_string('installation_name'),
            post_string('installation_code'),
            post_string('environment_type','test'),
            is_int(current_user()['id']??null)?(int)current_user()['id']:null
        );
        $_SESSION['new_installation_plan']=$result;
        flash('success','Copia preparada como expediente independiente. Se generó un token nuevo; los datos operativos no se copiaron.');
        redirect('repository-new-installation');
    }
    if ($route === 'repository-delete-test-installation' && is_post()) {
        require_permission('system.manage'); verify_csrf();
        if (!InstallationMode::isCentral()) throw new RuntimeException('Esta acción pertenece al Property Hub Central.');
        $result=(new RepositoryService())->deleteTestInstallation(
            post_int('installation_id'),
            post_string('confirmation'),
            !empty($_POST['purge_offsite']),
            !empty($_POST['ack_active'])
        );
        flash('success','Instalación de prueba eliminada del Central: '.(string)$result['code'].'.'.((int)($result['offsite_files']??0)>0?' Copias externas eliminadas: '.(int)$result['offsite_files'].'.':''));
        redirect('repository-installations');
    }

    if ($route === 'repository-rename-property' && is_post()) {
        require_permission('system.manage'); verify_csrf();
        if (!InstallationMode::isCentral()) throw new RuntimeException('Esta acción pertenece al Property Hub Central.');
        $result=(new RepositoryService())->renameProperty(
            post_string('property_code'),post_string('installation_name'),is_int(current_user()['id']??null)?(int)current_user()['id']:null
        );
        flash('success','Nombre de la instalación actualizado: '.(string)$result['name'].'. El código técnico no cambió.');
        redirect('repository-property',['code'=>(string)$result['code']]);
    }

    if ($route === 'repository-delete-property' && is_post()) {
        require_permission('system.manage'); verify_csrf();
        if (!InstallationMode::isCentral()) throw new RuntimeException('Esta acción pertenece al Property Hub Central.');
        $code=strtoupper(post_string('property_code'));
        $result=(new RepositoryService())->deleteInactiveProperty($code,post_string('confirmation'),!empty($_POST['purge_offsite']));
        flash('success','Instalación eliminada de Central: '.(string)$result['code'].'. Registros Node/Edge eliminados: '.(int)$result['registered_removed'].'.'.((int)($result['offsite_files']??0)>0?' Respaldos físicos eliminados: '.(int)$result['offsite_files'].'.':' Respaldos externos conservados.'));
        redirect('repository-installations');
    }

    if ($route === 'repository-central-cleanup-run' && is_post()) {
        require_permission('system.manage'); verify_csrf();
        if (!InstallationMode::isCentral()) throw new RuntimeException('Esta acción pertenece al Property Hub Central.');
        $result=(new CentralCleanupService(Connection::pdo()))->cleanup(
            (array)($_POST['installation_ids']??[]),
            post_string('confirmation'),
            !empty($_POST['purge_offsite']),
            !empty($_POST['ack_active']),
            !empty($_POST['cleanup_plans']),
            !empty($_POST['prune_tokens'])
        );
        $_SESSION['central_cleanup_result']=$result;
        $removed=count((array)($result['removed_installations']??[]));$errors=count((array)($result['errors']??[]));
        flash($errors?'warning':'success','Limpieza Central finalizada. Instalaciones eliminadas: '.$removed.'. Preparaciones: '.(int)($result['removed_plans']??0).'. Tokens huérfanos: '.(int)($result['removed_tokens']??0).($errors?' · Errores: '.$errors.'.':''));
        redirect('repository-central-cleanup');
    }


    if ($route === 'repository-vault-settings-save' && is_post()) {
        require_permission('system.manage'); verify_csrf();
        if (!InstallationMode::isCentral()) throw new RuntimeException('Esta acción pertenece al Property Hub Central.');
        (new BackupVaultService(Connection::pdo()))->saveSettings($_POST);
        flash('success','Política automática del Backup Vault actualizada.');redirect('repository-backup-vault');
    }
    if ($route === 'repository-request-backup' && is_post()) {
        require_permission('system.manage'); verify_csrf();
        if (!InstallationMode::isCentral()) throw new RuntimeException('Esta acción pertenece al Property Hub Central.');
        $r=(new BackupVaultService(Connection::pdo()))->queueBackup(post_int('installation_id'),is_int(current_user()['id']??null)?(int)current_user()['id']:null);
        flash(!empty($r['queued'])?'success':'info',!empty($r['queued'])?'Solicitud de respaldo encolada. El Node la recogerá en el próximo heartbeat/runner.':'Ya existe una solicitud pendiente para esa instalación.');
        $return=post_string('return_route','repository-backup-vault');
        if($return==='repository-installation')redirect($return,['id'=>post_int('installation_id')]);
        if($return==='repository-property')redirect($return,['code'=>post_string('property_code')]);
        redirect('repository-backup-vault');
    }
    if ($route === 'repository-request-backup-all' && is_post()) {
        require_permission('system.manage'); verify_csrf();
        if (!InstallationMode::isCentral()) throw new RuntimeException('Esta acción pertenece al Property Hub Central.');
        $r=(new BackupVaultService(Connection::pdo()))->queueAll(is_int(current_user()['id']??null)?(int)current_user()['id']:null);
        flash('success','Mantenimiento encolado: '.$r['queued'].' solicitudes nuevas; '.$r['existing'].' ya estaban pendientes.');
        redirect('repository-backup-vault');
    }
    if ($route === 'repository-prepare-recovery' && is_post()) {
        require_permission('system.manage'); verify_csrf();
        if (!InstallationMode::isCentral()) throw new RuntimeException('Esta acción pertenece al Property Hub Central.');
        $_SESSION['vault_recovery_token']=(new BackupVaultService(Connection::pdo()))->issueRecoveryToken(post_int('archive_id'),is_int(current_user()['id']??null)?(int)current_user()['id']:null,24);
        flash('warning','Token temporal de recuperación generado. Se muestra una sola vez y vence en 24 horas.');
        redirect('repository-backup-vault');
    }
    if ($route === 'repository-regenerate-plan-token' && is_post()) {
        require_permission('system.manage'); verify_csrf();
        if (!InstallationMode::isCentral()) throw new RuntimeException('Esta acción pertenece al Property Hub Central.');
        $result=(new RepositoryService())->regeneratePlanToken(
            post_int('plan_id'),is_int(current_user()['id']??null)?(int)current_user()['id']:null
        );
        $_SESSION['new_installation_plan']=$result;
        flash('success','Token inicial reemplazado. El expediente y la instalación se conservaron.');
        redirect('repository-new-installation');
    }
    if ($route === 'repository-create-edge-enrollment' && is_post()) {
        require_permission('system.manage'); verify_csrf();
        if (!InstallationMode::isCentral()) throw new RuntimeException('Esta acción pertenece al Property Hub Central.');
        $service=new RepositoryService();$propertyCode=strtoupper(post_string('property_code'));
        if($propertyCode!=='')$result=$service->createEdgeAgentRegistrationTokenForProperty($propertyCode,post_string('agent_code'),is_int(current_user()['id']??null)?(int)current_user()['id']:null,post_string('agent_name'));
        else{$installationId=post_int('installation_id');$result=$service->createEdgeAgentRegistrationToken($installationId,post_string('agent_code'),is_int(current_user()['id']??null)?(int)current_user()['id']:null,post_string('agent_name'));$propertyCode=$service->propertyCodeForInstallation($installationId);}
        $_SESSION['new_edge_enrollment']=$result;
        flash('success','Datos de instalación Edge generados. El token se muestra una sola vez.');
        redirect('repository-property',['code'=>$propertyCode]);
    }
    if ($route === 'repository-download-deployment-file' && is_post()) {
        require_permission('system.manage'); verify_csrf();
        if (!InstallationMode::isCentral()) throw new RuntimeException('Esta acción pertenece al Property Hub Central.');
        $result=(new PropertyDeploymentFileService(Connection::pdo()))->issue(
            strtoupper(post_string('property_code')),
            is_int(current_user()['id']??null)?(int)current_user()['id']:null,
            true
        );
        header('Content-Type: application/vnd.2cam.propertyhub-deployment+json; charset=utf-8');
        header('Content-Disposition: attachment; filename="'.str_replace('"','',(string)$result['filename']).'"');
        header('Cache-Control: no-store, private, max-age=0');header('Pragma: no-cache');
        echo (string)$result['content'];exit;
    }
    if ($route === 'repository-create-relink-token' && is_post()) {
        require_permission('system.manage'); verify_csrf();
        if (!InstallationMode::isCentral()) throw new RuntimeException('Esta acción pertenece al Property Hub Central.');
        $installationId=post_int('installation_id');
        $result=(new RepositoryService())->createRelinkToken(
            $installationId,is_int(current_user()['id']??null)?(int)current_user()['id']:null
        );
        $_SESSION['new_relink_token']=$result;
        flash('success','Token de reconexión generado sin borrar la instalación. Se muestra una sola vez.');
        redirect('repository-installation',['id'=>$installationId]);
    }
    if ($route === 'repository-revoke-token' && is_post()) {
        require_permission('system.manage'); verify_csrf();
        if (!InstallationMode::isCentral()) throw new RuntimeException('Esta acción pertenece al Property Hub Central.');
        (new RepositoryService())->revokeToken(
            post_int('token_id'),is_int(current_user()['id']??null)?(int)current_user()['id']:null,post_string('reason','Revocado manualmente')
        );
        flash('success','Token revocado. La instalación y su expediente se conservaron.');
        $returnRoute=post_string('return_route','repository-tokens');
        if($returnRoute==='repository-installation') redirect($returnRoute,['id'=>post_int('installation_id')]);
        if($returnRoute==='repository-property') redirect($returnRoute,['code'=>post_string('property_code')]);
        redirect('repository-tokens');
    }
    if ($route === 'repository-revoke-edge-enrollment' && is_post()) {
        require_permission('system.manage'); verify_csrf();
        if (!InstallationMode::isCentral()) throw new RuntimeException('Esta acción pertenece al Property Hub Central.');
        $code=(new RepositoryService())->revokeEdgeEnrollmentToken(post_int('token_id'),is_int(current_user()['id']??null)?(int)current_user()['id']:null,post_string('reason','Revocado manualmente'));
        flash('success','Token de enrolamiento Edge revocado. El Edge y la propiedad se conservaron.');
        redirect('repository-property',['code'=>$code]);
    }
    if ($route === 'repository-create-token' && is_post()) {
        require_permission('system.manage'); verify_csrf();
        if (!InstallationMode::isCentral()) throw new RuntimeException('Esta instalación no es la fuente oficial.');
        $token=(new RepositoryService())->createRegistrationToken(
            post_string('token_name'), post_string('installation_code') ?: null, post_string('channel','stable'),
            max(1,post_int('max_uses',1)), post_string('expires_at') ?: null, is_int(current_user()['id']??null)?(int)current_user()['id']:null
        );
        $_SESSION['new_repository_token']=$token;
        flash('success','Token generado. Se mostrará una sola vez.');
        redirect('repository-tokens');
    }
    if ($route === 'repository-create-edge-registration-token' && is_post()) {
        require_permission('system.manage'); verify_csrf();
        if (!InstallationMode::isCentral()) throw new RuntimeException('Esta acción pertenece al Property Hub Central.');
        $installationId=post_int('installation_id');
        flash('warning','Los Edge Agents ya no reciben tokens de Central. Genere un PHEDGE desde el Property Node; la distribución funciona Central → Node → Edge.');
        redirect('repository-installation',['id'=>$installationId]);
    }
    if ($route === 'repository-create-api-version' && is_post()) {
        require_permission('system.manage'); verify_csrf();
        if (!InstallationMode::isCentral()) throw new RuntimeException('Esta instalación no es la fuente oficial.');
        (new RepositoryService())->createApiVersion([
            'api_code'=>post_string('api_code'),'version'=>post_string('version'),'status'=>post_string('status','draft'),
            'minimum_app_version'=>post_string('minimum_app_version'),'maximum_app_version'=>post_string('maximum_app_version'),
            'documentation_path'=>post_string('documentation_path'),'changelog'=>post_string('changelog'),'support_ends_at'=>post_string('support_ends_at'),
        ]);
        flash('success','Versión de API registrada.');
        redirect('repository-api-versions');
    }
    if ($route === 'repository-sync-official-core' && is_post()) {
        require_permission('system.manage'); verify_csrf();
        if (!InstallationMode::isCentral()) throw new RuntimeException('Esta instalación no es la fuente oficial.');
        $result=(new RepositorySynchronizer(Connection::pdo()))->syncOfficialComponents(is_int(current_user()['id']??null)?(int)current_user()['id']:null);
        $core=(array)($result['core']??[]);$connectorCount=count((array)($result['connectors']??[]));
        $conflicts=[];if(!empty($core['source_conflict']))$conflicts[]='Core '.(string)($core['version']??config('app.version'));
        foreach((array)($result['core_bridges']??[]) as $bridge){if(is_array($bridge)&&!empty($bridge['source_conflict']))$conflicts[]='Core '.(string)($bridge['version']??'?');}
        if($conflicts!==[]){
            flash('warning','Repositorio sincronizado conservando los releases canónicos inmutables. Hay divergencia de ZIP local en '.implode(', ',array_values(array_unique($conflicts))).'; no se sobrescribió ningún SHA-256 publicado.');
        }else{
            flash('success','Repositorio oficial sincronizado: Core '.(string)($core['version']??config('app.version')).' · conectores embebidos '.$connectorCount.'.');
        }
        redirect('repository-releases');
    }
    if ($route === 'repository-publish-release' && is_post()) {
        require_permission('system.manage'); verify_csrf();
        if (!InstallationMode::isCentral()) throw new RuntimeException('Esta instalación no es la fuente oficial.');
        $result=(new ReleasePublisher())->publish([
            'selected_component_code'=>post_string('selected_component_code','__auto__'),
            'channel'=>post_string('channel','development'),'release_notes'=>post_string('release_notes'),
        ], $_FILES['package']??[], is_int(current_user()['id']??null)?(int)current_user()['id']:null);
        flash('success','Paquete adicional publicado: '.$result['release_code'].' · SHA-256 '.$result['hash']);
        redirect('repository-releases');
    }
    if ($route === 'repository-promote-release' && is_post()) {
        require_permission('system.manage'); verify_csrf();
        if (!InstallationMode::isCentral()) throw new RuntimeException('Esta instalación no es la fuente oficial.');
        $result=(new ReleasePublisher())->promote(post_string('release_code'),post_string('channel','stable'),is_int(current_user()['id']??null)?(int)current_user()['id']:null);
        flash('success','Release '.$result['component_code'].' '.$result['version'].' promovida a '.$result['channel'].' sin volver a subir el ZIP.');
        redirect('repository-releases');
    }
    if ($route === 'repository-save-privacy' && is_post()) {
        require_permission('system.manage'); verify_csrf();
        if (!InstallationMode::isCentral()) throw new RuntimeException('Esta acción pertenece al Property Hub Central.');
        (new RepositoryService())->savePrivacySettings($_POST);
        flash('success','Política global de privacidad actualizada.'); redirect('repository-privacy');
    }
    if ($route === 'repository-reseed-demo' && is_post()) {
        require_permission('system.manage'); verify_csrf();
        if (!InstallationMode::isCentral()) throw new RuntimeException('Esta acción pertenece al Property Hub Central.');
        require_once base_path('app/Seeders/CentralDemoSeeder.php');
        $result=(new CentralDemoSeeder(Connection::pdo()))->seed();
        flash('success','Demostración central restablecida: '.$result['installations'].' instalaciones, '.$result['plans'].' preparaciones y '.$result['connectors'].' conectores.'); redirect('repository-dashboard');
    }
    if ($route === 'installation-profile-save' && is_post()) {
        require_permission('properties.manage'); verify_csrf();
        if(InstallationMode::isCentral())throw new RuntimeException('El perfil operativo pertenece a un Property Node.');
        (new NodeRepository())->saveInstallationProfile($_POST);
        flash('success','Perfil de la instalación actualizado. Algunos textos se actualizarán al recargar.');redirect('building-profile');
    }
    if ($route === 'structure-wizard-generate' && is_post()) {
        require_permission('properties.manage'); verify_csrf();
        if (InstallationMode::isCentral()) throw new RuntimeException('El asistente de estructura pertenece a un Property Node.');
        try {
            $result=(new NodeRepository())->generateStructureFromWizard($_POST);
        } catch (InvalidArgumentException $e) {
            $_SESSION['structure_wizard_old_payload']=(string)($_POST['wizard_payload']??'');
            flash('warning',$e->getMessage());
            redirect('structure-wizard');
        }
        $summary=$result['summary']??[];
        $message='Diseño generado: '.(int)($summary['towers']??0).' torres, '.(int)($summary['floors']??0).' niveles, '.(int)($summary['apartments']??0).' unidades, '.(int)($summary['parkings']??0).' estacionamientos, '.(int)($summary['storages']??0).' maleteros, '.(int)($summary['areas']??0).' áreas y '.(int)($summary['vertical_transports']??0).' elementos de circulación vertical.';
        if((int)($summary['areas_existing']??0)>0)$message.=' Se conservaron '.(int)$summary['areas_existing'].' áreas que ya existían.';
        flash('success',$message);
        redirect('properties');
    }
    if ($route === 'structure-wizard-rollback' && is_post()) {
        require_permission('properties.manage'); verify_csrf();
        if (InstallationMode::isCentral()) throw new RuntimeException('El asistente de estructura pertenece a un Property Node.');
        $result=(new NodeRepository())->rollbackStructureWizardRun(post_int('run_id'));
        flash('success','Ejecución deshecha. Se eliminaron '.(int)($result['items']??0).' elementos sin actividad relacionada.');
        redirect('structure-wizard');
    }
    if ($route === 'property-save' && is_post()) {
        require_permission('properties.manage'); verify_csrf();
        if (InstallationMode::isCentral()) throw new RuntimeException('La estructura se administra dentro de cada Property Node.');
        $id=post_int('id')?:null;(new NodeRepository())->saveProperty($_POST,$id);
        flash('success',$id?'Elemento de estructura actualizado.':'Elemento de estructura creado.');redirect('properties');
    }
    if ($route === 'property-delete' && is_post()) {
        require_permission('properties.manage'); verify_csrf();
        $result=(new NodeRepository())->deleteProperty(post_int('id'));
        flash('success',$result==='deleted'?'Elemento eliminado.':'El elemento tiene relaciones y fue marcado inactivo.');redirect('properties');
    }
    if ($route === 'property-bulk-action' && is_post()) {
        require_permission('properties.manage'); verify_csrf();
        if (InstallationMode::isCentral()) throw new RuntimeException('La estructura se administra dentro de cada Property Node.');
        try {
            $result=(new NodeRepository())->bulkPropertyAction($_POST);
        } catch (InvalidArgumentException $e) {
            flash('warning',$e->getMessage()); redirect('properties');
        }
        $action=(string)($result['action']??'');
        $message=match($action){
            'move'=>'Elementos movidos: '.(int)($result['moved']??0).'. Jerarquías internas conservadas: '.(int)($result['nested_preserved']??0).'.',
            'delete'=>'Eliminados: '.(int)($result['deleted']??0).'. Desactivados por dependencias: '.(int)($result['deactivated']??0).'.',
            'rename'=>'Elementos renombrados: '.(int)($result['updated']??0).'.',
            'status','type'=>'Elementos actualizados: '.(int)($result['updated']??0).'.',
            default=>'Acción masiva completada.',
        };
        flash('success',$message);redirect('properties');
    }
    if ($route === 'property-duplicate' && is_post()) {
        require_permission('properties.manage'); verify_csrf();
        if (InstallationMode::isCentral()) throw new RuntimeException('La estructura se administra dentro de cada Property Node.');
        try {
            $result=(new NodeRepository())->duplicatePropertySubtree(post_int('source_id'),$_POST);
        } catch (InvalidArgumentException $e) {
            flash('warning',$e->getMessage()); redirect('properties');
        }
        $summary=(array)($result['summary']??[]);
        flash('success','Estructura duplicada: '.(int)($summary['properties']??0).' elementos, '.(int)($summary['areas']??0).' áreas, '.(int)($summary['devices']??0).' activos, '.(int)($summary['access_points']??0).' accesos, '.(int)($summary['cameras']??0).' cámaras y '.(int)($summary['vertical_transports']??0).' ascensores.');
        redirect('properties');
    }
    if ($route === 'area-save' && is_post()) {
        require_permission('areas.manage'); verify_csrf();
        $id=post_int('id')?:null;(new NodeRepository())->saveArea($_POST,$id);
        flash('success',$id?'Área actualizada.':'Área creada.');redirect('areas');
    }
    if ($route === 'area-delete' && is_post()) {
        require_permission('areas.manage'); verify_csrf();
        $result=(new NodeRepository())->deleteArea(post_int('id'));
        flash('success',$result==='deleted'?'Área eliminada.':'El área tiene operaciones relacionadas y fue desactivada.');redirect('areas');
    }
    if ($route === 'area-bulk-action' && is_post()) {
        require_permission('areas.manage'); verify_csrf();
        if (InstallationMode::isCentral()) throw new RuntimeException('Las áreas se administran dentro de cada Property Node.');
        try {
            $result=(new NodeRepository())->bulkAreaAction($_POST);
        } catch (InvalidArgumentException $e) {
            flash('warning',$e->getMessage()); redirect('areas');
        }
        $action=(string)($result['action']??'');
        $message=match($action){
            'move'=>'Áreas movidas: '.(int)($result['moved']??0).'.',
            'delete'=>'Áreas eliminadas: '.(int)($result['deleted']??0).'. Desactivadas por dependencias: '.(int)($result['deactivated']??0).'.',
            'duplicate'=>'Áreas duplicadas: '.(int)($result['duplicated']??0).'. Componentes copiados como planificación: '.(int)($result['components']??0).'.',
            'status','scope','usage'=>'Áreas actualizadas: '.(int)($result['updated']??0).'.',
            default=>'Acción masiva de áreas completada.',
        };
        flash('success',$message); redirect('areas');
    }
    if ($route === 'area-context-links-save' && is_post()) {
        require_permission('integrations.manage'); verify_csrf();
        $areaId=post_int('area_id');(new NodeRepository())->saveAreaContextLinks($areaId,(array)($_POST['camera_ids']??[]),(array)($_POST['access_ids']??[]),(array)($_POST['device_ids']??[]));
        flash('success','Relaciones del área actualizadas. La misma ubicación ya se refleja desde cámaras, accesos e inventario técnico.');redirect('area-context',['id'=>$areaId]);
    }
    if ($route === 'vertical-transport-save' && is_post()) {
        require_permission('properties.manage'); verify_csrf();$id=post_int('id')?:null;(new NodeRepository())->saveVerticalTransport($_POST,$id);flash('success',$id?'Ascensor actualizado.':'Ascensor creado.');redirect('vertical-transport');
    }
    if ($route === 'vertical-transport-delete' && is_post()) {
        require_permission('technical.delete'); verify_csrf();(new NodeRepository())->deleteVerticalTransport(post_int('id'));flash('success','Ascensor eliminado.');redirect('vertical-transport');
    }
    if ($route === 'person-save' && is_post()) {
        require_permission('people.manage'); verify_csrf();
        $id=post_int('id')?:null;$personId=(new NodeRepository())->savePerson($_POST,$id);$message=$id?'Persona actualizada.':'Persona registrada.';
        if(!empty($_POST['provision_zkbio'])&&InstallationMode::isNode()){try{$propertyId=post_int('property_id');if($propertyId<1)throw new InvalidArgumentException('Para provisionar ZKBio seleccione una unidad inicial.');$levels=$_POST['zk_access_level_ids']??[];(new ZkBioOperationalService(Connection::pdo()))->queuePersonProvision($personId,$propertyId,['zk_pin'=>post_string('zk_pin'),'zk_dept_code'=>post_string('zk_dept_code'),'zk_access_level_ids'=>is_array($levels)?$levels:[]],is_int(current_user()['id']??null)?(int)current_user()['id']:null);$message.=' Provisionamiento ZKBio en cola.';}catch(Throwable $e){ErrorReporter::report($e,['operation'=>'zkbio_person_provision','person_id'=>$personId]);$message.=' ZKBio quedó pendiente: '.$e->getMessage();}}
        flash('success',$message);redirect('people');
    }
    if ($route === 'person-delete' && is_post()) {
        require_permission('people.manage'); verify_csrf();
        $result=(new NodeRepository())->deletePerson(post_int('id'));
        flash('success',$result==='deleted'?'Persona eliminada.':'La persona tiene historial relacionado y fue desactivada.');redirect('people');
    }
    if ($route === 'membership-save' && is_post()) {
        require_permission('people.manage'); verify_csrf();
        $id=post_int('id')?:null;(new NodeRepository())->saveMembership($_POST,$id);
        flash('success',$id?'Vinculación actualizada.':'Persona vinculada a la unidad.');redirect('people');
    }
    if ($route === 'membership-delete' && is_post()) {
        require_permission('people.manage'); verify_csrf();(new NodeRepository())->deleteMembership(post_int('id'));
        flash('success','Vinculación eliminada.');redirect('people');
    }
    if ($route === 'person-reclassify-vehicle' && is_post()) {
        require_permission('people.manage');verify_csrf();try{if(mb_strtoupper(post_string('confirmation'))!=='RECLASIFICAR')throw new InvalidArgumentException('Escriba RECLASIFICAR para confirmar el cambio de tipo.');$r=(new NodeRepository())->reclassifyPersonToVehicle(post_int('person_id'),$_POST);flash('success','Identidad reclasificada como vehículo: '.(string)($r['display_name']??'Vehículo').'. Se conservaron sus bindings externos y se retiró del directorio de personas.');}catch(Throwable $e){flash('danger','Reclasificación: '.$e->getMessage());}redirect('people');
    }
    if ($route === 'people-bulk-reclassify-vehicles' && is_post()) {
        require_permission('people.manage');verify_csrf();$ids=$_POST['person_ids']??[];if(!is_array($ids))$ids=[];try{$r=(new NodeRepository())->bulkReclassifyPeopleToVehicles($ids);$msg='Reclasificación masiva: '.(int)($r['converted']??0).' convertido(s) a vehículo, '.(int)($r['skipped']??0).' omitido(s).';if(!empty($r['errors']))$msg.=' '.implode(' | ',array_slice((array)$r['errors'],0,5));flash((int)($r['skipped']??0)>0?'warning':'success',$msg);}catch(Throwable $e){flash('danger','Reclasificación masiva: '.$e->getMessage());}redirect('people');
    }
    if ($route === 'membership-bulk-relationship' && is_post()) {
        require_permission('people.manage');verify_csrf();$ids=$_POST['membership_ids']??[];if(!is_array($ids))$ids=[];try{$r=(new NodeRepository())->bulkUpdateMembershipRelationships($ids,post_string('relationship_type'),post_int('access_profile_id')?:null);flash('success','Relaciones actualizadas: '.(int)($r['updated']??0).'. Omitidas: '.(int)($r['skipped']??0).'.');}catch(Throwable $e){flash('danger','Cambio masivo de relación: '.$e->getMessage());}redirect('people');
    }
    if ($route === 'membership-permissions') { require_permission('people.manage');$data=(new NodeRepository())->membershipPermissionData(max(1,(int)($_GET['id']??0)));render('admin/membership-permissions',['title'=>'Permisos de usuario','permissionData'=>$data],'admin');exit; }
    if ($route === 'membership-permissions-save' && is_post()) { require_permission('people.manage');verify_csrf();$id=post_int('membership_id');(new NodeRepository())->saveMembershipPermissions($id,$_POST);flash('success','Permisos detallados guardados para esta relación.');redirect('membership-permissions',['id'=>$id]); }

    if ($route === 'node-zkbio-open-access' && is_post()) {require_permission('access.operate');verify_csrf();try{$uid=is_int(current_user()['id']??null)?(int)current_user()['id']:null;$r=(new ZkBioOperationalService(Connection::pdo()))->requestRemoteOpen(post_int('access_point_id'),$uid,null,null,'node_admin_test');flash('success','Orden de apertura enviada al Edge. Correlación: '.(string)($r['correlation_uuid']??''));}catch(Throwable $e){flash('danger','Apertura ZKBio: '.$e->getMessage());}redirect('zkbio-lab');}

    if ($route === 'vehicle-save' && is_post()) {require_permission('people.manage');verify_csrf();try{$svc=new ZkBioOperationalService(Connection::pdo());$id=$svc->saveVehicle($_POST);$msg='Vehículo registrado.';if(!empty($_POST['sync_zkbio'])){try{$svc->queueVehicleProvision($id,is_int(current_user()['id']??null)?(int)current_user()['id']:null);$msg.=' Sincronización ZKBio en cola.';}catch(Throwable $e){$msg.=' ZKBio pendiente: '.$e->getMessage();}}flash('success',$msg);}catch(Throwable $e){flash('danger',$e->getMessage());}redirect('vehicles');}
    if ($route === 'vehicle-zkbio-sync' && is_post()) {require_permission('people.manage');verify_csrf();try{(new ZkBioOperationalService(Connection::pdo()))->queueVehicleProvision(post_int('id'),is_int(current_user()['id']??null)?(int)current_user()['id']:null);flash('success','Vehículo enviado a la cola ZKBio.');}catch(Throwable $e){flash('danger',$e->getMessage());}redirect('vehicles');}
    if ($route === 'site-location-save' && is_post()) {
        require_permission('integrations.manage'); verify_csrf();
        $id=post_int('id')?:null;(new TechnologyRepository())->saveSiteLocation($_POST,$id);
        flash('success',$id?'Ubicación técnica actualizada.':'Ubicación técnica creada.');redirect('site-topology');
    }
    if ($route === 'site-location-delete' && is_post()) {
        require_permission('integrations.manage'); verify_csrf();
        $result=(new TechnologyRepository())->deleteSiteLocation(post_int('id'));
        flash('success',$result==='deleted'?'Ubicación eliminada.':'La ubicación tiene dependencias y fue archivada como planificada.');redirect('site-topology');
    }
    if ($route === 'connectivity-save' && is_post()) {
        require_permission('integrations.manage'); verify_csrf();
        $id=post_int('id')?:null;(new TechnologyRepository())->saveConnectivityLink($_POST,$id);
        flash('success',$id?'Enlace actualizado.':'Enlace de conectividad creado.');redirect('site-topology');
    }
    if ($route === 'connectivity-delete' && is_post()) {
        require_permission('integrations.manage'); verify_csrf();(new TechnologyRepository())->deleteConnectivityLink(post_int('id'));
        flash('success','Enlace eliminado.');redirect('site-topology');
    }
    if ($route === 'connector-instance-save' && is_post()) {
        require_permission('integrations.manage'); verify_csrf();
        if (InstallationMode::isCentral()) throw new RuntimeException('Los conectores se configuran dentro de cada Property Node.');
        $id=post_int('id')?:null;(new TechnologyRepository())->saveConnectorInstance($_POST,$id);
        flash('success',$id?'Conector configurado actualizado.':'Conector agregado a esta instalación.');redirect('connector-instances');
    }
    if ($route === 'connector-instance-delete' && is_post()) {
        require_permission('technical.delete'); verify_csrf();
        $result=(new TechnologyRepository())->deleteConnectorInstance(post_int('id'));
        flash('success',$result==='deleted'?'Conector configurado eliminado.':'El conector tiene equipos relacionados y fue deshabilitado.');redirect('connector-instances');
    }
    if ($route === 'device-save' && is_post()) {
        require_permission('integrations.manage'); verify_csrf();
        $id=post_int('id')?:null;(new TechnologyRepository())->saveDevice($_POST,$id);
        flash('success',$id?'Equipo actualizado.':'Equipo agregado al inventario.');redirect('technology-inventory');
    }
    if ($route === 'device-delete' && is_post()) {
        require_permission('technical.delete'); verify_csrf();
        $result=(new TechnologyRepository())->deleteDevice(post_int('id'));
        flash('success',$result==='deleted'?'Equipo eliminado.':'El equipo tiene elementos relacionados y fue deshabilitado.');redirect('technology-inventory');
    }

    if ($route === 'connector-instance-create' && is_post()) {
        require_permission('integrations.manage'); verify_csrf();
        if (InstallationMode::isCentral()) throw new RuntimeException('Los conectores se configuran dentro de cada Property Node.');
        (new TechnologyRepository())->saveConnectorInstance($_POST);
        flash('success','Conector agregado a esta instalación.'); redirect('connector-instances');
    }
    if ($route === 'node-privacy-save' && is_post()) {
        require_permission('system.manage'); verify_csrf();
        if (InstallationMode::isCentral()) throw new RuntimeException('Esta acción pertenece a un Property Node.');
        (new TechnologyRepository())->updatePrivacyProfile($_POST);
        flash('success','Política local de telemetría actualizada.'); redirect('node-privacy');
    }

    if ($route === 'deployment-save' && is_post()) {
        require_permission('system.manage'); verify_csrf();
        if(InstallationMode::isEdge())throw new RuntimeException('El Edge Agent no configura Property Hub Central manualmente. Empareje el Agent con su Property Node.');
        $channel=post_string('update_channel','stable');
        if(!in_array($channel,['development','beta','stable','pinned'],true))throw new InvalidArgumentException('Canal no válido.');
        $allowOffline=isset($_POST['allow_offline']);
        $repoUrl=RepositoryTransport::normalizeBaseUrl(post_string('central_repository_url'));
        if($repoUrl!==''&&!filter_var($repoUrl,FILTER_VALIDATE_URL))throw new InvalidArgumentException('URL de repositorio no válida.');
        // Canal/offline siguen siendo parámetros locales de bootstrap. La topología remota
        // (URL/credencial Central) vive en bh_remote_connections desde 0.12.0.
        EnvironmentEditor::set(['UPDATE_CHANNEL'=>$channel,'ALLOW_OFFLINE'=>$allowOffline?'true':'false']);
        $pdo=Connection::pdo();
        $pdo->prepare('UPDATE bh_installations SET update_channel=? WHERE public_uuid=?')->execute([$channel,(string)config('deployment.installation_uuid')]);
        $pdo->prepare('UPDATE bh_deployment_settings SET update_channel=?,allow_offline=?,repository_url=? WHERE installation_uuid=?')->execute([$channel,$allowOffline?1:0,$repoUrl?:null,(string)config('deployment.installation_uuid')]);
        if($repoUrl!==''){
            $registry=new ConnectionRegistry();$current=$registry->central();
            $registry->save('central',$repoUrl,(string)($current['credential']??''),'','self','configured',['configured_at'=>date(DATE_ATOM)]);
        }
        flash('success','Configuración de despliegue actualizada.'); redirect('deployment-settings');
    }
    if ($route === 'deployment-test-repository' && is_post()) {
        require_permission('system.manage'); verify_csrf();
        if (InstallationMode::isCentral()) throw new RuntimeException('El Property Hub Central no prueba conexión contra sí mismo.');
        if(InstallationMode::isEdge())try{EdgeNodeClient::refreshProvisioning();}catch(Throwable $e){ErrorReporter::report($e,['operation'=>'edge_refresh_before_repository_probe']);}
        $probe=RepositoryClient::probe();
        if(empty($probe['online'])){
            $error=new RuntimeException((string)($probe['message']??(InstallationMode::isEdge()?'No fue posible localizar el Property Node.':'No fue posible localizar la API del Property Hub Central.')));
            $incident=ErrorReporter::report($error,'repository-endpoint-probe');
            flash('danger',(InstallationMode::isEdge()?'No se encontró el Property Node de distribución. ':'No se encontró el endpoint del Central. ').'Incidente: '.$incident.'. '.(string)$probe['message']);
            redirect('deployment-settings');
        }
        flash('success','Conexión correcta. Fuente: '.(InstallationMode::isEdge()?'Property Node':'Property Hub Central').' · Endpoint: '.(string)($probe['endpoint']??'detectado').' · Transporte: '.(string)($probe['transport_mode']??'desconocido'));
        redirect('deployment-settings');
    }

    if ($route === 'deployment-register' && is_post()) {
        require_permission('system.manage'); verify_csrf();
        if(InstallationMode::isEdge())throw new RuntimeException('El Edge Agent no se registra manualmente en Central. Use URL del Property Node + PHEDGE.');
        $result=(new NodeRegistrationService())->register(post_string('central_repository_url'),post_string('registration_token'));
        $operation=(string)($result['operation']??'registered');
        flash('success',($operation==='relinked'?'Vínculo renovado sin borrar la instalación.':InstallationMode::label().' registrado en Property Hub Central.').' Canal: '.($result['channel']??config('deployment.update_channel'))); redirect('deployment-settings');
    }
    if ($route === 'deployment-heartbeat' && is_post()) {
        require_permission('system.manage'); verify_csrf();
        if (InstallationMode::isCentral()) throw new RuntimeException('El Property Hub Central no envía heartbeat a sí mismo.');
        if(InstallationMode::isEdge())try{EdgeNodeClient::refreshProvisioning();}catch(Throwable $e){ErrorReporter::report($e,['operation'=>'edge_refresh_before_central_heartbeat']);}
        $status=RepositoryClient::status();
        if(empty($status['online'])){
            $error=new RuntimeException((string)($status['message']??(InstallationMode::isEdge()?'No fue posible contactar el Property Node.':'No fue posible contactar el Property Hub Central.')));
            $incident=ErrorReporter::report($error,'deployment-heartbeat');
            flash('danger','Heartbeat no enviado. Incidente: '.$incident.'. '.(string)$status['message']);
            redirect('deployment-settings');
        }
        $maintenance=(array)($status['maintenance']??[]);$response=(array)($status['response']??[]);$suffix=$maintenance!==[]?' · tareas de mantenimiento procesadas: '.count($maintenance):'';$confirmed='';if(!InstallationMode::isEdge()&&!empty($response['installation_code'])){$confirmed=' · Central confirmó '.(string)$response['installation_code'].' a las '.(string)($response['recorded_last_seen_at']??$response['server_time']??'');}flash('success',(InstallationMode::isEdge()?'Vínculo de distribución Edge comprobado':'Heartbeat enviado').' por '.(string)($status['transport_mode']??'API').' a '.(string)($status['endpoint']??(InstallationMode::isEdge()?'Property Node':'Property Hub Central')).$confirmed.$suffix.'.');
        redirect('deployment-settings');
    }

    if ($route === 'check-updates' && is_post()) {
        require_permission('system.manage'); verify_csrf();
        if (InstallationMode::isCentral()) throw new RuntimeException('La fuente oficial publica versiones; no consulta otra fuente.');
        $manifest=RepositoryClient::manifest();
        $analysis=(new UpdateManager(Connection::pdo()))->analyzeManifest($manifest);
        $_SESSION['last_update_manifest']=$manifest;
        $diagnostics=(array)($manifest['manifest']['diagnostics']??[]);
        $localEdgePendingCount=0;
        if(InstallationMode::isNode()){
            $edgeState=UpdateManager::reconcileDownstreamEdgeReleases((array)($diagnostics['downstream_edge_releases']??[]),(new EdgeNodeService(Connection::pdo()))->agents());
            $localEdgePendingCount=(int)($edgeState['pending_count']??0);
        }
        ErrorReporter::runtimeLog('update_manifest_analyzed',[
            'current_core'=>(string)config('app.version',''),
            'channel'=>(string)config('deployment.update_channel','stable'),
            'central_version'=>(string)($diagnostics['central_version']??$manifest['manifest']['repository_version']??''),
            'manifest_channel'=>(string)($manifest['manifest']['channel']??''),
            'eligible_channels'=>(array)($manifest['manifest']['eligible_channels']??[]),
            'published_in_channels'=>(int)($diagnostics['published_in_channels']??0),
            'excluded_count'=>(int)($diagnostics['excluded_count']??0),
            'release_count'=>count((array)($manifest['manifest']['releases']??[])),
            'available_count'=>count($analysis['available']),
            'downstream_edge_candidate_count'=>(int)($diagnostics['downstream_edge_candidate_count']??$diagnostics['downstream_edge_pending_count']??0),
            'downstream_edge_pending_count'=>$localEdgePendingCount,
            'available_versions'=>array_map(static fn(array $r):string=>(string)($r['component_code']??'').':'.(string)($r['version']??''),(array)$analysis['available']),
        ]);
        $message='Consulta completada'.(InstallationMode::isEdge()?' vía Property Node':'').'. Central '.(string)($diagnostics['central_version']??'desconocido').' · compatibles '.count((array)($analysis['releases']??[])).' · actualizaciones locales '.count($analysis['available']).'.';if(InstallationMode::isNode()&&$localEdgePendingCount>0)$message.=' Pendientes para Edge: '.$localEdgePendingCount.'.';elseif(InstallationMode::isNode()&&!empty($diagnostics['downstream_edge_releases']))$message.=' Componentes Edge al día.';
        if((int)($diagnostics['excluded_count']??0)>0)$message.=' Excluidas por compatibilidad: '.(int)$diagnostics['excluded_count'].'.';
        flash(count($analysis['available'])>0?'success':'info',$message);
        redirect('updates');
    }

    if ($route === 'apply-update' && is_post()) {
        require_permission('system.manage'); verify_csrf();
        if (InstallationMode::isCentral()) throw new RuntimeException('El Property Hub Central se actualiza únicamente con el paquete oficial del Central.');
        $releaseCode=post_string('release_code');
        $manifest=$_SESSION['last_update_manifest']??null;
        if(!is_array($manifest)){
            // La sesión puede rotar/expirar entre Buscar actualizaciones y Aplicar.
            // Revalidamos contra el manifiesto firmado actual en vez de obligar al operador a repetir la consulta.
            $manifest=RepositoryClient::manifest();
            $_SESSION['last_update_manifest']=$manifest;
            ErrorReporter::runtimeLog('update_manifest_refetched_on_apply',[
                'release_code'=>$releaseCode,
                'reason'=>'session_manifest_missing',
                'current_core'=>(string)config('app.version',''),
            ]);
        }
        $result=(new UpdateManager(Connection::pdo()))->installFromManifest($manifest,$releaseCode,is_int(current_user()['id']??null)?(int)current_user()['id']:null);
        unset($_SESSION['last_update_manifest']);
        flash('success','Actualización instalada: '.(string)($result['release']['display_name']??$result['release']['component_code']).' '.(string)$result['to_version'].'. Se creó punto de reversión antes de aplicar cambios.');
        redirect('updates');
    }

    if ($route === 'start-update-batch' && is_post()) {
        require_permission('system.manage'); verify_csrf();
        if (InstallationMode::isCentral()) throw new RuntimeException('El Property Hub Central se actualiza únicamente con el paquete oficial del Central.');
        $manifest=RepositoryClient::manifest();$_SESSION['last_update_manifest']=$manifest;
        $batch=(new UpdateBatchManager(Connection::pdo()))->create($manifest,is_int(current_user()['id']??null)?(int)current_user()['id']:null);
        redirect('update-batch',['id'=>(string)$batch['id']]);
    }

    if ($route === 'update-batch-resume' && is_post()) {
        require_permission('system.manage'); verify_csrf();
        $id=post_string('batch_id');(new UpdateBatchManager(Connection::pdo()))->resume($id);
        redirect('update-batch',['id'=>$id]);
    }

    if ($route === 'update-batch-step' && is_post()) {
        require_permission('system.manage'); verify_csrf();
        header('Content-Type: application/json; charset=utf-8');
        $id=post_string('batch_id');$state=(new UpdateBatchManager(Connection::pdo()))->processNext($id,is_int(current_user()['id']??null)?(int)current_user()['id']:null);
        echo json_encode(['ok'=>($state['status']??'')!=='failed','batch'=>$state],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);exit;
    }

    if ($route === 'update-batch') {
        require_permission('system.manage');if(InstallationMode::isCentral())redirect('repository-dashboard');
        $id=query_string('id');$manager=new UpdateBatchManager(Connection::pdo());$state=$manager->load($id);
        render('admin/update-batch',['title'=>'Actualización automática','batch'=>$manager->publicState($state)],'admin');exit;
    }


    if ($route === 'edge-command-worker-start' && is_post()) {require_permission('system.manage');verify_csrf();if(!InstallationMode::isEdge())throw new RuntimeException('Solo disponible en Edge Agent.');try{$r=EdgeCommandWorkerService::startDetached();if(!empty($r['running']))flash('success','Worker de comandos Edge activo.');else flash('warning','Worker no confirmó heartbeat: '.(string)($r['message']??'revise el log de bootstrap.'));}catch(Throwable $e){flash('danger','Worker: '.$e->getMessage());}redirect('edge-zkbio-lab');}
    if ($route === 'edge-zkbio-bio-read' && is_post()) {require_permission('system.manage');verify_csrf();if(!InstallationMode::isEdge())throw new RuntimeException('Solo disponible en Edge Agent.');try{$_SESSION['edge_zkbio_lab_result']=(new EdgeConnectorLab(Connection::pdo()))->readZkBioBiometrics(post_string('pin'));flash('success','Consulta de huellas ejecutada.');}catch(Throwable $e){$_SESSION['edge_zkbio_lab_result']=['ok'=>false,'message'=>$e->getMessage()];flash('danger',$e->getMessage());}redirect('edge-zkbio-lab');}
    if ($route === 'edge-zkbio-bio-upload' && is_post()) {require_permission('system.manage');verify_csrf();if(!InstallationMode::isEdge())throw new RuntimeException('Solo disponible en Edge Agent.');if(mb_strtoupper(post_string('confirmation'))!=='HUELLA')throw new InvalidArgumentException('Escriba HUELLA para confirmar la carga del template.');try{$_SESSION['edge_zkbio_lab_result']=(new EdgeConnectorLab(Connection::pdo()))->uploadZkBioBiometric(['personId'=>post_string('person_id'),'template'=>post_string('template'),'templateNo'=>(string)post_int('template_no'),'validType'=>(string)post_int('valid_type',1)]);flash('success','Template biométrico enviado a ZKBio.');}catch(Throwable $e){$_SESSION['edge_zkbio_lab_result']=['ok'=>false,'message'=>$e->getMessage()];flash('danger',$e->getMessage());}redirect('edge-zkbio-lab');}

    if ($route === 'edge-zkbio-lab') {
        require_permission('system.manage');if(!InstallationMode::isEdge())throw new RuntimeException('Laboratorio ZKBio disponible únicamente en Property Edge Agent.');
        if(!LocalIntegrationProvisioning::edgeEnabled('zkteco'))try{EdgeNodeClient::refreshProvisioning();}catch(Throwable $e){ErrorReporter::report($e,['operation'=>'edge_zkbio_lab_refresh_provisioning']);}
        LocalIntegrationProvisioning::assertEdgeEnabled('zkteco');$lab=new EdgeConnectorLab(Connection::pdo());$matrix=new ZkBioApiMatrixRunner(Connection::pdo());try{EdgeCommandWorkerService::startDetached();}catch(Throwable $workerError){ErrorReporter::report($workerError,['operation'=>'edge_command_worker_autostart']);}
        render('edge/zkbio-lab',['title'=>'ZKBio API Lab','zkbioConfig'=>$lab->zkBioConfig(),'zkbioCatalog'=>$lab->zkBioExplorerCatalog(),'zkbioLabResult'=>$_SESSION['edge_zkbio_lab_result']??null,'zkbioHistory'=>$lab->zkBioTestHistory(),'zkbioDiscoverySummary'=>$lab->zkBioDiscoverySummary(),'zkbioApiCatalog'=>$matrix->catalog(),'zkbioApiCatalogStats'=>$matrix->catalogStats(),'zkbioMatrixRuns'=>$matrix->latestRuns(),'edgeCommandWorker'=>EdgeCommandWorkerService::status()],'admin');unset($_SESSION['edge_zkbio_lab_result']);exit;
    }

    if ($route === 'edge-vimar-lab') {
        require_permission('system.manage');if(!InstallationMode::isEdge())throw new RuntimeException('Vimar local disponible únicamente en Property Edge Agent.');
        if(!LocalIntegrationProvisioning::edgeEnabled('vimar'))try{EdgeNodeClient::refreshProvisioning();}catch(Throwable $e){ErrorReporter::report($e,['operation'=>'edge_vimar_lab_refresh_provisioning']);}
        LocalIntegrationProvisioning::assertEdgeEnabled('vimar');$lab=new EdgeConnectorLab(Connection::pdo());
        $result=$_SESSION['edge_vimar_result']??($_SESSION['edge_vimar_test']??null);$sync=$_SESSION['edge_vimar_sync']??null;
        render('edge/vimar-lab',['title'=>'Vimar · VIEW / By-me / KNX','vimarConfig'=>$lab->vimarConfig(),'vimarResult'=>$result,'vimarInventory'=>$lab->vimarEquipmentInventory(),'vimarSummary'=>$lab->vimarDiscoverySummary(),'vimarSyncResult'=>$sync,'vimarInterfaces'=>$lab->networkInterfaces()],'admin');
        unset($_SESSION['edge_vimar_result'],$_SESSION['edge_vimar_test'],$_SESSION['edge_vimar_sync']);exit;
    }

    if ($route === 'edge-video-lab') {
        require_permission('system.manage');if(!InstallationMode::isEdge())throw new RuntimeException('Video local disponible únicamente en Property Edge Agent.');$lab=new EdgeConnectorLab(Connection::pdo());
        render('edge/video-lab',['title'=>'Video local · ONVIF / RTSP','onvifResult'=>$_SESSION['edge_onvif_result']??null,'onvifInterfaces'=>$lab->networkInterfaces(),'edgeCameras'=>$lab->cameras(),'cameraTestResult'=>$_SESSION['edge_camera_test']??null,'rtspResult'=>$_SESSION['edge_rtsp_result']??null,'rtspHost'=>$_SESSION['edge_rtsp_host']??'','rtspPort'=>$_SESSION['edge_rtsp_port']??554],'admin');
        unset($_SESSION['edge_onvif_result'],$_SESSION['edge_camera_test'],$_SESSION['edge_rtsp_result']);exit;
    }

    if ($route === 'edge-dashboard') {
        require_permission('system.manage');if(!InstallationMode::isEdge())throw new RuntimeException('Consola disponible únicamente en Property Edge Agent.');
        $lab=new EdgeConnectorLab(Connection::pdo());$registry=new ConnectionRegistry();try{$registry->importLegacyIfNeeded();}catch(Throwable $e){ErrorReporter::report($e,['operation'=>'edge_dashboard_connection_import']);}
        try{EdgeNodeClient::refreshProvisioning();}catch(Throwable $e){ErrorReporter::report($e,['operation'=>'edge_dashboard_refresh_provisioning']);}
        // Abrir el dashboard también actúa como recuperación de último recurso. Si la
        // tarea Windows no está instalada, al menos la primera visita vuelve a levantar
        // el supervisor y los túneles sin pedir intervención manual.
        try{EdgeCommandWorkerService::startDetached();}catch(Throwable $e){ErrorReporter::report($e,['operation'=>'edge_dashboard_worker_autostart']);}
        $mediaWatchdog=null;try{$mediaWatchdog=(new EdgeMediaGatewayService(Connection::pdo()))->watchdog(true);}catch(Throwable $e){ErrorReporter::report($e,['operation'=>'edge_dashboard_media_watchdog']);$mediaWatchdog=['ok'=>false,'error'=>$e->getMessage()];}
        $central=RepositoryClient::status();$parent=$registry->parentNode();$node=['configured'=>(string)($parent['endpoint_url']??'')!==''&&(string)($parent['credential']??'')!=='','ok'=>false,'property_code'=>(string)($parent['remote_code']??''),'endpoint_url'=>(string)($parent['endpoint_url']??'')];
        if(!empty($node['configured'])){try{$node=EdgeNodeClient::heartbeat()+$node+['configured'=>true];}catch(Throwable $e){$node=$node+['configured'=>true,'ok'=>false,'message'=>$e->getMessage()];}}
        $manager=new BackupManager(Connection::pdo());$mediaGateway=['status'=>'not_installed','camera_counts'=>[],'relay'=>[]];try{$mediaGateway=(new EdgeMediaGatewayService(Connection::pdo()))->state();}catch(Throwable $e){ErrorReporter::report($e,['operation'=>'edge_media_gateway_state']);$mediaGateway=['status'=>'error','last_error'=>$e->getMessage(),'camera_counts'=>[],'relay'=>[]];}
        render('edge/dashboard',['title'=>'Property Edge Agent','localIntegrations'=>LocalIntegrationProvisioning::edgeIntegrations(),'zkbioConfig'=>$lab->zkBioConfig(),'edgeTestResult'=>$_SESSION['edge_zkbio_test']??null,'zkbioDiscovery'=>$_SESSION['edge_zkbio_discovery']??null,'zkbioDiscoverySummary'=>$lab->zkBioDiscoverySummary(),'vimarConfig'=>$lab->vimarConfig(),'vimarTestResult'=>$_SESSION['edge_vimar_test']??null,'onvifResult'=>$_SESSION['edge_onvif_result']??null,'onvifInterfaces'=>$lab->networkInterfaces(),'edgeCameras'=>$lab->cameras(),'cameraTestResult'=>$_SESSION['edge_camera_test']??null,'rtspResult'=>$_SESSION['edge_rtsp_result']??null,'rtspHost'=>$_SESSION['edge_rtsp_host']??'','rtspPort'=>$_SESSION['edge_rtsp_port']??554,'centralStatus'=>$central,'nodeStatus'=>$node,'schedulerInfo'=>$manager->schedulerInfo(),'mediaGateway'=>$mediaGateway,'mediaWatchdog'=>$mediaWatchdog,'edgeCommandWorker'=>EdgeCommandWorkerService::status()],'admin');
        unset($_SESSION['edge_zkbio_test'],$_SESSION['edge_zkbio_discovery'],$_SESSION['edge_vimar_test'],$_SESSION['edge_onvif_result'],$_SESSION['edge_camera_test'],$_SESSION['edge_rtsp_result']);exit;
    }
    if ($route === 'edge-agents') {
        require_permission('system.manage');if(!InstallationMode::isNode())throw new RuntimeException('Gestión disponible únicamente en Property Node.');$service=new EdgeNodeService(Connection::pdo());$token=$_SESSION['new_edge_pairing_token']??null;unset($_SESSION['new_edge_pairing_token']);
        render('admin/edge-agents',['title'=>'Property Edge Agents','edgeAgents'=>$service->agents(),'edgeArchives'=>$service->archives(),'newEdgePairingToken'=>$token],'admin');exit;
    }
    if ($route === 'zkbio-lab') {
        require_permission('system.manage');if(!InstallationMode::isNode())throw new RuntimeException('El laboratorio ZKBio remoto está disponible únicamente en Property Node.');if(!LocalIntegrationProvisioning::nodeEnabled('zkteco',Connection::pdo()))throw new RuntimeException('Active primero ZKTeco/ZKBio en Infraestructura y tecnología → Sistemas conectados.');
        $service=new EdgeNodeService(Connection::pdo());$op=new ZkBioOperationalService(Connection::pdo());
        $reconciliation=$op->departmentReconciliation();
        $nodeRepo=new NodeRepository(Connection::pdo());
        render('admin/zkbio-lab',['title'=>'ZKBio · Integración operativa','edgeAgents'=>$service->agents(),'zkbioCommands'=>$service->zkbioCommands(100),'zkbioInventory'=>$op->inventory(),'zkbioPersonMappings'=>$op->personMappingOptions(),'zkbioDepartmentReconciliation'=>$reconciliation,'zkbioPeopleByProperty'=>$op->peopleByMappedProperty(),'accessProfileOptions'=>(new AccessPolicyService(Connection::pdo()))->options(),'areaOptions'=>$nodeRepo->areaOptions(),'structuralLocationOptions'=>$nodeRepo->structuralLocationOptions()],'admin');exit;
    }

    $user = current_user();
    // Las cuentas RESIDENT permanecen dentro del portal residente, pero ciertos endpoints
    // de soporte son parte de ese portal aunque no lleven el prefijo resident-.
    // En 0.14.12/0.14.13 camera-live-config, camera-hls y camera-snapshot eran
    // interceptados aquí y redirigidos a resident-home antes de ejecutar su autorización
    // específica. El resultado era: web administrativa OK, Property App sin video.
    $residentUtilityRoutes = ['camera-live-config','camera-hls','camera-snapshot'];
    if (($user['role'] ?? '') === 'RESIDENT' && !str_starts_with($route, 'resident-') && !in_array($route,$residentUtilityRoutes,true)) redirect('resident-home');

    $month = preg_match('/^\d{4}-\d{2}$/', (string)($_GET['month'] ?? '')) ? (string)$_GET['month'] : date('Y-m');
    $adminViews = InstallationMode::isCentral() ? [
        'system-check' => ['admin/system-check','system.manage','Diagnóstico central'],
        'deployment-settings' => ['admin/deployment-settings','system.manage','Identidad del repositorio'],
    ] : (InstallationMode::isEdge() ? [
        'system-check' => ['admin/system-check','system.manage','Diagnóstico del Edge Agent'],
        'deployment-settings' => ['admin/deployment-settings','system.manage','Vínculo del Agent'],
        'updates' => ['admin/updates','system.manage','Actualizaciones'],
    ] : [
        'dashboard' => ['admin/dashboard','dashboard.view','Panel operativo'],
        'resident-app-settings' => ['admin/resident-app-settings','system.manage','Property App · Vista residente'],
        'building-profile' => ['admin/building-profile','properties.view','Perfil de la instalación'],
        'properties' => ['admin/properties','properties.view','Estructura física'],
        'structure-explorer' => ['admin/structure-explorer','properties.view','Mapa jerárquico del inmueble'],
        'structure-context' => ['admin/structure-context','properties.view','Ficha contextual de estructura'],
        'area-context' => ['admin/area-context','areas.view','Ficha contextual de área'],
        'structure-wizard' => ['admin/structure-wizard','properties.manage','Asistente de diseño del inmueble'],
        'areas' => ['admin/areas','areas.view','Áreas y espacios'],
        'vertical-transport' => ['admin/vertical-transport','properties.view','Ascensores y circulación vertical'],
        'people' => ['admin/people','people.view','Personas'],
        'vehicles' => ['admin/vehicles','people.view','Vehículos'],
        'access-profiles' => ['admin/access-profiles','people.view','Perfiles de acceso'],
        'user-activity' => ['admin/user-activity','people.view','Actividad de usuarios'],
        'visitors' => ['admin/visitors','visitors.view','Visitantes'],
        'reservations' => ['admin/reservations','reservations.view','Reservas y calendario'],
        'bulletin' => ['admin/bulletin','bulletin.view','Cartelera informativa'],
        'notifications' => ['admin/notifications','notifications.view','Notificaciones'],
        'emergency' => ['admin/emergency','emergency.view','Directorio de emergencias'],
        'site-topology' => ['admin/site-topology','integrations.view','Sedes, garitas y conectividad'],
        'technology-inventory' => ['admin/technology-inventory','integrations.view','Equipos y activos'],
        'connector-instances' => ['admin/connector-instances','integrations.view','Sistemas y plataformas conectadas'],
        'integrations' => ['admin/integrations','integrations.view','Catálogo de integraciones'],
        'node-privacy' => ['admin/node-privacy','system.manage','Privacidad y telemetría'],
        'access' => ['admin/access','access.view','Control de accesos'],
        'cameras' => ['admin/cameras','cameras.view','Cámaras'],
        'intercom' => ['admin/intercom','intercom.view','Intercomunicación y perifonía'],
        'internet' => ['admin/internet','internet.view','Internet y vouchers'],
        'lorawan' => ['admin/lorawan','integrations.view','LoRaWAN e IoT'],
        'system-check' => ['admin/system-check','system.manage','Diagnóstico'],
        'deployment-settings' => ['admin/deployment-settings','system.manage','Vínculo con Property Hub Central'],
        'updates' => ['admin/updates','system.manage','Actualizaciones'],
    ]);
    if (isset($adminViews[$route])) {
        [$view,$permission,$title] = $adminViews[$route];
        require_permission($permission);
        $residentRepo=new ResidentRepository();
        $payload=['title'=>$title,'month'=>$month,'calendar'=>month_calendar($month,$residentRepo->adminReservations())];
        if (InstallationMode::isNode()) {
            $node=new NodeRepository();$tech=new TechnologyRepository();
            if(in_array($route,['building-profile','site-topology','technology-inventory','connector-instances','integrations','lorawan','node-privacy'],true))$payload['technology']=$tech;
            if($route==='dashboard'){$payload['metrics']=$node->dashboardMetrics();$payload['activity']=$node->recentActivity();$payload['technologySummary']=$tech->summary();}
            if($route==='building-profile')$payload['installationProfile']=$node->installationProfile();
            if($route==='properties'){$payload['pageData']=$node->properties(['search'=>query_string('search'),'type'=>query_string('type'),'status'=>query_string('status'),'page'=>query_int('page',1),'per_page'=>query_per_page()]);$payload['propertyOptions']=$node->propertyOptions();}
            if($route==='structure-explorer')$payload['structureExplorer']=$node->structureExplorer();
            if($route==='structure-context')$payload['structureContext']=$node->propertyContext(max(1,query_int('id',0)));
            if($route==='area-context')$payload['areaContext']=$node->areaContext(max(1,query_int('id',0)));
            if($route==='structure-wizard')$payload['wizard']=$node->structureWizardContext();
            if($route==='areas'){$payload['pageData']=$node->areas(['search'=>query_string('search'),'type'=>query_string('type'),'scope'=>query_string('scope'),'usage'=>query_string('usage'),'status'=>query_string('status'),'page'=>query_int('page',1),'per_page'=>query_per_page()]);$payload['propertyOptions']=$node->structuralResourceOptions();}
            if($route==='vertical-transport'){$payload['verticalTransports']=$node->verticalTransports();$payload['verticalOptions']=$node->verticalTransportOptions();}
            if($route==='resident-app-settings'){$payload['residentSettings']=$residentRepo->settings();$payload['residentAccounts']=$residentRepo->residentAccounts();$payload['personOptions']=$node->personOptions();$payload['residentAccounts']=$residentRepo->residentAccounts();}
            if($route==='people'){$payload['accessProfiles']=(new AccessPolicyService(Connection::pdo()))->options();$payload['pageData']=$node->people(['search'=>query_string('search'),'status'=>query_string('status'),'page'=>query_int('page',1),'per_page'=>query_per_page()]);$payload['propertyOptions']=$node->propertyOptions();$payload['memberships']=$node->memberships();$payload['personOptions']=$node->personOptions();$payload['residentAccounts']=$residentRepo->residentAccounts();$payload['zkAccessLevels']=[];$payload['zkDepartments']=[];$payload['zkPersonMappings']=[];if(LocalIntegrationProvisioning::nodeEnabled('zkteco',Connection::pdo())){try{$zi=(new ZkBioOperationalService(Connection::pdo()))->inventory();$payload['zkAccessLevels']=$zi['access_levels']??[];$payload['zkDepartments']=$zi['departments']??[];$payload['zkPersonMappings']=(new ZkBioOperationalService(Connection::pdo()))->personMappingOptions();}catch(Throwable $e){ErrorReporter::report($e,['operation'=>'people_zkbio_payload']);}}}
            if($route==='vehicles'){$op=new ZkBioOperationalService(Connection::pdo());$payload['vehicles']=$op->vehicles();$payload['propertyOptions']=$node->propertyOptions();$payload['personOptions']=$node->personOptions();$payload['zkAccessLevels']=[];if(LocalIntegrationProvisioning::nodeEnabled('zkteco',Connection::pdo())){try{$payload['zkAccessLevels']=$op->inventory()['access_levels']??[];}catch(Throwable $ignore){}}}
            if($route==='access-profiles'){$policy=new AccessPolicyService(Connection::pdo());$payload['accessProfiles']=$policy->profiles();$payload['accessProfileOptions']=$policy->options();$payload['propertyOptions']=$node->propertyOptions();}
            if($route==='user-activity'){$sec=new ResidentSecurityService();$filters=['property_id'=>query_int('property_id',0),'relationship_type'=>query_string('relationship_type'),'user_type'=>query_string('user_type'),'email'=>query_string('email'),'action'=>query_string('action'),'online'=>query_string('online')];$payload['activityFilters']=$filters;$payload['activity']=$sec->activity($filters);$payload['onlineUsers']=$sec->onlineUsers($filters['property_id']?:null);$payload['propertyOptions']=$node->propertyOptions();$payload['mailSettings']=$sec->mailSettings();}
            if($route==='technology-inventory'){try{$payload['equipmentReconcile']=$tech->reconcileManagedExternalEquipment();}catch(Throwable $e){ErrorReporter::report($e,['operation'=>'technology_inventory_reconcile']);$payload['equipmentReconcile']=['error'=>$e->getMessage()];}$payload['connectorInstances']=$tech->connectorInstances();$payload['devicePage']=$tech->devices(['search'=>query_string('search'),'type'=>query_string('type'),'status'=>query_string('status'),'category'=>query_string('category'),'connector_id'=>query_int('connector_id',0),'page'=>query_int('page',1),'per_page'=>query_per_page()]);$payload['siteLocations']=$tech->siteLocations();$payload['propertyOptions']=$node->structuralResourceOptions();$payload['areaOptions']=$node->areaOptions();$binding=new \App\Core\AssetBindingService(Connection::pdo());$payload['bindingData']=$binding->dashboard();$payload['authenticators']=$binding->authenticators();$payload['activeTechnologyTab']=query_string('tab')?:'equipment';}
            if($route==='reservations'){$payload['reservations']=$residentRepo->adminReservations();$payload['areasReservable']=$residentRepo->areas(true);}
            if($route==='bulletin')$payload['bulletins']=$residentRepo->adminBulletins();
            if($route==='notifications')$payload['notificationCampaigns']=$residentRepo->adminNotifications();
            if($route==='emergency')$payload['emergencyContacts']=$residentRepo->adminEmergencyContacts();
            if($route==='visitors'){$payload['invitations']=$residentRepo->adminInvitations();$payload['visits']=$residentRepo->adminVisits();}
            if($route==='access'){$payload['accessPoints']=$residentRepo->adminAccessPoints();$payload['cameraOptions']=$residentRepo->adminCameras();$payload['areaOptions']=$node->areaOptions();$payload['propertyOptions']=$node->structuralResourceOptions();$payload['deviceOptions']=$tech->devices(['page'=>1,'per_page'=>500])['items'];}
            if($route==='cameras'){$payload['cameras']=$residentRepo->adminCameras();$payload['accessOptions']=$residentRepo->adminAccessPoints();$payload['areaOptions']=$node->areaOptions();$payload['propertyOptions']=$node->structuralResourceOptions();$payload['deviceOptions']=$tech->devices(['page'=>1,'per_page'=>500])['items'];$mediaService=new MediaRelayService(Connection::pdo());$payload['mediaRelay']=$mediaService->settings();$payload['mediaEndpoints']=$mediaService->directEndpoints();}
            if($route==='updates'){
                $payload['repositoryStatus']=RepositoryClient::status();
                $payload['updateJobs']=(new UpdateManager(Connection::pdo()))->jobs();$payload['activeUpdateBatch']=(new UpdateBatchManager(Connection::pdo()))->latestActive();$payload['edgeAgents']=(new EdgeNodeService(Connection::pdo()))->agents();
                if(is_array($_SESSION['last_update_manifest']??null))$payload['updateAnalysis']=(new UpdateManager(Connection::pdo()))->analyzeManifest((array)$_SESSION['last_update_manifest']);
            }
        }
        if(InstallationMode::isEdge() && $route==='updates'){
            $payload['repositoryStatus']=RepositoryClient::status();
            $payload['updateJobs']=(new UpdateManager(Connection::pdo()))->jobs();$payload['activeUpdateBatch']=(new UpdateBatchManager(Connection::pdo()))->latestActive();
            if(is_array($_SESSION['last_update_manifest']??null))$payload['updateAnalysis']=(new UpdateManager(Connection::pdo()))->analyzeManifest((array)$_SESSION['last_update_manifest']);
        }
        render($view,$payload,'admin');
        exit;
    }
    if (InstallationMode::isEdge() && in_array($route,['dashboard','resident-app-settings','properties','structure-explorer','structure-context','area-context','structure-wizard','areas','vertical-transport','people','vehicles','access-profiles','visitors','reservations','bulletin','notifications','emergency','access','asset-bindings','cameras','intercom','internet','integrations','building-profile','site-topology','technology-inventory','connector-instances','lorawan','node-privacy'],true)) redirect('edge-dashboard');
    if (InstallationMode::isCentral() && in_array($route,['dashboard','resident-app-settings','properties','structure-explorer','structure-context','area-context','structure-wizard','areas','vertical-transport','people','vehicles','access-profiles','visitors','reservations','bulletin','notifications','emergency','access','asset-bindings','cameras','intercom','internet','integrations','updates','building-profile','site-topology','technology-inventory','connector-instances','lorawan','node-privacy'],true)) redirect('repository-dashboard');

    $repositoryViews = [
        'repository-dashboard' => ['repository/dashboard','Vista ejecutiva'],
        'repository-installations' => ['repository/installations','Instalaciones'],
        'repository-property' => ['repository/property-detail','Ficha de propiedad'],
        'repository-central-cleanup' => ['repository/central-cleanup','Limpieza controlada'],
        'repository-new-installation' => ['repository/new-installation','Nueva instalación'],
        'repository-installation' => ['repository/installation-detail','Ficha de instalación'],
        'repository-health' => ['repository/health','Salud y sincronización'],
        'repository-metrics' => ['repository/metrics','Métricas consolidadas'],
        'repository-releases' => ['repository/releases','Versiones y paquetes'],
        'repository-api-versions' => ['repository/api-versions','APIs de Property Hub'],
        'repository-vendor-apis' => ['repository/vendor-apis','APIs de fabricantes'],
        'repository-connectors' => ['repository/connectors','Conectores y versiones'],
        'repository-compatibility' => ['repository/compatibility','Matriz de compatibilidad'],
        'repository-tokens' => ['repository/tokens','Tokens de instalación'],
        'repository-privacy' => ['repository/privacy','Política de datos'],
        'repository-support' => ['repository/support','Soporte remoto'],
        'repository-lab' => ['repository/lab','Laboratorio y pruebas'],
        'repository-backup-vault' => ['repository/backup-vault','Backup Vault de instalaciones'],
    ];
    if (isset($repositoryViews[$route])) {
        require_permission('system.manage');
        if (!InstallationMode::isCentral()) throw new RuntimeException('Módulo disponible únicamente en la fuente oficial.');
        [$view,$title]=$repositoryViews[$route]; $service=new RepositoryService();
        $payload=['title'=>$title];
        if($route==='repository-dashboard')$payload['repository']=$service->dashboard((string)($_GET['property_code']??''));
        if($route==='repository-installations')$payload['properties']=$service->propertyPortfolio();
        if($route==='repository-central-cleanup'){$payload['cleanup']=(new CentralCleanupService(Connection::pdo()))->preview();$payload['cleanupResult']=$_SESSION['central_cleanup_result']??null;unset($_SESSION['central_cleanup_result']);}
        if($route==='repository-new-installation'){$payload['plans']=$service->installationPlans();$payload['provisioning']=$_SESSION['new_installation_plan']??null;unset($_SESSION['new_installation_plan']);}
        if($route==='repository-installation'){$id=max(1,(int)($_GET['id']??0));redirect('repository-property',['code'=>$service->propertyCodeForInstallation($id)]);}
        if($route==='repository-property'){$code=strtoupper(trim((string)($_GET['code']??'')));$payload['property']=$service->propertyDetail($code);$nodeId=(int)($payload['property']['node_id']??0);$payload['connectors']=$nodeId>0?$service->installationConnectors($nodeId):[];$payload['connectorCatalog']=$service->connectorCatalog();$payload['desiredConnectorState']=$nodeId>0?$service->installationDesiredConnectorState($nodeId):['items'=>[],'codes'=>[]];$payload['desiredConnectors']=(array)($payload['desiredConnectorState']['codes']??[]);$payload['backupVault']=$nodeId>0?(new BackupVaultService(Connection::pdo()))->installationSummary($nodeId):[];$payload['deletionState']=$service->propertyDeletionState($code);$payload['relinkToken']=$_SESSION['new_relink_token']??null;$payload['edgeEnrollment']=$_SESSION['new_edge_enrollment']??null;unset($_SESSION['new_relink_token'],$_SESSION['new_edge_enrollment']);}
        if($route==='repository-health')$payload['health']=$service->healthOverview();
        if($route==='repository-metrics')$payload['metrics']=$service->consolidatedMetrics();
        if($route==='repository-releases'){
            $syncer=new RepositorySynchronizer(Connection::pdo());
            try{$payload['officialCoreSync']=$syncer->syncOfficialComponents(is_int(current_user()['id']??null)?(int)current_user()['id']:null);}
            catch(Throwable $syncError){$payload['officialCoreSync']=['ok'=>false,'message'=>$syncError->getMessage()];ErrorReporter::report($syncError,['operation'=>'repository_releases_auto_sync']);}
            $payload['releases']=$service->releases();$payload['components']=$service->components();$payload['officialCoreStatus']=$syncer->status();
        }
        if($route==='repository-api-versions')$payload['apiVersions']=$service->apiVersions();
        if($route==='repository-vendor-apis')$payload['vendorApis']=$service->vendorApis();
        if($route==='repository-connectors')$payload['connectors']=$service->connectorCatalog();
        if($route==='repository-compatibility')$payload['matrix']=$service->compatibilityMatrix();
        if($route==='repository-tokens'){$payload['tokens']=$service->tokens();$payload['newToken']=$_SESSION['new_repository_token']??null;unset($_SESSION['new_repository_token']);}
        if($route==='repository-privacy')$payload['privacy']=$service->privacySettings();
        if($route==='repository-support')$payload['sessions']=$service->supportSessions();
        if($route==='repository-backup-vault'){$payload['vault']=(new BackupVaultService(Connection::pdo()))->overview();$payload['recoveryToken']=$_SESSION['vault_recovery_token']??null;unset($_SESSION['vault_recovery_token']);}
        render($view,$payload,'admin'); exit;
    }

    if ($route === 'camera-live-config') {
        header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store, no-cache, must-revalidate');
        // API de reproducción: nunca redirigir a login ni entregar configuración a una
        // sesión anónima. La autorización de cámara se resuelve además en cameraForViewing().
        if(!current_user()){http_response_code(401);echo json_encode(['ok'=>false,'available'=>false,'message'=>'Sesión requerida.'],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);exit;}
        if(!can('cameras.view')){http_response_code(403);echo json_encode(['ok'=>false,'available'=>false,'message'=>'No autorizado para ver cámaras.'],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);exit;}
        $id=max(1,(int)($_GET['id']??0));$repo=new ResidentRepository();$camera=$repo->cameraForViewing($id);
        try{(new ResidentSecurityService())->audit('camera.view','camera',$id,['source'=>'live_config'],'success',null,(int)($_SESSION['resident_active_property_id']??0)?:null);}catch(Throwable $ignore){}
        if(!$camera){
            ErrorReporter::runtimeLog('camera_live_config_result',['camera_id'=>$id,'user_id'=>(int)(current_user()['id']??0),'user_role'=>(string)(current_user()['role']??''),'portal'=>(string)(current_user()['portal']??''),'authorized'=>false,'available'=>false,'reason'=>'camera_not_available']);
            http_response_code(404);echo json_encode(['ok'=>false,'available'=>false,'message'=>'Cámara no disponible.'],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);exit;
        }
        try{
            $cfg=(new MediaRelayService(Connection::pdo()))->viewerConfig($camera);
            ErrorReporter::runtimeLog('camera_live_config_result',['camera_id'=>$id,'user_id'=>(int)(current_user()['id']??0),'user_role'=>(string)(current_user()['role']??''),'portal'=>(string)(current_user()['portal']??''),'authorized'=>true,'available'=>!empty($cfg['available']),'has_hls'=>!empty($cfg['hls_url']),'has_whep'=>!empty($cfg['whep_url']),'mode'=>(string)($cfg['mode']??''),'live_status'=>(string)($camera['live_status']??'')]);
            if(!empty($cfg['available'])&&!empty($cfg['hls_url'])){
                $cfg['whep_user']=(string)($cfg['user']??'');$cfg['whep_pass']=(string)($cfg['pass']??'');
                $cfg['hls_url']=url('camera-hls',['id'=>$id,'asset'=>'index.m3u8']);
                $cfg['hls_page_url']='';$cfg['hls_auth_mode']='node_proxy';$cfg['hls_user']='';$cfg['hls_pass']='';
            }
            echo json_encode($cfg,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        }
        catch(Throwable $e){ErrorReporter::report($e,['operation'=>'camera_live_config','camera_id'=>$id]);http_response_code(503);echo json_encode(['ok'=>false,'available'=>false,'message'=>$e->getMessage()],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);}exit;
    }

    if ($route === 'camera-hls') {
        header('Cache-Control: private, no-store, no-cache, must-revalidate');header('Pragma: no-cache');
        if(!current_user()){http_response_code(401);header('Content-Type: text/plain; charset=utf-8');exit('Sesión requerida.');}
        if(!can('cameras.view')){http_response_code(403);header('Content-Type: text/plain; charset=utf-8');exit('No autorizado.');}
        $id=max(1,(int)($_GET['id']??0));$repo=new ResidentRepository();$camera=$repo->cameraForViewing($id);
        if(!$camera){http_response_code(404);header('Content-Type: text/plain; charset=utf-8');exit('Cámara no disponible.');}
        $asset=trim((string)($_GET['asset']??'index.m3u8'));if($asset==='')$asset='index.m3u8';
        try{
            $result=(new MediaRelayService(Connection::pdo()))->proxyHlsAsset($camera,$asset,(string)($_SERVER['HTTP_RANGE']??''));
            $body=(string)$result['body'];$type=strtolower((string)($result['content_type']??''));
            $isPlaylist=str_contains($type,'mpegurl')||str_ends_with(strtolower((string)$result['asset']),'.m3u8')||str_starts_with(ltrim($body),'#EXTM3U');
            if($isPlaylist){
                $livePath=trim((string)($camera['live_path']??''),'/');
                $normaliseRef=static function(string $ref) use($livePath): string {
                    $ref=html_entity_decode(trim($ref),ENT_QUOTES|ENT_HTML5,'UTF-8');if($ref==='')return '';
                    if(preg_match('#^[a-z][a-z0-9+.-]*://#i',$ref)){
                        $parts=parse_url($ref);$candidate=ltrim((string)($parts['path']??''),'/');
                        if($livePath!==''&&str_starts_with($candidate,$livePath.'/'))$candidate=substr($candidate,strlen($livePath)+1);
                        if(isset($parts['query'])&&$parts['query']!=='')$candidate.='?'.$parts['query'];
                        return $candidate;
                    }
                    $candidate=ltrim($ref,'/');if($livePath!==''&&str_starts_with($candidate,$livePath.'/'))$candidate=substr($candidate,strlen($livePath)+1);return $candidate;
                };
                $proxyRef=static function(string $ref) use($id,$normaliseRef): string {
                    $assetRef=$normaliseRef($ref);return $assetRef===''?$ref:url('camera-hls',['id'=>$id,'asset'=>$assetRef]);
                };
                $lines=preg_split('/\r\n|\n|\r/',$body)?:[];$out=[];
                foreach($lines as $line){
                    if(str_starts_with($line,'#')){
                        $line=preg_replace_callback('/URI="([^"]+)"/',static fn(array $m): string=>'URI="'.$proxyRef((string)$m[1]).'"',$line)??$line;
                        $out[]=$line;continue;
                    }
                    $trim=trim($line);$out[]=$trim===''?$line:$proxyRef($trim);
                }
                header('Content-Type: application/vnd.apple.mpegurl');echo implode("\n",$out);exit;
            }
            header('Content-Type: '.(((string)$result['content_type']??'')!=='')?(string)$result['content_type']:'application/octet-stream');
            header('Content-Length: '.strlen($body));echo $body;exit;
        }catch(Throwable $e){
            ErrorReporter::report($e,['operation'=>'camera_hls_proxy','camera_id'=>$id,'asset'=>$asset]);http_response_code(502);header('Content-Type: text/plain; charset=utf-8');echo 'Video HLS no disponible: '.$e->getMessage();exit;
        }
    }

    if ($route === 'camera-snapshot') {
        require_permission('cameras.view');$id=max(1,(int)($_GET['id']??0));$repo=new ResidentRepository();$camera=$repo->cameraForViewing($id);if(!$camera){http_response_code(404);exit('Cámara no disponible.');}
        $rel=trim((string)($camera['snapshot_path']??''));$path=$rel!==''?(str_starts_with($rel,'storage/')?base_path($rel):$rel):'';if($path===''||!is_file($path)){http_response_code(404);header('Cache-Control: no-store');exit('Snapshot todavía no disponible.');}
        $mime=(string)($camera['snapshot_mime']??'image/jpeg');if(!in_array($mime,['image/jpeg','image/png','image/webp'],true))$mime='image/jpeg';header('Content-Type: '.$mime);header('Content-Length: '.filesize($path));header('Cache-Control: no-store, no-cache, must-revalidate');header('Pragma: no-cache');readfile($path);exit;
    }


    if ($route === 'camera-associated-access') {
        header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store, no-cache, must-revalidate');
        if(!current_user()){http_response_code(401);echo json_encode(['ok'=>false,'message'=>'Sesión requerida.']);exit;}
        if(!can('cameras.view')){http_response_code(403);echo json_encode(['ok'=>false,'message'=>'No autorizado para ver cámaras.']);exit;}
        $cameraId=max(1,(int)($_GET['camera_id']??0));$repo=new ResidentRepository();$camera=$repo->cameraForViewing($cameraId);if(!$camera){http_response_code(404);echo json_encode(['ok'=>false,'message'=>'Cámara no disponible.']);exit;}
        $u=current_user()??[];$restricted=strtoupper((string)($u['role']??''))==='RESIDENT'||(($u['portal']??'')==='resident');$canOperate=can('access.operate')||can('access.operate.allowed');$points=$canOperate?$repo->cameraViewerAccessPoints($cameraId,$restricted):[];
        $safe=[];foreach($points as $point)$safe[]=['id'=>(int)$point['id'],'name'=>(string)($point['display_name']??$point['name']??'Acceso'),'type'=>(string)($point['access_type']??'door'),'area'=>(string)($point['area']??$point['property_name']??''),'remote_ready'=>!empty($point['remote_ready']),'can_open'=>!empty($point['immediate_open_allowed']),'reason'=>(string)($point['viewer_reason']??''),'policy'=>(string)($point['remote_policy']??'disabled')];
        echo json_encode(['ok'=>true,'camera_id'=>$cameraId,'can_operate'=>$canOperate,'access_points'=>$safe],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;
    }

    if ($route === 'camera-access-open' && is_post()) {
        header('Content-Type: application/json; charset=utf-8');
        if(!current_user()){http_response_code(401);echo json_encode(['ok'=>false,'message'=>'Sesión requerida.']);exit;}verify_csrf();
        try{
            if(!can('cameras.view'))throw new RuntimeException('No está autorizado para ver esta cámara.');if(!(can('access.operate')||can('access.operate.allowed')))throw new RuntimeException('No está autorizado para operar accesos.');
            $repo=new ResidentRepository();$cameraId=post_int('camera_id');$accessId=post_int('access_point_id');$camera=$repo->cameraForViewing($cameraId);if(!$camera)throw new RuntimeException('Cámara no disponible.');if(!$repo->cameraHasAccessLink($cameraId,$accessId))throw new RuntimeException('La puerta no está asociada a esta cámara.');
            $u=current_user()??[];$restricted=strtoupper((string)($u['role']??''))==='RESIDENT'||(($u['portal']??'')==='resident');$point=null;foreach($repo->cameraViewerAccessPoints($cameraId,$restricted) as $candidate)if((int)($candidate['id']??0)===$accessId){$point=$candidate;break;}if(!is_array($point))throw new RuntimeException('Este acceso no está autorizado en el contexto actual.');if(empty($point['immediate_open_allowed']))throw new RuntimeException((string)($point['viewer_reason']??'La apertura remota no está disponible.'));
            $propertyId=$restricted?(int)(($repo->currentContext()['property_id']??0)):(int)($camera['property_id']??$camera['area_property_id']??0);$source=$restricted?'property_app':'admin';$r=(new ZkBioOperationalService(Connection::pdo()))->requestRemoteOpen($accessId,$repo->localUserId(),$restricted?$repo->personId():null,$propertyId?:null,$source);
            try{(new ResidentSecurityService())->audit('camera.access.open','camera',$cameraId,['access_point_id'=>$accessId,'access_name'=>(string)($point['display_name']??$point['name']??''),'correlation_uuid'=>(string)($r['correlation_uuid']??''),'source'=>'camera_viewer'],'success',null,$propertyId?:null);}catch(Throwable $ignore){}
            echo json_encode($r+['ok'=>true,'camera_id'=>$cameraId,'access_point_id'=>$accessId],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;
        }catch(Throwable $e){ErrorReporter::report($e,['operation'=>'camera_access_open','camera_id'=>post_int('camera_id'),'access_point_id'=>post_int('access_point_id')]);http_response_code(422);echo json_encode(['ok'=>false,'message'=>$e->getMessage()],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
    }

    if ($route === 'camera-access-command-status') {
        header('Content-Type: application/json; charset=utf-8');if(!current_user()){http_response_code(401);echo json_encode(['ok'=>false,'message'=>'Sesión requerida.']);exit;}if(!(can('access.operate')||can('access.operate.allowed'))){http_response_code(403);echo json_encode(['ok'=>false,'message'=>'No autorizado.']);exit;}
        try{$repo=new ResidentRepository();$u=current_user()??[];$restricted=strtoupper((string)($u['role']??''))==='RESIDENT'||(($u['portal']??'')==='resident');$correlation=trim((string)($_GET['correlation']??$_GET['correlation_uuid']??''));if($correlation==='')throw new InvalidArgumentException('Falta correlación.');$r=(new ZkBioOperationalService(Connection::pdo()))->accessCommandStatus($correlation,$repo->localUserId(),$restricted?$repo->personId():null);echo json_encode($r+['ok'=>true],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}catch(Throwable $e){http_response_code(404);echo json_encode(['ok'=>false,'message'=>$e->getMessage()],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
    }


    if ($route === 'resident-access-open' && is_post()) {
        require_permission('access.view');verify_csrf();header('Content-Type: application/json; charset=utf-8');try{$repo=new ResidentRepository();$id=post_int('access_point_id');$point=$repo->accessPointForOperation($id);if(!$point)throw new RuntimeException('Este acceso no está autorizado para la unidad/perfil actual.');if(empty($point['remote_ready']))throw new RuntimeException('La puerta todavía no tiene una ruta remota operativa.');$ctx=$repo->currentContext();$r=(new ZkBioOperationalService(Connection::pdo()))->requestRemoteOpen($id,$repo->localUserId(),$repo->personId(),(int)($ctx['property_id']??0),'property_app');echo json_encode($r+['ok'=>true],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}catch(Throwable $e){ErrorReporter::report($e,['operation'=>'resident_access_open']);http_response_code(422);echo json_encode(['ok'=>false,'message'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);exit;}
    }
    if ($route === 'resident-access-command-status') {
        require_permission('access.view');header('Content-Type: application/json; charset=utf-8');try{$c=trim((string)($_GET['correlation']??''));if($c==='')throw new InvalidArgumentException('Falta correlación.');$repo=new ResidentRepository();$r=(new ZkBioOperationalService(Connection::pdo()))->accessCommandStatus($c,$repo->localUserId(),$repo->personId());echo json_encode($r+['ok'=>true],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}catch(Throwable $e){http_response_code(404);echo json_encode(['ok'=>false,'message'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);exit;}
    }
    if ($route === 'resident-vehicle-create' && is_post()) {
        require_permission('resident.app');verify_csrf();$repo=new ResidentRepository();$ctx=$repo->currentContext();try{$svc=new ZkBioOperationalService(Connection::pdo());$id=$svc->saveVehicle($_POST,$repo->personId(),(int)($ctx['property_id']??0));$msg='Vehículo registrado.';if(LocalIntegrationProvisioning::nodeEnabled('zkteco',Connection::pdo())){try{$svc->queueVehicleProvision($id,$repo->localUserId());$msg.=' Se está sincronizando con ZKBio.';}catch(Throwable $e){$msg.=' ZKBio pendiente: '.$e->getMessage();}}flash('success',$msg);}catch(Throwable $e){flash('danger',$e->getMessage());}redirect('resident-vehicles');
    }

    $residentViews = [
        'resident-home' => ['resident/home','dashboard.view','Inicio'],
        'resident-visitors' => ['resident/visitors','visitors.view','Mis visitas'],
        'resident-reservations' => ['resident/reservations','reservations.view','Reservas'],
        'resident-bulletin' => ['resident/bulletin','bulletin.view','Cartelera'],
        'resident-notifications' => ['resident/notifications','notifications.view','Notificaciones'],
        'resident-emergency' => ['resident/emergency','emergency.view','Emergencias'],
        'resident-access' => ['resident/access','access.view','Accesos'],
        'resident-cameras' => ['resident/cameras','cameras.view','Cámaras'],
        'resident-profile' => ['resident/profile','resident.app','Mi perfil'],
        'resident-vehicles' => ['resident/vehicles','resident.app','Vehículos'],
    ];
    if (isset($residentViews[$route])) {
        [$view,$permission,$title] = $residentViews[$route];
        require_permission($permission);
        $residentRepo=new ResidentRepository();
        $home=$residentRepo->homeData();
        $payload=[
            'title'=>$title,'month'=>$month,'residentRepo'=>$residentRepo,
            'residentContext'=>$home['context'],'residentMemberships'=>$residentRepo->memberships(),
            'residentSettings'=>$home['settings'],'residentProfile'=>$home['profile'],
            'unread'=>$residentRepo->unreadNotificationCount(),'homeData'=>$home,
            'areas'=>$residentRepo->areas(true),'reservations'=>$residentRepo->reservations(),
            'invitations'=>$residentRepo->invitations(),'bulletins'=>$residentRepo->bulletins(),
            'notifications'=>$residentRepo->notifications(),'emergencyContacts'=>$residentRepo->emergencyContacts(),
            'cameras'=>$residentRepo->cameras(),'accessPoints'=>$residentRepo->accessPoints(),
        ];
        if($route==='resident-vehicles'){try{$ctx=$home['context'];$payload['vehicles']=(new ZkBioOperationalService(Connection::pdo()))->vehicles($residentRepo->personId(),(int)($ctx['property_id']??0));}catch(Throwable $e){$payload['vehicles']=[];ErrorReporter::report($e,['operation'=>'resident_vehicles_payload']);}}
        $payload['calendar']=month_calendar($month,$payload['reservations']);
        try{(new ResidentSecurityService())->audit('page.view','route',null,['route'=>$route,'title'=>$title],'success',null,(int)($home['context']['property_id']??0)?:null);}catch(Throwable $ignore){}
        render($view,$payload,'resident');
        exit;
    }

    http_response_code(404);
    render('errors/404', ['title'=>'Página no encontrada'], portal_layout());
} catch (Throwable $e) {
    $incident=ErrorReporter::report($e,'front-controller');
    http_response_code(500);
    $showDiagnostics=(bool)config('app.diagnostics',true) || (current_user() && can('system.manage'));
    $details=$showDiagnostics ? ErrorReporter::publicDetails($e) : [];
    try {
        render('errors/500',[
            'title'=>'No se pudo cargar la página',
            'incident'=>$incident,
            'requestId'=>ErrorReporter::requestId(),
            'details'=>$details,
            'logPath'=>'storage/logs/property-hub-'.date('Y-m-d').'.log',
        ],current_user()?portal_layout():false);
    } catch (Throwable $renderError) {
        ErrorReporter::report($renderError,'error-view');
        echo '<!doctype html><meta charset="utf-8"><title>Error</title><h1>No se pudo cargar la página</h1><p>Incidente: '.e($incident).'</p><p>Request ID: '.e(ErrorReporter::requestId()).'</p>';
    }
}
