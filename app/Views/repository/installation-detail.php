<?php $i=$installation??[];$connectors=$connectors??[];$connectorCatalog=$connectorCatalog??[];$desiredConnectors=$desiredConnectors??[];$desiredByCode=[];foreach((array)($desiredConnectorState['items']??[]) as $d){if(is_array($d)&&!empty($d['code']))$desiredByCode[(string)$d['code']]=$d;}$infra=(array)($i['infrastructure']??[]);$tokens=$tokens??[];$relinkToken=$relinkToken??null;$edgeCentralToken=$edgeCentralToken??null;$deploymentCenter=$deploymentCenter??[];$edgeEnrollment=$edgeEnrollment??null;$role=(string)($i['instance_role']??'building_node'); ?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3"><div><a href="<?=url('repository-installations')?>" class="text-decoration-none"><i class="bi bi-arrow-left"></i> Portafolio</a><h4 class="mt-2 mb-1"><?=e($i['installation_name']??'')?></h4><div class="text-body-secondary"><code><?=e($i['installation_code']??'')?></code> · <span class="badge text-bg-<?=$role==='edge_agent'?'warning':'primary'?>"><?=e(installation_role_label($role))?></span> · <?=e(installation_type_label((string)($i['installation_type']??'other')))?><?php if(!empty($i['is_demo'])):?><span class="badge text-bg-info ms-2">DATOS DEMO</span><?php endif;?><?php $env=(string)($i['environment_type']??'production');?><span class="badge ms-2 text-bg-<?=$env==='production'?'success':($env==='lab'?'warning':'secondary')?>"><?=e($env==='production'?'PRODUCCIÓN':($env==='lab'?'LABORATORIO':'PRUEBA'))?></span><?php if(!empty($i['clone_source_code'])):?><span class="ms-2">copia de <code><?=e((string)$i['clone_source_code'])?></code></span><?php endif;?><?php if($role==='edge_agent'):?><span class="ms-2">propiedad padre <code><?=e((string)($i['parent_installation_code']??'—'))?></code></span><?php endif;?></div></div><div class="d-flex gap-2"><span class="badge fs-6 text-bg-<?=e(health_badge((string)($i['health_status']??'unknown')))?>"><?=e(health_label((string)($i['health_status']??'unknown')))?></span><?php if(!empty($i['base_url'])):?><a class="btn btn-outline-primary" href="<?=e($i['base_url'])?>" target="_blank" rel="noopener">Abrir <?=e($role==='edge_agent'?'Agent':'Node')?></a><?php endif;?></div></div>
<div class="alert alert-light border"><i class="bi bi-info-circle me-2"></i><?php if($role==='edge_agent'):?>Este Edge Agent conserva localmente las credenciales de ZKBio, ONVIF/RTSP y demás infraestructura LAN. Central recibe solo estado, inventario técnico y respaldos cifrados.<?php else:?>El Property Node es el sistema maestro de la propiedad. La configuración sensible local no debe enviarse en texto legible al Central.<?php endif;?></div>
<?php if(is_array($relinkToken)):?>
<div class="alert alert-warning"><strong>Token de reconexión generado.</strong><p class="mb-2 mt-1">Péguelo en esta instalación desde <em>Sistema local → Vínculo con Property Hub Central → Registrar o renovar vínculo</em>.</p><code class="d-block p-3 rounded bg-dark text-white text-break fs-6"><?=e($relinkToken['registration_token'])?></code><small class="d-block mt-2">Vence <?=e(format_datetime_es((string)$relinkToken['expires_at']))?> y se muestra una sola vez. La ficha, métricas e historial central se conservarán.</small></div>
<?php endif;?>
<div class="alert alert-info"><strong>Cadena de distribución:</strong> Central define la propiedad, el Node y los Edge esperados. El Edge sigue conectándose únicamente a su Property Node; un token emitido por Central es reclamado por el Node durante el enrolamiento, sin entregar credenciales Central al Edge.</div>
<?php if($role==='building_node'):?>
<div class="card mb-3 border-primary"><div class="card-header d-flex justify-content-between align-items-center"><strong><i class="bi bi-diagram-3 me-2"></i>Centro de despliegue</strong><span class="badge text-bg-primary">Fuente de verdad: Central</span></div><div class="card-body">
  <?php if(is_array($edgeEnrollment)):?><div class="alert alert-warning"><strong>Datos de instalación del Edge — se muestra el token una sola vez</strong><dl class="row mt-2 mb-2"><dt class="col-md-3">Propiedad</dt><dd class="col-md-9"><code><?=e((string)$edgeEnrollment['property_code'])?></code> · <?=e((string)$edgeEnrollment['property_name'])?></dd><dt class="col-md-3">Código Edge</dt><dd class="col-md-9"><code><?=e((string)$edgeEnrollment['agent_code'])?></code></dd><dt class="col-md-3">Nombre Edge</dt><dd class="col-md-9"><?=e((string)$edgeEnrollment['agent_name'])?></dd><dt class="col-md-3">URL Property Node</dt><dd class="col-md-9"><code><?=e((string)($edgeEnrollment['property_node_url']?:'Aún no reportada'))?></code></dd></dl><label class="form-label">Token de enrolamiento</label><code class="d-block p-3 rounded bg-dark text-white text-break fs-6"><?=e((string)$edgeEnrollment['token'])?></code><small class="d-block mt-2">Vence <?=e(format_datetime_es((string)$edgeEnrollment['expires_at']))?>. En el instalador Edge use exactamente el código Edge, la URL del Node y este token. No introduzca código de propiedad padre.</small></div><?php endif;?>
  <div class="row g-3"><div class="col-lg-6"><div class="border rounded p-3 h-100"><h6>Propiedad / Property Node</h6><dl class="row mb-0"><dt class="col-5">Property Code</dt><dd class="col-7"><code><?=e((string)($deploymentCenter['property_code']??$i['installation_code']))?></code></dd><dt class="col-5">Node Code</dt><dd class="col-7"><code><?=e((string)($deploymentCenter['node_code']??$i['installation_code']))?></code></dd><dt class="col-5">URL Central</dt><dd class="col-7 text-break"><code><?=e((string)($deploymentCenter['central_url']??''))?></code></dd><dt class="col-5">URL Node</dt><dd class="col-7 text-break"><code><?=e((string)(($deploymentCenter['node_url']??'')?:'Pendiente de reporte'))?></code></dd><dt class="col-5">Estado</dt><dd class="col-7"><span class="badge text-bg-<?=empty($i['last_seen_at'])?'warning':'success'?>"><?=empty($i['last_seen_at'])?'Pendiente':'Vinculado'?></span></dd></dl></div></div>
  <div class="col-lg-6"><div class="border rounded p-3 h-100"><h6>Agregar Edge Agent</h6><form method="post" action="<?=url('repository-create-edge-enrollment')?>"><?=csrf_field()?><input type="hidden" name="installation_id" value="<?=(int)$i['id']?>"><label class="form-label small">Nombre descriptivo</label><input class="form-control mb-2" name="agent_name" placeholder="Edge Principal"><label class="form-label small">Código Edge <span class="text-body-secondary">(opcional; se genera automáticamente)</span></label><input class="form-control text-uppercase mb-3" name="agent_code" placeholder="<?=e((string)$i['installation_code'])?>-EDGE-01"><button class="btn btn-primary w-100"><i class="bi bi-plus-circle me-2"></i>Generar datos de instalación Edge</button></form></div></div></div>
  <?php $edgeRows=(array)($deploymentCenter['edges']??[]);if($edgeRows):?><hr><h6>Edge Agents preparados</h6><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Código</th><th>Nombre</th><th>Estado</th><th>Vence / reclamado</th></tr></thead><tbody><?php foreach($edgeRows as $edge):?><tr><td><code><?=e((string)$edge['edge_code'])?></code></td><td><?=e((string)$edge['edge_name'])?></td><td><span class="badge text-bg-<?=($edge['status']??'')==='claimed'?'success':(($edge['status']??'')==='pending'?'warning':'secondary')?>"><?=e((string)$edge['status'])?></span></td><td><?=e(format_datetime_es((string)(($edge['claimed_at']??'')?:$edge['expires_at'])))?></td></tr><?php endforeach;?></tbody></table></div><?php endif;?>
