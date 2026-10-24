<?php
$provisioning=$provisioning??null;
$types=[
 'urbanization'=>'Urbanización','residential_complex'=>'Conjunto residencial','residential'=>'Edificio residencial',
 'office_building'=>'Edificio de oficinas','office'=>'Oficinas','shopping_center'=>'Centro comercial',
 'commercial'=>'Comercial / locales','mixed'=>'Complejo mixto','campus'=>'Campus','industrial'=>'Industrial','other'=>'Otro',
];
$technologies=[
 'zkteco'=>'ZKTeco','hikvision'=>'Hikvision','dahua'=>'Dahua','ttlock'=>'TTLock','grandstream'=>'Grandstream',
 'unifi'=>'Ubiquiti UniFi','vimar'=>'Vimar','sonoff'=>'Sonoff / eWeLink','onvif'=>'ONVIF','rtsp'=>'RTSP / Video Gateway',
 'lorawan'=>'LoRaWAN / IoT','generic_relay'=>'Relés IP / MQTT / HTTP',
];
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
  <div><a href="<?=url('repository-installations')?>" class="text-decoration-none"><i class="bi bi-arrow-left"></i> Volver al portafolio</a><h5 class="mt-2 mb-1">Preparar una nueva instalación</h5><p class="text-body-secondary mb-0">Aquí se crea el expediente del cliente y su token. La instalación física del Property Node se realiza después, en otra carpeta, servidor o dominio.</p></div>
</div>

<?php if(is_array($provisioning)):?>
<div class="alert alert-success"><strong>Instalación preparada.</strong> El token se muestra una sola vez. Guárdelo antes de salir de esta página.</div>
<div class="row g-3 mb-4">
  <div class="col-xl-7"><div class="card h-100"><div class="card-header"><strong><?=e($provisioning['installation_name'])?></strong></div><div class="card-body">
    <dl class="row mb-3">
      <dt class="col-sm-4">Código</dt><dd class="col-sm-8"><code><?=e($provisioning['installation_code'])?></code></dd>
      <dt class="col-sm-4">Tipo</dt><dd class="col-sm-8"><?=e(installation_type_label((string)$provisioning['installation_type']))?></dd>
      <dt class="col-sm-4">Canal</dt><dd class="col-sm-8"><?=e($provisioning['update_channel'])?></dd>
      <dt class="col-sm-4">Entorno</dt><dd class="col-sm-8"><?=e(match((string)($provisioning['environment_type']??'production')){'test'=>'Prueba','lab'=>'Laboratorio',default=>'Producción'})?></dd>
      <dt class="col-sm-4">Central</dt><dd class="col-sm-8"><code><?=e($provisioning['repository_url'])?></code></dd>
      <dt class="col-sm-4">Vencimiento</dt><dd class="col-sm-8"><?=e(format_datetime_es((string)$provisioning['expires_at']))?></dd>
    </dl>
    <label class="form-label">Token de registro</label>
    <div class="p-3 rounded bg-dark text-white font-monospace fs-5 text-break"><?=e($provisioning['registration_token'])?></div>
    <small class="text-body-secondary d-block mt-2">La base solo conserva el hash. Este valor no podrá recuperarse luego.</small>
  </div></div></div>
  <div class="col-xl-5"><div class="card h-100"><div class="card-header"><strong>Pasos para instalar el Property Node</strong></div><div class="card-body"><ol class="mb-3">
    <li>Copiar este mismo paquete Property Hub en la carpeta o dominio del cliente.</li>
    <li>Abrir <code>/install/</code> en esa nueva copia.</li>
    <li>Seleccionar <strong>Property Node / instalación operativa</strong>.</li>
    <li>Usar exactamente el código <code><?=e($provisioning['installation_code'])?></code>.</li>
    <li>Colocar la URL central y el token mostrado.</li>
    <li>Finalizar el instalador y enviar el primer heartbeat.</li>
  </ol><a class="btn btn-primary w-100" href="<?=url('repository-installations')?>">Ver portafolio y estado</a></div></div></div>
</div>
<?php endif;?>

