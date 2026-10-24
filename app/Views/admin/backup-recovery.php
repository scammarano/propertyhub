<?php
$settings=$backupSettings??[];$runs=$backupRuns??[];$preview=$restorePreview??null;$scheduler=$schedulerInfo??['installed'=>false];$isCentral=InstallationMode::isCentral();$isEdge=InstallationMode::isEdge();
$fmt=function($bytes){$b=(int)$bytes;if($b<=0)return '—';$u=['B','KB','MB','GB'];$i=0;while($b>=1024&&$i<count($u)-1){$b/=1024;$i++;}return number_format($b,$i>1?1:0,',','.').' '.$u[$i];};
$typeLabels=['manual'=>'Manual','scheduled'=>'Automático','pre_update'=>'Pre-actualización','pre_restore'=>'Pre-restauración'];
$statusLabels=['completed'=>'Completo','warning'=>'Con avisos','failed'=>'Falló','running'=>'En proceso','deleted'=>'Eliminado'];
$newKey=$newRecoveryKey??null;
$osFamily=PHP_OS_FAMILY;
$lastRun=$runs[0]??null;
?>
<?php if($newKey):?>
<div class="alert alert-warning border-warning shadow-sm"><div class="d-flex gap-3"><i class="bi bi-key-fill fs-3"></i><div class="flex-grow-1"><strong class="d-block mb-1">Clave de recuperación — guárdala ahora fuera de este servidor</strong><p class="mb-2">Los respaldos automáticos quedan cifrados con esta clave. Si se pierde el servidor y también esta clave, el respaldo no podrá descifrarse.</p><code class="d-block bg-dark text-white p-3 rounded user-select-all text-break fs-6"><?=e($newKey)?></code><small class="d-block mt-2">No volveremos a mostrar esta clave existente. Puedes rotarla, pero los respaldos anteriores seguirán necesitando la clave anterior.</small></div></div></div>
<?php endif;?>

<div class="row g-3 mb-3">
  <div class="col-6 col-xl-3"><div class="card h-100 shadow-sm"><div class="card-body"><small class="text-body-secondary">Último respaldo</small><div class="fw-semibold mt-1"><?=e(format_datetime_es((string)($settings['last_backup_at']??''))?:'Nunca')?></div><small class="text-body-secondary"><?=e((string)($settings['last_message']??'Sin ejecuciones todavía'))?></small></div></div></div>
  <div class="col-6 col-xl-3"><div class="card h-100 shadow-sm"><div class="card-body"><small class="text-body-secondary">Versión actual</small><div class="fw-semibold mt-1">Property Hub <?=e((string)config('app.version'))?></div><small class="text-body-secondary"><?=e(InstallationMode::label())?></small></div></div></div>
  <div class="col-6 col-xl-3"><div class="card h-100 shadow-sm"><div class="card-body"><small class="text-body-secondary">Política</small><div class="fw-semibold mt-1"><?=e(($settings['schedule_frequency']??'daily').' · '.substr((string)($settings['schedule_time']??'02:00:00'),0,5))?></div><small class="text-body-secondary">Retención <?=e((string)($settings['retention_daily']??7))?> / <?=e((string)($settings['retention_weekly']??4))?> / <?=e((string)($settings['retention_monthly']??6))?></small></div></div></div>
  <div class="col-6 col-xl-3"><div class="card h-100 shadow-sm"><div class="card-body"><small class="text-body-secondary">Automatización del servidor</small><div class="fw-semibold mt-1 text-<?=!empty($scheduler['installed'])?'success':'warning'?>"><?=!empty($scheduler['installed'])?'Instalada':'Pendiente'?></div><small class="text-body-secondary"><?=e($osFamily==='Windows'?'Tarea programada de Windows':'Runner / cron del servidor')?></small></div></div></div>
</div>