</div></div>
<?php endif;?>
<div class="row g-3 mb-3">
<?php foreach([['Usuarios',$i['user_count']??0,'bi-people'],['Unidades',$i['unit_count']??0,'bi-grid-3x3-gap'],['Cámaras',$i['camera_count']??0,'bi-camera-video'],['Accesos',$i['access_point_count']??0,'bi-door-open'],['Conectores',$i['connector_count']??0,'bi-plug'],['Cola',$i['queue_depth']??0,'bi-hourglass-split']] as [$l,$v,$icon]):?><div class="col-6 col-lg-4 col-xl-2"><div class="card h-100"><div class="card-body"><i class="bi <?=e($icon)?> text-primary fs-4"></i><div class="fs-3 fw-semibold"><?=number_format((int)$v,0,',','.')?></div><small class="text-body-secondary"><?=e($l)?></small></div></div></div><?php endforeach;?></div>
<?php if($infra): ?><div class="card mb-3"><div class="card-header"><strong>Topología reportada</strong></div><div class="card-body"><div class="row g-3"><?php foreach([
['Zonas / sedes',$infra['location_count']??0,'bi-map'],['Garitas',$infra['guard_post_count']??0,'bi-shield-check'],['Enlaces',$infra['connectivity_link_count']??0,'bi-diagram-3'],['Gateways LoRaWAN',$infra['lorawan_gateway_count']??0,'bi-router'],['Dispositivos IoT',$infra['lorawan_device_count']??0,'bi-broadcast'],['Alertas IoT',$infra['lorawan_warning_count']??0,'bi-exclamation-triangle']
] as [$label,$value,$icon]):?><div class="col-6 col-lg-2"><div class="border rounded p-3 h-100"><i class="bi <?=e($icon)?> text-primary"></i><div class="fs-4 fw-semibold"><?=e($value)?></div><small class="text-body-secondary"><?=e($label)?></small></div></div><?php endforeach;?></div><p class="small text-body-secondary mb-0 mt-3"><strong>Arquitectura:</strong> un servidor principal por instalación y comunicación directa con garitas, controladores, plataformas y dispositivos. Property Edge requerido: <strong>No</strong>.</p></div></div><?php endif; ?>
<div class="card mb-3"><div class="card-header d-flex justify-content-between align-items-center"><strong><i class="bi bi-plug me-2"></i>Conectores habilitados para esta instalación</strong><span class="badge text-bg-primary">Central → Node → Edge</span></div><div class="card-body"><form method="post" action="<?=url('repository-installation-connectors-save')?>"><?php $desiredSource=(string)($desiredConnectorState['source']??'');?><div class="alert alert-light border py-2 small"><strong>Fuente efectiva:</strong> <?=e((string)(($desiredConnectorState['source']??'')==='installation_plan_materialized'?'Plan inicial materializado':(($desiredConnectorState['source']??'')==='central_installation_configuration'?'Configuración explícita de Central':($desiredConnectorState['source']??'metadata'))))?><?php if(!empty($desiredConnectorState['materialized'])):?><br><span class="text-success"><i class="bi bi-check2-circle me-1"></i>El plan histórico fue materializado automáticamente. UI, manifiestos y distribución Edge ya usan la misma fuente de verdad.</span><?php endif;?></div><?=csrf_field()?><input type="hidden" name="installation_id" value="<?=(int)$i['id']?>"><div class="row g-2"><?php foreach($connectorCatalog as $def):$code=(string)$def['code'];$state=(array)($desiredByCode[$code]??[]);$checked=!empty($state)&&!empty($state['enabled']);$policy=(string)($state['version_policy']??'latest_compatible');$desiredVersion=(string)($state['version']??'');$constraint=(string)($state['version_constraint']??'');?><div class="col-md-6 col-xl-4"><div class="border rounded p-3 h-100"><label class="d-flex gap-2"><input class="form-check-input mt-1" type="checkbox" name="connector_codes[]" value="<?=e($code)?>" <?=$checked?'checked':''?>><span><strong><?=e((string)$def['name'])?></strong><small class="d-block text-body-secondary"><code><?=e($code)?></code> · catálogo v<?=e((string)$def['version'])?> · <?=e((string)$def['status'])?></small></span></label><div class="mt-2"><label class="form-label small mb-1">Política de versión</label><select class="form-select form-select-sm" name="connector_policy[<?=e($code)?>]"><option value="latest_compatible" <?=$policy==='latest_compatible'?'selected':''?>>Última compatible</option><option value="exact" <?=$policy==='exact'?'selected':''?>>Versión exacta</option><option value="minimum" <?=$policy==='minimum'?'selected':''?>>Mínimo</option><option value="range" <?=$policy==='range'?'selected':''?>>Rango</option></select></div><div class="row g-1 mt-1"><div class="col-5"><input class="form-control form-control-sm" name="connector_version[<?=e($code)?>]" value="<?=e($desiredVersion)?>" placeholder="0.5.1" title="Usado por versión exacta o mínimo"></div><div class="col-7"><input class="form-control form-control-sm" name="connector_constraint[<?=e($code)?>]" value="<?=e($constraint)?>" placeholder=">=0.5,<0.7" title="Usado por política Rango"></div></div></div></div><?php endforeach;?></div><div class="d-flex justify-content-between align-items-center gap-2 mt-3"><small class="text-body-secondary">La política <strong>Última compatible</strong> recibe la versión publicada más alta que cumpla Core/rol/dependencias. Use <strong>Exacta</strong> para fijar una versión, <strong>Mínimo</strong> para exigir un piso o <strong>Rango</strong> para limitar upgrades.</small><button class="btn btn-primary"><i class="bi bi-check2-circle me-1"></i>Guardar conectores</button></div></form></div></div>
<div class="row g-3"><div class="col-xl-8"><div class="card"><div class="card-header"><strong>Stack tecnológico declarado</strong></div><div class="card-body p-0"><div class="table-responsive"><table class="table mb-0 align-middle"><thead><tr><th>Dominio</th><th>Sistema</th><th>API / protocolo</th><th>Modo</th><th>Dispositivos</th><th>Estado</th></tr></thead><tbody><?php foreach($connectors as $c):?><tr><td><?=e($c['domain'])?></td><td><strong><?=e($c['display_name'])?></strong><small class="d-block text-body-secondary"><?=e($c['vendor_name'])?> · <?=e($c['product_family'])?></small></td><td><?=e($c['api_family']?:'Por definir')?><small class="d-block text-body-secondary"><?=e($c['api_version']?:'Versión según instalación')?></small></td><td><?=e($c['integration_mode']?:'-')?></td><td><?=e($c['device_count'])?></td><td><span class="badge text-bg-<?=e(status_badge((string)$c['status']))?>"><?=e(status_label((string)$c['status']))?></span></td></tr><?php endforeach;?><?php if(!$connectors):?><tr><td colspan="6" class="text-center py-4 text-body-secondary">La instalación todavía no ha reportado su inventario tecnológico.</td></tr><?php endif;?></tbody></table></div></div></div></div>
<div class="col-xl-4">
  <?php $bv=(array)($backupVault??[]);?>
  <div class="card mb-3"><div class="card-header d-flex justify-content-between align-items-center"><strong><i class="bi bi-shield-lock me-2"></i>Backup Vault</strong><a class="btn btn-sm btn-link" href="<?=url('repository-backup-vault')?>">Abrir Vault</a></div><div class="card-body"><div class="row g-2 mb-3"><div class="col-6"><small class="text-body-secondary">Última copia</small><strong class="d-block"><?=e(!empty($bv['last_archive_at'])?format_datetime_es((string)$bv['last_archive_at']):'Nunca')?></strong></div><div class="col-6"><small class="text-body-secondary">Copias</small><strong class="d-block"><?=(int)($bv['archive_count']??0)?></strong></div></div><form method="post" action="<?=url('repository-request-backup')?>"><?=csrf_field()?><input type="hidden" name="installation_id" value="<?=(int)$i['id']?>"><input type="hidden" name="return_route" value="repository-installation"><button class="btn btn-outline-primary w-100"><i class="bi bi-cloud-arrow-up me-2"></i>Solicitar respaldo completo al Node</button></form><small class="d-block text-body-secondary mt-2">La solicitud viaja en el próximo heartbeat. El Node cifra la copia con su PHRK y la envía al Central.</small></div></div>
  <?php if($role==='building_node'):?>
  <div class="card mb-3"><div class="card-header"><strong><i class="bi bi-hdd-network me-2"></i>Property Edge Agents</strong></div><div class="card-body"><p class="mb-2">Desde 0.14.53 el alta recomendada del Agent se prepara desde el <strong>Centro de despliegue de esta propiedad en Central</strong>.</p><small class="text-body-secondary">Central genera la identidad y el token temporal; durante la instalación el Edge presenta ese token al Property Node. El Node lo valida contra Central y conserva la credencial operativa local del Agent. También se mantiene la generación local de PHEDGE en el Node como vía técnica de contingencia.</small></div></div>
  <?php endif;?>
  <div class="card mb-3">
    <div class="card-header"><strong>Vínculo y versiones</strong></div>
    <div class="card-body">
      <dl class="row mb-3">
        <dt class="col-5">Core</dt><dd class="col-7"><?=e($i['app_version']??'-')?></dd>
        <dt class="col-5">API</dt><dd class="col-7"><?=e($i['api_version']??'-')?></dd>
        <dt class="col-5">UI</dt><dd class="col-7"><?=e($i['ui_version']??'-')?></dd>
        <dt class="col-5">Canal</dt><dd class="col-7"><?=e($i['update_channel']??'-')?></dd>
        <dt class="col-5">Último contacto</dt><dd class="col-7"><?=e($i['last_seen_at']??'-')?></dd>
        <dt class="col-5">Credenciales</dt><dd class="col-7"><?=e($i['last_credentials_rotated_at']??'Registro original')?></dd>
      </dl>
      <?php if($role==='building_node'):?>
      <form method="post" action="<?=url('repository-create-relink-token')?>" onsubmit="return confirm('Se revocará cualquier token de reconexión anterior y se generará uno nuevo. La instalación NO se borrará. ¿Continuar?');">
        <?=csrf_field()?><input type="hidden" name="installation_id" value="<?=e($i['id'])?>">
        <button class="btn btn-outline-primary w-100"><i class="bi bi-key me-2"></i>Generar token de reconexión</button>
      </form>
      <small class="d-block text-body-secondary mt-2">Úselo si perdió el token, reinstaló el Node o necesita renovar su vínculo con Central. Conserva esta misma ficha e historial.</small>
      <?php else:?><div class="alert alert-info small mb-0">Este registro histórico de Edge no usa reconexión directa con Central. Reempareje el Agent desde su Property Node con un PHEDGE.</div><?php endif;?>
    </div>
  </div>

  <div class="card mb-3" id="admin-actions">
    <div class="card-header"><strong><i class="bi bi-diagram-2 me-2"></i>Entorno y copias de prueba</strong></div>
    <div class="card-body">
      <form method="post" action="<?=url('repository-set-installation-environment')?>" class="row g-2 mb-3">
        <?=csrf_field()?><input type="hidden" name="installation_id" value="<?=(int)$i['id']?>">
        <div class="col-7">
          <label class="form-label small">Clasificación</label>
          <select class="form-select form-select-sm" name="environment_type">
            <option value="production" <?=($i['environment_type']??'production')==='production'?'selected':''?>>Producción</option>
            <option value="test" <?=($i['environment_type']??'')==='test'?'selected':''?>>Prueba</option>
            <option value="lab" <?=($i['environment_type']??'')==='lab'?'selected':''?>>Laboratorio</option>
          </select>
        </div>
        <div class="col-5 d-flex align-items-end"><button class="btn btn-outline-secondary btn-sm w-100">Guardar</button></div>
      </form>

      <button class="btn btn-outline-primary w-100 mb-2" type="button" data-bs-toggle="modal" data-bs-target="#duplicateInstallationModal">
        <i class="bi bi-copy me-2"></i>Duplicar expediente
      </button>
      <small class="d-block text-body-secondary">Crea un nuevo expediente y token independiente con el mismo perfil técnico. <strong>No copia residentes ni datos operativos</strong>; para eso use un <code>.phbackup</code> en modo copia segura.</small>

      <?php $canDelete=!empty($i['is_demo'])||in_array((string)($i['environment_type']??'production'),['test','lab'],true);?>
      <?php if($canDelete):?>
        <hr>
        <button class="btn btn-outline-danger w-100" type="button" data-bs-toggle="collapse" data-bs-target="#deleteTestInstallation">
          <i class="bi bi-trash3 me-2"></i>Eliminar instalación de prueba
        </button>
        <div class="collapse mt-3" id="deleteTestInstallation">
          <div class="alert alert-danger small">
            Elimina del Central la ficha, tokens, métricas, conectores declarados e historial de entrega. El nodo físico no se desinstala: si sigue encendido perderá el vínculo con este Central.
          </div>
          <form method="post" action="<?=url('repository-delete-test-installation')?>" onsubmit="return confirm('Esta eliminación es permanente en el Central. ¿Continuar?');">
            <?=csrf_field()?><input type="hidden" name="installation_id" value="<?=(int)$i['id']?>">
            <label class="form-label small">Escriba <code><?=e((string)$i['installation_code'])?></code></label>
            <input class="form-control form-control-sm mb-2" name="confirmation" required autocomplete="off">
            <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="ack_active" value="1" id="ackActive"><label class="form-check-label small" for="ackActive">Entiendo que el nodo puede seguir encendido y quedará desvinculado.</label></div>
            <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="purge_offsite" value="1" id="purgeOffsite"><label class="form-check-label small" for="purgeOffsite">Eliminar también las copias cifradas de este nodo guardadas en el Central.</label></div>
            <button class="btn btn-danger btn-sm w-100">Eliminar definitivamente del Central</button>
          </form>
        </div>
      <?php else:?>
        <div class="alert alert-light border small mt-3 mb-0"><i class="bi bi-shield-check me-1"></i>Las instalaciones de <strong>Producción</strong> no se pueden eliminar físicamente. Si este registro es realmente de prueba, cambie primero su clasificación.</div>
      <?php endif;?>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-header"><strong>Tokens de reconexión</strong></div>
    <div class="card-body p-0"><div class="table-responsive"><table class="table table-sm mb-0 align-middle">
      <thead><tr><th>Creado</th><th>Uso</th><th>Estado</th><th></th></tr></thead>
      <tbody>
      <?php foreach($tokens as $token):?><tr>
        <td><?=e(format_datetime_es((string)$token['created_at']))?><small class="d-block text-body-secondary">Vence <?=e($token['expires_at']?format_datetime_es((string)$token['expires_at']):'sin fecha')?></small></td>
        <td><?=e($token['used_count'].' / '.$token['max_uses'])?></td>
        <td><span class="badge text-bg-<?=($token['status']??'')==='active'?'success':(($token['status']??'')==='revoked'?'danger':'secondary')?>"><?=e($token['status'])?></span></td>
        <td><?php if(($token['status']??'')==='active'):?><form method="post" action="<?=url('repository-revoke-token')?>" onsubmit="return confirm('¿Revocar este token? El nodo y su ficha se conservarán.');"><?=csrf_field()?><input type="hidden" name="token_id" value="<?=e($token['id'])?>"><input type="hidden" name="installation_id" value="<?=e($i['id'])?>"><input type="hidden" name="return_route" value="repository-installation"><button class="btn btn-sm btn-outline-danger">Revocar</button></form><?php endif;?></td>
      </tr><?php endforeach;?>
      <?php if(!$tokens):?><tr><td colspan="4" class="text-center py-3 text-body-secondary">No se han emitido tokens de reconexión.</td></tr><?php endif;?>
      </tbody>
    </table></div></div>
  </div>

  <div class="card">
    <div class="card-header"><strong>Privacidad</strong></div>
    <div class="card-body"><p><span class="badge text-bg-primary"><?=e(data_scope_label((string)($i['data_scope']??'aggregates')))?></span></p><ul class="small ps-3 mb-0"><li>No se reciben nombres ni documentos.</li><li>No se reciben mensajes de agua, luz o mantenimiento.</li><li>Las credenciales de fabricantes permanecen en la instalación local.</li><li>El soporte detallado requerirá autorización temporal local.</li></ul></div>
  </div>
