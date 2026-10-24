<?php $properties=(array)($properties??[]); ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div><h5 class="mb-1">Instalaciones</h5><p class="text-body-secondary mb-0">Una instalación representa la propiedad. Expándala para ver su Property Node y sus Edge Agents.</p></div>
  <div class="d-flex flex-wrap gap-2"><a class="btn btn-outline-danger" href="<?=url('repository-central-cleanup')?>"><i class="bi bi-trash3 me-2"></i>Limpiar pruebas</a><a class="btn btn-outline-secondary" href="<?=url('repository-tokens')?>"><i class="bi bi-key me-2"></i>Tokens globales</a><a class="btn btn-primary" href="<?=url('repository-new-installation')?>"><i class="bi bi-plus-circle me-2"></i>Nueva instalación</a></div>
</div>

<?php if(!$properties):?>
<div class="card"><div class="card-body text-center py-5"><i class="bi bi-buildings fs-1 text-body-secondary"></i><h5 class="mt-3">Todavía no hay instalaciones</h5><p class="text-body-secondary">Cree primero la propiedad en Central. Desde su ficha obtendrá los datos para instalar Node y Edge.</p><a class="btn btn-primary" href="<?=url('repository-new-installation')?>">Crear primera instalación</a></div></div>
<?php endif;?>

<div class="d-grid gap-3">
<?php foreach($properties as $idx=>$property):
  $node=is_array($property['node']??null)?$property['node']:null;$plan=is_array($property['plan']??null)?$property['plan']:null;
  $env=(string)($property['environment_type']??'production');$health=(string)($property['health_status']??'pending');$connected=!empty($property['node_connected']);$collapse='propertyChildren'.$idx;
  $registeredEdges=(array)($property['registered_edges']??[]);$deployments=(array)($property['edge_deployments']??[]);$registeredByCode=[];foreach($registeredEdges as $e)$registeredByCode[(string)$e['installation_code']]=$e;
