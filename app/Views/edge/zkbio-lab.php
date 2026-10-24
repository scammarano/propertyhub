<?php
$cfg=(array)($zkbioConfig??[]);$catalog=(array)($zkbioCatalog??[]);$result=$zkbioLabResult??null;$history=(array)($zkbioHistory??[]);$summary=(array)($zkbioDiscoverySummary??[]);
$apiCatalog=(array)($zkbioApiCatalog??[]);$apiEndpoints=array_values((array)($apiCatalog['endpoints']??[]));$apiStats=(array)($zkbioApiCatalogStats??[]);$matrixRuns=(array)($zkbioMatrixRuns??[]);$worker=(array)($edgeCommandWorker??[]);
$riskLabels=['read_safe'=>'Lectura','mutating'=>'Escritura','physical_action'=>'Acción física','financial_action'=>'Financiero','unknown'=>'Por verificar'];
$riskBadges=['read_safe'=>'success','mutating'=>'warning','physical_action'=>'danger','financial_action'=>'danger','unknown'=>'secondary'];
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div><h4 class="mb-1"><i class="bi bi-shield-lock me-2"></i>ZKBio API Lab</h4><p class="text-body-secondary mb-0">Laboratorio local del Property Edge. El token se guarda cifrado en el Agent y nunca se envía al navegador, Node ni Central.</p></div>
  <a class="btn btn-outline-secondary" href="<?=url('edge-dashboard')?>"><i class="bi bi-arrow-left me-1"></i>Volver al Agent</a>
</div>

<div class="alert alert-info"><strong>Modo laboratorio.</strong> El catálogo completo proviene del manual oficial. El RUN automático es deliberadamente de <strong>solo lectura</strong>: las escrituras, aperturas/cierres, operaciones financieras y riesgos no confirmados se documentan y exportan, pero no se ejecutan automáticamente.</div>
<div class="card mb-3 border-success"><div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-3"><div><strong><i class="bi bi-lightning-charge-fill me-1"></i>Worker de comandos en tiempo real</strong><div class="text-body-secondary small">Necesario para que una apertura desde el móvil no espere el heartbeat de 5 minutos. Edge consulta al Node cada 1–2 segundos sin abrir puertos entrantes.</div><div class="mt-1"><span class="badge text-bg-<?=!empty($worker['running'])?'success':'warning'?>"><?=!empty($worker['running'])?'Activo':'No detectado'?></span><?php if(!empty($worker['heartbeat_at'])):?><span class="small text-body-secondary ms-2">Último pulso <?=e((string)$worker['heartbeat_at'])?></span><?php endif;?></div></div><div><form method="post" action="<?=url('edge-command-worker-start')?>" class="d-inline"><?=csrf_field()?><button class="btn btn-outline-success"><i class="bi bi-play-fill"></i> Iniciar worker</button></form><div class="small text-body-secondary mt-1">Para autoarranque Windows: <code>tools\install-edge-command-worker.bat</code> como administrador. Estado: <strong><?=e((string)($worker['startup_task']['label']??'No verificable'))?></strong>. Log: <code><?=e((string)($worker['bootstrap_log']??'storage/logs/edge-command-worker-bootstrap.log'))?></code>.</div></div></div></div>

