<?php
declare(strict_types=1);
$agents=$edgeAgents??[];$commands=$zkbioCommands??[];$inventory=$zkbioInventory??['counts'=>[],'doors'=>[],'departments'=>[],'access_levels'=>[],'persons'=>[]];$personMappings=$zkbioPersonMappings??[];$areas=$areaOptions??[];$locations=$structuralLocationOptions??[];$recon=$zkbioDepartmentReconciliation??['units'=>[],'departments'=>[]];$peopleByProperty=$zkbioPeopleByProperty??[];$profiles=$accessProfileOptions??[];
$catalog=['devices'=>['Dispositivos / controladoras','GET /api/device/accList'],'doors'=>['Puertas','GET /api/v2/door/list'],'readers'=>['Lectores','GET /api/v2/reader/list'],'departments'=>['Departamentos','POST /api/department/getDepartmentList'],'access_levels'=>['Niveles de acceso','GET /api/v2/accLevel/list'],'persons'=>['Personas','POST /api/person/getPersonList · query-only 6.7.1'],'transactions'=>['Transacciones','GET /api/v2/transaction/list']];
$decode=static function($v): array {$d=json_decode((string)$v,true);return is_array($d)?$d:[];};
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3"><div><h4 class="mb-1"><i class="bi bi-shield-lock me-2"></i>ZKBio · Integración operativa</h4><p class="text-body-secondary mb-0">Property es el modelo de negocio; el Edge habla localmente con ZKBio. El token nunca sale del Edge.</p></div><div class="d-flex gap-2"><form method="post" action="<?=url('node-zkbio-inventory-sync')?>"><?=csrf_field()?><button class="btn btn-primary"><i class="bi bi-cloud-download me-1"></i>Actualizar inventario ZK</button></form><a class="btn btn-outline-secondary" href="<?=url('zkbio-lab')?>"><i class="bi bi-arrow-clockwise"></i></a></div></div>
<div class="alert alert-success"><strong>Ya es operativo.</strong> Desde aquí puedes adoptar puertas ZK como accesos Property. Una vez adoptadas y autorizadas por perfil, la Property App puede abrirlas mediante Node → Edge → <code>door/remoteOpenById</code>. Personas, visitantes y vehículos también pueden provisionarse desde sus pantallas funcionales.</div>
<div class="row g-2 mb-3 zk-kpi-strip"><?php foreach(['device'=>'Controladoras','door'=>'Puertas','reader'=>'Lectores','department'=>'Departamentos','access_level'=>'Niveles','person'=>'Personas'] as $k=>$label):?><div class="col-6 col-md-2"><div class="card h-100"><div class="card-body py-2 px-3"><small class="text-body-secondary d-block lh-sm"><?=e($label)?></small><div class="fs-5 fw-semibold lh-sm mt-1"><?=(int)($inventory['counts'][$k]??0)?></div></div></div></div><?php endforeach;?></div>

<ul class="nav nav-pills gap-2 mb-3" id="zkOperationalTabs" role="tablist">
  <li class="nav-item" role="presentation"><button class="nav-link active" id="zk-units-tab" data-bs-toggle="tab" data-bs-target="#zk-units-pane" type="button" role="tab"><i class="bi bi-building me-1"></i>Unidades y personas <span class="badge text-bg-light border ms-1"><?=count($recon['units']??[])?></span></button></li>
  <li class="nav-item" role="presentation"><button class="nav-link" id="zk-doors-tab" data-bs-toggle="tab" data-bs-target="#zk-doors-pane" type="button" role="tab"><i class="bi bi-door-open me-1"></i>Puertas y accesos <span class="badge text-bg-light border ms-1"><?=count($inventory['doors']??[])?></span></button></li>
  <li class="nav-item" role="presentation"><button class="nav-link" id="zk-tools-tab" data-bs-toggle="tab" data-bs-target="#zk-tools-pane" type="button" role="tab"><i class="bi bi-tools me-1"></i>Herramientas / diagnóstico</button></li>