<?php if(!empty($settings['enabled'])&&empty($scheduler['installed'])):?>
<div class="alert alert-warning py-2"><i class="bi bi-exclamation-triangle me-2"></i>La política automática está habilitada, pero el runner del servidor todavía figura como <strong>pendiente</strong>. Los respaldos manuales funcionan; los automáticos no se ejecutarán solos hasta instalar el runner.</div>
<?php endif;?>

<div class="row g-3 mb-3">
  <div class="col-xl-7">
    <div class="card shadow-sm h-100"><div class="card-header"><strong><i class="bi bi-database-down me-2"></i>Acciones de respaldo</strong></div><div class="card-body">
      <p class="mb-3">El respaldo completo conserva la base de datos, configuración cifrada, identidad, usuarios, estructura, personas, mapeos y archivos persistentes. <strong>No incluye</strong> logs, cachés ni artefactos temporales del actualizador.<?= $isCentral?' En Central sí incluye el Backup Vault remoto y los paquetes del repositorio oficial.':'' ?></p>
      <?php if($isEdge):?><div class="alert alert-info py-2"><strong>Edge Agent:</strong> la estrategia recomendada es 3 copias: local en el Agent + copia cifrada en su Property Node + copia cifrada en Property Hub Central.</div><?php endif;?>
      <?php if(empty($recoveryKeyExists)):?>
        <div class="alert alert-warning">Antes del primer respaldo debes generar una clave de recuperación.</div>
        <form method="post" action="<?=url('backup-key-generate')?>"><?=csrf_field()?><button class="btn btn-warning"><i class="bi bi-key me-2"></i>Generar clave de recuperación</button></form>
      <?php else:?>
        <div class="d-flex flex-wrap gap-2">
          <form method="post" action="<?=url('backup-create')?>"><?=csrf_field()?><input type="hidden" name="backup_type" value="manual"><button class="btn btn-primary"><i class="bi bi-download me-2"></i>Generar respaldo completo</button></form>
          <form method="post" action="<?=url('backup-create')?>"><?=csrf_field()?><input type="hidden" name="backup_type" value="pre_update"><button class="btn btn-outline-primary"><i class="bi bi-arrow-repeat me-2"></i>Crear punto pre-actualización</button></form>
        </div>
      <?php endif;?>
      <div class="row g-2 mt-3 small">
        <div class="col-md-4"><div class="border rounded p-2 h-100"><i class="bi bi-shield-lock text-primary me-1"></i><strong>Cifrado</strong><div class="text-body-secondary">AES-256-GCM + clave externa</div></div></div>
        <div class="col-md-4"><div class="border rounded p-2 h-100"><i class="bi bi-check2-circle text-success me-1"></i><strong>Integridad</strong><div class="text-body-secondary">SHA-256 por archivo y del contenedor</div></div></div>
        <div class="col-md-4"><div class="border rounded p-2 h-100"><i class="bi bi-arrow-counterclockwise text-primary me-1"></i><strong>Recuperación</strong><div class="text-body-secondary">Misma instalación o copia segura</div></div></div>
      </div>
    </div></div>
  </div>
  <div class="col-xl-5">
    <div class="card shadow-sm h-100"><div class="card-header d-flex justify-content-between align-items-center"><strong><i class="bi bi-key me-2"></i>Clave y política automática</strong><span class="badge text-bg-<?=!empty($recoveryKeyExists)?'success':'warning'?>"><?=!empty($recoveryKeyExists)?'Clave configurada':'Sin clave'?></span></div><div class="card-body">
      <p class="small text-body-secondary">La clave no se envía al Central ni se guarda dentro del <code>.phbackup</code>.</p>
      <?php if(!empty($recoveryKeyExists)):?><form method="post" action="<?=url('backup-key-generate')?>" onsubmit="return confirm('Rotar la clave hará que los respaldos anteriores requieran la clave anterior. ¿Continuar?')"><?=csrf_field()?><input type="hidden" name="rotate" value="1"><button class="btn btn-outline-warning btn-sm mb-3">Rotar clave</button></form><?php endif;?>
      <details class="border rounded p-3">
        <summary class="fw-semibold" style="cursor:pointer"><i class="bi bi-calendar2-check me-2"></i>Configurar automatización y destinos</summary>
        <form method="post" action="<?=url('backup-settings-save')?>" class="row g-3 mt-1"><?=csrf_field()?>
          <div class="col-12 form-check ms-2"><input class="form-check-input" type="checkbox" name="enabled" value="1" id="bken" <?=!empty($settings['enabled'])?'checked':''?>><label class="form-check-label" for="bken">Respaldos automáticos habilitados</label></div>
          <div class="col-6"><label class="form-label">Frecuencia</label><select class="form-select" name="schedule_frequency"><?php foreach(['manual'=>'Manual','daily'=>'Diario','weekly'=>'Semanal'] as $v=>$l):?><option value="<?=$v?>" <?=($settings['schedule_frequency']??'daily')===$v?'selected':''?>><?=$l?></option><?php endforeach;?></select></div>
          <div class="col-6"><label class="form-label">Hora</label><input class="form-control" type="time" name="schedule_time" value="<?=e(substr((string)($settings['schedule_time']??'02:00:00'),0,5))?>"></div>
          <div class="col-12"><label class="form-label">Día semanal <small class="text-body-secondary">(solo semanal)</small></label><select class="form-select" name="schedule_weekday"><?php foreach([0=>'Domingo',1=>'Lunes',2=>'Martes',3=>'Miércoles',4=>'Jueves',5=>'Viernes',6=>'Sábado'] as $v=>$l):?><option value="<?=$v?>" <?=(int)($settings['schedule_weekday']??0)===$v?'selected':''?>><?=$l?></option><?php endforeach;?></select></div>
          <div class="col-4"><label class="form-label">Diarios</label><input class="form-control" type="number" min="1" max="365" name="retention_daily" value="<?=(int)($settings['retention_daily']??7)?>"></div>
          <div class="col-4"><label class="form-label">Semanales</label><input class="form-control" type="number" min="0" max="104" name="retention_weekly" value="<?=(int)($settings['retention_weekly']??4)?>"></div>
          <div class="col-4"><label class="form-label">Mensuales</label><input class="form-control" type="number" min="0" max="120" name="retention_monthly" value="<?=(int)($settings['retention_monthly']??6)?>"></div>
          <div class="col-12"><label class="form-label">Destino local</label><input class="form-control" name="local_path" value="<?=e((string)($settings['local_path']??'storage/backups'))?>"></div>
          <div class="col-12"><label class="form-label">Carpeta de red / NAS <small class="text-body-secondary">opcional</small></label><input class="form-control" name="network_path" value="<?=e((string)($settings['network_path']??''))?>" placeholder="\\SERVIDOR\Backups\PropertyHub"></div>
          <?php if(!$isCentral):?><div class="col-12 form-check ms-2"><input class="form-check-input" type="checkbox" name="central_copy_enabled" value="1" id="bkcentral" <?=!empty($settings['central_copy_enabled'])?'checked':''?>><label class="form-check-label" for="bkcentral">Enviar copia cifrada al Property Hub Central</label><small class="d-block text-body-secondary">El Central almacena el archivo cifrado; no recibe la clave.</small></div><?php endif;?>
          <?php if($isEdge):?><div class="col-12 form-check ms-2"><input class="form-check-input" type="checkbox" name="node_copy_enabled" value="1" id="bknode" <?=!empty($settings['node_copy_enabled'])?'checked':''?>><label class="form-check-label" for="bknode">Enviar copia cifrada también al Property Node</label><small class="d-block text-body-secondary">Custodia local adicional: Agent → Property Node. La clave PHRK permanece fuera del Node.</small></div><?php endif;?>
          <div class="col-12"><button class="btn btn-primary w-100">Guardar política</button></div>
        </form>
        <hr>
        <div class="small"><strong>Runner del servidor</strong><p class="mb-2 mt-1"><?=e($osFamily==='Windows'?'En Windows instálalo una vez como administrador:':'En Linux programe el runner con cron o el scheduler del hosting:')?></p><code class="d-block bg-body-tertiary p-2 rounded"><?=e($osFamily==='Windows'?'tools\\install-scheduler-task.bat':'php tools/backup-scheduled.php')?></code><p class="mb-0 mt-2 text-body-secondary">El Scheduler ejecuta heartbeat, órdenes remotas y revisa la política de respaldo. En Edge Agent también mantiene vivo el vínculo con su Property Node.</p></div>
      </details>
    </div></div>
  </div>