<div class="card mb-3"><div class="card-header d-flex justify-content-between align-items-center"><div><strong>1. Conexión ZKBio CVSecurity</strong><small class="d-block text-body-secondary">La API oficial usa <code>access_token</code>. Property Edge lo inyecta exclusivamente en servidor.</small></div><span class="badge text-bg-<?=($cfg['status']??'disabled')==='online'?'success':'secondary'?>"><?=e((string)($cfg['status']??'sin configurar'))?></span></div><div class="card-body">
  <form method="post" action="<?=url('edge-zkbio-lab-save')?>" class="row g-3 align-items-end"><?=csrf_field()?>
    <div class="col-lg-7"><label class="form-label">Servidor ZKBio</label><input class="form-control" name="endpoint_url" value="<?=e((string)($cfg['endpoint_url']??'http://127.0.0.1:8098'))?>" placeholder="http://127.0.0.1:8098" required><small class="text-body-secondary">Use el puerto web/API real de CVSecurity.</small></div>
    <div class="col-lg-5"><label class="form-label">Access token</label><input class="form-control" type="password" name="access_token" autocomplete="new-password" placeholder="<?=e((string)($cfg['token_masked']??'Token API'))?>"><small class="text-body-secondary"><?=!empty($cfg['token_masked'])?'Ya existe un token cifrado. Déjelo vacío para conservarlo.':'Pegue aquí el token generado por ZKBio.'?></small></div>
    <div class="col-12"><button class="btn btn-primary"><i class="bi bi-floppy me-1"></i>Guardar configuración</button></div>
  </form>
</div></div>

<div class="card mb-3"><div class="card-header"><strong>2. Pruebas rápidas comprobadas</strong><small class="d-block text-body-secondary">Atajos para los objetos que ya estamos levantando: hardware, estructura, niveles, personas y transacciones.</small></div><div class="card-body">
  <form method="post" action="<?=url('edge-zkbio-lab-run')?>" class="row g-3"><?=csrf_field()?>
    <div class="col-lg-5"><label class="form-label">Prueba</label><select class="form-select" name="preset"><?php foreach($catalog as $code=>$spec):?><option value="<?=e((string)$code)?>"><?=e((string)$spec['label'])?> · <?=e((string)$spec['method'])?> <?=e((string)$spec['path'])?></option><?php endforeach;?></select></div>
    <div class="col-md-2"><label class="form-label">Página</label><input class="form-control" type="number" name="pageNo" value="1" min="1"></div>
    <div class="col-md-2"><label class="form-label">Tamaño</label><input class="form-control" type="number" name="pageSize" value="20" min="1" max="1000"></div>
    <div class="col-md-3 d-flex align-items-end"><button class="btn btn-success w-100"><i class="bi bi-play-circle me-1"></i>Ejecutar</button></div>
    <div class="col-md-6"><label class="form-label">PIN / PINs <span class="text-body-secondary">(opcional)</span></label><input class="form-control" name="pins" placeholder="PIN o lista"></div>
    <div class="col-md-6"><label class="form-label">Departamento(s) <span class="text-body-secondary">(opcional)</span></label><input class="form-control" name="deptCodes" placeholder="P6A, P6B..."></div>
    <div class="col-md-4"><label class="form-label">Person PIN <span class="text-body-secondary">(transacciones)</span></label><input class="form-control" name="personPin"></div>
    <div class="col-md-4"><label class="form-label">Desde</label><input class="form-control" name="startDate" placeholder="YYYY-MM-DD HH:MM:SS"></div>
    <div class="col-md-4"><label class="form-label">Hasta</label><input class="form-control" name="endDate" placeholder="YYYY-MM-DD HH:MM:SS"></div>
  </form>
  <div class="mt-3"><form method="post" action="<?=url('edge-zkbio-discover')?>"><?=csrf_field()?><input type="hidden" name="return_route" value="edge-zkbio-lab"><button class="btn btn-outline-primary"><i class="bi bi-radar me-1"></i>Aprender instalación completa</button></form></div>
</div></div>

