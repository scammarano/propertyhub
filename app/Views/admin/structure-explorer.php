<?php
$d=$structureExplorer??[];
$properties=$d['properties']??[];$areas=$d['areas']??[];$cameras=$d['cameras']??[];$access=$d['access']??[];$devices=$d['devices']??[];$people=$d['people']??[];$vehicles=$d['vehicles']??[];$vertical=$d['vertical']??[];
$propChildren=$areasByProp=$camsByArea=$camsByProp=$accessByArea=$accessByProp=$devicesByArea=$devicesByProp=$peopleByProp=$vehiclesByProp=$verticalByProp=[];
foreach($properties as $p)$propChildren[(int)($p['parent_id']??0)][]=$p;
foreach($areas as $a)$areasByProp[(int)($a['property_id']??0)][]=$a;
foreach($cameras as $c){if((int)($c['area_id']??0)>0)$camsByArea[(int)$c['area_id']][]=$c;elseif((int)($c['property_id']??0)>0)$camsByProp[(int)$c['property_id']][]=$c;}
foreach($access as $a){if(!empty($a['is_vertical_stop']))continue;if((int)($a['area_id']??0)>0)$accessByArea[(int)$a['area_id']][]=$a;else $accessByProp[(int)($a['property_id']??0)][]=$a;}
foreach($devices as $x){if((int)($x['area_id']??0)>0)$devicesByArea[(int)$x['area_id']][]=$x;else $devicesByProp[(int)($x['property_id']??0)][]=$x;}
foreach($people as $x)$peopleByProp[(int)$x['property_id']][]=$x;
foreach($vehicles as $x)$vehiclesByProp[(int)$x['property_id']][]=$x;
foreach($vertical as $x)if((int)($x['level_property_id']??0)>0)$verticalByProp[(int)$x['level_property_id']][]=$x;

$unlocatedCameras=array_values(array_filter($cameras,fn($x)=>(int)($x['area_id']??0)<1&&(int)($x['property_id']??0)<1));
$unlocatedAccess=array_values(array_filter($access,fn($x)=>empty($x['is_vertical_stop'])&&(int)($x['area_id']??0)<1&&(int)($x['property_id']??0)<1));
$unlocatedDevices=array_values(array_filter($devices,fn($x)=>(int)($x['area_id']??0)<1&&(int)($x['property_id']??0)<1));

$propIcon=static function(string $type):string{return match($type){'building'=>'bi-building','tower'=>'bi-buildings','floor','ground_floor','basement','mezzanine','terrace','roof','technical_floor'=>'bi-layers','apartment','house','townhouse'=>'bi-house-door','office','commercial_unit'=>'bi-briefcase','parking','parking_zone','garage'=>'bi-p-square','technical','storage','warehouse'=>'bi-box-seam','guard_post'=>'bi-shield-lock',default=>'bi-diagram-3'};};
$areaIcon=static function(string $type):string{return match($type){'technical'=>'bi-hdd-rack','parking'=>'bi-p-square','security'=>'bi-shield-lock','reservable'=>'bi-calendar2-check','circulation'=>'bi-signpost-split',default=>'bi-grid-1x2'};};

$areaNameCounts=[];
foreach($areas as $a){$n=trim(preg_replace('/\s+/u',' ',(string)($a['name']??'')));$key=(int)($a['property_id']??0).'|'.mb_strtolower($n,'UTF-8');$areaNameCounts[$key]=($areaNameCounts[$key]??0)+1;}
$duplicateAreaGroups=count(array_filter($areaNameCounts,static fn($n)=>$n>1));
$areaDuplicateCount=static function(array $a)use($areaNameCounts):int{$n=trim(preg_replace('/\s+/u',' ',(string)($a['name']??'')));$key=(int)($a['property_id']??0).'|'.mb_strtolower($n,'UTF-8');return(int)($areaNameCounts[$key]??1);};

