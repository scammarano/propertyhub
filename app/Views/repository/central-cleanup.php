<?php
$cleanup=(array)($cleanup??[]);$candidates=(array)($cleanup['candidates']??[]);$protected=(array)($cleanup['protected']??[]);$plans=(array)($cleanup['plans']??[]);$tokens=(array)($cleanup['tokens']??[]);$result=$cleanupResult??null;
$fmtEnv=static fn(string $v):string=>$v==='lab'?'LABORATORIO':($v==='test'?'PRUEBA':'PRODUCCIÓN');
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
  <div><a href="<?=url('repository-installations')?>" class="text-decoration-none"><i class="bi bi-arrow-left"></i> Red de instalaciones</a><h4 class="mt-2 mb-1">Limpieza controlada de Property Hub Central</h4><p class="text-body-secondary mb-0">Elimine Nodes/Agents de Demo, Prueba o Laboratorio sin tocar instalaciones de Producción ni respaldos del Vault por defecto.</p></div>
  <span class="badge text-bg-light border fs-6"><?=count($candidates)?> candidatos · <?=count($protected)?> protegidos</span>
</div>

<?php if(is_array($result)):?>
<div class="alert alert-<?=empty($result['errors'])?'success':'warning'?>">
  <strong>Limpieza ejecutada.</strong>
  Instalaciones eliminadas: <strong><?=count((array)($result['removed_installations']??[]))?></strong> · preparaciones: <strong><?=(int)($result['removed_plans']??0)?></strong> · tokens inactivos huérfanos: <strong><?=(int)($result['removed_tokens']??0)?></strong>.
  <?php if(!empty($result['removed_installations'])):?><div class="mt-1"><code><?=e(implode(', ',(array)$result['removed_installations']))?></code></div><?php endif;?>
  <?php foreach((array)($result['errors']??[]) as $error):?><div class="text-danger mt-1"><strong><?=e((string)($error['code']??''))?>:</strong> <?=e((string)($error['message']??''))?></div><?php endforeach;?>
</div>
<?php endif;?>

<div class="alert alert-info border"><i class="bi bi-shield-check me-2"></i><strong>Protección:</strong> las instalaciones marcadas <strong>PRODUCCIÓN</strong> no pueden eliminarse desde esta herramienta. Para Premier deben conservarse <code>PREMIER-LM</code> y <code>PREMIER-LM-EDGE-01</code>. Las copias <code>.phbackup</code> del Vault se conservan salvo que marque explícitamente lo contrario.</div>

<form method="post" action="<?=url('repository-central-cleanup-run')?>" onsubmit="return confirm('Esta operación elimina físicamente los expedientes seleccionados de Prueba/Laboratorio/Demo. ¿Continuar?');">
<?=csrf_field()?>
<div class="card mb-3">
  <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2"><div><strong>Instalaciones eliminables</strong><small class="d-block text-body-secondary">Solo Demo / Prueba / Laboratorio</small></div><div class="btn-group btn-group-sm"><button type="button" class="btn btn-outline-secondary" onclick="toggleCleanup(true)">Seleccionar todas</button><button type="button" class="btn btn-outline-secondary" onclick="toggleCleanup(false)">Ninguna</button></div></div>
  <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th style="width:44px"></th><th>Instalación</th><th>Rol</th><th>Entorno</th><th>Actividad</th><th>Historial asociado</th><th>Vault</th></tr></thead><tbody>
  <?php foreach($candidates as $i):$role=(string)($i['instance_role']??'building_node');$env=(string)($i['environment_type']??'production');?>
    <tr>
      <td><input class="form-check-input cleanup-check" type="checkbox" name="installation_ids[]" value="<?=(int)$i['id']?>"></td>
      <td><strong><?=e((string)$i['installation_name'])?></strong><?php if(!empty($i['is_demo'])):?><span class="badge text-bg-info ms-1">DEMO</span><?php endif;?><small class="d-block text-body-secondary"><code><?=e((string)$i['installation_code'])?></code><?php if($role==='edge_agent'):?> · padre <code><?=e((string)($i['parent_installation_code']??'—'))?></code><?php endif;?></small></td>
      <td><span class="badge text-bg-<?=$role==='edge_agent'?'warning':'primary'?>"><?=e(installation_role_short($role))?></span></td>
      <td><span class="badge text-bg-<?=$env==='lab'?'warning':'secondary'?>"><?=e($fmtEnv($env))?></span></td>
      <td><?php if(!empty($i['recently_active'])):?><span class="badge text-bg-danger">ACTIVA &lt; 5 min</span><?php else:?><span class="badge text-bg-light border">Sin actividad reciente</span><?php endif;?><small class="d-block text-body-secondary"><?=e((string)($i['last_seen_at']??'Nunca'))?></small></td>
      <td><small><?=number_format((int)$i['delivery_count'])?> eventos · <?=number_format((int)$i['metrics_count'])?> métricas<br><?=number_format((int)$i['token_count'])?> tokens · <?=number_format((int)$i['backup_request_count'])?> solicitudes backup</small></td>
      <td><strong><?=(int)$i['offsite_count']?></strong> copias</td>
    </tr>
  <?php endforeach;?>
  <?php if(!$candidates):?><tr><td colspan="7" class="text-center text-body-secondary py-5"><i class="bi bi-check-circle fs-3 d-block mb-2"></i>No quedan instalaciones Demo, Prueba o Laboratorio registradas.</td></tr><?php endif;?>
  </tbody></table></div>