<div class="card mb-3 border-primary" id="api-matrix"><div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2"><div><strong>3. Matriz completa del manual · RUN</strong><small class="d-block text-body-secondary">Todos los endpoints y versiones documentados se recorren caso por caso y quedan en un ZIP de diagnóstico.</small></div><span class="badge text-bg-primary"><?=(int)($apiStats['endpoints']??0)?> endpoints · <?=(int)($apiStats['run_cases']??0)?> casos · <?=(int)($apiStats['error_codes']??0)?> códigos de error</span></div><div class="card-body">
  <div class="row g-3 mb-3">
    <div class="col-lg-8"><div class="alert alert-light border h-100 mb-0"><strong>Qué hace RUN</strong><br>Ejecuta automáticamente las lecturas seguras, incluyendo variantes de compatibilidad conocidas. Los endpoints de escritura/DELETE, apertura física, cobro/pago o riesgo no confirmado quedan registrados como <code>SKIPPED_*</code> sin ser enviados. El ZIP contiene catálogo, resumen CSV/JSON y una respuesta por caso.</div></div>
    <div class="col-lg-4"><div class="border rounded p-3 h-100"><strong>Manual fuente</strong><small class="d-block text-body-secondary mt-1"><?=e((string)($apiStats['source']['title']??'ZKBio CVSecurity API'))?></small><div class="mt-2">v<?=e((string)($apiStats['source']['manual_version']??''))?> · <?=e((string)($apiStats['source']['date']??''))?></div><small class="text-body-secondary"><?=e((string)($apiStats['source']['software_version']??''))?></small></div></div>
  </div>
  <form id="zkMatrixStartForm" class="d-flex flex-wrap gap-2 align-items-center"><?=csrf_field()?>
    <button class="btn btn-primary" type="button" id="zkMatrixStart"><i class="bi bi-play-fill me-1"></i>RUN diagnóstico completo seguro</button>
    <span class="text-body-secondary small">Puede tardar varios minutos según módulos instalados y timeouts.</span>
  </form>
  <div id="zkMatrixProgress" class="border rounded p-3 mt-3 d-none">
    <div class="d-flex flex-wrap justify-content-between gap-2"><strong id="zkMatrixTitle">Preparando...</strong><span id="zkMatrixCount">0 / 0</span></div>
    <div class="progress mt-2" role="progressbar"><div class="progress-bar" id="zkMatrixBar" style="width:0%">0%</div></div>
    <div class="d-flex flex-wrap gap-2 mt-2 small"><span class="badge text-bg-success" id="zkMatrixOk">OK 0</span><span class="badge text-bg-danger" id="zkMatrixErr">Error 0</span><span class="badge text-bg-secondary" id="zkMatrixSkip">Omitidos 0</span></div>
    <div class="small text-body-secondary mt-2" id="zkMatrixLast"></div>
    <div class="mt-3" id="zkMatrixDownload"></div>
  </div>
  <?php if($matrixRuns):?><div class="mt-4"><h6>Corridas recientes</h6><div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Run</th><th>Estado</th><th>Progreso</th><th>OK</th><th>Error</th><th>Omitidos</th><th></th></tr></thead><tbody><?php foreach($matrixRuns as $run):?><tr><td><code><?=e((string)$run['run_id'])?></code></td><td><?=e((string)$run['status'])?></td><td><?=(int)$run['index']?> / <?=(int)$run['total_cases']?></td><td><?=(int)($run['counts']['success']??0)?></td><td><?=(int)($run['counts']['error']??0)?></td><td><?=(int)($run['counts']['skipped']??0)?></td><td class="text-end"><?php if(($run['status']??'')==='completed'):?><a class="btn btn-sm btn-outline-primary" href="<?=url('edge-zkbio-matrix-download',['run_id'=>(string)$run['run_id']])?>"><i class="bi bi-file-earmark-zip me-1"></i>ZIP</a><?php else:?><button class="btn btn-sm btn-outline-success js-zk-resume" type="button" data-run="<?=e((string)$run['run_id'])?>">Continuar</button><?php endif;?></td></tr><?php endforeach;?></tbody></table></div></div><?php endif;?>
</div></div>