// Totales agregados por rama. Los badges de un Piso/Edificio muestran TODO lo que
// contiene debajo, no sólo los recursos vinculados directamente a ese registro.
$aggregateMemo=[];
$aggregateProp=function(int $id)use(&$aggregateProp,&$aggregateMemo,$propChildren,$areasByProp,$camsByArea,$camsByProp,$accessByArea,$accessByProp,$devicesByArea,$devicesByProp,$peopleByProp,$vehiclesByProp,$verticalByProp):array{
    if(isset($aggregateMemo[$id]))return $aggregateMemo[$id];
    $sets=['areas'=>[],'cameras'=>[],'access'=>[],'devices'=>[],'people'=>[],'vehicles'=>[],'vertical'=>[]];
    foreach($areasByProp[$id]??[] as $a){$aid=(int)$a['id'];$sets['areas'][$aid]=1;foreach($camsByArea[$aid]??[] as $x)$sets['cameras'][(int)$x['id']]=1;foreach($accessByArea[$aid]??[] as $x)$sets['access'][(int)$x['id']]=1;foreach($devicesByArea[$aid]??[] as $x)$sets['devices'][(int)$x['id']]=1;}
    foreach($camsByProp[$id]??[] as $x)$sets['cameras'][(int)$x['id']]=1;
    foreach($accessByProp[$id]??[] as $x)$sets['access'][(int)$x['id']]=1;
    foreach($devicesByProp[$id]??[] as $x)$sets['devices'][(int)$x['id']]=1;
    foreach($peopleByProp[$id]??[] as $x)$sets['people'][(int)($x['person_id']??$x['id']??0)]=1;
    foreach($vehiclesByProp[$id]??[] as $x)$sets['vehicles'][(int)$x['id']]=1;
    foreach($verticalByProp[$id]??[] as $x)$sets['vertical'][(int)($x['vertical_transport_id']??$x['id']??0)]=1;
    foreach($propChildren[$id]??[] as $child){$sub=$aggregateProp((int)$child['id']);foreach($sets as $k=>$_)foreach((array)($sub['_sets'][$k]??[]) as $sid=>$one)$sets[$k][$sid]=1;}
    $counts=[];foreach($sets as $k=>$v)$counts[$k]=count(array_filter($v,fn($x,$key)=>$key>0,ARRAY_FILTER_USE_BOTH));
    return $aggregateMemo[$id]=$counts+['_sets'=>$sets];
};

$renderCountChips=static function(array $counts):void{
    $defs=[
        ['areas','properties.view','bi-grid-1x2','Áreas / espacios'],
        ['cameras','cameras.view','bi-camera-video','Cámaras'],
        ['access','access.view','bi-door-open','Puertas / accesos'],
        ['devices','integrations.view','bi-cpu','IoT / activos'],
        ['people','people.view','bi-people','Personas'],
        ['vehicles','people.view','bi-car-front','Vehículos'],
        ['vertical','properties.view','bi-building-up','Ascensores con parada'],
    ];
    foreach($defs as [$key,$permission,$icon,$title]){if(can($permission)&&(int)($counts[$key]??0)>0):?><span class="twin-chip twin-chip-count" title="<?=e($title)?>"><i class="bi <?=e($icon)?>"></i><strong><?=(int)$counts[$key]?></strong></span><?php endif;}
};

