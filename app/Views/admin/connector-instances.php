<?php
$definitions=$technology->connectorDefinitions();
$instances=$technology->connectorInstances();
?>
<div class="row g-3 mb-3">
  <div class="col-lg-7">
    <div class="alert alert-primary h-100 mb-0">
      <strong>Sistemas y plataformas conectadas al inmueble</strong>
      <div>Esta pantalla registra el sistema con el que Property Hub se comunica: por ejemplo, el servidor ZKBioSecurity, HikCentral, Dahua DSS, UniFi Console, Grandstream UCM o una nube de fabricante.</div>
      <div class="mt-2"><strong>No representa cada controlador o cámara.</strong> Los equipos físicos administrados por esas plataformas aparecen consolidados en “Equipos y activos”.</div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="alert alert-light border h-100 mb-0">
      <strong>Catálogo de integraciones</strong>
      <div>Es la biblioteca de marcas, plataformas y protocolos disponibles o en investigación. Que aparezcan allí no significa que estén instalados en Premier.</div>
      <a class="btn btn-sm btn-outline-primary mt-2" href="<?=url('integrations')?>">Abrir catálogo</a>
    </div>
  </div>
</div>

<div class="alert alert-warning">
  <div class="d-flex gap-2"><i class="bi bi-diagram-3 fs-4"></i><div>
    <strong>Modelo correcto para ZKTeco en Premier</strong>
    <div>Property Hub se conectará al <strong>servidor ZKBioSecurity</strong>. ZKBioSecurity seguirá conectándose y administrando los paneles inBio. Los inBio aparecerán únicamente como equipos administrados por esa plataforma, no como conexiones directas de Property Hub.</div>
  </div></div>
</div>

<div class="card">
  <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div><strong>Sistemas conectados en esta instalación</strong><small class="d-block text-body-secondary"><?=count($instances)?> sistema(s) declarado(s)</small></div>
    <?php if(can('integrations.manage')):?><button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#connectorModal"><i class="bi bi-plus-lg"></i> Nuevo sistema conectado</button><?php endif;?>
  </div>
  <div class="card-body p-0">
    <div class="table-responsive"><table class="table table-hover align-middle mb-0">
      <thead><tr><th>Integración</th><th>Destino real</th><th>Producto / versión</th><th>API / endpoint</th><th>Equipos administrados</th><th>Estado</th><th class="text-end">Acciones</th></tr></thead>
      <tbody>
      <?php foreach($instances as $i):?>
        <tr>
          <td><strong><?=e($i['name'])?></strong><small class="d-block text-body-secondary"><?=e($i['connector_name'])?> · <?=e(connector_domain_label((string)$i['domain']))?></small></td>
          <td><span class="badge text-bg-light border"><?=e(connector_target_type_label((string)($i['target_type']??'platform_server')))?></span><small class="d-block text-body-secondary mt-1"><?=!empty($i['manages_downstream_assets'])?'Administra equipos aguas abajo':'No administra inventario aguas abajo'?></small></td>
          <td><?=e(trim(($i['platform_product']??'').' '.($i['platform_version']??''))?:'Pendiente de identificar')?><small class="d-block text-body-secondary"><?=e($i['integration_mode']?:'Modalidad no definida')?></small></td>
          <td><?=e(trim(($i['api_family']??'').' '.($i['api_version']??''))?:'API por verificar')?><small class="d-block text-body-secondary text-break"><?=e($i['endpoint_url']?:'Sin endpoint')?></small></td>
          <td><?php $physical=max((int)($i['actual_device_count']??0),(int)($i['discovered_device_count']??0));$doors=(int)($i['discovered_door_count']??0);$readers=(int)($i['discovered_reader_count']??0);$adopted=(int)($i['adopted_access_count']??0);?><strong><?=e((string)$physical)?></strong><small class="d-block text-body-secondary"><?= $doors||$readers ? e($doors.' puertas · '.$readers.' lectores') : 'Calculado desde el inventario' ?></small><?php if($adopted):?><small class="d-block text-success"><?=e($adopted)?> acceso(s) vinculado(s) a Property</small><?php endif;?></td>
          <td><span class="badge text-bg-<?=e(status_badge((string)$i['status']))?>"><?=e(status_label((string)$i['status']))?></span><small class="d-block text-body-secondary"><?=e($i['environment'])?></small></td>
          <td class="text-end text-nowrap"><?php if(can('integrations.manage')):?><button class="btn btn-sm btn-outline-primary js-edit-connector" data-bs-toggle="modal" data-bs-target="#connectorModal" data-json='<?=e(json_encode($i,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))?>'><i class="bi bi-pencil"></i> Editar</button> <?php if(can('technical.delete')):?><form class="d-inline" method="post" action="<?=url('connector-instance-delete')?>"><?=csrf_field()?><input type="hidden" name="id" value="<?=e($i['id'])?>"><button class="btn btn-sm btn-outline-danger" data-confirm="Si tiene equipos relacionados se deshabilitará. ¿Continuar?"><i class="bi bi-trash"></i></button></form><?php endif;?><?php endif;?></td>
        </tr>
      <?php endforeach;?>
      <?php if(!$instances):?><tr><td colspan="7"><?=empty_state('No hay sistemas conectados','Crea primero el sistema o plataforma real con el que se comunicará Property Hub. Para Premier, el primero será el servidor ZKBioSecurity.','bi bi-server')?></td></tr><?php endif;?>
      </tbody>
    </table></div>
  </div>
