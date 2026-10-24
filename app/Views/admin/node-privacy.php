<?php $p=$technology->profile(); ?>
<div class="row g-3">
  <div class="col-xl-7">
    <div class="card">
      <div class="card-header"><strong>Telemetría enviada al Property Hub Central</strong></div>
      <div class="card-body">
        <form method="post" action="<?=url('node-privacy-save')?>" class="row g-3">
          <?=csrf_field()?>
          <div class="col-12"><label class="form-label">Alcance autorizado</label><select class="form-select" name="data_scope"><option value="metadata_only" <?=($p['data_scope']??'')==='metadata_only'?'selected':''?>>Solo versiones, salud y conexión</option><option value="aggregates" <?=($p['data_scope']??'aggregates')==='aggregates'?'selected':''?>>Metadatos + métricas agregadas</option><option value="support_snapshot" <?=($p['data_scope']??'')==='support_snapshot'?'selected':''?>>Snapshot temporal de soporte autorizado</option></select></div>
          <div class="col-12">
            <div class="form-check"><input class="form-check-input" type="checkbox" name="share_aggregate_metrics" id="metrics" <?=!empty($p['share_aggregate_metrics'])?'checked':''?>><label class="form-check-label" for="metrics">Compartir conteos agregados</label></div>
            <div class="form-check"><input class="form-check-input" type="checkbox" name="share_connector_inventory" id="inventory" <?=!empty($p['share_connector_inventory'])?'checked':''?>><label class="form-check-label" for="inventory">Compartir inventario técnico sin secretos</label></div>
            <div class="form-check"><input class="form-check-input" type="checkbox" name="remote_support_enabled" id="remote" <?=!empty($p['remote_support_enabled'])?'checked':''?>><label class="form-check-label" for="remote">Permitir solicitudes de soporte remoto</label></div>
            <div class="form-check"><input class="form-check-input" type="checkbox" name="remote_support_requires_local_approval" id="localapproval" <?=!empty($p['remote_support_requires_local_approval'])?'checked':''?>><label class="form-check-label" for="localapproval">Exigir aprobación local para cada sesión</label></div>
          </div>
          <div class="col-12"><label class="form-label">Notas</label><textarea class="form-control" name="notes" rows="3"><?=e($p['notes']??'')?></textarea></div>
          <div class="col-12"><button class="btn btn-primary">Guardar política local</button></div>
        </form>
      </div>
    </div>
  </div>
  <div class="col-xl-5">
    <div class="alert alert-success"><strong>Nunca se envían por heartbeat:</strong><ul class="mb-0 mt-2"><li>Nombres o cédulas.</li><li>Fotos o documentos.</li><li>Contenido de mensajes.</li><li>Usuarios y contraseñas de fabricantes.</li><li>Plantillas biométricas.</li></ul></div>
    <div class="alert alert-warning"><strong>Acceso detallado no habilitado:</strong> una futura sesión de soporte deberá ser temporal, aprobada localmente y auditada.</div>
  </div>
</div>