$renderArea=function(array $a,int $depth=0)use(&$renderArea,$camsByArea,$accessByArea,$devicesByArea,$peopleByProp,$vehiclesByProp,$areaIcon,$renderCountChips,$areaDuplicateCount){
    $id=(int)$a['id'];$pid=(int)($a['property_id']??0);
    $counts=['cameras'=>count($camsByArea[$id]??[]),'access'=>count($accessByArea[$id]??[]),'devices'=>count($devicesByArea[$id]??[]),'people'=>count($peopleByProp[$pid]??[]),'vehicles'=>count($vehiclesByProp[$pid]??[])];
    $hasChildren=(can('cameras.view')&&$counts['cameras']>0)||(can('access.view')&&$counts['access']>0)||(can('integrations.view')&&$counts['devices']>0)||(can('people.view')&&($counts['people']>0||$counts['vehicles']>0));
    $childId='twin-area-'.$id.'-children';$branchId='twin-area-'.$id;
?>
<li class="twin-row twin-area" id="<?=$branchId?>" style="--depth:<?=$depth?>">
  <div class="twin-main">
    <div class="twin-leading">
      <?php if($hasChildren):?><button type="button" class="twin-toggle" data-twin-toggle="<?=$childId?>" aria-controls="<?=$childId?>" aria-expanded="false" title="Abrir/cerrar este grupo"><i class="bi bi-chevron-right"></i></button><?php else:?><span class="twin-toggle-spacer"></span><?php endif;?>
      <a href="<?=url('area-context',['id'=>$id])?>" class="twin-label"><i class="bi <?=$areaIcon((string)$a['area_type'])?>"></i><span><?=e($a['name'])?><?php if($areaDuplicateCount($a)>1):?> <span class="badge text-bg-warning ms-1" title="Hay más de un área con este nombre en la misma ubicación">nombre repetido ×<?=$areaDuplicateCount($a)?></span><?php endif;?></span><small><?=e(area_scope_label((string)($a['scope_type']??'common')))?> · <?=e(area_usage_label((string)($a['usage_type']??'general')))?> · <code><?=e($a['code'])?></code></small></a>
    </div>
    <div class="twin-actions"><div class="twin-chips"><?php $renderCountChips($counts);?></div><?php if($hasChildren):?><button type="button" class="twin-deep-toggle" data-twin-deep-toggle="<?=$branchId?>" title="Expandir/colapsar toda esta rama"><i class="bi bi-arrows-expand"></i></button><?php endif;?></div>
  </div>
  <?php if($hasChildren):?><ul class="twin-children twin-attached" id="<?=$childId?>" hidden>
    <?php if(can('cameras.view'))foreach($camsByArea[$id]??[] as $c):?><li><a href="<?=url('cameras')?>#camera-<?=e($c['id'])?>"><i class="bi bi-camera-video"></i><?=e($c['name'])?></a></li><?php endforeach;?>
    <?php if(can('access.view'))foreach($accessByArea[$id]??[] as $x):?><li><a href="<?=url('access')?>#access-<?=e($x['id'])?>"><i class="bi bi-door-open"></i><?=e($x['name'])?></a></li><?php endforeach;?>
    <?php if(can('integrations.view'))foreach($devicesByArea[$id]??[] as $x):?><li><a href="<?=url('technology-inventory',['search'=>$x['name']])?>"><i class="bi bi-cpu"></i><?=e($x['name'])?></a></li><?php endforeach;?>
    <?php if(can('people.view')&&(!empty($peopleByProp[$pid])||!empty($vehiclesByProp[$pid]))):?><li class="twin-summary"><details><summary><i class="bi bi-person-vcard"></i> Personas y vehículos del elemento</summary><div class="twin-mini-list"><?php foreach($peopleByProp[$pid]??[] as $x):?><a href="<?=url('people',['search'=>$x['display_name']])?>"><i class="bi bi-person"></i><?=e($x['display_name'])?> <small><?=e(relationship_label((string)$x['relationship_type']))?></small></a><?php endforeach;?><?php foreach($vehiclesByProp[$pid]??[] as $x):?><a href="<?=url('vehicles')?>"><i class="bi bi-car-front"></i><?=e(trim(($x['make']??'').' '.($x['model']??''))?:($x['plate']??'Vehículo'))?> <small><?=e((string)($x['plate']??''))?></small></a><?php endforeach;?></div></details></li><?php endif;?>
  </ul><?php endif;?>
</li>
<?php };

