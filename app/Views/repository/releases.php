<?php $core=$officialCoreStatus??[];$components=$components??[]; ?>
<div class="card mb-3">
  <div class="card-header d-flex flex-wrap justify-content-between gap-2 align-items-center"><strong>Property Hub Core oficial</strong><span class="badge text-bg-<?=!empty($core['ok'])?'success':'warning'?>"><?=!empty($core['ok'])?'Publicado y sincronizado':'Requiere sincronización'?></span></div>
  <div class="card-body">
    <div class="row g-3 align-items-end">
      <div class="col-md-3"><div class="text-body-secondary small">Central instalado</div><div class="fs-5 fw-semibold"><?=e((string)($core['installed_version']??config('app.version')))?></div></div>
      <div class="col-md-4"><div class="text-body-secondary small">Release oficial</div><code><?=e((string)($core['release_code']??'No disponible'))?></code></div>
      <div class="col-md-3"><div class="text-body-secondary small">Paquete distribuible</div><div><?php if(empty($core['source_exists'])):?>✗ no encontrado<?php elseif(!empty($core['source_valid'])):?>✓ incluido y válido<?php else:?>✗ incluido pero inválido<?php endif;?></div><?php if(!empty($core['source_exists'])&&empty($core['source_valid'])&&!empty($core['source_validation_error'])):?><div class="small text-danger mt-1"><?=e((string)$core['source_validation_error'])?></div><?php endif;?></div>
      <div class="col-md-2"><form method="post" action="<?=url('repository-sync-official-core')?>"><?=csrf_field()?><button class="btn btn-primary w-100"><i class="bi bi-arrow-repeat me-1"></i>Sincronizar</button></form></div>
    </div>
    <div class="small text-body-secondary mt-3">El Core oficial no se publica manualmente. La identidad del componente es <code>property-hub-core</code> y cada versión queda bloqueada por SHA-256. Una misma versión nunca se sustituye por bytes diferentes.</div>
  </div>
</div>

<div class="card mb-3">
  <div class="card-header d-flex justify-content-between align-items-center"><strong>Registro maestro de componentes</strong><span class="badge text-bg-primary"><?=count($components)?> componentes</span></div>
  <div class="card-body p-0"><div class="table-responsive"><table class="table table-sm mb-0 align-middle"><thead><tr><th>ID</th><th>Componente</th><th>Tipo</th><th>Alias conector</th><th>Versiones</th><th>Última publicación</th></tr></thead><tbody>
  <?php foreach($components as $component):?><tr><td><code>#<?=(int)$component['id']?></code></td><td><strong><?=e((string)$component['display_name'])?></strong><small class="d-block text-body-secondary"><code><?=e((string)$component['component_code'])?></code></small></td><td><?=e((string)$component['component_type'])?></td><td><?=e((string)($component['connector_code']??'—'))?></td><td><?=number_format((int)($component['release_count']??0),0,',','.')?></td><td><?=e((string)($component['last_release_at']??'—'))?></td></tr><?php endforeach;?>
  <?php if(!$components):?><tr><td colspan="6" class="text-center py-3 text-body-secondary">El registro se materializará al ejecutar la migración 0042.</td></tr><?php endif;?>
  </tbody></table></div></div>
</div>

