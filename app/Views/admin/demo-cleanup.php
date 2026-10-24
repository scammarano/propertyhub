<?php
$preview=$preview??[];
$mode=(string)($preview['mode']??'node');
$total=0;
foreach($preview as $key=>$value)if(is_int($value))$total+=$value;
?>
<div class="row g-3">
  <div class="col-xl-7">
    <div class="card">
      <div class="card-header"><strong>Revisión de datos demostrativos</strong></div>
      <div class="card-body">
        <p>Esta herramienta desactiva <code>APP_DEMO_MODE</code>, limpia los datos temporales de la sesión y elimina únicamente registros reconocidos como parte de los escenarios demostrativos oficiales.</p>
        <div class="alert alert-info"><strong>No se eliminan:</strong> el administrador, la identidad de la instalación, el vínculo con Property Hub Central, migraciones, catálogos de conectores, versiones de API ni datos creados manualmente que no coincidan con los identificadores demo.</div>
        <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Conjunto detectado</th><th class="text-end">Cantidad</th></tr></thead><tbody>
        <?php foreach($preview as $key=>$value):if(!is_int($value))continue;?><tr><td><?=e(ucwords(str_replace('_',' ',$key)))?></td><td class="text-end"><strong><?=e($value)?></strong></td></tr><?php endforeach;?>
        </tbody><tfoot><tr><th>Total detectado</th><th class="text-end"><?=e($total)?></th></tr></tfoot></table></div>
      </div>
    </div>
  </div>
  <div class="col-xl-5">
    <div class="card border-danger">
      <div class="card-header"><strong>Ejecutar limpieza segura</strong></div>
      <div class="card-body">
        <p>Escribe el código de esta instalación para confirmar:</p>
        <p><code><?=e(config('app.instance_code'))?></code></p>
        <form method="post" action="<?=url('system-demo-cleanup-run')?>">
          <?=csrf_field()?>
          <label class="form-label" for="confirmation">Código de confirmación</label>
          <input class="form-control" id="confirmation" name="confirmation" autocomplete="off" required>
          <button class="btn btn-danger w-100 mt-3" type="submit"><i class="bi bi-trash3"></i> Eliminar datos demo y desactivar demo</button>
        </form>
      </div>
    </div>
  </div>
</div>