</ul>
<div class="tab-content" id="zkOperationalTabContent">
<div class="tab-pane fade show active" id="zk-units-pane" role="tabpanel" aria-labelledby="zk-units-tab" tabindex="0">
<div class="card mb-3">
  <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div><strong><i class="bi bi-diagram-3 me-2"></i>Conciliación Unidades Property ↔ Departamentos ZKBio</strong><small class="d-block text-body-secondary">Property propone coincidencias por código y también entiende formatos como <code>06-A ↔ P6A</code>. Vincular el departamento permite ver inmediatamente sus personas y sus identidades vehiculares sin copiarlas todavía.</small></div>
    <div class="d-flex flex-wrap gap-2 align-items-center">
      <div class="btn-group btn-group-sm" role="group" aria-label="Detalle de unidades"><button type="button" class="btn btn-outline-secondary js-zk-units-expand"><i class="bi bi-arrows-expand me-1"></i>Expandir</button><button type="button" class="btn btn-outline-secondary js-zk-units-collapse"><i class="bi bi-arrows-collapse me-1"></i>Colapsar</button></div>
      <span class="badge text-bg-light border"><?=(int)($recon['mapped_count']??0)?> vinculadas</span>
      <?php if((int)($recon['suggested_count']??0)>0):?><form method="post" action="<?=url('node-zkbio-department-bulk-map')?>" onsubmit="return confirm('¿Vincular automáticamente todas las coincidencias inequívocas Property ↔ ZKBio? Las unidades sin coincidencia exacta no se tocarán.');"><?=csrf_field()?><button class="btn btn-sm btn-primary"><i class="bi bi-link-45deg"></i> Vincular todas las coincidencias (<?=(int)$recon['suggested_count']?>)</button></form><?php endif;?>
      <?php if(((int)($recon['mapped_count']??0)+(int)($recon['suggested_count']??0))>0):?>
      <form method="post" action="<?=url('node-zkbio-adopt-all-found')?>" class="d-inline-flex flex-wrap align-items-center gap-2" onsubmit="return confirm('¿Adoptar TODO lo encontrado en todas las unidades vinculadas? Se procesarán personas y vehículos/RFID. Las coincidencias inequívocas pendientes también pueden vincularse primero.');">
        <?=csrf_field()?>
        <input type="hidden" name="relationship_mode" value="infer">
        <label class="form-check form-check-inline small mb-0" title="Vincula primero departamentos con coincidencia inequívoca antes de adoptar."><input class="form-check-input" type="checkbox" name="map_suggested" value="1" checked><span class="form-check-label">Vincular sugeridas</span></label>
        <label class="form-check form-check-inline small mb-0" title="Normaliza los nombres de personas después de adoptar sin modificar ZKBio."><input class="form-check-input" type="checkbox" name="normalize_names" value="1" checked><span class="form-check-label">Normalizar nombres</span></label>
        <button class="btn btn-sm btn-success text-nowrap"><i class="bi bi-check2-all"></i> Adoptar todo lo encontrado</button>
      </form>
      <?php endif;?>
    </div>
  </div>
  <div class="card-body p-0"><div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0"><thead><tr><th>Unidad Property</th><th>Departamento ZK</th><th>Contenido ZK</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
<?php foreach($recon['units']??[] as $u):
  $pid=(int)$u['id'];$current=(string)($u['zk_department_code']??'');$suggest=(string)($u['suggested_department_code']??'');$subjects=$peopleByProperty[$pid]??[];
  $zkPeople=array_values(array_filter($subjects,static fn(array $r):bool=>(string)($r['subject_type']??'person')==='person'));
  $zkVehicles=array_values(array_filter($subjects,static fn(array $r):bool=>(string)($r['subject_type']??'person')==='vehicle'));