<div class="card mb-3"><div class="card-header"><strong>4. Catálogo completo · <?=count($apiEndpoints)?> definiciones</strong><small class="d-block text-body-secondary">GET, POST y DELETE, todas las versiones/variantes que aparecen en el manual. Puede cargar cualquiera directamente en la solicitud manual.</small></div><div class="card-body">
  <div class="row g-2 mb-3"><div class="col-md-8"><input class="form-control" id="zkCatalogSearch" placeholder="Buscar endpoint, sección, módulo o nombre..."></div><div class="col-md-4"><select class="form-select" id="zkCatalogRisk"><option value="">Todos los riesgos</option><?php foreach($riskLabels as $risk=>$label):?><option value="<?=e($risk)?>"><?=e($label)?></option><?php endforeach;?></select></div></div>
  <div class="table-responsive" style="max-height:620px;overflow:auto"><table class="table table-sm table-hover align-middle" id="zkCatalogTable"><thead class="sticky-top bg-body"><tr><th>Sección</th><th>Módulo</th><th>Método</th><th>Endpoint</th><th>Riesgo</th><th></th></tr></thead><tbody><?php foreach($apiEndpoints as $idx=>$ep):$risk=(string)($ep['risk']??'unknown');?><tr data-search="<?=e(strtolower((string)($ep['manual_section'].' '.$ep['group'].' '.$ep['title'].' '.$ep['path'].' '.$ep['method'])))?>" data-risk="<?=e($risk)?>"><td><code><?=e((string)$ep['manual_section'])?></code></td><td><?=e((string)$ep['group'])?></td><td><span class="badge text-bg-<?=($ep['method']??'GET')==='GET'?'info':(($ep['method']??'')==='DELETE'?'danger':'warning')?>"><?=e((string)$ep['method'])?></span></td><td><code><?=e((string)$ep['path'])?></code><small class="d-block text-body-secondary"><?=e((string)$ep['title'])?></small><?php if(!empty($ep['compatibility_variants'])):?><small class="d-block"><strong>Variantes:</strong> <?=e(implode(', ',(array)$ep['compatibility_variants']))?></small><?php endif;?></td><td><span class="badge text-bg-<?=e($riskBadges[$risk]??'secondary')?>"><?=e($riskLabels[$risk]??$risk)?></span></td><td class="text-end"><button class="btn btn-sm btn-outline-secondary js-zk-load-endpoint" type="button" data-index="<?=$idx?>">Cargar</button></td></tr><?php endforeach;?></tbody></table></div>
</div></div>

<div class="card mb-3" id="manual-request"><div class="card-header"><strong>5. Solicitud manual avanzada</strong><small class="d-block text-body-secondary">El token se agrega automáticamente y no se muestra. El catálogo de arriba puede precargar método, ruta, query y body cuando existe plantilla estructurada.</small></div><div class="card-body">
  <form method="post" action="<?=url('edge-zkbio-lab-custom')?>" class="row g-3" id="zkManualForm"><?=csrf_field()?>
    <div class="col-md-2"><label class="form-label">Método</label><select class="form-select" name="method" id="zkManualMethod"><option>GET</option><option>POST</option><option>DELETE</option></select></div>
    <div class="col-md-6"><label class="form-label">Ruta</label><input class="form-control" name="path" id="zkManualPath" value="/api/v2/transaction/list" required></div>
    <div class="col-md-4"><label class="form-label">Query sin token</label><input class="form-control" name="query" id="zkManualQuery" value="pageNo=1&pageSize=20"></div>
    <div class="col-12"><label class="form-label">Body JSON <span class="text-body-secondary">(opcional)</span></label><textarea class="form-control font-monospace" rows="6" name="body_json" id="zkManualBody" placeholder='{"pins":"333","deptCodes":"P6A","pageNo":1,"pageSize":20}'></textarea><small class="text-body-secondary" id="zkManualDocHint"></small></div>
    <div class="col-md-8"><label class="form-label">Confirmación para POST / DELETE</label><input class="form-control" name="post_confirmation" placeholder="Escriba EJECUTAR"><small class="text-body-secondary">La confirmación evita escrituras accidentales. El RUN automático nunca usa esta vía para mutaciones.</small></div>
    <div class="col-md-4 d-flex align-items-end"><button class="btn btn-outline-warning w-100"><i class="bi bi-terminal me-1"></i>Ejecutar solicitud</button></div>
  </form>