<div class="row g-3">
  <div class="col-xl-8"><div class="card"><div class="card-header"><strong>Datos de la instalación</strong></div><div class="card-body">
    <form method="post" action="<?=url('repository-create-installation')?>" class="row g-3"><?=csrf_field()?>
      <div class="col-md-8"><label class="form-label">Nombre del inmueble, conjunto o urbanización</label><input class="form-control" name="installation_name" placeholder="Ej. Urbanización Terraza del Ávila" required></div>
      <div class="col-md-4"><label class="form-label">Código único</label><input class="form-control text-uppercase" name="installation_code" placeholder="TERRAZA-AVILA" pattern="[A-Za-z0-9_-]{3,60}" required><small class="text-body-secondary">Se usará para limitar el token.</small></div>
      <div class="col-md-3"><label class="form-label">Tipo</label><select class="form-select" name="installation_type"><?php foreach($types as $value=>$label):?><option value="<?=e($value)?>"><?=e($label)?></option><?php endforeach;?></select></div>
      <div class="col-md-3"><label class="form-label">Canal inicial</label><select class="form-select" name="update_channel"><option value="stable">Stable</option><option value="beta">Beta</option><option value="development">Development</option><option value="pinned">Pinned</option></select></div>
      <div class="col-md-3"><label class="form-label">Datos recibidos por el central</label><select class="form-select" name="data_scope"><option value="aggregates">Métricas agregadas</option><option value="metadata_only">Solo metadatos técnicos</option><option value="support_snapshot">Snapshot temporal de soporte</option></select></div>
      <div class="col-md-3"><label class="form-label">Entorno</label><select class="form-select" name="environment_type"><option value="production">Producción</option><option value="test">Prueba</option><option value="lab">Laboratorio</option></select><small class="text-body-secondary">Prueba/Laboratorio puede eliminarse luego desde el Central.</small></div>
      <div class="col-12"><label class="form-label">URL prevista del nodo <span class="text-body-secondary">(opcional)</span></label><input class="form-control" type="url" name="planned_base_url" placeholder="https://cliente.ejemplo.com/property/public"><small class="text-body-secondary">Debe terminar en <code>/public</code>. Puede definirse después.</small></div>
      <div class="col-12"><label class="form-label d-block">Tecnologías previstas</label><div class="row g-2"><?php foreach($technologies as $value=>$label):?><div class="col-6 col-md-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="technology[]" value="<?=e($value)?>" id="tech-<?=e($value)?>"><label class="form-check-label" for="tech-<?=e($value)?>"><?=e($label)?></label></div></div><?php endforeach;?></div><small class="text-body-secondary">Esto prepara el expediente. Las credenciales y endpoints se configuran únicamente dentro del Property Node.</small></div>
      <div class="col-12"><label class="form-label">Notas de levantamiento</label><textarea class="form-control" name="notes" rows="3" placeholder="Garitas, NVR, controladoras, enlaces, marcas ya instaladas, observaciones del cliente..."></textarea></div>
      <div class="col-12 d-flex justify-content-end"><button class="btn btn-primary"><i class="bi bi-plus-circle me-2"></i>Crear expediente y generar token</button></div>
    </form>
  </div></div></div>
  <div class="col-xl-4"><div class="card mb-3"><div class="card-header"><strong>Qué crea este proceso</strong></div><div class="card-body"><div class="architecture-list"><span><i class="bi bi-folder-plus"></i><b>Expediente</b><small>Nombre, código, tipo, tecnologías y alcance</small></span><span><i class="bi bi-key"></i><b>Token limitado</b><small>Un uso, código específico y vigencia de 30 días</small></span><span><i class="bi bi-link-45deg"></i><b>Vínculo pendiente</b><small>Se completa al instalar y registrar el nodo</small></span><span><i class="bi bi-shield-lock"></i><b>Privacidad</b><small>Sin residentes, visitantes ni credenciales de fabricantes</small></span></div></div></div>
  <div class="card"><div class="card-header"><strong>Importante</strong></div><div class="card-body"><p class="mb-0">El Central no crea por sí solo la carpeta, la base de datos ni el servidor remoto. Prepara la instalación y controla su registro. El Property Node se instala con el mismo ZIP en el servidor del cliente.</p></div></div></div>
</div>