?>
<tr class="<?=($current!==''?'table-light':'')?>">
  <td><div class="d-flex align-items-start gap-2"><?php if($current!==''&&($zkPeople||$zkVehicles)):?><button type="button" class="btn btn-sm btn-outline-secondary py-0 px-1 js-zk-unit-toggle" data-bs-toggle="collapse" data-bs-target="#zkUnitDetail-<?=$pid?>" aria-expanded="false" aria-controls="zkUnitDetail-<?=$pid?>" title="Mostrar/ocultar contenido"><i class="bi bi-chevron-right"></i></button><?php else:?><span style="width:28px"></span><?php endif;?><span><strong><?=e($u['name'])?></strong><small class="d-block text-body-secondary"><?=e($u['code'])?> · <?=e(property_type_label((string)$u['property_type']))?></small></span></div></td>
  <td><form method="post" action="<?=url('node-zkbio-department-map')?>" class="d-flex gap-2 align-items-center"><?=csrf_field()?><input type="hidden" name="property_id" value="<?=$pid?>"><select class="form-select form-select-sm" name="department_code" required><option value="">Seleccione…</option><?php foreach($recon['departments']??[] as $d):$dc=(string)($d['external_code']?:$d['external_id']);?><option value="<?=e($dc)?>" <?=($current!==''?$current:$suggest)===$dc?'selected':''?>><?=e($dc)?> · <?=e($d['name']?:$dc)?></option><?php endforeach;?></select><button class="btn btn-sm btn-outline-primary text-nowrap"><i class="bi bi-link-45deg"></i> Vincular</button></form><?php if($current===''&&$suggest!==''):?><small class="d-block text-success mt-1"><i class="bi bi-stars"></i> Coincidencia sugerida: <?=e($suggest)?></small><?php endif;?></td>
  <td><span class="me-2"><i class="bi bi-people"></i> <?=count($zkPeople)?> persona(s)</span><?php if($zkVehicles):?><span><i class="bi bi-car-front"></i> <?=count($zkVehicles)?> vehículo(s)/RFID</span><?php endif;?></td>
  <td><?=$current!==''?'<span class="badge text-bg-success">Vinculada</span>':($suggest!==''?'<span class="badge text-bg-warning">Coincidencia sugerida</span>':'<span class="badge text-bg-light border">Pendiente</span>')?></td>
  <td class="text-nowrap"><?php if($current!==''):?><form method="post" action="<?=url('node-zkbio-department-unmap')?>" class="d-inline" onsubmit="return confirm('¿Desvincular esta unidad del departamento ZKBio? No se borrarán personas ni datos.');"><?=csrf_field()?><input type="hidden" name="property_id" value="<?=$pid?>"><button class="btn btn-sm btn-outline-secondary" title="Desvincular"><i class="bi bi-unlink"></i></button></form><?php if($subjects):?><form method="post" action="<?=url('node-zkbio-adopt-department-all')?>" class="d-inline-flex gap-1 ms-1 align-items-center flex-wrap"><?=csrf_field()?><input type="hidden" name="property_id" value="<?=$pid?>"><select class="form-select form-select-sm" name="relationship_mode" title="Relación si la identidad es Persona"><option value="infer">Inferir por niveles ZK</option><option value="owner">Propietario</option><option value="tenant">Inquilino</option><option value="resident">Residente</option><option value="family">Familiar</option><option value="driver">Chofer</option><option value="maintenance">Mantenimiento</option></select><select class="form-select form-select-sm" name="access_profile_id" title="Perfil Property para personas"><option value="">Perfil automático</option><?php foreach($profiles as $pr):?><option value="<?=e($pr['id'])?>"><?=e($pr['name'])?></option><?php endforeach;?></select><label class="form-check form-check-inline small mb-0 ms-1" title="Al finalizar, corrige mayúsculas/minúsculas de las personas adoptadas sin modificar ZKBio."><input class="form-check-input" type="checkbox" name="normalize_names" value="1" checked><span class="form-check-label">Normalizar nombres</span></label><button class="btn btn-sm btn-outline-success text-nowrap" onclick="return confirm('¿Adoptar TODO el contenido de esta unidad? Property aplicará la clasificación automática: personas como Persona y las identidades exclusivamente VEHICULOS* como Vehículo/RFID.');"><i class="bi bi-check2-all"></i> Adoptar todo</button></form><form method="post" action="<?=url('node-zkbio-normalize-adopted-names')?>" class="d-inline ms-1" onsubmit="return confirm('¿Normalizar ahora los nombres de las personas Property ya adoptadas/vinculadas con ZKBio en esta unidad? No se modificará ZKBio.');"><?=csrf_field()?><input type="hidden" name="property_id" value="<?=$pid?>"><button class="btn btn-sm btn-outline-primary text-nowrap" title="Normalizar personas ya adoptadas"><i class="bi bi-type"></i> Nombres</button></form><?php endif;?><?php endif;?></td>