</div>

<div class="row g-3 mb-3">
  <div class="col-xl-7"><div class="card h-100"><div class="card-header"><strong>Preparaciones y tokens</strong></div><div class="card-body">
    <div class="row g-3 mb-3"><div class="col-sm-4"><small class="text-body-secondary">Preparaciones Prueba/Lab</small><div class="fs-3 fw-semibold"><?=count($plans)?></div></div><div class="col-sm-4"><small class="text-body-secondary">Tokens activos</small><div class="fs-3 fw-semibold"><?=(int)($tokens['active_tokens']??0)?></div></div><div class="col-sm-4"><small class="text-body-secondary">Tokens inactivos</small><div class="fs-3 fw-semibold"><?=(int)($tokens['inactive_tokens']??0)?></div></div></div>
    <label class="form-check mb-2"><input class="form-check-input" type="checkbox" name="cleanup_plans" value="1" checked><span class="form-check-label">Eliminar preparaciones <strong>Prueba/Laboratorio/Demo</strong> que nunca llegaron a registrarse.</span></label>
    <label class="form-check"><input class="form-check-input" type="checkbox" name="prune_tokens" value="1" checked><span class="form-check-label">Eliminar tokens <strong>inactivos/expirados/usados</strong> que ya no pertenecen a una instalación ni a una preparación.</span></label>
    <small class="text-body-secondary d-block mt-2">No se eliminan tokens activos de instalaciones vigentes.</small>
  </div></div></div>
  <div class="col-xl-5"><div class="card h-100 border-danger"><div class="card-header"><strong>Confirmar limpieza</strong></div><div class="card-body">
    <label class="form-check mb-2"><input class="form-check-input" type="checkbox" name="ack_active" value="1"><span class="form-check-label">Permitir eliminar una instalación de prueba aunque haya reportado actividad en los últimos 5 minutos.</span></label>
    <label class="form-check mb-3"><input class="form-check-input" type="checkbox" name="purge_offsite" value="1"><span class="form-check-label text-danger">Eliminar también sus archivos cifrados del Backup Vault.</span></label>
    <label class="form-label">Escriba el código de este Central para confirmar</label><div class="mb-2"><code><?=e((string)($cleanup['central_code']??''))?></code></div><input class="form-control" name="confirmation" autocomplete="off" required>
    <button class="btn btn-danger w-100 mt-3" <?=(!$candidates&&!$plans)?'disabled':''?>><i class="bi bi-trash3 me-2"></i>Ejecutar limpieza seleccionada</button>
  </div></div></div>
</div>
</form>

<div class="card"><div class="card-header"><strong>Instalaciones protegidas</strong><small class="d-block text-body-secondary">Producción: visibles para referencia, no seleccionables.</small></div><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Instalación</th><th>Rol</th><th>Padre</th><th>Último contacto</th><th>Estado</th></tr></thead><tbody>
<?php foreach($protected as $i):$role=(string)($i['instance_role']??'building_node');?><tr><td><strong><?=e((string)$i['installation_name'])?></strong><small class="d-block"><code><?=e((string)$i['installation_code'])?></code></small></td><td><span class="badge text-bg-<?=$role==='edge_agent'?'warning':'primary'?>"><?=e(installation_role_short($role))?></span></td><td><?=e((string)($i['parent_installation_code']??'—'))?></td><td><?=e((string)($i['last_seen_at']??'Nunca'))?></td><td><span class="badge text-bg-success">PROTEGIDA</span></td></tr><?php endforeach;?>
<?php if(!$protected):?><tr><td colspan="5" class="text-center text-body-secondary py-4">No hay instalaciones de Producción registradas.</td></tr><?php endif;?>
</tbody></table></div></div>
<script>function toggleCleanup(v){document.querySelectorAll('.cleanup-check').forEach(function(el){el.checked=v;});}</script>