</div>

<div class="card shadow-sm mb-3">
  <div class="card-header d-flex justify-content-between align-items-center"><div><strong><i class="bi bi-archive me-2"></i>Respaldos disponibles</strong><small class="d-block text-body-secondary">El nombre físico se mantiene para trazabilidad; la tabla muestra una lectura más compacta.</small></div><span class="badge text-bg-light border"><?=count($runs)?> copias</span></div>
  <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Fecha</th><th>Tipo</th><th>Versión</th><th>Respaldo</th><th>Contenido</th><th>Tamaño</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead><tbody>
  <?php if(!$runs):?><tr><td colspan="8" class="text-center text-body-secondary py-4">Todavía no hay respaldos.</td></tr><?php endif;?>
  <?php foreach($runs as $r):
    $manifest=json_decode((string)($r['manifest_json']??''),true);$manifest=is_array($manifest)?$manifest:[];
    $db=(array)($manifest['database']??[]);$categories=(array)($manifest['storage_categories']??[]);
    $type=(string)($r['backup_type']??'manual');$status=(string)($r['status']??'');$version=(string)($manifest['app_version']??'—');
    $displayName=($typeLabels[$type]??$type).' · '.date('d/m/Y H:i',strtotime((string)$r['started_at']));
  ?><tr>
    <td class="text-nowrap"><strong><?=e(date('d/m/Y',strtotime((string)$r['started_at'])))?></strong><small class="d-block text-body-secondary"><?=e(date('H:i',strtotime((string)$r['started_at'])))?></small></td>
    <td><span class="badge text-bg-light border"><?=e($typeLabels[$type]??$type)?></span></td>
    <td class="text-nowrap">v<?=e($version)?></td>
    <td style="min-width:220px"><strong><?=e($displayName)?></strong><small class="d-block text-body-secondary text-truncate" style="max-width:330px" title="<?=e((string)($r['filename']??''))?>"><?=e((string)($r['filename']??'En proceso'))?></small><?php if(!empty($r['sha256'])):?><small class="d-block text-body-secondary font-monospace" title="<?=e((string)$r['sha256'])?>">SHA <?=e(substr((string)$r['sha256'],0,12))?>…</small><?php endif;?></td>
    <td class="text-nowrap"><?php if($manifest):?><?=e((string)($db['tables']??0))?> tablas<small class="d-block text-body-secondary"><?=e((string)($manifest['storage_files']??0))?> archivos persistentes</small><?php else:?><span class="text-body-secondary">Sin detalle</span><?php endif;?></td>
    <td class="text-nowrap"><?=$fmt($r['size_bytes']??0)?></td>
    <td><span class="badge text-bg-<?=$status==='completed'?'success':($status==='failed'?'danger':($status==='warning'?'warning':'secondary'))?>"><?=e($statusLabels[$status]??$status)?></span></td>
    <td class="text-end text-nowrap"><div class="btn-group btn-group-sm">
      <?php if(in_array($status,['completed','warning'],true)&&!empty($r['local_path'])):?><a class="btn btn-outline-secondary" href="<?=url('backup-download',['id'=>(int)$r['id']])?>" title="Descargar respaldo"><i class="bi bi-download"></i></a><?php endif;?>
      <button class="btn btn-outline-info" type="button" data-bs-toggle="modal" data-bs-target="#backupDetail<?=(int)$r['id']?>" title="Ver contenido"><i class="bi bi-info-circle"></i></button>
      <?php if(in_array($status,['completed','warning'],true)&&!empty($r['local_path'])):?><form method="post" action="<?=url('backup-restore-local-prepare')?>" class="d-inline"><?=csrf_field()?><input type="hidden" name="id" value="<?=(int)$r['id']?>"><button class="btn btn-outline-primary" title="Preparar restauración"><i class="bi bi-arrow-counterclockwise"></i></button></form><?php endif;?>
      <form method="post" action="<?=url('backup-delete')?>" onsubmit="return confirm('¿Eliminar esta copia local?')"><?=csrf_field()?><input type="hidden" name="id" value="<?=(int)$r['id']?>"><button class="btn btn-outline-danger" title="Eliminar respaldo"><i class="bi bi-trash"></i></button></form>
    </div></td>
  </tr>
  <?php endforeach;?>
  </tbody></table></div>
