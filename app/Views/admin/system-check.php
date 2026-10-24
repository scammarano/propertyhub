<?php
$schemaStatus=null;$schemaError='';
try{$schemaStatus=(new SchemaManager(Connection::pdo()))->status();}catch(Throwable $e){$schemaError=$e->getMessage();}
$checks=[
 ['PHP '.PHP_VERSION,version_compare(PHP_VERSION,'8.1.0','>=')],
 ['Extensión cURL',extension_loaded('curl')],
 ['Extensión OpenSSL',extension_loaded('openssl')],
 ['Extensión ZIP',extension_loaded('zip')||class_exists('ZipArchive')],
 ['Extensión PDO MySQL',extension_loaded('pdo_mysql')],
 ['Carpeta storage/logs',is_dir(base_path('storage/logs'))&&is_writable(base_path('storage/logs'))],
 ['Carpeta storage/repository',!InstallationMode::isCentral()||(is_dir(base_path('storage/repository'))&&is_writable(base_path('storage/repository')))],
 ['Migraciones',is_array($schemaStatus)&&!empty($schemaStatus['ready'])],
 ['Modo demostración desactivado',!demo_mode_enabled()],
];
?>
<div class="card"><div class="card-header d-flex justify-content-between align-items-center"><strong>Diagnóstico de la instalación</strong><span class="badge text-bg-secondary"><?=e((string)config('app.version',''))?></span></div><div class="card-body">
<p class="text-body-secondary">Perfil: <strong><?=e(InstallationMode::isCentral()?'Property Hub Central':'Property Node')?></strong>. En cPanel/Linux este panel sustituye los comandos <code>.bat</code> de diagnóstico usados únicamente en Nodes Windows/XAMPP.</p>
<div class="system-check-list"><?php foreach($checks as [$label,$ok]):?><div><span><i class="bi bi-<?=$ok?'check-circle-fill text-success':'exclamation-circle-fill text-warning'?>"></i> <?=e($label)?></span><strong><?=$ok?'Correcto':'Pendiente'?></strong></div><?php endforeach;?></div>
<?php if($schemaError!==''):?><div class="alert alert-warning mt-3 mb-0"><?=e($schemaError)?></div><?php endif;?>
</div></div>
<div class="card mt-3"><div class="card-header"><strong>Estado de migraciones</strong></div><div class="card-body">
<?php if(is_array($schemaStatus)):?><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Migración</th><th>Estado</th><th>Aplicada</th></tr></thead><tbody>
<?php foreach((array)($schemaStatus['items']??[]) as $item):?><tr><td><code><?=e((string)($item['migration']??''))?></code></td><td><span class="badge text-bg-<?=($item['state']??'')==='applied'?'success':'warning'?>"><?=e((string)($item['state']??''))?></span></td><td><?=e((string)($item['executed_at']??'—'))?></td></tr><?php endforeach;?>
</tbody></table></div><?php endif;?>
</div></div>
<div class="card mt-3"><div class="card-header d-flex justify-content-between align-items-center"><strong>Logs y soporte</strong><a class="btn btn-sm btn-outline-primary" href="<?=url('support-logs-download',['days'=>7])?>"><i class="bi bi-file-earmark-zip me-1"></i>Descargar logs ZIP</a></div><div class="card-body"><p class="mb-2">Ruta: <code>storage/logs/</code></p><p class="text-body-secondary mb-2">El ZIP incluye los logs de los últimos 7 días, información básica de versión/rol y una segunda pasada de redacción de contraseñas, tokens, cookies y credenciales RTSP.</p><div class="d-flex gap-2 flex-wrap"><a class="btn btn-sm btn-outline-secondary" href="<?=url('support-logs-download',['days'=>1])?>">Últimas 24 h</a><a class="btn btn-sm btn-outline-secondary" href="<?=url('support-logs-download',['days'=>7])?>">7 días</a><a class="btn btn-sm btn-outline-secondary" href="<?=url('support-logs-download',['days'=>30])?>">30 días</a></div></div></div>
<div class="card mt-3"><div class="card-header"><strong>Datos de demostración</strong></div><div class="card-body"><p>Estado actual: <span class="badge text-bg-<?=demo_mode_enabled()?'warning':'success'?>"><?=demo_mode_enabled()?'Activado':'Desactivado'?></span></p><a class="btn btn-outline-danger" href="<?=url('system-demo-cleanup')?>"><i class="bi bi-trash3"></i> Revisar y limpiar datos demo</a></div></div>
