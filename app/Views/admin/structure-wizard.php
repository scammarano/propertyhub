<?php
$wizard=$wizard??['profile'=>[],'roots'=>[],'standard_areas'=>[],'existing_areas'=>[],'history'=>[]];
$profile=$wizard['profile']??[];$roots=$wizard['roots']??[];$standardAreas=$wizard['standard_areas']??[];$existingAreas=$wizard['existing_areas']??[];$existingProperties=$wizard['existing_properties']??[];$history=$wizard['history']??[];
$oldPayload=$_SESSION['structure_wizard_old_payload']??null;unset($_SESSION['structure_wizard_old_payload']);$oldPlan=is_string($oldPayload)?json_decode($oldPayload,true):null;if(!is_array($oldPlan))$oldPlan=null;
$defaultRootCode=preg_replace('/[^A-Z0-9._-]+/','-',strtoupper((string)($profile['code']??config('app.instance_code'))))?:'PROPIEDAD';
?>
<div class="alert alert-primary d-flex gap-3 align-items-start"><i class="bi bi-bezier2 fs-4"></i><div><strong>Diseñador topológico del inmueble.</strong><div>Modela torres, niveles especiales, apartamentos, estacionamientos, maleteros, áreas comunes, ascensores y accesos. Cada elemento se ubica en una torre, piso, sótano, terraza o zona general concreta.</div></div></div>
<div class="alert alert-light border"><strong>El piso es un contenedor real.</strong> Puede tener apartamentos, cowork, lavandería, cuarto de fibra, rack, fábrica de hielo, puertas, cámaras, switches, AP y otros componentes BMS. Un nivel puede existir sin apartamentos.</div>

<div class="row g-3 mb-3">
  <?php foreach([['metricTowers','Torres / edificios','bi-buildings','primary'],['metricLevels','Niveles','bi-layers','info'],['metricUnits','Unidades','bi-house-door','success'],['metricExtras','Áreas + activos','bi-grid','warning']] as [$id,$label,$icon,$tone]):?><div class="col-md-3"><div class="metric-card"><div class="metric-icon bg-<?=$tone?>-subtle text-<?=$tone?>"><i class="bi <?=$icon?>"></i></div><div><span><?=$label?></span><strong id="<?=$id?>">0</strong></div></div></div><?php endforeach;?>
</div>