$renderProp=function(array $p,int $depth=0)use(&$renderProp,$propChildren,$areasByProp,$camsByProp,$accessByProp,$devicesByProp,$peopleByProp,$vehiclesByProp,$verticalByProp,$renderArea,$propIcon,$aggregateProp,$renderCountChips){
    $id=(int)$p['id'];$counts=$aggregateProp($id);
    $hasChildren=!empty($propChildren[$id])||!empty($areasByProp[$id])||(can('cameras.view')&&!empty($camsByProp[$id]))||(can('access.view')&&!empty($accessByProp[$id]))||(can('integrations.view')&&!empty($devicesByProp[$id]))||(can('people.view')&&!empty($peopleByProp[$id]))||(can('people.view')&&!empty($vehiclesByProp[$id]))||(can('properties.view')&&!empty($verticalByProp[$id]));
    $childId='twin-prop-'.$id.'-children';$branchId='twin-prop-'.$id;
?>
<li class="twin-row twin-property" id="<?=$branchId?>" style="--depth:<?=$depth?>">
  <div class="twin-main">
    <div class="twin-leading">
      <?php if($hasChildren):?><button type="button" class="twin-toggle" data-twin-toggle="<?=$childId?>" aria-controls="<?=$childId?>" aria-expanded="false" title="Abrir/cerrar este grupo"><i class="bi bi-chevron-right"></i></button><?php else:?><span class="twin-toggle-spacer"></span><?php endif;?>
      <?php $pm=json_decode((string)($p['metadata_json']??''),true);if(!is_array($pm))$pm=[];$organizer=(($pm['structure_role']??'physical')==='organizer');?>
      <a href="<?=url('structure-context',['id'=>$id])?>" class="twin-label"><i class="bi <?=$organizer?'bi-folder':$propIcon((string)$p['property_type'])?>"></i><span><?=e($p['name'])?></span><small><?=e(property_row_type_label($p))?> · <?=e($p['code'])?><?php if($organizer&&!array_key_exists('accepts_resources',$pm)||($organizer&&empty($pm['accepts_resources']))):?> · no admite recursos<?php endif;?></small></a>
    </div>
    <div class="twin-actions"><div class="twin-chips"><?php $renderCountChips($counts);?></div><?php if($hasChildren):?><button type="button" class="twin-deep-toggle" data-twin-deep-toggle="<?=$branchId?>" title="Expandir/colapsar toda esta rama"><i class="bi bi-arrows-expand"></i></button><?php endif;?></div>
  </div>
  <?php if($hasChildren):?><ul class="twin-children" id="<?=$childId?>" hidden>
    <?php foreach($propChildren[$id]??[] as $child)$renderProp($child,$depth+1);?>
    <?php foreach($areasByProp[$id]??[] as $area)$renderArea($area,$depth+1);?>
    <?php if(can('properties.view')&&!empty($verticalByProp[$id])):$vuniq=[];foreach($verticalByProp[$id] as $vx)$vuniq[(int)($vx['id']??0)]=$vx;?><li class="twin-summary"><details><summary><i class="bi bi-building-up"></i> Ascensores con parada <span class="badge text-bg-light border ms-1"><?=count($vuniq)?></span></summary><div class="twin-mini-list"><?php foreach($vuniq as $vx):?><a href="<?=url('vertical-transport')?>#vertical-<?=e($vx['id'])?>"><i class="bi bi-building-up"></i><?=e($vx['name'])?> <small><?=e($vx['code'])?></small></a><?php endforeach;?></div></details></li><?php endif;?>
    <?php if(can('cameras.view'))foreach($camsByProp[$id]??[] as $x):?><li class="twin-leaf"><a href="<?=url('cameras')?>#camera-<?=e($x['id'])?>"><i class="bi bi-camera-video"></i><?=e($x['name'])?></a></li><?php endforeach;?>
    <?php if(can('access.view'))foreach($accessByProp[$id]??[] as $x):?><li class="twin-leaf"><a href="<?=url('access')?>#access-<?=e($x['id'])?>"><i class="bi bi-door-open"></i><?=e($x['name'])?></a></li><?php endforeach;?>
    <?php if(can('integrations.view'))foreach($devicesByProp[$id]??[] as $x):?><li class="twin-leaf"><a href="<?=url('technology-inventory',['search'=>$x['name']])?>"><i class="bi bi-cpu"></i><?=e($x['name'])?></a></li><?php endforeach;?>
    <?php if(can('people.view')&&(!empty($peopleByProp[$id])||!empty($vehiclesByProp[$id]))):?><li class="twin-summary"><details><summary><i class="bi bi-person-vcard"></i> Personas y vehículos</summary><div class="twin-mini-list"><?php foreach($peopleByProp[$id]??[] as $x):?><a href="<?=url('people',['search'=>$x['display_name']])?>"><i class="bi bi-person"></i><?=e($x['display_name'])?> <small><?=e(relationship_label((string)$x['relationship_type']))?></small></a><?php endforeach;?><?php foreach($vehiclesByProp[$id]??[] as $x):?><a href="<?=url('vehicles')?>"><i class="bi bi-car-front"></i><?=e(trim(($x['make']??'').' '.($x['model']??''))?:($x['plate']??'Vehículo'))?> <small><?=e((string)($x['plate']??''))?></small></a><?php endforeach;?></div></details></li><?php endif;?>
  </ul><?php endif;?>
</li>
<?php };
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
  <div><h1 class="h4 mb-1">Mapa jerárquico del inmueble</h1><div class="text-body-secondary">Abre únicamente la rama que necesitas. Todas las ramas comienzan colapsadas y los indicadores resumen su contenido interno.</div></div>
  <div class="d-flex flex-wrap gap-2"><button type="button" class="btn btn-outline-primary" id="twinExpandAll"><i class="bi bi-arrows-expand me-1"></i>Expandir todo</button><button type="button" class="btn btn-outline-secondary" id="twinCollapseAll"><i class="bi bi-arrows-collapse me-1"></i>Colapsar todo</button><a class="btn btn-outline-secondary" href="<?=url('properties')?>"><i class="bi bi-table"></i> Vista tabla</a><?php if(can('properties.manage')):?><a class="btn btn-primary" href="<?=url('structure-wizard')?>"><i class="bi bi-magic"></i> Diseñador</a><?php endif;?></div>