</tr>
<?php if($current!==''&&($zkPeople||$zkVehicles)):?>
<tr class="zk-reconciliation-detail"><td colspan="5" class="p-0 border-top-0">
  <div class="collapse zk-unit-detail" id="zkUnitDetail-<?=$pid?>">
  <div class="ms-md-4 ps-md-3 border-start border-3 border-primary-subtle py-2 pe-2">
    <form method="post" action="<?=url('node-zkbio-adopt-selected')?>" class="zk-subject-adoption-form">
      <?=csrf_field()?><input type="hidden" name="property_id" value="<?=$pid?>">
      <div class="d-flex flex-wrap justify-content-between gap-2 align-items-center mb-2">
        <div><strong><i class="bi bi-person-vcard me-1"></i>Contenido de <?=e($current)?></strong><small class="d-block text-body-secondary">Marca las identidades que quieras importar. La clasificación automática es solo una sugerencia: puedes forzar Persona o Vehículo antes de adoptar.</small></div>
        <div class="d-flex flex-wrap gap-3 align-items-center"><label class="form-check mb-0"><input type="checkbox" class="form-check-input js-zk-select-all"> <span class="form-check-label">Seleccionar todos</span></label><label class="form-check mb-0" title="Normaliza sólo las personas procesadas; no cambia vehículos ni datos fuente en ZKBio."><input type="checkbox" class="form-check-input" name="normalize_names" value="1" checked> <span class="form-check-label">Normalizar nombres al finalizar</span></label></div>
      </div>
      <div class="row g-2 mb-2">
        <?php foreach($subjects as $zs):$isVehicle=(string)($zs['subject_type']??'person')==='vehicle';$mappedPerson=(int)($zs['mapped_person_id']??0)>0;$mappedVehicle=(int)($zs['mapped_vehicle_id']??0)>0;$mapped=$mappedPerson||$mappedVehicle;$mappingLabel=$isVehicle?($mappedVehicle?'Vehículo Property':($mappedPerson?'En Property como persona':'Sugerido: vehículo')):($mappedPerson?'En Property':($mappedVehicle?'En Property como vehículo':'Sólo ZK'));$mappingStyle=$mapped?($isVehicle&&$mappedPerson?'warning':'success'):($isVehicle?'warning':'secondary');?>
        <div class="col-xxl-3 col-xl-4 col-lg-6">
          <label class="d-flex gap-2 bg-body-tertiary rounded px-2 py-1 h-100 border small <?= $mapped?'border-success-subtle':'' ?>">
            <input class="form-check-input mt-1 js-zk-subject" type="checkbox" name="pins[]" value="<?=e($zs['pin'])?>">
            <span class="flex-grow-1">
              <span class="d-flex justify-content-between gap-2">
                <strong><i class="bi <?=$isVehicle?'bi-car-front':'bi-person'?> me-1"></i><?=e($zs['name'])?></strong>
                <span class="badge text-bg-<?=e($mappingStyle)?>"><?=e($mappingLabel)?></span>
              </span>
              <code>PIN <?=e($zs['pin'])?></code>
              <?php if(!empty($zs['card_no'])):?><small class="d-block">RFID/cardNo: <code><?=e($zs['card_no'])?></code></small><?php endif;?>
              <?php if(!empty($zs['car_plate'])):?><small class="d-block">Placa: <?=e($zs['car_plate'])?></small><?php endif;?>
              <small class="d-block text-body-secondary"><?=e(implode(' · ',$zs['access_level_names']??[]))?></small>
            </span>
          </label>
        </div>
        <?php endforeach;?>
      </div>
      <div class="row g-2 align-items-end border-top pt-3">
        <div class="col-md-3"><label class="form-label small">Importar seleccionados como</label><select class="form-select form-select-sm" name="target_mode"><option value="auto">Clasificación automática</option><option value="person">Persona</option><option value="vehicle">Vehículo / RFID</option></select></div>
        <div class="col-md-3"><label class="form-label small">Relación si son personas</label><select class="form-select form-select-sm" name="relationship_mode"><option value="infer">Inferir por niveles ZK</option><option value="owner">Propietario</option><option value="tenant">Inquilino</option><option value="resident">Residente</option><option value="family">Familiar</option><option value="driver">Chofer</option><option value="security">Seguridad</option><option value="maintenance">Mantenimiento</option></select></div>
        <div class="col-md-3"><label class="form-label small">Perfil Property</label><select class="form-select form-select-sm" name="access_profile_id"><option value="">Perfil automático</option><?php foreach($profiles as $pr):?><option value="<?=e($pr['id'])?>"><?=e($pr['name'])?></option><?php endforeach;?></select></div>
        <div class="col-md-3 d-grid"><button class="btn btn-sm btn-outline-success" onclick="return confirm('¿Adoptar/reclasificar las identidades ZKBio seleccionadas? Property conservará el PIN y la relación externa.');"><i class="bi bi-check2-square"></i> Adoptar seleccionados</button></div>
      </div>
    </form>
  </div>
  </div>
