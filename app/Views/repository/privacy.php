<?php $p=$privacy??[]; ?>
<div class="row g-3">
  <div class="col-xl-7">
    <div class="card">
      <div class="card-header"><strong>Política global de datos</strong></div>
      <div class="card-body">
        <form method="post" action="<?=url('repository-save-privacy')?>" class="row g-3">
          <?=csrf_field()?>
          <div class="col-12">
            <label class="form-label">Alcance predeterminado</label>
            <select class="form-select" name="default_data_scope">
              <?php foreach(['metadata_only'=>'Solo metadatos técnicos','aggregates'=>'Metadatos + métricas agregadas','support_snapshot'=>'Snapshot temporal de soporte autorizado'] as $v=>$l):?>
                <option value="<?=e($v)?>" <?=($p['default_data_scope']??'aggregates')===$v?'selected':''?>><?=e($l)?></option>
              <?php endforeach;?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Retención de métricas (días)</label>
            <input class="form-control" type="number" min="30" name="retain_metrics_days" value="<?=e($p['retain_metrics_days']??365)?>">
          </div>
          <div class="col-12">
            <div class="form-check"><input class="form-check-input" type="checkbox" checked disabled><label class="form-check-label">Bloquear recepción de datos personales</label></div>
            <div class="form-check"><input class="form-check-input" type="checkbox" checked disabled><label class="form-check-label">Bloquear contenido de cartelera, avisos y mensajes operativos</label></div>
            <div class="form-check"><input class="form-check-input" type="checkbox" name="remote_support_enabled" id="support" <?=!empty($p['remote_support_enabled'])?'checked':''?>><label class="form-check-label" for="support">Habilitar solicitudes de soporte remoto temporal</label></div>
            <div class="form-check"><input class="form-check-input" type="checkbox" name="remote_support_requires_local_approval" id="approval" <?=!empty($p['remote_support_requires_local_approval'])?'checked':''?>><label class="form-check-label" for="approval">Exigir aprobación local de la instalación</label></div>
          </div>
          <div class="col-12"><label class="form-label">Notas</label><textarea class="form-control" name="notes" rows="3"><?=e($p['notes']??'')?></textarea></div>
          <div class="col-12"><button class="btn btn-primary">Guardar política</button></div>
        </form>
      </div>
    </div>
  </div>
  <div class="col-xl-5">
    <div class="card mb-3"><div class="card-header"><strong>Alcance recomendado</strong></div><div class="card-body"><p class="fw-semibold">Metadatos + métricas agregadas</p><ul class="small ps-3"><li>Conteos de usuarios, unidades y dispositivos.</li><li>Versiones, conectores y estado de salud.</li><li>Profundidad de colas y alertas técnicas.</li><li>Sin nombres, cédulas, fotos o mensajes.</li></ul></div></div>
    <div class="alert alert-warning"><strong>Espejo operativo no habilitado:</strong> la estructura futura queda documentada, pero esta versión no permite activarla. Cualquier acceso detallado deberá implementarse como sesión temporal, aprobada y auditada.</div>
  </div>
</div>