</div></div>

<div class="card mb-3 border-info"><div class="card-header"><strong>6. Biometría / huellas</strong><small class="d-block text-body-secondary">La API confirma lectura y carga de templates. La captura directa desde el lector USB ZK requiere todavía estudiar el SDK/plugin del enrolador.</small></div><div class="card-body"><div class="row g-3"><div class="col-lg-5"><form method="post" action="<?=url('edge-zkbio-bio-read')?>" class="row g-2"><?=csrf_field()?><div class="col-8"><label class="form-label">PIN con huella</label><input class="form-control" name="pin" value="333" required></div><div class="col-4 d-flex align-items-end"><button class="btn btn-outline-info w-100">Leer templates</button></div></form><small class="text-body-secondary">Prueba recomendada también con PIN 27.</small></div><div class="col-lg-7"><form method="post" action="<?=url('edge-zkbio-bio-upload')?>" class="row g-2"><?=csrf_field()?><div class="col-md-3"><label class="form-label">Person ID / PIN</label><input class="form-control" name="person_id" required></div><div class="col-md-2"><label class="form-label">Dedo 0–9</label><input class="form-control" type="number" name="template_no" value="0" min="0" max="9"></div><div class="col-md-2"><label class="form-label">Tipo</label><select class="form-select" name="valid_type"><option value="1">Normal</option><option value="3">Coacción</option></select></div><div class="col-md-5"><label class="form-label">Confirmar</label><input class="form-control" name="confirmation" placeholder="HUELLA"></div><div class="col-12"><label class="form-label">Template ZK</label><textarea class="form-control font-monospace" name="template" rows="3" required></textarea></div><div class="col-12"><button class="btn btn-outline-info">Enviar template a ZKBio</button></div></form></div></div></div></div>

<div class="card mb-3 border-danger"><div class="card-header"><strong>7. Prueba controlada de apertura</strong><small class="d-block text-body-secondary">Escritura física real; permanece fuera del RUN automático.</small></div><div class="card-body">
  <form method="post" action="<?=url('edge-zkbio-remote-open')?>" class="row g-3 align-items-end"><?=csrf_field()?>
    <div class="col-md-6"><label class="form-label">Door ID ZKBio</label><input class="form-control" name="door_id" placeholder="ID exacto devuelto por /api/v2/door/list" required></div>
    <div class="col-md-2"><label class="form-label">Segundos</label><input class="form-control" type="number" name="interval" value="3" min="1" max="30"></div>
    <div class="col-md-2"><label class="form-label">Confirmar</label><input class="form-control" name="confirmation" placeholder="ABRIR" required></div>
    <div class="col-md-2"><button class="btn btn-danger w-100"><i class="bi bi-door-open me-1"></i>Abrir</button></div>
  </form>
</div></div>

<?php if(is_array($result)):?>
<div class="card mb-3"><div class="card-header d-flex justify-content-between"><div><strong>Respuesta de la última prueba</strong><small class="d-block text-body-secondary"><?=e((string)($result['method']??''))?> <?=e((string)($result['path']??''))?> · <?=e((string)($result['latency_ms']??0))?> ms · filas <?=e((string)($result['row_count']??0))?> / total <?=e((string)($result['total']??0))?></small></div><span class="badge text-bg-<?=!empty($result['ok'])?'success':'danger'?>"><?=!empty($result['ok'])?'OK':'ERROR'?></span></div><div class="card-body">
<?php if(!empty($result['message'])||!empty($result['error'])):?><div class="alert alert-<?=!empty($result['ok'])?'info':'danger'?>"><?=e((string)($result['message']??$result['error']??''))?></div><?php endif;?>
<?php if(!empty($result['request'])):?><details class="mb-3"><summary>Solicitud enviada (token omitido)</summary><pre class="bg-body-tertiary border rounded p-3 mt-2 small" style="max-height:280px;overflow:auto"><?=e(json_encode($result['request'],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE))?></pre></details><?php endif;?>
<details open><summary>Respuesta JSON saneada</summary><pre class="bg-dark text-light rounded p-3 mt-2 small" style="max-height:650px;overflow:auto;white-space:pre-wrap"><?=e(json_encode($result['response']??$result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE))?></pre></details>
</div></div>
<?php endif;?>