</td></tr>
<?php endif;?>
<?php endforeach;?>
<?php if(empty($recon['units'])):?><tr><td colspan="5" class="text-center text-body-secondary py-4">No hay unidades Property elegibles para conciliar.</td></tr><?php endif;?>
</tbody></table></div></div>
</div>
</div>
<div class="tab-pane fade" id="zk-doors-pane" role="tabpanel" aria-labelledby="zk-doors-tab" tabindex="0">
<div class="card mb-3">
  <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
    <div><strong><i class="bi bi-door-open me-2"></i>Mapeo de puertas ZKBio → Accesos Property</strong><small class="d-block text-body-secondary">Ubica la puerta primero en la estructura real del inmueble (edificio/torre/piso/sótano). El área específica es opcional y puede crearse aquí mismo. También puedes indicar los dos espacios que la puerta separa.</small></div>
    <span class="badge text-bg-light border"><i class="bi bi-geo-alt"></i> ubicación estructural</span>
  </div>
  <div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0"><thead><tr><th>Puerta ZK</th><th>ID externo</th><th>Estado</th><th>Property / ubicación</th><th class="text-end">Acciones</th></tr></thead><tbody>
  <?php foreach($inventory['doors']??[] as $d):$mapped=(int)($d['mapped_local_id']??0)>0;$locationParts=array_values(array_filter([(string)($d['mapped_property_name']??''),(string)($d['mapped_area_name']??'')]));$locationLabel=$locationParts?implode(' › ',$locationParts):'Sin ubicación estructural';?>
    <tr>
      <td><strong><?=e($d['name']?:$d['external_code'])?></strong></td>
      <td><code class="small"><?=e($d['external_id'])?></code></td>
      <td><span class="badge text-bg-<?=$mapped?'success':'light border'?>"><?=e($d['adoption_status']??'discovered')?></span></td>
      <td><?php if($mapped):?><span class="badge text-bg-success"><?=e($d['mapped_access_name']?:('Access #'.$d['mapped_local_id']))?></span><small class="d-block text-body-secondary mt-1"><i class="bi bi-geo-alt"></i> <?=e($locationLabel)?></small><?php else:?><span class="text-body-secondary">Sin mapear</span><?php endif;?></td>
      <td class="text-end"><div class="d-inline-flex flex-wrap gap-1 justify-content-end">
        <button type="button" class="btn btn-sm btn-outline-primary js-zk-door-place" data-bs-toggle="modal" data-bs-target="#zkDoorPlacementModal"
          data-external-object-id="<?=e($d['id'])?>" data-door-name="<?=e($d['name']?:$d['external_code'])?>" data-mapped="<?=$mapped?'1':'0'?>"
          data-property-id="<?=e($d['mapped_property_id']??'')?>" data-area-id="<?=e($d['mapped_area_id']??'')?>" data-side-a-area-id="<?=e($d['mapped_side_a_area_id']??'')?>" data-side-b-area-id="<?=e($d['mapped_side_b_area_id']??'')?>">
          <i class="bi bi-geo-alt"></i> <?=$mapped?'Ubicar':'Ubicar y adoptar'?>
        </button>
        <?php if($mapped):?><a class="btn btn-sm btn-outline-success" href="<?=url('access')?>"><i class="bi bi-door-open"></i> Ver</a><form method="post" action="<?=url('node-zkbio-open-access')?>" class="d-inline" onsubmit="return confirm('¿Abrir físicamente esta puerta ZKBio ahora?');"><?=csrf_field()?><input type="hidden" name="access_point_id" value="<?=e($d['mapped_local_id'])?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-unlock"></i> Abrir prueba</button></form><?php endif;?>
      </div></td>
    </tr>
  <?php endforeach;?>
  <?php if(empty($inventory['doors'])):?><tr><td colspan="5" class="text-center text-body-secondary py-4">Pulsa <strong>Actualizar inventario ZK</strong>. El Edge devolverá controladoras, puertas, lectores, departamentos, niveles y personas sin modificar ZK.</td></tr><?php endif;?>
  </tbody></table></div>
</div>