</div>

<?php foreach($runs as $r):
  $manifest=json_decode((string)($r['manifest_json']??''),true);$manifest=is_array($manifest)?$manifest:[];$db=(array)($manifest['database']??[]);$cats=(array)($manifest['storage_categories']??[]);$dest=json_decode((string)($r['destinations_json']??''),true);$dest=is_array($dest)?$dest:[];
?>
<div class="modal fade" id="backupDetail<?=(int)$r['id']?>" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content">
  <div class="modal-header"><div><h5 class="modal-title mb-0">Detalle del respaldo</h5><small class="text-body-secondary"><?=e((string)($r['filename']??''))?></small></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <?php if(!$manifest):?><div class="alert alert-warning">Este respaldo fue creado antes de que existiera el detalle de contenido. Sigue siendo válido, pero el manifiesto guardado no tiene el desglose nuevo.</div><?php else:?>
    <div class="row g-2 mb-3">
      <div class="col-6 col-md-3"><div class="border rounded p-2"><small class="text-body-secondary">Tipo</small><strong class="d-block"><?=e($typeLabels[(string)$r['backup_type']]??(string)$r['backup_type'])?></strong></div></div>
      <div class="col-6 col-md-3"><div class="border rounded p-2"><small class="text-body-secondary">Versión origen</small><strong class="d-block">v<?=e((string)($manifest['app_version']??'—'))?></strong></div></div>
      <div class="col-6 col-md-3"><div class="border rounded p-2"><small class="text-body-secondary">Base de datos</small><strong class="d-block"><?=e((string)($db['tables']??0))?> tablas</strong><small><?=e((string)($db['rows']??0))?> filas</small></div></div>
      <div class="col-6 col-md-3"><div class="border rounded p-2"><small class="text-body-secondary">Archivo final</small><strong class="d-block"><?=$fmt($r['size_bytes']??0)?></strong><small><?=e((string)($manifest['storage_files']??0))?> archivos storage</small></div></div>
    </div>
    <h6>Contenido persistente</h6>
    <?php if($cats):?><div class="table-responsive"><table class="table table-sm"><thead><tr><th>Grupo</th><th>Archivos</th><th>Tamaño sin comprimir</th></tr></thead><tbody><?php foreach($cats as $name=>$info):?><tr><td><code>storage/<?=e((string)$name)?></code></td><td><?=(int)($info['files']??0)?></td><td><?=$fmt($info['bytes']??0)?></td></tr><?php endforeach;?></tbody></table></div><?php else:?><p class="text-body-secondary small">Este manifiesto no contiene aún desglose por carpeta.</p><?php endif;?>
    <div class="alert alert-light border small mb-3"><strong>Excluido deliberadamente:</strong> <code>storage/backups</code>, <code>logs</code>, <code>tmp</code>, <code>cache</code>, <code>sessions</code>, <code>updates</code> y <code>installed.lock</code>. En Property Nodes y Edge Agents también se excluyen paquetes locales de repositorio.</div>
    <h6>Destinos</h6><div class="d-flex flex-wrap gap-2"><?php foreach($dest as $d):?><span class="badge text-bg-<?=($d['status']??'')==='ok'?'success':'warning'?>"><?=e((string)($d['type']??'destino'))?> · <?=e((string)($d['status']??''))?></span><?php endforeach;?><?php if(!$dest):?><span class="text-body-secondary small">Sin información de destinos.</span><?php endif;?></div>
    <?php endif;?>
  </div>
  <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button></div>