<?php if(!empty($summary['counts'])):?><div class="card mb-3"><div class="card-header"><strong>Inventario aprendido en Edge</strong></div><div class="card-body"><div class="d-flex flex-wrap gap-2"><?php foreach($summary['counts'] as $type=>$count):?><span class="badge text-bg-secondary"><?=e((string)$type)?> <?=(int)$count?></span><?php endforeach;?></div></div></div><?php endif;?>

<div class="card"><div class="card-header"><strong>Historial de pruebas</strong><small class="d-block text-body-secondary">No se guarda el access_token. Desde 0.14.16 también se redactan passwords/hashes y blobs grandes de foto/base64.</small></div><div class="card-body p-0"><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Fecha</th><th>Origen</th><th>Operación</th><th>Método</th><th>Resultado</th><th>ms</th><th>Ruta</th></tr></thead><tbody>
<?php foreach($history as $h):?><tr><td class="text-nowrap"><?=e((string)$h['created_at'])?></td><td><span class="badge text-bg-light border"><?=e((string)$h['origin'])?></span></td><td><?=e((string)$h['operation_code'])?></td><td><code><?=e((string)$h['http_method'])?></code></td><td><span class="badge text-bg-<?=($h['status']??'')==='success'?'success':'danger'?>"><?=e((string)$h['status'])?></span><?php if(!empty($h['error_message'])):?><small class="d-block text-danger"><?=e((string)$h['error_message'])?></small><?php endif;?></td><td><?=(int)$h['latency_ms']?></td><td><code><?=e((string)$h['request_path'])?></code><?php if(!empty($h['response_json'])):?><details><summary>respuesta</summary><pre class="small mt-1" style="max-width:700px;max-height:300px;overflow:auto"><?=e(json_encode(json_decode((string)$h['response_json'],true),JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE))?></pre></details><?php endif;?></td></tr><?php endforeach;?>
<?php if(!$history):?><tr><td colspan="7" class="text-center text-body-secondary py-4">Todavía no hay pruebas registradas.</td></tr><?php endif;?>
</tbody></table></div></div></div>

<script>
const zkApiCatalog=<?=json_encode($apiEndpoints,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)?>;
const matrixStartUrl=<?=json_encode(url('edge-zkbio-matrix-start'))?>;
const matrixStepUrl=<?=json_encode(url('edge-zkbio-matrix-step'))?>;
const matrixDownloadBase=<?=json_encode(url('edge-zkbio-matrix-download'))?>;
const csrfValue=<?=json_encode(csrf_token())?>;

function loadEndpointToManual(index){
  const ep=zkApiCatalog[index];if(!ep)return;
  document.getElementById('zkManualMethod').value=ep.method||'GET';
  document.getElementById('zkManualPath').value=ep.path||'';
  document.getElementById('zkManualQuery').value=ep.query_template||'';
  document.getElementById('zkManualBody').value=ep.request_body_template?JSON.stringify(ep.request_body_template,null,2):'';
  const variants=(ep.compatibility_variants||[]).join(', ');
  document.getElementById('zkManualDocHint').textContent='Manual '+(ep.manual_section||'')+' · '+(ep.title||'')+(variants?' · variantes: '+variants:'')+(ep.request_content_text?' · Request documentado incluido.':'')+(ep.response_result_text?' · Respuesta documentada incluida en catálogo/ZIP.':'');
  document.getElementById('manual-request').scrollIntoView({behavior:'smooth',block:'start'});
}
document.querySelectorAll('.js-zk-load-endpoint').forEach(b=>b.addEventListener('click',()=>loadEndpointToManual(Number(b.dataset.index))));