</div>

<?php if(can('integrations.manage')):?>
<div class="modal fade" id="connectorModal" tabindex="-1"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
<form method="post" action="<?=url('connector-instance-save')?>"><?=csrf_field()?><input type="hidden" name="id" id="connectorId">
  <div class="modal-header"><h5 class="modal-title" id="connectorTitle">Nuevo sistema conectado</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body">
    <div class="row g-3">
      <div class="col-md-6"><label class="form-label">Integración del catálogo</label><select class="form-select" name="connector_definition_id" id="connectorDefinition" required><option value="">Seleccione</option><?php foreach($definitions as $d):?><option value="<?=e($d['id'])?>" data-code="<?=e($d['code'])?>"><?=e($d['name'])?> · <?=e(connector_domain_label((string)$d['domain']))?> · v<?=e($d['version'])?></option><?php endforeach;?></select></div>
      <div class="col-md-6"><label class="form-label">Nombre local</label><input class="form-control" name="name" id="connectorName" placeholder="Servidor ZKBioSecurity Premier" required></div>
      <div class="col-md-4"><label class="form-label">Tipo de destino</label><select class="form-select" name="target_type" id="connectorTarget"><option value="platform_server">Servidor o plataforma local</option><option value="cloud_platform">Plataforma cloud</option><option value="protocol_service">Servicio de protocolo</option><option value="direct_device">Dispositivo directo</option><option value="technology_gateway">Gateway tecnológico</option><option value="other">Otro</option></select></div>
      <div class="col-md-4"><label class="form-label">Producto o plataforma</label><input class="form-control" name="platform_product" id="connectorProduct" placeholder="ZKBioSecurity"></div>
      <div class="col-md-4"><label class="form-label">Versión / build</label><input class="form-control" name="platform_version" id="connectorPlatformVersion" placeholder="Pendiente de identificar"></div>
      <div class="col-md-4"><label class="form-label">Modalidad de integración</label><input class="form-control" name="integration_mode" id="connectorMode" placeholder="API local / lectura controlada"></div>
      <div class="col-md-3"><label class="form-label">Familia API</label><input class="form-control" name="api_family" id="connectorApi" placeholder="ZKBioSecurity API"></div>
      <div class="col-md-2"><label class="form-label">Versión API</label><input class="form-control" name="api_version" id="connectorApiVersion"></div>
      <div class="col-md-3"><label class="form-label">Autenticación</label><select class="form-select" name="authentication_mode" id="connectorAuth"><option value="local_secret_vault">Secreto cifrado local</option><option value="oauth">OAuth</option><option value="api_key">API Key</option><option value="certificate">Certificado</option><option value="database_readonly">Base de datos solo lectura</option><option value="none">Sin autenticación</option></select></div>
      <div class="col-md-8"><label class="form-label">Endpoint del servidor, plataforma o servicio</label><input class="form-control" name="endpoint_url" id="connectorEndpoint" placeholder="https://IP-DEL-SERVIDOR-ZK/... o URL cloud"></div>
      <div class="col-md-2"><label class="form-label">Entorno</label><select class="form-select" name="environment" id="connectorEnvironment"><option value="production">Producción</option><option value="test">Prueba</option><option value="sandbox">Sandbox</option></select></div>
      <div class="col-md-2"><label class="form-label">Estado</label><select class="form-select" name="status" id="connectorStatus"><option value="configured">Configurado</option><option value="planned">Planificado</option><option value="online">En línea</option><option value="offline">Sin conexión</option><option value="warning">Advertencia</option><option value="error">Error</option><option value="disabled">Deshabilitado</option></select><small class="text-body-secondary">Configurado/operativo habilita el driver en Edge; Planificado o Deshabilitado lo oculta.</small></div>
      <div class="col-md-6"><label class="form-check form-switch"><input class="form-check-input" type="checkbox" name="manages_downstream_assets" id="connectorManages" checked><span class="form-check-label">La plataforma administra equipos aguas abajo</span></label><small class="text-body-secondary">Ejemplo: ZKBioSecurity administra inBio, lectores, puertas y eventos.</small></div>
      <div class="col-md-6"><label class="form-label">Política de conexión directa a equipos</label><select class="form-select" name="direct_device_policy" id="connectorDirectPolicy"><option value="not_applicable">No aplica</option><option value="forbidden">Prohibida para esta integración</option><option value="optional">Opcional según modelo</option><option value="required">Requerida</option></select></div>
      <div class="col-12"><div class="alert alert-warning mb-0" id="zkNotice"><strong>ZKTeco Premier:</strong> esta integración debe apuntar al servidor ZKBioSecurity. La conexión directa a paneles inBio queda bloqueada en este perfil. La versión y el alcance real de la API siguen pendientes de validar con la documentación exacta de la instalación.</div></div>
    </div>
  </div>
  <div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary">Guardar sistema conectado</button></div>