<form method="post" action="<?=url('structure-wizard-generate')?>" id="structureWizardForm"><?=csrf_field()?><input type="hidden" name="wizard_payload" id="wizardPayload">
<div class="alert alert-warning d-flex align-items-start gap-2" id="wizardRuntimeStatus" role="status"><i class="bi bi-hourglass-split"></i><div><strong>Inicializando el asistente…</strong><div class="small">Si este mensaje permanece visible, recarga la página con Ctrl+F5. El error quedará registrado en los logs del servidor.</div></div></div>
<div class="card mb-3">
  <div class="card-header p-0"><div class="wizard-steps" id="wizardSteps">
    <?php foreach([[1,'Base'],[2,'Torres y niveles'],[3,'Nomenclaturas'],[4,'Estacionamientos'],[5,'Áreas'],[6,'Ascensores'],[7,'Vista previa']] as [$n,$label]):?><button type="button" <?=$n===1?'class="active"':''?> data-step-target="<?=$n?>"><span><?=$n?></span> <?=$label?></button><?php endforeach;?>
  </div></div>
  <div class="card-body">
    <div class="alert alert-danger" id="wizardValidationMessage" role="alert" hidden></div>
    <section class="wizard-panel" data-step="1">
      <h5>Estructura principal</h5><p class="text-body-secondary">Agrega el diseño a una raíz existente o crea el conjunto/inmueble principal.</p>
      <?php if($roots):?><div class="mb-3"><label class="form-label">Destino</label><div class="d-flex flex-wrap gap-3"><label class="form-check"><input class="form-check-input" type="radio" name="root_mode" value="existing" checked><span class="form-check-label">Usar estructura existente</span></label><label class="form-check"><input class="form-check-input" type="radio" name="root_mode" value="create"><span class="form-check-label">Crear otra estructura raíz</span></label></div></div><div class="row g-3" id="existingRootFields"><div class="col-md-8"><label class="form-label">Estructura existente</label><select class="form-select" id="existingRootId"><?php foreach($roots as $root):?><option value="<?=e($root['id'])?>"><?=e($root['name'])?> · <?=e(property_type_label((string)$root['property_type']))?></option><?php endforeach;?></select></div></div><?php else:?><input type="hidden" name="root_mode" value="create"><?php endif;?>
      <div class="row g-3 mt-1" id="newRootFields" <?=$roots?'hidden':''?>><div class="col-md-3"><label class="form-label">Código interno</label><input class="form-control text-uppercase" id="rootCode" value="<?=e($defaultRootCode)?>"></div><div class="col-md-6"><label class="form-label">Nombre</label><input class="form-control" id="rootName" value="<?=e($profile['name']??config('app.instance_name'))?>"></div><div class="col-md-3"><label class="form-label">Tipo</label><select class="form-select" id="rootType"><option value="site">Conjunto / inmueble</option><option value="urbanization">Urbanización</option><option value="building">Edificio</option><option value="other">Otro</option></select></div></div>
      <hr><div class="row g-3"><div class="col-md-3"><label class="form-label">Cantidad de torres o edificios</label><input type="number" class="form-control" id="towerCount" value="1" min="1" max="50"></div><div class="col-md-4"><label class="form-label">Identificación</label><select class="form-select" id="towerNaming"><option value="single">Única / sin nomenclatura</option><option value="letters">Letras: A, B, C…</option><option value="numbers">Números: 1, 2, 3…</option><option value="prefix">Prefijo: T1, T2…</option><option value="custom">Personalizada</option></select><div class="form-text">Si hay una sola torre puedes usar simplemente “Torre principal” o el nombre real, sin A ni 1.</div></div><div class="col-md-3" id="towerPrefixWrap" hidden><label class="form-label">Prefijo</label><input class="form-control text-uppercase" id="towerPrefix" value="T"></div><div class="col-md-12" id="customTowerWrap" hidden><label class="form-label">Una torre por línea</label><textarea class="form-control" id="customTowerNames" rows="4" placeholder="A|Torre A&#10;B|Torre B"></textarea><div class="form-text">Formato: <code>CÓDIGO|NOMBRE</code>.</div></div></div>
    </section>

    <section class="wizard-panel" data-step="2" hidden>
      <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3"><div><h5 class="mb-1">Torres y niveles</h5><p class="text-body-secondary mb-0">Cada torre puede tener sótanos, PB, pisos numerados, mezzanina, terraza, azotea o niveles técnicos diferentes.</p></div><button type="button" class="btn btn-sm btn-outline-primary" id="applyCommonLevels"><i class="bi bi-arrow-repeat"></i> Aplicar base común</button></div>
      <div class="row g-3 mb-3"><div class="col-md-2"><label class="form-label">Sótanos</label><input type="number" class="form-control" id="commonBasements" value="0" min="0" max="20"></div><div class="col-md-2"><label class="form-label">Pisos numerados</label><input type="number" class="form-control" id="commonFloors" value="10" min="0" max="100"></div><div class="col-md-2"><label class="form-label">Unidades base</label><input type="number" class="form-control" id="commonApartments" value="4" min="0" max="50"></div><div class="col-md-2"><label class="form-check form-switch mt-4"><input class="form-check-input" type="checkbox" id="commonGround" checked><span class="form-check-label">Planta baja</span></label></div><div class="col-md-2"><label class="form-check form-switch mt-4"><input class="form-check-input" type="checkbox" id="commonTerrace"><span class="form-check-label">Terraza</span></label></div><div class="col-md-2"><label class="form-check form-switch mt-4"><input class="form-check-input" type="checkbox" id="commonRoof"><span class="form-check-label">Azotea</span></label></div></div>
      <div class="table-responsive"><table class="table table-bordered align-middle"><thead><tr><th>Código</th><th>Nombre</th><th>Niveles</th><th>Unidades</th><th>Detalle</th></tr></thead><tbody id="towerRows"></tbody></table></div>
      <div class="alert alert-info mb-0"><i class="bi bi-info-circle me-2"></i>La configuración común es sólo una plantilla inicial. En <strong>Configurar niveles</strong> cada torre puede quedar distinta: por ejemplo Torre A con 6 pisos y Torre B con 7, además de sus propios sótanos, PB, terrazas o niveles técnicos.</div>
    </section>

    <section class="wizard-panel" data-step="3" hidden>
      <h5>Nomenclaturas independientes</h5><p class="text-body-secondary">El código documental y el nombre visible pueden seguir reglas distintas. Las variables se sustituyen completas y ya no se interfieren entre sí.</p>
      <div class="row g-3 mb-3"><div class="col-md-3"><label class="form-label">Separador predeterminado</label><select class="form-select" id="defaultSeparator"><option value="-">Guion medio (-)</option><option value="">Sin separador</option><option value="_">Guion bajo (_)</option><option value=".">Punto (.)</option></select></div><div class="col-md-9"><div class="alert alert-light border mb-0"><strong>Variable especial:</strong> usa <code>{SEP}</code> en cualquier plantilla. Otras variables: <code>{TORRE}</code>, <code>{TORRE_LETRA}</code>, <code>{TORRE_N}</code>, <code>{NIVEL}</code>, <code>{NIVEL_NUM}</code>, <code>{APTO}</code>, <code>{LETRA}</code>, <code>{N}</code> y <code>{CODIGO}</code>.</div></div></div>
      <div class="accordion" id="namingAccordion">
        <?php foreach([
          ['floor','Pisos y niveles','{TORRE}{SEP}{NIVEL}','{TIPO_NIVEL} {NIVEL} · {TORRE_NOMBRE}'],
          ['apartment','Apartamentos / unidades','{TORRE}{SEP}{NIVEL_NUM}{APTO}','Apartamento {CODIGO}'],
          ['parking','Puestos de estacionamiento','EST{SEP}{N}','Estacionamiento {N}'],
          ['storage','Maleteros / depósitos','MAL{SEP}{N}','Maletero {N}']
        ] as $i=>[$key,$namingSectionTitle,$code,$name]):?>
        <?php
          $presetOptions = match($key) {
            'floor' => ['custom'=>'Personalizada','tower_level'=>'Torre + nivel','tower_level_number'=>'Torre + número de nivel','level_only'=>'Solo nivel'],
            'apartment' => ['custom'=>'Personalizada','floor_letter'=>'Piso + letra incremental: 1A','floor_number'=>'Piso + número incremental: 101','floor_tower_letter'=>'Piso + letra de torre + letra: 1AA','letter_floor'=>'Letra incremental + piso: A1','tower_floor_letter'=>'Torre + piso + letra: A-1A','tower_floor_number'=>'Torre + piso + número: A-101'],
            'parking' => ['custom'=>'Personalizada','zone_number'=>'Zona + número','parking_number'=>'EST + número'],
            'storage' => ['custom'=>'Personalizada','zone_number'=>'Zona + número','storage_number'=>'MAL + número'],
            default => ['custom'=>'Personalizada'],
          };
        ?>
        <div class="accordion-item"><h2 class="accordion-header"><button class="accordion-button <?=$i?'collapsed':''?>" type="button" data-bs-toggle="collapse" data-bs-target="#naming-<?=$key?>"><?=e($namingSectionTitle)?></button></h2><div id="naming-<?=$key?>" class="accordion-collapse collapse <?=$i?'':'show'?>" data-bs-parent="#namingAccordion"><div class="accordion-body"><div class="row g-3">
          <div class="col-md-4"><label class="form-label">Origen de la nomenclatura</label><select class="form-select naming-source" id="<?=$key?>NamingSource"><option value="condominium_document">Documento de condominio</option><option value="internal_rule">Regla operativa interna</option><option value="mixed">Mixta / conciliada</option><option value="pending" selected>Pendiente por confirmar</option></select><div class="form-text">Permite distinguir códigos oficiales de códigos internos.</div></div>
          <div class="col-md-4"><label class="form-label">Formato frecuente</label><select class="form-select naming-preset" id="<?=$key?>Preset" data-naming-key="<?=$key?>"><?php foreach($presetOptions as $presetValue=>$presetLabel):?><option value="<?=e($presetValue)?>"><?=e($presetLabel)?></option><?php endforeach;?></select></div>
          <div class="col-md-6"><label class="form-label">Plantilla de código</label><input class="form-control text-uppercase naming-code" id="<?=$key?>CodeTemplate" value="<?=e($code)?>"></div><div class="col-md-6"><label class="form-label">Plantilla de nombre</label><input class="form-control naming-name" id="<?=$key?>NameTemplate" value="<?=e($name)?>"></div><div class="col-12"><div class="naming-examples" id="<?=$key?>Examples"></div></div></div></div></div></div>
        <?php endforeach;?>
      </div>
      <div class="row g-3 mt-2"><div class="col-md-3"><label class="form-label">Secuencia de unidades</label><select class="form-select" id="unitSequence"><option value="letters">Letras: A, B, C…</option><option value="numbers">Números: 01, 02…</option></select></div><div class="col-md-2"><label class="form-label">Dígitos de unidad</label><select class="form-select" id="unitDigits"><option value="1">1</option><option value="2" selected>2</option><option value="3">3</option></select></div><div class="col-md-2"><label class="form-label">Mayúsculas</label><select class="form-select" id="letterCase"><option value="upper">A</option><option value="lower">a</option></select></div><div class="col-md-2"><label class="form-label">Dígitos nivel</label><select class="form-select" id="floorDigits"><option value="1">1</option><option value="2" selected>2</option><option value="3">3</option></select></div></div>
    </section>

    <section class="wizard-panel" data-step="4" hidden>
      <div class="d-flex flex-wrap justify-content-between gap-2 align-items-start mb-3"><div><h5 class="mb-1">Estacionamientos y maleteros</h5><p class="text-body-secondary mb-0">Crea varias zonas: propietarios en sótano, visitantes en superficie, techados, al aire libre o maleteros por torre.</p></div><div class="d-flex gap-2"><button type="button" class="btn btn-sm btn-outline-primary" id="addParkingZone"><i class="bi bi-plus-lg"></i> Zona de estacionamiento</button><button type="button" class="btn btn-sm btn-outline-secondary" id="addStorageZone"><i class="bi bi-plus-lg"></i> Zona de maleteros</button></div></div>
      <div id="parkingZones"></div><div id="storageZones"></div><div class="alert alert-light border mb-0" id="noZonesMessage">No has agregado estacionamientos ni maleteros. Puedes continuar.</div>
    </section>

    <section class="wizard-panel" data-step="5" hidden>
      <div class="d-flex flex-wrap justify-content-between gap-2 align-items-start mb-3"><div><h5 class="mb-1">Áreas comunes, técnicas y de seguridad</h5><p class="text-body-secondary mb-0">Agrega cada área por separado y define exactamente en qué torre y nivel está. Puedes crear varios cowork, gimnasios o maleteros en ubicaciones diferentes.</p></div><div class="d-flex gap-2"><button type="button" class="btn btn-sm btn-outline-primary" id="addArea"><i class="bi bi-plus-lg"></i> Agregar área</button><button type="button" class="btn btn-sm btn-outline-secondary" id="addTypicalAreas"><i class="bi bi-stars"></i> Áreas típicas</button></div></div>
      <div id="areaRows"></div><div class="alert alert-light border mb-0" id="noAreasMessage">No has agregado áreas. Puedes crearlas después desde el CRUD.</div>
    </section>

    <section class="wizard-panel" data-step="6" hidden>
      <div class="d-flex flex-wrap justify-content-between gap-2 align-items-start mb-3"><div><h5 class="mb-1">Ascensores y circulación vertical</h5><p class="text-body-secondary mb-0">Primero define la torre real de cada ascensor y luego marca únicamente los niveles de esa torre a los que llega. Property evita mezclar pisos de otra torre; un ascensor puede ir Sótano→PB y otro recorrer toda la torre.</p></div><button type="button" class="btn btn-sm btn-outline-primary" id="addElevator"><i class="bi bi-plus-lg"></i> Agregar ascensor</button></div>
      <div id="elevatorRows"></div><div class="alert alert-light border mb-0" id="noElevatorsMessage">No has agregado ascensores. Puedes continuar y registrarlos luego.</div>
    </section>

    <section class="wizard-panel" data-step="7" hidden>
      <div class="d-flex flex-wrap justify-content-between gap-2 align-items-start mb-3"><div><h5 class="mb-1">Vista previa topológica</h5><p class="text-body-secondary mb-0">Nada se guarda hasta confirmar.</p></div><span class="badge text-bg-warning">Transacción única</span></div>
      <div class="row g-3 mb-3" id="previewSummary"></div><div class="table-responsive preview-table"><table class="table table-sm table-hover"><thead><tr><th>Tipo</th><th>Código</th><th>Nombre</th><th>Ubicación / alcance</th><th>Acción</th></tr></thead><tbody id="previewRows"></tbody></table></div><small class="text-body-secondary" id="previewLimitNote"></small>
    </section>
  </div>
  <div class="card-footer d-flex justify-content-between"><button type="button" class="btn btn-outline-secondary" id="previousStep" disabled><i class="bi bi-arrow-left"></i> Atrás</button><div class="d-flex gap-2"><a class="btn btn-outline-secondary" href="<?=url('properties')?>">Cancelar</a><button type="button" class="btn btn-primary" id="nextStep">Continuar <i class="bi bi-arrow-right"></i></button><button type="submit" class="btn btn-success" id="generateStructure" hidden data-confirm="Se crearán todos los elementos mostrados. ¿Deseas continuar?"><i class="bi bi-check2-circle"></i> Generar diseño</button></div></div>