function filterCatalog(){const q=(document.getElementById('zkCatalogSearch').value||'').toLowerCase();const risk=document.getElementById('zkCatalogRisk').value;document.querySelectorAll('#zkCatalogTable tbody tr').forEach(tr=>{tr.style.display=(!q||tr.dataset.search.includes(q))&&(!risk||tr.dataset.risk===risk)?'':'none';});}
document.getElementById('zkCatalogSearch').addEventListener('input',filterCatalog);document.getElementById('zkCatalogRisk').addEventListener('change',filterCatalog);

let matrixRunning=false;
function matrixUi(state){
  const box=document.getElementById('zkMatrixProgress');box.classList.remove('d-none');const total=Number(state.total_cases||0),idx=Number(state.index||0),pct=total?Math.round(idx*100/total):0;
  document.getElementById('zkMatrixTitle').textContent='Run '+(state.run_id||'')+' · '+(state.status||'');document.getElementById('zkMatrixCount').textContent=idx+' / '+total;
  const bar=document.getElementById('zkMatrixBar');bar.style.width=pct+'%';bar.textContent=pct+'%';document.getElementById('zkMatrixOk').textContent='OK '+Number(state.counts?.success||0);document.getElementById('zkMatrixErr').textContent='Error '+Number(state.counts?.error||0);document.getElementById('zkMatrixSkip').textContent='Omitidos '+Number(state.counts?.skipped||0);
  const last=state.last_result||{};document.getElementById('zkMatrixLast').textContent=last.path?((last.manual_section||'')+' '+(last.method||'')+' '+last.path+' · '+(last.variant||'')+' · '+(last.status||'')):'';
  if(state.done||state.status==='completed')document.getElementById('zkMatrixDownload').innerHTML='<a class="btn btn-success" href="'+matrixDownloadBase+'&run_id='+encodeURIComponent(state.run_id)+'"><i class="bi bi-file-earmark-zip me-1"></i>Descargar ZIP completo</a>';
}
async function matrixPost(url,data){const fd=new FormData();fd.append('_csrf',csrfValue);Object.entries(data||{}).forEach(([k,v])=>fd.append(k,v));const r=await fetch(url,{method:'POST',body:fd,credentials:'same-origin'});const j=await r.json().catch(()=>({ok:false,message:'Respuesta no JSON'}));if(!r.ok||!j.ok)throw new Error(j.message||('HTTP '+r.status));return j;}
async function runMatrix(runId=''){
  if(matrixRunning)return;matrixRunning=true;document.getElementById('zkMatrixStart').disabled=true;
  try{
    let state=runId?{ok:true,run_id:runId}:await matrixPost(matrixStartUrl,{});matrixUi(state);
    while(!(state.done||state.status==='completed')){state=await matrixPost(matrixStepUrl,{run_id:state.run_id});matrixUi(state);await new Promise(r=>setTimeout(r,80));}
  }catch(e){document.getElementById('zkMatrixProgress').classList.remove('d-none');document.getElementById('zkMatrixLast').textContent='RUN detenido: '+e.message;}
  finally{matrixRunning=false;document.getElementById('zkMatrixStart').disabled=false;}
}
document.getElementById('zkMatrixStart').addEventListener('click',()=>runMatrix());document.querySelectorAll('.js-zk-resume').forEach(b=>b.addEventListener('click',()=>runMatrix(b.dataset.run||'')));
</script>