<div class="row g-3">
<div class="col-xl-8"><div class="card"><div class="card-header"><strong>Releases publicadas</strong></div><div class="card-body p-0"><div class="table-responsive"><table class="table mb-0 align-middle"><thead><tr><th>Componente</th><th>Versión</th><th>Canales</th><th>Estado</th><th>SHA-256</th><th>Promover</th></tr></thead><tbody>
<?php foreach (($releases??[]) as $item): $channels=(array)($item['channels']??[$item['channel']]); ?><tr>
<td><strong><?=e((string)$item['display_name'])?></strong><small class="d-block text-body-secondary"><?=e((string)$item['component_type'])?> · <code><?=e((string)$item['component_code'])?></code><?php if(!empty($item['component_id'])):?> · ID #<?=(int)$item['component_id']?><?php endif;?></small></td>
<td><strong><?=e((string)$item['version'])?></strong><?php if(!empty($item['artifact_locked_at'])):?><small class="d-block text-success"><i class="bi bi-lock-fill me-1"></i>artefacto bloqueado</small><?php endif;?></td>
<td><?php foreach($channels as $channel):?><span class="badge text-bg-light border me-1"><?=e((string)$channel)?></span><?php endforeach;?></td>
<td><span class="badge text-bg-<?=($item['status']==='published'?'success':'secondary')?>"><?=e((string)$item['status'])?></span></td>
<td><code title="<?=e((string)($item['package_hash_sha256']??''))?>"><?=e(!empty($item['package_hash_sha256'])?substr((string)$item['package_hash_sha256'],0,14).'…':'Pendiente')?></code></td>
<td><?php if(($item['status']??'')==='published'):?><form method="post" action="<?=url('repository-promote-release')?>" class="d-flex gap-1"><?=csrf_field()?><input type="hidden" name="release_code" value="<?=e((string)$item['release_code'])?>"><select class="form-select form-select-sm" name="channel"><?php foreach(['development','beta','stable','pinned'] as $ch):?><option value="<?=e($ch)?>"><?=e($ch)?></option><?php endforeach;?></select><button class="btn btn-sm btn-outline-primary" title="Asigna el mismo artefacto a otro canal sin subirlo otra vez"><i class="bi bi-arrow-up-circle"></i></button></form><?php endif;?></td>
</tr><?php endforeach;?><?php if(empty($releases)):?><tr><td colspan="6" class="text-center py-4 text-body-secondary">No hay paquetes publicados.</td></tr><?php endif;?></tbody></table></div></div></div></div>

<div class="col-xl-4"><div class="card"><div class="card-header"><strong>Publicar nueva versión</strong></div><div class="card-body">
<div class="alert alert-info small"><strong>El ZIP es la fuente de verdad.</strong> Property Hub leerá de <code>update-package.json</code> el tipo, código, nombre, versión, Core mínimo/máximo, destinos y dependencias. Esos datos ya no se escriben dos veces.</div>
<form method="post" enctype="multipart/form-data" action="<?=url('repository-publish-release')?>" class="row g-3"><?=csrf_field()?>
<div class="col-12"><label class="form-label">Componente esperado</label><select class="form-select" name="selected_component_code"><option value="__auto__">Detectar / registrar desde el ZIP</option><?php foreach($components as $component): if(($component['component_type']??'')==='core')continue;?><option value="<?=e((string)$component['component_code'])?>"><?=e((string)$component['display_name'])?> · <?=e((string)$component['component_code'])?></option><?php endforeach;?></select><small class="text-body-secondary">Si selecciona uno, el ZIP debe pertenecer exactamente a ese componente o será rechazado.</small></div>
<div class="col-12"><label class="form-label">Canal inicial</label><select class="form-select" name="channel"><option>development</option><option selected>beta</option><option>stable</option><option>pinned</option></select></div>
<div class="col-12"><label class="form-label">Paquete ZIP</label><input class="form-control" type="file" name="package" accept=".zip,application/zip" required></div>
<div class="col-12"><label class="form-label">Notas de publicación <span class="text-body-secondary">(opcional)</span></label><textarea class="form-control" name="release_notes" rows="3" placeholder="Si se deja vacío se usan las notes del update-package.json."></textarea></div>
<div class="col-12"><button class="btn btn-primary w-100"><i class="bi bi-shield-check me-1"></i>Validar, bloquear SHA-256 y publicar</button></div>
</form></div></div>
<div class="card mt-3"><div class="card-body small text-body-secondary"><strong>Inmutabilidad:</strong> si ya existe <code>componente + versión</code>, sólo se acepta exactamente el mismo SHA-256. Para cambiar bytes debe publicar una versión nueva. Los canales se promueven sin re-subir el ZIP.</div></div></div>
</div>