</form></div></div></div>
<script>
document.addEventListener('DOMContentLoaded',()=>{
  const modal=document.getElementById('connectorModal');
  const definition=document.getElementById('connectorDefinition');
  const applyDefinitionRules=()=>{
    const code=definition.options[definition.selectedIndex]?.dataset.code||'';
    const isZk=code==='zkteco'||code==='zkteco_biosecurity';
    document.getElementById('zkNotice').classList.toggle('d-none',!isZk);
    if(isZk){
      document.getElementById('connectorTarget').value='platform_server';
      document.getElementById('connectorTarget').disabled=true;
      document.getElementById('connectorProduct').value=document.getElementById('connectorProduct').value||'ZKBioSecurity';
      document.getElementById('connectorApi').value=document.getElementById('connectorApi').value||'ZKBioSecurity API';
      document.getElementById('connectorManages').checked=true;
      document.getElementById('connectorManages').disabled=true;
      document.getElementById('connectorDirectPolicy').value='forbidden';
      document.getElementById('connectorDirectPolicy').disabled=true;
    }else{
      document.getElementById('connectorTarget').disabled=false;
      document.getElementById('connectorManages').disabled=false;
      document.getElementById('connectorDirectPolicy').disabled=false;
    }
  };
  definition.addEventListener('change',applyDefinitionRules);
  modal.addEventListener('show.bs.modal',e=>{
    const b=e.relatedTarget;let d={};if(b&&b.classList.contains('js-edit-connector')){try{d=JSON.parse(b.dataset.json||'{}')}catch(x){d={}}}
    document.getElementById('connectorTitle').textContent=d.id?'Editar sistema conectado':'Nuevo sistema conectado';
    const map={connectorId:'id',connectorDefinition:'connector_definition_id',connectorName:'name',connectorTarget:'target_type',connectorProduct:'platform_product',connectorPlatformVersion:'platform_version',connectorMode:'integration_mode',connectorApi:'api_family',connectorApiVersion:'api_version',connectorAuth:'authentication_mode',connectorEndpoint:'endpoint_url',connectorEnvironment:'environment',connectorStatus:'status',connectorDirectPolicy:'direct_device_policy'};
    for(const [el,k] of Object.entries(map)){document.getElementById(el).value=d[k]??(el==='connectorTarget'?'platform_server':el==='connectorAuth'?'local_secret_vault':el==='connectorEnvironment'?'production':el==='connectorStatus'?'configured':el==='connectorDirectPolicy'?'not_applicable':'');}
    document.getElementById('connectorManages').checked=d.id?!!Number(d.manages_downstream_assets):true;
    applyDefinitionRules();
  });
  modal.querySelector('form').addEventListener('submit',()=>{
    document.getElementById('connectorTarget').disabled=false;
    document.getElementById('connectorManages').disabled=false;
    document.getElementById('connectorDirectPolicy').disabled=false;
  });
});
</script>
<?php endif;?>