</div></div>

<div class="modal fade" id="duplicateInstallationModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Duplicar expediente de instalación</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <form method="post" action="<?=url('repository-duplicate-installation')?>">
      <?=csrf_field()?><input type="hidden" name="installation_id" value="<?=(int)$i['id']?>">
      <div class="modal-body">
        <div class="alert alert-info small"><strong>Qué duplica:</strong> tipo de inmueble, canal, alcance y tecnologías declaradas. <strong>No duplica</strong> personas, apartamentos, credenciales ni información privada del Property Node.</div>
        <div class="row g-3">
          <div class="col-md-7"><label class="form-label">Nombre de la copia</label><input class="form-control" name="installation_name" value="<?=e((string)$i['installation_name'])?> · Prueba" required></div>
          <div class="col-md-5"><label class="form-label">Código nuevo</label><input class="form-control text-uppercase" name="installation_code" value="<?=e((string)$i['installation_code'])?>-TEST" pattern="[A-Za-z0-9_-]{3,60}" required></div>
          <div class="col-md-6"><label class="form-label">Entorno</label><select class="form-select" name="environment_type"><option value="test">Prueba</option><option value="lab">Laboratorio</option></select></div>
          <div class="col-md-6 d-flex align-items-end"><div class="small text-body-secondary">Se generará un token inicial completamente nuevo para instalar otro nodo.</div></div>
        </div>
      </div>
      <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary"><i class="bi bi-copy me-2"></i>Crear copia y token</button></div>
    </form>
  </div></div>
</div>