</div>
<div class="alert alert-info"><i class="bi bi-compass me-2"></i><strong>Dos capas, un solo mapa.</strong> Los nodos morados representan <strong>estructura / DÓNDE</strong>. Los nodos verdes representan <strong>áreas / QUÉ espacio funcional existe allí</strong>. Cámaras, accesos e IoT pueden colgar directamente de una ubicación física o de un área más específica.</div><?php if($duplicateAreaGroups>0):?><div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i><strong>Hay <?=$duplicateAreaGroups?> nombre(s) de área repetido(s) dentro de la misma ubicación.</strong> El árbol los diferencia por <strong>tipo y código</strong>. Property bloqueará nuevos duplicados exactos en esa misma ubicación para evitar confusión.</div><?php endif;?>
<div class="card"><div class="card-header"><strong>Estructura visual</strong><small class="d-block text-body-secondary">Todo inicia colapsado. Flecha: abre un nivel. <i class="bi bi-arrows-expand"></i>: expande/colapsa la rama completa.</small></div><div class="card-body"><ul class="twin-tree"><?php foreach($propChildren[0]??[] as $root)$renderProp($root,0);?><?php if(empty($propChildren[0])):?><li class="text-body-secondary">No hay elementos raíz. Usa el Diseñador de estructura.</li><?php endif;?></ul></div></div>
<?php if($unlocatedCameras||$unlocatedAccess||$unlocatedDevices):?><div class="card mt-3"><div class="card-header"><strong>Recursos todavía sin ubicación</strong><small class="d-block text-body-secondary">No quedan escondidos: ubícalos desde su ficha o desde un Área/Espacio.</small></div><div class="card-body"><div class="contextual-links"><?php if(can('cameras.view'))foreach($unlocatedCameras as $x):?><a href="<?=url('cameras')?>#camera-<?=e($x['id'])?>"><i class="bi bi-camera-video"></i><span><?=e($x['name'])?></span><small>Sin área</small></a><?php endforeach;?><?php if(can('access.view'))foreach($unlocatedAccess as $x):?><a href="<?=url('access')?>#access-<?=e($x['id'])?>"><i class="bi bi-door-open"></i><span><?=e($x['name'])?></span><small>Sin ubicación</small></a><?php endforeach;?><?php if(can('integrations.view'))foreach($unlocatedDevices as $x):?><a href="<?=url('technology-inventory',['search'=>$x['name']])?>"><i class="bi bi-cpu"></i><span><?=e($x['name'])?></span><small>Sin ubicación</small></a><?php endforeach;?></div></div></div><?php endif;?>
<script>
(function(){
  const groups=()=>Array.from(document.querySelectorAll('.twin-children'));
  const setGroup=(ul,open)=>{if(!ul)return;ul.hidden=!open;const b=document.querySelector('[data-twin-toggle="'+CSS.escape(ul.id)+'"]');if(b){b.setAttribute('aria-expanded',open?'true':'false');const i=b.querySelector('i');if(i)i.className='bi '+(open?'bi-chevron-down':'bi-chevron-right');}};
  document.addEventListener('click',function(ev){
    const toggle=ev.target.closest('[data-twin-toggle]');if(toggle){const ul=document.getElementById(toggle.dataset.twinToggle||'');if(ul)setGroup(ul,ul.hidden);return;}
    const deep=ev.target.closest('[data-twin-deep-toggle]');if(deep){const root=document.getElementById(deep.dataset.twinDeepToggle||'');if(!root)return;const nested=Array.from(root.querySelectorAll('ul.twin-children'));const open=nested.some(x=>x.hidden);nested.forEach(x=>setGroup(x,open));const icon=deep.querySelector('i');if(icon)icon.className='bi '+(open?'bi-arrows-collapse':'bi-arrows-expand');return;}
  });
  document.getElementById('twinExpandAll')?.addEventListener('click',()=>groups().forEach(x=>setGroup(x,true)));
  document.getElementById('twinCollapseAll')?.addEventListener('click',()=>groups().forEach(x=>setGroup(x,false)));
  groups().forEach(x=>setGroup(x,false));
})();
</script>