<div class="modal fade" id="zkDoorPlacementModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content"><form method="post" action="<?=url('node-zkbio-adopt-door')?>" id="zkDoorPlacementForm"><?=csrf_field()?>
    <input type="hidden" name="external_object_id" id="zkPlaceExternalObjectId">
    <div class="modal-header"><div><h5 class="modal-title mb-0"><i class="bi bi-door-open me-2"></i><span id="zkPlaceTitle">Ubicar puerta</span></h5><small class="text-body-secondary">La ubicación estructural puede ser un piso o sótano aun cuando no exista un área específica.</small></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
      <div class="alert alert-primary py-2"><strong>1. Ubicación física mínima</strong><div class="small">Selecciona dónde está físicamente la puerta: por ejemplo <em>Edificio Premier › Sótano 1</em>. No necesitas crear un área para poder adoptarla.</div></div>
      <div class="row g-3">
        <div class="col-12"><label class="form-label">Elemento de estructura</label><select class="form-select" name="property_id" id="zkPlaceProperty"><option value="">General de la instalación / todavía sin ubicar</option><?php foreach($locations as $loc):?><option value="<?=e($loc['id'])?>"><?=e((string)$loc['label'])?> · <?=e(property_type_label((string)$loc['property_type']))?></option><?php endforeach;?></select><div class="form-text">Puede ser edificio, torre, piso, sótano, estacionamiento estructural, unidad u otro contenedor del gemelo digital.</div></div>
        <div class="col-md-7"><label class="form-label">Área / espacio específico <span class="text-body-secondary">(opcional)</span></label><select class="form-select js-zk-area-select" name="area_id" id="zkPlaceArea"><option value="">Sin área específica: usar sólo la ubicación estructural</option><?php foreach($areas as $a):?><option value="<?=e($a['id'])?>" data-property-id="<?=e($a['property_id']??'')?>"><?=e($a['name'])?> · <?=e(area_scope_label((string)($a['scope_type']??'common')))?> · <?=e(area_usage_label((string)($a['usage_type']??'general')))?><?php if(!empty($a['property_name'])):?> · <?=e($a['property_name'])?><?php endif;?></option><?php endforeach;?></select></div>
        <div class="col-md-5"><label class="form-label">Crear área aquí si no existe</label><input class="form-control" name="new_area_name" id="zkPlaceNewArea" placeholder="Ej. Lobby ascensores S1"><div class="row g-1 mt-1"><div class="col-6"><select class="form-select form-select-sm" name="new_area_scope" title="Ámbito"><option value="common">Común</option><option value="private">Privado</option><option value="technical">Técnico</option><option value="service">Servicio</option><option value="restricted">Restringido</option><option value="public">Público</option></select></div><div class="col-6"><select class="form-select form-select-sm" name="new_area_usage" title="Uso"><option value="circulation">Circulación</option><option value="lobby">Lobby</option><option value="parking">Estacionamiento</option><option value="security">Seguridad</option><option value="technical">Técnico</option><option value="general">General</option><option value="other">Otro</option></select></div></div><div class="form-text">Ámbito = quién/qué clase de espacio es; Uso = para qué sirve.</div></div>
      </div>
      <hr class="my-4">
      <div class="alert alert-light border py-2"><strong>2. ¿Qué espacios separa esta puerta? <span class="text-body-secondary fw-normal">(opcional)</span></strong><div class="small">Útil para puertas entre estacionamiento y lobby. Si todavía no tienes los espacios, puedes crearlos sin salir de aquí.</div></div>
      <div class="row g-3">
        <div class="col-md-6"><label class="form-label"><i class="bi bi-arrow-left-right me-1"></i>Lado A</label><select class="form-select js-zk-area-select" name="side_a_area_id" id="zkPlaceSideA"><option value="">Sin especificar</option><?php foreach($areas as $a):?><option value="<?=e($a['id'])?>" data-property-id="<?=e($a['property_id']??'')?>"><?=e($a['name'])?> · <?=e(area_scope_label((string)($a['scope_type']??'common')))?> · <?=e(area_usage_label((string)($a['usage_type']??'general')))?></option><?php endforeach;?></select><input class="form-control mt-2" name="side_a_new_area" placeholder="Crear, ej. Estacionamiento S1"></div>
        <div class="col-md-6"><label class="form-label"><i class="bi bi-arrow-left-right me-1"></i>Lado B</label><select class="form-select js-zk-area-select" name="side_b_area_id" id="zkPlaceSideB"><option value="">Sin especificar</option><?php foreach($areas as $a):?><option value="<?=e($a['id'])?>" data-property-id="<?=e($a['property_id']??'')?>"><?=e($a['name'])?> · <?=e(area_scope_label((string)($a['scope_type']??'common')))?> · <?=e(area_usage_label((string)($a['usage_type']??'general')))?></option><?php endforeach;?></select><input class="form-control mt-2" name="side_b_new_area" placeholder="Crear, ej. Lobby ascensores S1"></div>
      </div>
      <div class="alert alert-warning mt-3 mb-0 py-2"><i class="bi bi-info-circle me-1"></i><strong>Importante:</strong> adoptar/ubicar no crea hardware en ZK. Sólo enlaza la puerta física existente con el punto lógico Property y su posición dentro del edificio.</div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary"><i class="bi bi-link-45deg"></i> <span id="zkPlaceSubmitLabel">Ubicar y adoptar</span></button></div>
  </form></div></div>