</div></div></div>
<?php endforeach;?>

<details class="card shadow-sm mb-3" <?=is_array($preview)?'open':''?>>
  <summary class="card-header" style="cursor:pointer"><strong><i class="bi bi-arrow-counterclockwise me-2"></i>Recuperar una instalación</strong><small class="d-block text-body-secondary">Abrir solo cuando vaya a validar o restaurar un <code>.phbackup</code>.</small></summary>
  <div class="card-body">
  <?php if(is_array($preview)): $m=(array)($preview['info']['manifest']??[]);$mi=(array)($m['installation']??[]);?>
    <div class="alert alert-info"><strong>Respaldo validado.</strong> Revisa la identidad antes de ejecutar la restauración. Esta operación reemplaza la base actual; Property Hub creará primero un <strong>PRE_RESTORE</strong> automático.</div>
    <div class="row g-3 mb-3"><div class="col-md-3"><small class="text-body-secondary">Instalación del backup</small><strong class="d-block"><?=e((string)($mi['name']??''))?></strong></div><div class="col-md-2"><small class="text-body-secondary">Código</small><strong class="d-block"><?=e((string)($mi['code']??''))?></strong></div><div class="col-md-2"><small class="text-body-secondary">Versión</small><strong class="d-block"><?=e((string)($m['app_version']??''))?></strong></div><div class="col-md-2"><small class="text-body-secondary">Fecha</small><strong class="d-block"><?=e(format_datetime_es((string)($m['created_at']??'')))?></strong></div><div class="col-md-3"><small class="text-body-secondary">Base</small><strong class="d-block"><?=e((string)($m['database']['tables']??0))?> tablas · <?=e((string)($m['database']['rows']??0))?> filas</strong></div></div>
    <form method="post" action="<?=url('backup-restore-run')?>" class="row g-3"><?=csrf_field()?>
      <div class="col-lg-6"><label class="form-label">Modo de recuperación</label><div class="border rounded p-3 h-100"><div class="form-check"><input class="form-check-input" type="radio" name="restore_mode" id="same" value="same_installation" checked><label class="form-check-label" for="same"><strong>Recuperar la misma instalación</strong><small class="d-block text-body-secondary">Restaura UUID, código, usuarios, vínculos y credenciales externas. Conserva del nuevo servidor: URL, host/usuario/clave de MariaDB y diagnóstico.</small></label></div><hr><div class="form-check"><input class="form-check-input" type="radio" name="restore_mode" id="clone" value="safe_clone"><label class="form-check-label" for="clone"><strong>Crear copia segura / laboratorio</strong><small class="d-block text-body-secondary">Mantiene la identidad de la instalación básica actual y deshabilita conectores, aperturas remotas, cámaras, notificaciones y actualizaciones.</small></label></div></div></div>
      <div class="col-lg-6"><div class="alert alert-success py-2"><i class="bi bi-key-fill me-2"></i><strong>Clave PHRK validada.</strong><small class="d-block">Se conserva únicamente en esta sesión hasta ejecutar o cancelar la restauración. No es necesario escribirla otra vez.</small></div><label class="form-label mt-2">Confirmación destructiva</label><input class="form-control" name="confirmation" autocomplete="off" placeholder="Escriba <?=e((string)config('app.instance_code'))?>" required><small class="text-body-secondary">Se confirma con el código de la instalación básica que está ejecutándose ahora.</small></div>
      <div class="col-12 d-flex gap-2"><button class="btn btn-danger"><i class="bi bi-arrow-counterclockwise me-2"></i>Restaurar respaldo</button></form><form method="post" action="<?=url('backup-restore-cancel')?>"><?=csrf_field()?><button class="btn btn-outline-secondary">Cancelar</button></form></div>
  <?php else:?>
    <div class="row g-4"><div class="col-lg-7"><h5>Restaurar desde archivo <code>.phbackup</code></h5><p>En un servidor nuevo: instala Property Hub <?=e((string)config('app.version'))?> como una instalación básica del mismo perfil, entra como administrador y carga aquí el respaldo.</p><form method="post" action="<?=url('backup-restore-upload')?>" enctype="multipart/form-data" class="row g-3"><?=csrf_field()?><div class="col-12"><label class="form-label">Archivo</label><input class="form-control" type="file" name="backup_file" accept=".phbackup" required></div><div class="col-12"><label class="form-label">Clave de recuperación</label><input class="form-control" type="password" name="recovery_key" required autocomplete="off"></div><div class="col-12"><button class="btn btn-primary"><i class="bi bi-file-earmark-lock me-2"></i>Validar y preparar restauración</button></div></form><small class="d-block mt-3 text-body-secondary">Límites PHP actuales: upload_max_filesize <?=e((string)ini_get('upload_max_filesize'))?> · post_max_size <?=e((string)ini_get('post_max_size'))?>. Para copias grandes también puede colocar el archivo en <code>storage/backups/incoming</code> y usar la herramienta CLI.</small>
    <?php if(!$isCentral):?><hr class="my-4"><h5><i class="bi bi-cloud-arrow-down me-2"></i>Recuperar desde Property Hub Central</h5><p class="small text-body-secondary">Si el servidor original y sus backups locales se perdieron, el Central puede entregar una copia cifrada de su Backup Vault mediante un token temporal.</p><form method="post" action="<?=url('backup-restore-central')?>" class="row g-3"><?=csrf_field()?><div class="col-12"><label class="form-label">URL de Property Hub Central</label><input class="form-control" name="central_repository_url" value="<?=e((string)config('deployment.central_repository_url',''))?>" placeholder="https://2cam.services/hub/public" required></div><div class="col-12"><label class="form-label">Token temporal PHREC</label><input class="form-control font-monospace" name="recovery_token" placeholder="PHREC-..." required autocomplete="off"></div><div class="col-12"><label class="form-label">Clave de recuperación PHRK</label><input class="form-control" type="password" name="recovery_key" placeholder="PHRK-..." required autocomplete="off"></div><div class="col-12"><button class="btn btn-outline-primary"><i class="bi bi-cloud-arrow-down me-2"></i>Descargar y validar desde Central</button></div></form><?php endif;?></div>
    <div class="col-lg-5"><div class="p-3 rounded bg-body-tertiary"><strong>Qué se conserva del servidor nuevo</strong><ul class="small mt-2 mb-0"><li>URL pública de esta instalación.</li><li>Servidor, base, usuario y contraseña MariaDB.</li><li>Parámetros locales de diagnóstico.</li><li>En modo copia: UUID/código/nombre de la instalación básica y credenciales de nodo nuevas.</li></ul></div></div></div>
  <?php endif;?>
  </div>
</details>

<?php if($isCentral && !empty($offsiteArchives)):?>
<div class="card shadow-sm mt-3"><div class="card-header"><strong><i class="bi bi-cloud-check me-2"></i>Copias cifradas recibidas de Property Nodes y Edge Agents</strong></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Instalación</th><th>Recibido</th><th>Archivo</th><th>Tamaño</th><th>SHA-256</th><th></th></tr></thead><tbody><?php foreach($offsiteArchives as $a):?><tr><td><strong><?=e((string)$a['installation_code'])?></strong></td><td><?=e(format_datetime_es((string)$a['received_at']))?></td><td><?=e((string)$a['filename'])?></td><td><?=$fmt($a['size_bytes'])?></td><td><code><?=e(substr((string)$a['sha256'],0,18))?>…</code></td><td class="text-end"><a class="btn btn-sm btn-outline-primary" href="<?=url('backup-offsite-download',['id'=>(int)$a['id']])?>"><i class="bi bi-download"></i></a></td></tr><?php endforeach;?></tbody></table></div></div>
<?php endif;?>