?>
<div class="card shadow-sm">
  <div class="card-body py-3">
    <div class="row align-items-center g-3">
      <div class="col-lg-5">
        <div class="d-flex align-items-start gap-2">
          <button class="btn btn-sm btn-outline-secondary mt-1" type="button" data-bs-toggle="collapse" data-bs-target="#<?=$collapse?>" aria-expanded="false" aria-controls="<?=$collapse?>" title="Ver Node y Edge Agents"><i class="bi bi-chevron-down"></i></button>
          <div><a class="fw-semibold fs-5 text-decoration-none" href="<?=url('repository-property',['code'=>(string)$property['code']])?>"><?=e((string)$property['name'])?></a><div class="text-body-secondary"><code><?=e((string)$property['code'])?></code> · <?=e(installation_type_label((string)$property['installation_type']))?></div></div>
        </div>
      </div>
      <div class="col-6 col-lg-2"><small class="text-body-secondary d-block">Entorno</small><span class="badge text-bg-<?=$env==='production'?'success':($env==='lab'?'warning':'secondary')?>"><?=e($env==='production'?'PRODUCCIÓN':($env==='lab'?'LABORATORIO':'PRUEBA'))?></span></div>
      <div class="col-6 col-lg-2"><small class="text-body-secondary d-block">Despliegue</small><?php if($node):?><span class="badge text-bg-<?=$connected?'success':'secondary'?>">Node <?=$connected?'conectado':'sin conexión'?></span><small class="d-block text-body-secondary mt-1">Último heartbeat: <?=e(!empty($node['last_seen_at'])?format_datetime_es((string)$node['last_seen_at']):'Nunca')?></small><?php else:?><span class="badge text-bg-warning">Node pendiente</span><?php endif;?><small class="d-block text-body-secondary mt-1"><?=(int)($property['edge_count']??0)?> Edge Agent(s)</small></div>
      <div class="col-7 col-lg-2"><small class="text-body-secondary d-block">Versiones</small><?php if($node):?><strong>Core <?=e((string)($node['app_version']??'-'))?></strong><small class="d-block text-body-secondary">Canal <?=e((string)$property['update_channel'])?></small><?php else:?><span class="text-body-secondary">Aún sin Node</span><?php endif;?></div>
      <div class="col-5 col-lg-1 text-end"><a class="btn btn-sm btn-primary" href="<?=url('repository-property',['code'=>(string)$property['code']])?>">Abrir</a></div>
    </div>
  </div>
  <div class="collapse" id="<?=$collapse?>"><div class="border-top bg-body-tertiary px-3 py-3">
    <div class="row g-3">
      <div class="col-lg-6"><div class="card h-100"><div class="card-body py-3"><div class="d-flex justify-content-between align-items-start"><div><span class="badge text-bg-primary mb-2">PROPERTY NODE</span><?php if($node):?><h6 class="mb-1"><?=e((string)$node['installation_name'])?></h6><code><?=e((string)$node['installation_code'])?></code><small class="d-block text-body-secondary mt-1"><?=e((string)($node['base_url']??'Sin URL reportada'))?></small></div><div class="text-end"><span class="badge text-bg-<?=!empty($node['node_connected'])?'success':'secondary'?>"><?=!empty($node['node_connected'])?'Conectado':'Sin conexión'?></span><small class="d-block text-body-secondary mt-1">HB <?=e(!empty($node['last_seen_at'])?format_datetime_es((string)$node['last_seen_at']):'Nunca')?></small><small class="d-block text-body-secondary">Salud reportada: <?=e(health_label((string)($node['reported_health_status']??'unknown')))?></small></div><?php else:?><h6 class="mb-1">Pendiente de instalación</h6><small class="text-body-secondary">Use el token inicial de la propiedad para registrar el Node.</small></div><span class="badge text-bg-warning">PENDIENTE</span><?php endif;?></div></div></div></div>
      <div class="col-lg-6"><div class="card h-100"><div class="card-body py-3"><div class="d-flex justify-content-between align-items-center mb-2"><div><span class="badge text-bg-warning">EDGE AGENTS</span></div><span class="small text-body-secondary"><?=(int)($property['edge_count']??0)?> configurado(s)</span></div>
        <?php if(!$deployments&&!$registeredEdges):?><div class="text-body-secondary small">Todavía no se ha preparado ningún Edge Agent.</div><?php endif;?>
        <?php $shown=[];foreach($deployments as $edge):$ec=(string)$edge['edge_code'];$shown[$ec]=true;$reg=$registeredByCode[$ec]??null;?><div class="d-flex justify-content-between align-items-center border-top py-2"><div><strong><?=e((string)$edge['edge_name'])?></strong><small class="d-block"><code><?=e($ec)?></code></small></div><div class="text-end"><span class="badge text-bg-<?=($edge['status']??'')==='claimed'?'success':(($edge['status']??'')==='pending'?'warning':'secondary')?>"><?=e(strtoupper((string)$edge['status']))?></span><?php if($reg):?><small class="d-block text-body-secondary">registro Central disponible</small><?php endif;?></div></div><?php endforeach;?>
        <?php foreach($registeredEdges as $edge):$ec=(string)$edge['installation_code'];if(isset($shown[$ec]))continue;?><div class="d-flex justify-content-between align-items-center border-top py-2"><div><strong><?=e((string)$edge['installation_name'])?></strong><small class="d-block"><code><?=e($ec)?></code></small></div><span class="badge text-bg-<?=e(health_badge((string)($edge['health_status']??'unknown')))?>"><?=e(health_label((string)($edge['health_status']??'unknown')))?></span></div><?php endforeach;?>
      </div></div></div>
    </div>
    <div class="text-end mt-3"><a class="btn btn-outline-primary btn-sm" href="<?=url('repository-property',['code'=>(string)$property['code']])?>"><i class="bi bi-gear me-1"></i>Administrar toda la instalación</a></div>
  </div></div>
</div>
<?php endforeach;?>
</div>