</div>
<script>
document.addEventListener('DOMContentLoaded',()=>{
  const modal=document.getElementById('zkDoorPlacementModal'); if(!modal)return;
  const property=document.getElementById('zkPlaceProperty');
  const areaSelects=[...modal.querySelectorAll('.js-zk-area-select')];
  const filterAreas=()=>{const pid=String(property.value||''); areaSelects.forEach(sel=>{const current=String(sel.value||''); [...sel.options].forEach((opt,i)=>{if(i===0)return; const opid=String(opt.dataset.propertyId||''); opt.hidden=pid!==''&&opid!==pid;}); if(current&&sel.selectedOptions[0]?.hidden)sel.value='';});};
  property.addEventListener('change',filterAreas);
  modal.addEventListener('show.bs.modal',e=>{
    const b=e.relatedTarget; const d=b?.dataset||{};
    document.getElementById('zkPlaceExternalObjectId').value=d.externalObjectId||'';
    document.getElementById('zkPlaceTitle').textContent='Ubicar · '+(d.doorName||'Puerta ZK');
    document.getElementById('zkPlaceSubmitLabel').textContent=d.mapped==='1'?'Guardar ubicación':'Ubicar y adoptar';
    property.value=d.propertyId||''; filterAreas();
    document.getElementById('zkPlaceArea').value=d.areaId||'';
    document.getElementById('zkPlaceSideA').value=d.sideAAreaId||'';
    document.getElementById('zkPlaceSideB').value=d.sideBAreaId||'';
    document.getElementById('zkPlaceNewArea').value='';
    modal.querySelector('[name="side_a_new_area"]').value=''; modal.querySelector('[name="side_b_new_area"]').value='';
  });
});
document.addEventListener('change',e=>{if(e.target.classList.contains('js-zk-select-all')){const f=e.target.closest('form');f?.querySelectorAll('.js-zk-subject').forEach(x=>x.checked=e.target.checked);}});
</script>
</div>
<div class="tab-pane fade" id="zk-tools-pane" role="tabpanel" aria-labelledby="zk-tools-tab" tabindex="0">
<div class="alert alert-light border py-2 small"><i class="bi bi-info-circle me-1"></i>Estas herramientas son para conciliación manual, pruebas y diagnóstico. El trabajo diario de unidades y puertas está separado en las pestañas anteriores.</div>
<div class="row g-3 mb-3"><div class="col-lg-7"><div class="card h-100"><div class="card-header"><strong>Personas Property ↔ PIN ZKBio</strong><small class="d-block text-body-secondary">La conciliación automática usa unidad/departamento + nombre exacto. Aquí puedes corregir o establecer el PIN manualmente.</small></div><div class="card-body"><form method="post" action="<?=url('node-zkbio-person-map')?>" class="row g-2 align-items-end"><?=csrf_field()?><div class="col-md-7"><label class="form-label">Persona</label><select class="form-select" name="person_id" required><option value="">Seleccione…</option><?php foreach($personMappings as $p):?><option value="<?=e($p['id'])?>"><?=e($p['display_name'])?><?=!empty($p['zk_pin'])?' · PIN '.$p['zk_pin']:''?></option><?php endforeach;?></select></div><div class="col-md-3"><label class="form-label">PIN ZK</label><input class="form-control" name="zk_pin" required></div><div class="col-md-2 d-grid"><button class="btn btn-outline-primary">Vincular</button></div></form></div></div></div><div class="col-lg-5"><div class="card h-100"><div class="card-header"><strong>Modelo de prueba funcional</strong></div><div class="card-body small"><ol class="mb-0"><li>Inventario ZK.</li><li>Adoptar una puerta.</li><li>Dar esa puerta al perfil/relación.</li><li>Entrar desde el móvil → <strong>Accesos</strong> → Abrir.</li><li>Crear visita desde móvil → se registra en Property y se intenta provisionar a Visitor Management.</li><li>Crear persona desde <strong>Personas</strong> marcando “Provisionar ZKBio”.</li><li>Crear vehículo desde <strong>Vehículos</strong> o Property App.</li></ol></div></div></div></div>
<div class="card mb-3"><div class="card-header"><strong>Prueba manual Node → Edge → ZKBio</strong><small class="d-block text-body-secondary">Se conserva para diagnóstico. Para uso normal utiliza las pantallas funcionales anteriores.</small></div><div class="card-body"><form method="post" action="<?=url('node-zkbio-queue')?>" class="row g-3"><?=csrf_field()?><div class="col-lg-5"><label class="form-label">Edge</label><select class="form-select" name="agent_id" required><option value="">Seleccione…</option><?php foreach($agents as $a):?><option value="<?=(int)$a['id']?>"><?=e($a['agent_name'])?> · <?=e($a['agent_code'])?></option><?php endforeach;?></select></div><div class="col-lg-5"><label class="form-label">Recurso</label><select class="form-select" name="preset" required><?php foreach($catalog as $code=>$spec):?><option value="<?=e($code)?>"><?=e($spec[0])?> · <?=e($spec[1])?></option><?php endforeach;?></select></div><div class="col-md-1"><label class="form-label">Pág.</label><input class="form-control" type="number" name="pageNo" value="1" min="1"></div><div class="col-md-1"><label class="form-label">Tam.</label><input class="form-control" type="number" name="pageSize" value="20" min="1" max="200"></div><div class="col-md-6"><label class="form-label">PIN/PINs</label><input class="form-control" name="pins"></div><div class="col-md-6"><label class="form-label">Departamentos</label><input class="form-control" name="deptCodes"></div><div class="col-12"><button class="btn btn-outline-primary"><i class="bi bi-send"></i> Enviar prueba</button></div></form></div></div>
<div class="card"><div class="card-header"><strong>Cola operativa y laboratorio</strong><small class="d-block text-body-secondary">El worker Edge de baja latencia toma aperturas y escrituras sin esperar el heartbeat de 5 minutos.</small></div><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Fecha</th><th>Agent</th><th>Tipo</th><th>Estado</th><th>Resultado</th></tr></thead><tbody><?php foreach($commands as $c):$payload=$decode($c['payload_json']??'');$result=$decode($c['result_json']??'');$status=(string)$c['status'];$badge=$status==='completed'?'success':($status==='failed'?'danger':($status==='claimed'?'warning':'secondary'));?><tr><td class="text-nowrap"><?=e(format_datetime_es($c['queued_at']))?></td><td><?=e($c['agent_name'])?></td><td><code><?=e($c['command_type']??($payload['preset']??''))?></code></td><td><span class="badge text-bg-<?=$badge?>"><?=e($status)?></span><?php if(!empty($c['error_message'])):?><small class="d-block text-danger"><?=e($c['error_message'])?></small><?php endif;?></td><td><?php if($result):?><details><summary>JSON</summary><pre class="small bg-body-tertiary p-2" style="max-height:320px;overflow:auto"><?=e(json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))?></pre></details><?php else:?><span class="text-body-secondary">—</span><?php endif;?></td></tr><?php endforeach;?><?php if(!$commands):?><tr><td colspan="5" class="text-center text-body-secondary py-4">Sin comandos todavía.</td></tr><?php endif;?></tbody></table></div></div>