</div></form>

<?php if($history):?><div class="card"><div class="card-header"><strong>Historial del diseñador</strong><small class="d-block text-body-secondary">Se puede deshacer mientras los elementos no tengan actividad real.</small></div><div class="card-body p-0"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Fecha</th><th>Resultado</th><th>Estado</th><th>Usuario</th><th class="text-end">Acción</th></tr></thead><tbody><?php foreach($history as $run):$summary=json_decode((string)$run['summary_json'],true)?:[];?><tr><td><?=e(date('d/m/Y H:i',strtotime((string)$run['created_at'])))?></td><td><?=e((int)($summary['towers']??0))?> torres · <?=e((int)($summary['floors']??0))?> niveles · <?=e((int)($summary['apartments']??0))?> unidades · <?=e((int)($summary['areas']??0))?> áreas · <?=e((int)($summary['vertical_transports']??0))?> ascensores</td><td><span class="badge text-bg-<?=$run['status']==='completed'?'success':($run['status']==='rolled_back'?'secondary':'warning')?>"><?=e($run['status'])?></span></td><td><?=e($run['created_by_name']?:'Sistema')?></td><td class="text-end"><?php if(in_array($run['status'],['completed','rollback_blocked'],true)):?><form method="post" action="<?=url('structure-wizard-rollback')?>" class="d-inline"><?=csrf_field()?><input type="hidden" name="run_id" value="<?=e($run['id'])?>"><button class="btn btn-sm btn-outline-danger" data-confirm="Solo se desharán elementos sin actividad relacionada. ¿Continuar?"><i class="bi bi-arrow-counterclockwise"></i> Deshacer</button></form><?php endif;?></td></tr><?php endforeach;?></tbody></table></div></div></div><?php endif;?>
<script>
window.PropertyStructureWizard={
  standardAreas:<?=json_encode($standardAreas,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>,
  existingAreas:<?=json_encode($existingAreas,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>,
  existingProperties:<?=json_encode($existingProperties,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>,
  initialPlan:<?=json_encode($oldPlan,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)?>,
  hasRoots:<?=$roots?'true':'false'?>,
  clientLogUrl:<?=json_encode(url('client-log'),JSON_UNESCAPED_SLASHES)?>,
  csrf:<?=json_encode(csrf_token(),JSON_UNESCAPED_SLASHES)?>,
  release:'0.14.32'
};
window.PropertyWizardReportError=function(error,phase){
  const message=error instanceof Error?error.message:String(error||'Error desconocido');
  const stack=error instanceof Error?String(error.stack||''):'';
  const status=document.getElementById('wizardRuntimeStatus');
  if(status){status.className='alert alert-danger d-flex align-items-start gap-2';status.hidden=false;status.innerHTML='<i class="bi bi-exclamation-triangle-fill"></i><div><strong>No se pudo iniciar el asistente.</strong><div class="small"></div></div>';const detail=status.querySelector('.small');if(detail)detail.textContent=message+' · Revisa storage/logs/frontend-'+new Date().toISOString().slice(0,10)+'.jsonl';}
  console.error('[Property Hub Wizard]',phase,error);
  try{
    const data=new FormData();data.append('_csrf',window.PropertyStructureWizard.csrf||'');data.append('source','structure-wizard');data.append('phase',phase||'unknown');data.append('message',message);data.append('stack',stack.slice(0,12000));data.append('url',location.href);
    if(navigator.sendBeacon){navigator.sendBeacon(window.PropertyStructureWizard.clientLogUrl,data);}else{fetch(window.PropertyStructureWizard.clientLogUrl,{method:'POST',body:data,credentials:'same-origin',keepalive:true}).catch(()=>{});}
  }catch(_ignored){}
};
window.addEventListener('error',event=>window.PropertyWizardReportError(event.error||event.message,'window.error'));
window.addEventListener('unhandledrejection',event=>window.PropertyWizardReportError(event.reason,'unhandledrejection'));
</script>
<script src="<?=asset('assets/js/property-structure-wizard.js')?>" onerror="window.PropertyWizardReportError(new Error('No se pudo cargar property-structure-wizard.js'),'script-load')"></script>
<script>
window.setTimeout(()=>{
  if(document.documentElement.dataset.propertyWizardReady!=='1'){
    window.PropertyWizardReportError(new Error('El asistente no terminó su inicialización. Puede existir una copia JavaScript en caché o un archivo incompleto.'),'startup-timeout');
  }
},1800);
</script>
