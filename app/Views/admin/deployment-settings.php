<?php
$isCentral=InstallationMode::isCentral();
$isNode=InstallationMode::isNode();
$isEdge=InstallationMode::isEdge();
$deployment=null;$registry=null;$centralConnection=[];$parentConnection=[];
try{$stmt=Connection::pdo()->prepare('SELECT * FROM bh_deployment_settings WHERE installation_uuid=? LIMIT 1');$stmt->execute([(string)config('deployment.installation_uuid')]);$deployment=$stmt->fetch();}catch(Throwable $e){}
if(!$isCentral){
    try{$registry=new ConnectionRegistry();$registry->importLegacyIfNeeded();if(!$isEdge)$centralConnection=$registry->central();if($isEdge)$parentConnection=$registry->parentNode();}catch(Throwable $e){}
}
$centralEndpoint=(string)($centralConnection['endpoint_url']??'');
$centralStatus=(string)($centralConnection['status']??($deployment['repository_status']??'not_configured'));
$centralHasCredential=trim((string)($centralConnection['credential']??''))!== '';
$nodeEndpoint=(string)($parentConnection['endpoint_url']??'');
$parentCode=(string)($parentConnection['remote_code']??'');
$schedulerInfo=[];if(!$isCentral){try{$schedulerInfo=(new BackupManager(Connection::pdo()))->schedulerInfo();}catch(Throwable $e){$schedulerInfo=[];}}$schedulerLast=(array)($schedulerInfo['last_run']??[]);
?>
<div class="row g-3">
  <div class="col-xl-7">
    <div class="card">
      <div class="card-header"><strong>Identidad de esta instalación</strong></div>
      <div class="card-body"><dl class="row mb-0">
        <dt class="col-sm-4">Rol</dt><dd class="col-sm-8"><span class="badge text-bg-primary"><?=e(InstallationMode::label())?></span></dd>
        <dt class="col-sm-4">Código</dt><dd class="col-sm-8"><code><?=e(config('app.instance_code'))?></code></dd>
        <dt class="col-sm-4">UUID</dt><dd class="col-sm-8 text-break"><code><?=e(config('deployment.installation_uuid'))?></code></dd>
        <dt class="col-sm-4">Nombre</dt><dd class="col-sm-8"><?=e(config('app.instance_name'))?></dd>
        <dt class="col-sm-4">Versión</dt><dd class="col-sm-8">Core <?=e(config('app.version'))?> · API v1 · UI <?=e(config('app.ui_standard_version'))?></dd>
        <?php if(!$isCentral):?><dt class="col-sm-4">Estado Central</dt><dd class="col-sm-8"><?=e($centralStatus)?></dd><?php endif;?>
        <?php if($isEdge):?>
          <dt class="col-sm-4">Propiedad padre</dt><dd class="col-sm-8"><code><?=e($parentCode!==''?$parentCode:'Sin emparejar')?></code></dd>
          <dt class="col-sm-4">Property Node</dt><dd class="col-sm-8 text-break"><?=e($nodeEndpoint!==''?$nodeEndpoint:'Sin emparejar')?></dd>
        <?php endif;?>
      </dl></div>
    </div>
    <?php if($isCentral):?>
      <div class="card mt-3"><div class="card-header"><strong>Endpoints del repositorio</strong></div><div class="card-body">
        <code class="d-block mb-2"><?=e(rtrim((string)config('deployment.repository_public_url'),'/').'/api/repository/v1/ping.php')?></code>
        <code class="d-block mb-2"><?=e(rtrim((string)config('deployment.repository_public_url'),'/').'/api/repository/v1/register.php')?></code>
        <code class="d-block mb-2"><?=e(rtrim((string)config('deployment.repository_public_url'),'/').'/api/repository/v1/manifest.php')?></code>
        <code class="d-block"><?=e(rtrim((string)config('deployment.repository_public_url'),'/').'/api/repository/v1/catalog.php')?></code>
      </div></div>
    <?php elseif($isEdge):?>
      <div class="card mt-3"><div class="card-header"><strong>Arquitectura Edge</strong></div><div class="card-body">
        <p class="mb-2"><strong>Un solo emparejamiento manual:</strong> este Agent se vincula al Property Node con URL + PHEDGE.</p>
        <ul class="mb-3"><li><strong>Property Node:</strong> administra la propiedad, recibe inventario local y entrega al Agent la topología de Central.</li><li><strong>Property Hub Central:</strong> actualizaciones, salud y Backup Vault. Su ruta y credencial son administradas automáticamente por el Node.</li></ul>
        <a class="btn btn-outline-primary" href="<?=url('edge-dashboard')?>"><i class="bi bi-cpu me-2"></i>Abrir consola técnica del Agent</a>
      </div></div>
    <?php endif;?>
  </div>
  <div class="col-xl-5">
    <?php if($isEdge):?>
      <div class="card"><div class="card-header"><strong>Vínculos del Agent</strong></div><div class="card-body">
        <div class="alert alert-info small"><strong>No configure Central manualmente.</strong> El Agent recibe y mantiene esa conexión desde <strong><?=e($parentCode!==''?$parentCode:'su Property Node')?></strong>.</div>
        <dl class="row small mb-3">
          <dt class="col-5">Property Node</dt><dd class="col-7 text-break"><?=e($nodeEndpoint!==''?$nodeEndpoint:'Pendiente')?></dd>
          <dt class="col-5">Propiedad padre</dt><dd class="col-7"><code><?=e($parentCode!==''?$parentCode:'Pendiente')?></code></dd>
          <dt class="col-5">Central</dt><dd class="col-7 text-break"><?=e($centralEndpoint!==''?$centralEndpoint:'Se recibe automáticamente del Node')?></dd>
          <dt class="col-5">Origen Central</dt><dd class="col-7">Property Node</dd>
        </dl>
        <a class="btn btn-primary w-100" href="<?=url('edge-dashboard')?>"><i class="bi bi-link-45deg me-2"></i>Emparejar / revisar Agent</a>
        <form method="post" action="<?=url('edge-central-repair')?>" class="mt-2"><?=csrf_field()?><button class="btn btn-outline-secondary w-100"><i class="bi bi-arrow-repeat me-2"></i>Refrescar distribución desde el Node</button></form>
        <form method="post" action="<?=url('deployment-heartbeat')?>" class="mt-2"><?=csrf_field()?><button class="btn btn-outline-success w-100"><i class="bi bi-heart-pulse me-2"></i>Probar heartbeat Central</button></form>
      </div></div>
    <?php elseif(!$isCentral):?>
      <div class="card"><div class="card-header"><strong>Conexión del Property Node con Property Hub Central</strong></div><div class="card-body">
        <div class="alert alert-info small"><strong>Una sola conexión.</strong> Este Property Node usa Central para actualizaciones, heartbeat y Backup Vault. Los Edge Agents asociados reciben de este Node el manifiesto firmado, los paquetes cacheados y la configuración de integraciones; no usan credenciales directas de Central.</div>
        <form method="post" action="<?=url('deployment-save')?>" class="row g-3"><?=csrf_field()?>
          <div class="col-12"><label class="form-label">URL de Property Hub Central</label><input class="form-control" name="central_repository_url" value="<?=e($centralEndpoint)?>" placeholder="https://2cam.services/property-hub/public"></div>
          <div class="col-12"><label class="form-label">Canal de actualizaciones</label><select class="form-select" name="update_channel"><?php foreach(['development','beta','stable','pinned'] as $channel):?><option <?=config('deployment.update_channel')===$channel?'selected':''?>><?=e($channel)?></option><?php endforeach;?></select></div>
          <div class="col-12"><label><input type="checkbox" name="allow_offline" <?=config('deployment.allow_offline')?'checked':''?>> Permitir operación local si Central no está disponible</label></div>
          <div class="col-12"><button class="btn btn-outline-primary w-100">Guardar conexión</button></div>
        </form>
        <div class="card bg-body-tertiary border-0 mb-3"><div class="card-body py-2 small"><div class="d-flex justify-content-between"><strong>Heartbeat automático</strong><span class="badge text-bg-<?=!empty($schedulerInfo['installed'])?'success':'warning'?>"><?=!empty($schedulerInfo['installed'])?'Scheduler instalado':'Scheduler pendiente'?></span></div><div class="text-body-secondary mt-1">Central considera el Node conectado sólo si recibe heartbeat reciente. El botón manual envía <strong>un solo heartbeat</strong>; no sustituye el Scheduler.</div><?php if(!empty($schedulerLast['started_at'])):?><div class="mt-1">Última ejecución del Scheduler: <strong><?=e((string)$schedulerLast['started_at'])?></strong> · Central: <strong><?=array_key_exists('central_heartbeat_online',$schedulerLast)?(!empty($schedulerLast['central_heartbeat_online'])?'OK':'FALLÓ'):'sin dato'?></strong></div><?php endif;?><?php if(empty($schedulerInfo['installed'])):?><code class="d-block mt-2">tools\install-scheduler-task.bat</code><?php endif;?></div></div>
        <div class="d-grid gap-2 mt-2">
          <form method="post" action="<?=url('deployment-test-repository')?>"><?=csrf_field()?><button class="btn btn-outline-secondary w-100"><i class="bi bi-plug me-2"></i>Probar Central</button></form>
          <?php if($centralHasCredential):?><form method="post" action="<?=url('deployment-heartbeat')?>"><?=csrf_field()?><button class="btn btn-outline-success w-100"><i class="bi bi-heart-pulse me-2"></i>Enviar un heartbeat ahora</button></form><?php endif;?>
          <a class="btn btn-outline-primary" href="<?=url('updates')?>"><i class="bi bi-arrow-repeat me-2"></i>Ir a actualizaciones</a>
        </div>
        <details class="mt-3"><summary class="small text-body-secondary">Registro / reconexión con Central (solo alta inicial o recuperación)</summary>
          <div class="alert alert-warning small mt-3 mb-2">No genere un token nuevo para el uso diario. Use esta sección únicamente si Central indica que este Node no está registrado o sus credenciales fueron revocadas.</div>
          <form method="post" action="<?=url('deployment-register')?>" class="row g-3"><?=csrf_field()?>
            <input type="hidden" name="central_repository_url" value="<?=e($centralEndpoint)?>">
            <div class="col-12"><label class="form-label">Token BHR del Property Node</label><input class="form-control" name="registration_token" autocomplete="off" required></div>
            <div class="col-12"><button class="btn btn-primary w-100">Registrar / reconectar este Node</button></div>
          </form>
        </details>
      </div></div>
    <?php else:?>
      <div class="card"><div class="card-header"><strong>Seguridad del repositorio</strong></div><div class="card-body"><p><i class="bi bi-key me-2"></i>Clave privada RSA almacenada fuera de <code>public</code>.</p><p><i class="bi bi-shield-check me-2"></i>Manifiestos firmados con RSA-SHA256.</p><p><i class="bi bi-file-earmark-lock me-2"></i>Paquetes descargados únicamente por la API autenticada.</p><div class="d-grid gap-2"><a class="btn btn-primary" href="<?=url('repository-tokens')?>">Administrar tokens</a><a class="btn btn-outline-primary" href="<?=url('repository-releases')?>">Publicar versión</a></div></div></div>
    <?php endif;?>
  </div>
</div>
