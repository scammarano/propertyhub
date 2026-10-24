<?php
$batch=is_array($batch??null)?$batch:[];$steps=(array)($batch['steps']??[]);$status=(string)($batch['status']??'unknown');$phase=(string)($batch['phase']??'');$id=(string)($batch['id']??'');
$total=max(1,(int)($batch['total_steps']??count($steps)));$downloaded=(int)($batch['downloaded_steps']??0);$installed=(int)($batch['installed_steps']??0);
$downloadPct=(int)round(($downloaded/$total)*100);$installPct=(int)round(($installed/$total)*100);
?>
<div class="row justify-content-center"><div class="col-xl-9">
<div class="card"><div class="card-header d-flex justify-content-between align-items-center"><strong><i class="bi bi-arrow-repeat me-2"></i>Actualización automática</strong><span class="badge text-bg-<?=$status==='completed'?'success':($status==='failed'?'danger':'primary')?>"><?=e($status)?></span></div><div class="card-body">
<div class="d-flex flex-wrap gap-3 mb-3"><div><span class="text-body-secondary small">Desde</span><br><strong><?=e((string)($batch['initial_version']??''))?></strong></div><div><span class="text-body-secondary small">Hasta</span><br><strong><?=e((string)($batch['target_version']??''))?></strong></div><div><span class="text-body-secondary small">Pasos</span><br><strong><?=$total?></strong></div></div>
<?php if($status==='running'):?><div class="alert alert-info"><span class="spinner-border spinner-border-sm me-2" role="status"></span><?=e((string)($batch['current_message']??'Procesando…'))?><div class="small mt-2">Puede dejar esta pantalla abierta. Property Hub descarga primero los ZIP conocidos, los verifica, instala cada paso y vuelve a consultar la fuente para incorporar componentes que se habiliten después de actualizar el Core.</div></div><?php elseif($status==='completed'):?><div class="alert alert-success"><i class="bi bi-check-circle-fill me-2"></i><strong>Actualización completada.</strong> <?=e((string)($batch['current_message']??''))?></div><?php else:?><div class="alert alert-danger"><i class="bi bi-exclamation-triangle-fill me-2"></i><strong>El lote se detuvo.</strong><div class="mt-1"><?=e((string)($batch['last_error']??$batch['current_message']??'Error desconocido'))?></div></div><?php endif;?>

<div class="mb-3"><div class="d-flex justify-content-between small"><span>Descarga y verificación</span><span><?=$downloaded?>/<?=$total?></span></div><div class="progress" style="height:10px"><div class="progress-bar" style="width:<?=$downloadPct?>%"></div></div></div>
<div class="mb-4"><div class="d-flex justify-content-between small"><span>Instalación secuencial</span><span><?=$installed?>/<?=$total?></span></div><div class="progress" style="height:10px"><div class="progress-bar" style="width:<?=$installPct?>%"></div></div></div>

<div class="table-responsive"><table class="table align-middle"><thead><tr><th>Orden</th><th>Componente</th><th>Descarga</th><th>Instalación</th></tr></thead><tbody><?php foreach($steps as $i=>$step):if(!is_array($step))continue;$ds=(string)($step['download_status']??'pending');$is=(string)($step['install_status']??'pending');?><tr><td><?=((int)$i+1)?></td><td><div class="small text-body-secondary"><code><?=e((string)($step['component_code']??'property-hub-core'))?></code></div><strong><?=e((string)($step['display_name']??$step['component_code']??'Componente'))?> · <?=e((string)($step['version']??''))?></strong><?php if(!empty($step['repair_bridge'])):?><br><span class="badge text-bg-warning">bridge de reparación</span><?php endif;?></td><td><span class="badge text-bg-<?=$ds==='downloaded'?'success':'secondary'?>"><?=e($ds==='downloaded'?'Verificado':'Pendiente')?></span></td><td><span class="badge text-bg-<?=$is==='installed'?'success':'secondary'?>"><?=e($is==='installed'?'Instalado':'Pendiente')?></span></td></tr><?php endforeach;?></tbody></table></div>

<div class="d-flex flex-wrap gap-2 mt-3"><a class="btn btn-outline-secondary" href="<?=url('updates')?>">Volver a actualizaciones</a><?php if($status==='failed'):?><form method="post" action="<?=url('update-batch-resume')?>"><?=csrf_field()?><input type="hidden" name="batch_id" value="<?=e($id)?>"><button class="btn btn-primary"><i class="bi bi-play-fill me-1"></i>Reanudar desde donde quedó</button></form><?php endif;?></div>
</div></div></div></div>
<?php if($status==='running'):?>
<script>
(function(){
  const batchId=<?=json_encode($id,JSON_UNESCAPED_SLASHES)?>;
  const csrf=<?=json_encode(csrf_token(),JSON_UNESCAPED_SLASHES)?>;
  let busy=false;
  async function next(){
    if(busy)return;busy=true;
    try{
      const body=new URLSearchParams();body.set('_csrf',csrf);body.set('batch_id',batchId);
      const r=await fetch('index.php?route=update-batch-step',{method:'POST',headers:{'Accept':'application/json','Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},body:body.toString(),credentials:'same-origin',redirect:'follow'});
      const ct=(r.headers.get('content-type')||'').toLowerCase();if(!r.ok||!ct.includes('application/json'))throw new Error('El servidor no devolvió el estado JSON esperado (HTTP '+r.status+').');
      const data=await r.json();const b=data.batch||{};
      if(b.needs_finalize){window.location.href='update-finalize.php?batch='+encodeURIComponent(batchId);return;}
      window.location.href='index.php?route=update-batch&id='+encodeURIComponent(batchId)+'&tick='+(Date.now());
    }catch(e){
      const box=document.createElement('div');box.className='alert alert-warning mt-3';box.textContent='La pantalla automática se detuvo: '+e.message+'. Puede recargar; el lote conserva su progreso.';document.querySelector('.card-body').appendChild(box);busy=false;
    }
  }
  setTimeout(next,700);
})();
</script>
<?php endif;?>