</div>
</div>
<style>
#zkOperationalTabs .nav-link{font-size:.92rem;padding:.45rem .75rem}
#zkOperationalTabs .badge{font-size:.72rem;font-weight:500}
.zk-kpi-strip .card{border-radius:.65rem}
.zk-kpi-strip small{font-size:.78rem}
.zk-kpi-strip .fs-5{font-size:1.12rem!important}
#zk-units-pane .table>tbody>tr>td,#zk-doors-pane .table>tbody>tr>td{padding-top:.45rem;padding-bottom:.45rem}
.zk-reconciliation-detail>td{background:var(--bs-body-bg)}
.js-zk-unit-toggle i{transition:transform .15s ease}
.js-zk-unit-toggle[aria-expanded="true"] i{transform:rotate(90deg)}
</style>
<script>
document.addEventListener('DOMContentLoaded',()=>{
  const tabKey='property.zkbiolab.activeTab';
  const tabButtons=[...document.querySelectorAll('#zkOperationalTabs [data-bs-toggle="tab"]')];
  const saved=window.sessionStorage?.getItem(tabKey)||'';
  if(saved){const btn=tabButtons.find(x=>x.dataset.bsTarget===saved);if(btn&&window.bootstrap?.Tab)bootstrap.Tab.getOrCreateInstance(btn).show();}
  tabButtons.forEach(btn=>btn.addEventListener('shown.bs.tab',()=>{try{sessionStorage.setItem(tabKey,btn.dataset.bsTarget||'');}catch(e){}}));

  const details=()=>[...document.querySelectorAll('.zk-unit-detail')];
  document.querySelector('.js-zk-units-expand')?.addEventListener('click',()=>details().forEach(el=>window.bootstrap?.Collapse?bootstrap.Collapse.getOrCreateInstance(el,{toggle:false}).show():el.classList.add('show')));
  document.querySelector('.js-zk-units-collapse')?.addEventListener('click',()=>details().forEach(el=>window.bootstrap?.Collapse?bootstrap.Collapse.getOrCreateInstance(el,{toggle:false}).hide():el.classList.remove('show')));
});
</script>
