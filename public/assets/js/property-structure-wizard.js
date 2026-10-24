document.addEventListener('DOMContentLoaded',()=>{
  try {
  const cfg=window.PropertyStructureWizard||{};
  const $=id=>document.getElementById(id);
  const panels=[...document.querySelectorAll('.wizard-panel')];
  const stepButtons=[...document.querySelectorAll('[data-step-target]')];
  const existingAreas=cfg.existingAreas||{};
  let step=1,uidCounter=1,towers=[],parkingZones=[],storageZones=[],areas=[],elevators=[];

  function showValidation(message){
    const box=$('wizardValidationMessage');
    if(!box){window.alert(message);return;}
    box.textContent=message;box.hidden=false;box.scrollIntoView({behavior:'smooth',block:'center'});
  }
  function clearValidation(){const box=$('wizardValidationMessage');if(box){box.hidden=true;box.textContent='';}}

  const escapeHtml=v=>String(v??'').replace(/[&<>'"]/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c]));
  const cleanCode=v=>String(v??'').trim().toUpperCase().replace(/[^A-Z0-9._-]+/g,'-').replace(/^-+|-+$/g,'');
  const toLetters=index=>{let n=index+1,out='';while(n>0){n--;out=String.fromCharCode(65+n%26)+out;n=Math.floor(n/26);}return out;};
  const num=id=>Number.parseInt($(id)?.value||'0',10)||0;
  const rootMode=()=>document.querySelector('input[name="root_mode"]:checked')?.value||'create';
  const uid=prefix=>`${prefix}-${uidCounter++}`;
  const selectedValues=select=>[...select.selectedOptions].map(o=>o.value);
  const namingPresets={
    floor:{
      tower_level:['{TORRE}{SEP}{NIVEL}','{TIPO_NIVEL} {NIVEL} · {TORRE_NOMBRE}'],
      tower_level_number:['{TORRE}{SEP}{NIVEL_NUM}','{TIPO_NIVEL} {NIVEL} · {TORRE_NOMBRE}'],
      level_only:['{NIVEL}','{TIPO_NIVEL} {NIVEL}']
    },
    apartment:{
      floor_letter:['{NIVEL_NUM}{LETRA}','Apartamento {CODIGO}'],
      floor_number:['{NIVEL_NUM}{NUMERO}','Apartamento {CODIGO}'],
      floor_tower_letter:['{NIVEL_NUM}{TORRE_LETRA}{LETRA}','Apartamento {CODIGO}'],
      letter_floor:['{LETRA}{NIVEL_NUM}','Apartamento {CODIGO}'],
      tower_floor_letter:['{TORRE}{SEP}{NIVEL_NUM}{LETRA}','Apartamento {CODIGO}'],
      tower_floor_number:['{TORRE}{SEP}{NIVEL_NUM}{NUMERO}','Apartamento {CODIGO}']
    },
    parking:{zone_number:['{ZONA}{SEP}{N}','Estacionamiento {CODIGO}'],parking_number:['EST{SEP}{N}','Estacionamiento {CODIGO}']},
    storage:{zone_number:['{ZONA}{SEP}{N}','Maletero {CODIGO}'],storage_number:['MAL{SEP}{N}','Maletero {CODIGO}']}
  };

  function renderTemplate(template,tokens){
    let out=String(template??'');
    Object.keys(tokens).sort((a,b)=>b.length-a.length).forEach(key=>{out=out.split(key).join(String(tokens[key]??''));});
    return out;
  }
  function separator(){return $('defaultSeparator')?.value??'-';}
  function toggleRootMode(){if(!$('existingRootFields')||!$('newRootFields'))return;const existing=rootMode()==='existing';$('existingRootFields').hidden=!existing;$('newRootFields').hidden=existing;}
  function towerDefaults(index){
    const mode=$('towerNaming').value;
    if(mode==='single')return{code:'TORRE',name:'Torre principal'};
    if(mode==='letters'){const c=toLetters(index);return{code:c,name:`Torre ${c}`};}
    if(mode==='numbers'){const c=String(index+1);return{code:c,name:`Torre ${c}`};}
    if(mode==='prefix'){const c=cleanCode(($('towerPrefix').value||'T')+(index+1));return{code:c,name:`Torre ${c}`};}
    const lines=($('customTowerNames').value||'').split(/\r?\n/).map(v=>v.trim()).filter(Boolean),line=lines[index]||`Torre ${index+1}`,parts=line.split('|');
    return parts.length>1?{code:cleanCode(parts[0])||String(index+1),name:parts.slice(1).join('|').trim()||`Torre ${index+1}`}:{code:cleanCode(line)||String(index+1),name:line};
  }
  function newLevel(kind,label,numberValue,units=0){return{uid:uid('level'),kind,label,number:numberValue,units,enabled:true};}
  function commonLevels(){
    const list=[];
    for(let i=num('commonBasements');i>=1;i--)list.push(newLevel('basement',`S${i}`,-i,0));
    if($('commonGround').checked)list.push(newLevel('ground_floor','PB',0,0));
    for(let i=1;i<=num('commonFloors');i++)list.push(newLevel('floor',String(i),i,num('commonApartments')));
    if($('commonTerrace').checked)list.push(newLevel('terrace','TERRAZA',1000,0));
    if($('commonRoof').checked)list.push(newLevel('roof','AZOTEA',1001,0));
    return list;
  }
  function syncTowerCount(){
    const count=Math.max(1,Math.min(50,num('towerCount')));
    if(count===1&&$('towerNaming').value!=='custom')$('towerNaming').value='single';
    if(count>1&&$('towerNaming').value==='single')$('towerNaming').value='letters';
    const next=[];
    for(let i=0;i<count;i++){
      const old=towers[i],d=towerDefaults(i);
      next.push(old||{uid:uid('tower'),code:d.code,name:d.name,touchedCode:false,touchedName:false,levels:commonLevels()});
    }
    towers=next;refreshTowerNames();renderTowers();toggleNamingFields();
  }
  function refreshTowerNames(){towers.forEach((t,i)=>{const d=towerDefaults(i);if(!t.touchedCode)t.code=d.code;if(!t.touchedName)t.name=d.name;});}
  function levelTypeOptions(value){return [['basement','Sótano'],['ground_floor','Planta baja'],['floor','Piso'],['mezzanine','Mezzanina'],['terrace','Terraza'],['roof','Azotea'],['technical_floor','Piso técnico']].map(([v,l])=>`<option value="${v}" ${v===value?'selected':''}>${l}</option>`).join('');}
  function levelTypeLabel(kind){return{basement:'Sótano',ground_floor:'Planta baja',floor:'Piso',mezzanine:'Mezzanina',terrace:'Terraza',roof:'Azotea',technical_floor:'Piso técnico'}[kind]||'Nivel';}
  function renderTowers(){
    const tbody=$('towerRows');tbody.innerHTML='';
    towers.forEach((tower,index)=>{
      const active=tower.levels.filter(l=>l.enabled),units=active.reduce((n,l)=>n+Number(l.units||0),0);
      const row=document.createElement('tr');row.dataset.index=index;row.innerHTML=`<td><input class="form-control form-control-sm text-uppercase tower-code" value="${escapeHtml(tower.code)}"></td><td><input class="form-control form-control-sm tower-name" value="${escapeHtml(tower.name)}"></td><td><span class="badge text-bg-info">${active.length}</span></td><td><span class="badge text-bg-success">${units}</span></td><td><button type="button" class="btn btn-sm btn-outline-primary tower-detail-toggle"><i class="bi bi-sliders"></i> Configurar niveles</button></td>`;tbody.appendChild(row);
      const detail=document.createElement('tr');detail.className='tower-detail-row';detail.hidden=true;detail.innerHTML=`<td colspan="5"><div class="p-3 bg-body-tertiary"><div class="d-flex justify-content-between align-items-center mb-2"><strong>${escapeHtml(tower.name)} · niveles</strong><button type="button" class="btn btn-sm btn-outline-primary add-level" data-index="${index}"><i class="bi bi-plus-lg"></i> Nivel libre</button></div><div class="table-responsive"><table class="table table-sm table-bordered mb-0"><thead><tr><th>Crear</th><th>Tipo</th><th>Etiqueta</th><th>Número lógico</th><th>Apartamentos</th><th></th></tr></thead><tbody>${tower.levels.map((l,j)=>`<tr><td class="text-center"><input class="form-check-input level-enabled" data-tower="${index}" data-level="${j}" type="checkbox" ${l.enabled?'checked':''}></td><td><select class="form-select form-select-sm level-kind" data-tower="${index}" data-level="${j}">${levelTypeOptions(l.kind)}</select></td><td><input class="form-control form-control-sm level-label" data-tower="${index}" data-level="${j}" value="${escapeHtml(l.label)}"></td><td><input type="number" class="form-control form-control-sm level-number" data-tower="${index}" data-level="${j}" value="${escapeHtml(l.number)}"></td><td><input type="number" min="0" max="50" class="form-control form-control-sm level-units" data-tower="${index}" data-level="${j}" value="${escapeHtml(l.units)}"></td><td><button type="button" class="btn btn-sm btn-outline-danger remove-level" data-tower="${index}" data-level="${j}"><i class="bi bi-trash"></i></button></td></tr>`).join('')}</tbody></table></div></div></td>`;tbody.appendChild(detail);
    });
    bindTowerEvents();updateAll();
  }
  function bindTowerEvents(){
    document.querySelectorAll('#towerRows tr[data-index]').forEach(row=>{const i=Number(row.dataset.index),t=towers[i];
      row.querySelector('.tower-code').addEventListener('input',e=>{t.code=cleanCode(e.target.value);t.touchedCode=true;updateAll();});
      row.querySelector('.tower-name').addEventListener('input',e=>{t.name=e.target.value.trim();t.touchedName=true;updateAll();});
      row.querySelector('.tower-detail-toggle').addEventListener('click',()=>row.nextElementSibling.hidden=!row.nextElementSibling.hidden);
    });
    document.querySelectorAll('.add-level').forEach(b=>b.addEventListener('click',()=>{towers[Number(b.dataset.index)].levels.push(newLevel('floor',`NIVEL-${towers[Number(b.dataset.index)].levels.length+1}`,towers[Number(b.dataset.index)].levels.length+1,0));renderTowers();}));
    ['enabled','kind','label','number','units'].forEach(field=>document.querySelectorAll(`.level-${field}`).forEach(el=>el.addEventListener(field==='enabled'?'change':'input',e=>{const l=towers[Number(e.target.dataset.tower)].levels[Number(e.target.dataset.level)];l[field]=field==='enabled'?e.target.checked:field==='number'||field==='units'?Number(e.target.value)||0:e.target.value;updateAll();})));
    document.querySelectorAll('.remove-level').forEach(b=>b.addEventListener('click',()=>{towers[Number(b.dataset.tower)].levels.splice(Number(b.dataset.level),1);renderTowers();}));
  }
  function toggleNamingFields(){const mode=$('towerNaming').value;$('towerPrefixWrap').hidden=mode!=='prefix';$('customTowerWrap').hidden=mode!=='custom';}

  function locationOptions(selected='root',includeGeneral=true){
    const opts=[];if(includeGeneral)opts.push({value:'root',label:'General del conjunto'});
    towers.forEach((t,ti)=>{opts.push({value:`tower:${ti}`,label:t.name});t.levels.filter(l=>l.enabled).forEach(l=>opts.push({value:`tower:${ti}:level:${l.uid}`,label:`${t.name} → ${levelTypeLabel(l.kind)} ${l.label}`}));});
    return opts.map(o=>`<option value="${escapeHtml(o.value)}" ${o.value===selected?'selected':''}>${escapeHtml(o.label)}</option>`).join('');
  }
  function allLocationRefs(){const out=[{value:'root',label:'General del conjunto'}];(cfg.existingProperties||[]).forEach(p=>out.push({value:`existing:${p.id}`,label:`Existente → ${p.name}`}));towers.forEach((t,ti)=>{out.push({value:`tower:${ti}`,label:t.name});t.levels.filter(l=>l.enabled).forEach(l=>out.push({value:`tower:${ti}:level:${l.uid}`,label:`${t.name} → ${levelTypeLabel(l.kind)} ${l.label}`}));});return out;}

  function addZone(kind){
    const list=kind==='parking'?parkingZones:storageZones,index=list.length+1;
    list.push({uid:uid(kind),code:kind==='parking'?`EST-ZONA-${index}`:`MAL-ZONA-${index}`,name:kind==='parking'?`Zona de estacionamiento ${index}`:`Zona de maleteros ${index}`,zone_type:kind==='parking'?'owners_covered':'owners',location_ref:'root',count:0,digits:3,code_template:kind==='parking'?$('parkingCodeTemplate').value:$('storageCodeTemplate').value,name_template:kind==='parking'?$('parkingNameTemplate').value:$('storageNameTemplate').value});renderZones();
  }
  function renderZones(){
    const render=(z,kind,index)=>`<div class="card mb-3 zone-card" data-kind="${kind}" data-index="${index}"><div class="card-header d-flex justify-content-between"><strong>${kind==='parking'?'Zona de estacionamiento':'Zona de maleteros'}</strong><button type="button" class="btn btn-sm btn-outline-danger remove-zone"><i class="bi bi-trash"></i></button></div><div class="card-body"><div class="row g-3"><div class="col-md-2"><label class="form-label">Código de zona</label><input class="form-control text-uppercase zone-code" value="${escapeHtml(z.code)}"></div><div class="col-md-3"><label class="form-label">Nombre</label><input class="form-control zone-name" value="${escapeHtml(z.name)}"></div><div class="col-md-2"><label class="form-label">Clasificación</label><select class="form-select zone-type">${(kind==='parking'?[['owners_covered','Propietarios techado'],['owners_open','Propietarios al aire libre'],['visitors_covered','Visitantes techado'],['visitors_open','Visitantes al aire libre'],['mixed','Mixto']]:[['owners','Propietarios'],['common','Comunes'],['technical','Técnicos']]).map(([v,l])=>`<option value="${v}" ${v===z.zone_type?'selected':''}>${l}</option>`).join('')}</select></div><div class="col-md-3"><label class="form-label">Ubicación</label><select class="form-select zone-location">${locationOptions(z.location_ref)}</select></div><div class="col-md-2"><label class="form-label">Cantidad</label><input type="number" min="0" max="5000" class="form-control zone-count" value="${z.count}"></div><div class="col-md-2"><label class="form-label">Dígitos</label><select class="form-select zone-digits">${[1,2,3,4,5].map(n=>`<option value="${n}" ${n===Number(z.digits)?'selected':''}>${n}</option>`).join('')}</select></div><div class="col-md-5"><label class="form-label">Plantilla código</label><input class="form-control text-uppercase zone-code-template" value="${escapeHtml(z.code_template)}"></div><div class="col-md-5"><label class="form-label">Plantilla nombre</label><input class="form-control zone-name-template" value="${escapeHtml(z.name_template)}"></div></div></div></div>`;
    $('parkingZones').innerHTML=parkingZones.map((z,i)=>render(z,'parking',i)).join('');$('storageZones').innerHTML=storageZones.map((z,i)=>render(z,'storage',i)).join('');$('noZonesMessage').hidden=parkingZones.length+storageZones.length>0;bindZoneEvents();updateAll();
  }
  function bindZoneEvents(){document.querySelectorAll('.zone-card').forEach(card=>{const kind=card.dataset.kind,index=Number(card.dataset.index),list=kind==='parking'?parkingZones:storageZones,z=list[index];const bind=(cls,key,number=false)=>card.querySelector(cls).addEventListener('input',e=>{z[key]=number?Number(e.target.value)||0:e.target.value;updateAll();});bind('.zone-code','code');bind('.zone-name','name');bind('.zone-type','zone_type');bind('.zone-location','location_ref');bind('.zone-count','count',true);bind('.zone-digits','digits',true);bind('.zone-code-template','code_template');bind('.zone-name-template','name_template');card.querySelector('.remove-zone').addEventListener('click',()=>{list.splice(index,1);renderZones();});});}

  function catalogOptions(selected='custom'){const entries=Object.entries(cfg.standardAreas||{});return `<option value="custom" ${selected==='custom'?'selected':''}>Personalizada</option>`+entries.map(([key,a])=>`<option value="${escapeHtml(key)}" ${selected===key?'selected':''}>${escapeHtml(a.name)}</option>`).join('');}
  function addArea(catalogKey='custom',locationRef='root'){
    const cat=cfg.standardAreas?.[catalogKey],n=areas.length+1;areas.push({uid:uid('area'),catalog_key:catalogKey,code:cat?.code?`${cat.code}${areas.some(a=>a.code===cat.code)?'-'+n:''}`:`AREA-${n}`,name:cat?.name||`Área ${n}`,area_type:cat?.area_type||'common',is_reservable:Boolean(cat?.is_reservable),is_security_zone:Boolean(cat?.is_security_zone),location_ref:locationRef});renderAreas();
  }
  function renderAreas(){
    $('areaRows').innerHTML=areas.map((a,i)=>`<div class="card mb-3 area-row" data-index="${i}"><div class="card-body"><div class="row g-3 align-items-end"><div class="col-md-2"><label class="form-label">Modelo</label><select class="form-select area-catalog">${catalogOptions(a.catalog_key)}</select></div><div class="col-md-2"><label class="form-label">Código</label><input class="form-control text-uppercase area-code" value="${escapeHtml(a.code)}"></div><div class="col-md-3"><label class="form-label">Nombre</label><input class="form-control area-name" value="${escapeHtml(a.name)}"></div><div class="col-md-3"><label class="form-label">Ubicación exacta</label><select class="form-select area-location">${locationOptions(a.location_ref)}</select></div><div class="col-md-1"><label class="form-check"><input class="form-check-input area-reservable" type="checkbox" ${a.is_reservable?'checked':''}><span class="form-check-label">Reservable</span></label></div><div class="col-md-1 text-end"><button type="button" class="btn btn-outline-danger remove-area"><i class="bi bi-trash"></i></button></div></div></div></div>`).join('');$('noAreasMessage').hidden=areas.length>0;bindAreaEvents();updateAll();
  }
  function bindAreaEvents(){document.querySelectorAll('.area-row').forEach(card=>{const i=Number(card.dataset.index),a=areas[i];card.querySelector('.area-catalog').addEventListener('change',e=>{a.catalog_key=e.target.value;const cat=cfg.standardAreas?.[a.catalog_key];if(cat){a.code=cat.code;a.name=cat.name;a.area_type=cat.area_type;a.is_reservable=Boolean(cat.is_reservable);a.is_security_zone=Boolean(cat.is_security_zone);}renderAreas();});card.querySelector('.area-code').addEventListener('input',e=>{a.code=cleanCode(e.target.value);updateAll();});card.querySelector('.area-name').addEventListener('input',e=>{a.name=e.target.value;updateAll();});card.querySelector('.area-location').addEventListener('change',e=>{a.location_ref=e.target.value;updateAll();});card.querySelector('.area-reservable').addEventListener('change',e=>{a.is_reservable=e.target.checked;updateAll();});card.querySelector('.remove-area').addEventListener('click',()=>{areas.splice(i,1);renderAreas();});});}

  function addElevator(){const n=elevators.length+1;elevators.push({uid:uid('elevator'),code:`ASC-${n}`,name:`Ascensor ${n}`,transport_type:'passenger_elevator',tower_refs:towers[0]?[`tower:0`]:[],stop_refs:towers[0]?towers[0].levels.filter(l=>l.enabled).map(l=>`tower:0:level:${l.uid}`):[],access_doors:true});renderElevators();}
  function renderElevators(){
    const towerCheckHtml=(selected=[])=>towers.map((t,ti)=>`<label class="form-check border rounded p-2 mb-2"><input class="form-check-input elevator-tower-check" type="checkbox" value="tower:${ti}" ${selected.includes(`tower:${ti}`)?'checked':''}><span class="form-check-label"><strong>${escapeHtml(t.name)}</strong><small class="d-block text-body-secondary">${t.levels.filter(l=>l.enabled).length} niveles reales</small></span></label>`).join('');
    $('elevatorRows').innerHTML=elevators.map((e,i)=>{const allowed=[];e.tower_refs.forEach(ref=>{const ti=Number(ref.split(':')[1]);const t=towers[ti];if(!t)return;t.levels.filter(l=>l.enabled).sort((a,b)=>Number(a.number)-Number(b.number)).forEach(l=>allowed.push({value:`tower:${ti}:level:${l.uid}`,tower:t.name,level:l}));});return `<div class="card mb-3 elevator-row" data-index="${i}"><div class="card-body"><div class="row g-3"><div class="col-md-2"><label class="form-label">Código</label><input class="form-control text-uppercase elevator-code" value="${escapeHtml(e.code)}"></div><div class="col-md-4"><label class="form-label">Nombre</label><input class="form-control elevator-name" value="${escapeHtml(e.name)}"></div><div class="col-md-3"><label class="form-label">Tipo</label><select class="form-select elevator-type">${[['passenger_elevator','Ascensor de pasajeros'],['service_elevator','Ascensor de servicio'],['freight_elevator','Montacargas'],['escalator','Escalera mecánica'],['stair','Escalera']].map(([v,l])=>`<option value="${v}" ${e.transport_type===v?'selected':''}>${l}</option>`).join('')}</select></div><div class="col-md-3 d-flex align-items-end justify-content-end"><button type="button" class="btn btn-outline-danger remove-elevator"><i class="bi bi-trash"></i> Quitar</button></div><div class="col-lg-4"><label class="form-label fw-semibold">Torre(s) reales</label><div class="elevator-tower-grid">${towerCheckHtml(e.tower_refs)}</div><div class="form-text">Selecciona sólo las torres/núcleo que físicamente atiende este ascensor.</div></div><div class="col-lg-8"><div class="d-flex justify-content-between align-items-center gap-2"><label class="form-label fw-semibold mb-0">Paradas de las torres seleccionadas</label><div class="btn-group btn-group-sm"><button type="button" class="btn btn-outline-primary elevator-all-stops">Todas</button><button type="button" class="btn btn-outline-secondary elevator-no-stops">Ninguna</button></div></div><div class="border rounded p-2 mt-2" style="max-height:260px;overflow:auto">${allowed.length?allowed.map(o=>`<label class="form-check py-1"><input class="form-check-input elevator-stop-check" type="checkbox" value="${escapeHtml(o.value)}" ${e.stop_refs.includes(o.value)?'checked':''}><span class="form-check-label"><strong>${escapeHtml(o.level.kind==='basement'?'Sótano '+o.level.label:o.level.kind==='ground_floor'?'Planta baja':o.level.kind==='floor'?'Piso '+o.level.label:levelTypeLabel(o.level.kind)+' '+o.level.label)}</strong><small class="text-body-secondary ms-2">${escapeHtml(o.tower)} · orden ${escapeHtml(o.level.number)}</small></span></label>`).join(''):'<div class="text-body-secondary small p-2">Selecciona una torre para ver sus niveles.</div>'}</div><div class="form-text">Una torre de 6 pisos sólo mostrará sus 6 pisos (más sus niveles especiales); otra de 7 tendrá su propio recorrido.</div></div><div class="col-12"><label class="form-check"><input class="form-check-input elevator-doors" type="checkbox" ${e.access_doors?'checked':''}><span class="form-check-label">Planificar puerta/control de acceso por parada</span></label></div></div></div></div>`;}).join('');$('noElevatorsMessage').hidden=elevators.length>0;bindElevatorEvents();updateAll();
  }
  function bindElevatorEvents(){document.querySelectorAll('.elevator-row').forEach(card=>{const i=Number(card.dataset.index),e=elevators[i];card.querySelector('.elevator-code').addEventListener('input',ev=>{e.code=cleanCode(ev.target.value);updateAll();});card.querySelector('.elevator-name').addEventListener('input',ev=>{e.name=ev.target.value;updateAll();});card.querySelector('.elevator-type').addEventListener('change',ev=>{e.transport_type=ev.target.value;updateAll();});card.querySelectorAll('.elevator-tower-check').forEach(ch=>ch.addEventListener('change',()=>{e.tower_refs=[...card.querySelectorAll('.elevator-tower-check:checked')].map(x=>x.value);e.stop_refs=e.stop_refs.filter(r=>e.tower_refs.some(t=>r.startsWith(t+':')));renderElevators();}));card.querySelectorAll('.elevator-stop-check').forEach(ch=>ch.addEventListener('change',()=>{e.stop_refs=[...card.querySelectorAll('.elevator-stop-check:checked')].map(x=>x.value);updateAll();}));card.querySelector('.elevator-all-stops')?.addEventListener('click',()=>{card.querySelectorAll('.elevator-stop-check').forEach(x=>x.checked=true);e.stop_refs=[...card.querySelectorAll('.elevator-stop-check')].map(x=>x.value);updateAll();});card.querySelector('.elevator-no-stops')?.addEventListener('click',()=>{card.querySelectorAll('.elevator-stop-check').forEach(x=>x.checked=false);e.stop_refs=[];updateAll();});card.querySelector('.elevator-doors').addEventListener('change',ev=>{e.access_doors=ev.target.checked;updateAll();});card.querySelector('.remove-elevator').addEventListener('click',()=>{elevators.splice(i,1);renderElevators();});});}

  function generatedPreview(limit=400){
    const plan=buildPlan(),rows=[],codes=new Set();let levels=0,units=0,spots=0,storages=0,areasNew=0,areasExisting=0,doors=0;
    const add=(type,code,name,parent,status='Se creará',existing=false)=>{const conflict=code&&codes.has(code);if(code)codes.add(code);rows.push({type,code,name,parent,status:conflict?'Código repetido':status,existing,conflict});};
    const rootLabel=plan.root.mode==='existing'?$('existingRootId')?.selectedOptions?.[0]?.textContent||'Estructura existente':plan.root.name; if(plan.root.mode==='create')add('Raíz',plan.root.code,plan.root.name,'—');
    towers.forEach((t,ti)=>{add('Torre / edificio',t.code,t.name,rootLabel);t.levels.forEach(level=>{levels++;const tokens=levelTokens(t,ti,level),fc=floorCode(t,ti,level);tokens['{CODIGO}']=fc;add(levelTypeLabel(level.kind),fc,renderTemplate(plan.naming.floor.name_template,tokens),t.name,level.units===0?'Contenedor sin apartamentos':'Se creará');for(let u=1;u<=Number(level.units||0);u++){units++;let letter=toLetters(u-1);if(plan.naming.apartment.letter_case==='lower')letter=letter.toLowerCase();const numeric=String(u).padStart(plan.naming.apartment.unit_digits,'0'),ut={...tokens,'{APTO}':plan.naming.apartment.unit_sequence==='letters'?letter:numeric,'{UNIDAD}':plan.naming.apartment.unit_sequence==='letters'?letter:numeric,'{LETRA}':letter,'{N}':String(u),'{NUMERO}':numeric};const code=cleanCode(renderTemplate(plan.naming.apartment.code_template,ut));ut['{CODIGO}']=code;add('Apartamento / unidad',code,renderTemplate(plan.naming.apartment.name_template,ut),fc);}});});
    const zoneRows=(list,kind)=>list.forEach(z=>{add(kind==='parking'?'Zona de estacionamiento':'Zona de maleteros',cleanCode(z.code),z.name,locationLabel(z.location_ref));for(let n=1;n<=Number(z.count||0);n++){const formatted=String(n).padStart(Number(z.digits||3),'0'),tokens={'{SEP}':separator(),'{N}':formatted,'{NUMERO}':String(n),'{ZONA}':cleanCode(z.code),'{ZONA_NOMBRE}':z.name};const code=cleanCode(renderTemplate(z.code_template,tokens));tokens['{CODIGO}']=code;add(kind==='parking'?'Puesto de estacionamiento':'Maletero',code,renderTemplate(z.name_template,tokens),z.name);kind==='parking'?spots++:storages++;}});zoneRows(parkingZones,'parking');zoneRows(storageZones,'storage');
    areas.forEach(a=>{const exists=Boolean(existingAreas[cleanCode(a.code)]);exists?areasExisting++:areasNew++;add('Área',cleanCode(a.code),a.name,locationLabel(a.location_ref),exists?'Se conservará':'Se creará',exists);});
    elevators.forEach(e=>{add('Ascensor / circulación',cleanCode(e.code),e.name,e.tower_refs.map(locationLabel).join(', '));e.stop_refs.forEach(ref=>{if(e.access_doors){doors++;const stopKey=cleanCode(ref.replace(/:/g,'-'));add('Puerta planificada',cleanCode(`${e.code}-${stopKey}-PUERTA`),`Puerta de acceso · ${e.name}`,locationLabel(ref));}});});
    return{plan,rows,counts:{towers:towers.length,levels,units,spots,storages,areas:areasNew,areasExisting,elevators:elevators.length,doors,total:rows.filter(r=>!r.existing).length,conflicts:rows.filter(r=>r.conflict).length},displayRows:rows.slice(0,limit)};
  }
  function updateExamples(){
    const preview=generatedPreview(150).rows;
    const map={floor:'Piso',apartment:'Apartamento / unidad',parking:'Puesto de estacionamiento',storage:'Maletero'};
    Object.entries(map).forEach(([key,type])=>{const el=$(`${key}Examples`),items=preview.filter(r=>r.type===type).slice(0,6);el.innerHTML=items.length?items.map(r=>`<span><code>${escapeHtml(r.code)}</code><small>${escapeHtml(r.name)}</small></span>`).join(''):'<small class="text-body-secondary">Configura elementos para ver ejemplos.</small>';});
  }
  function updateAll(){const c=generatedPreview(0).counts;$('metricTowers').textContent=c.towers;$('metricLevels').textContent=c.levels;$('metricUnits').textContent=c.units;$('metricExtras').textContent=c.spots+c.storages+c.areas+c.areasExisting+c.elevators+c.doors;updateExamples();}
  function renderPreview(){const p=generatedPreview(),c=p.counts;$('wizardPayload').value=JSON.stringify(p.plan);$('previewSummary').innerHTML=[['Torres',c.towers],['Niveles',c.levels],['Unidades',c.units],['Estacionamientos',c.spots],['Maleteros',c.storages],['Áreas',c.areas],['Ascensores',c.elevators],['Puertas',c.doors],['Conflictos',c.conflicts]].map(([l,v])=>`<div class="col-6 col-md-4 col-xl-2"><div class="metric-card"><div><span>${l}</span><strong>${v}</strong></div></div></div>`).join('');$('previewRows').innerHTML=p.displayRows.map(r=>`<tr><td>${escapeHtml(r.type)}</td><td><code>${escapeHtml(r.code)}</code></td><td>${escapeHtml(r.name)}</td><td>${escapeHtml(r.parent)}</td><td><span class="badge ${r.conflict?'text-bg-danger':r.existing?'text-bg-info':r.status.includes('sin apartamentos')?'text-bg-warning':'text-bg-success'}">${escapeHtml(r.status)}</span></td></tr>`).join('');$('previewLimitNote').textContent=p.rows.length>p.displayRows.length?`Se muestran ${p.displayRows.length} de ${p.rows.length} elementos.`:`Se mostrarán ${p.rows.length} elementos.`;}

  function validateStep(n){
    if(n===1){if(num('towerCount')<1)return'Indica al menos una torre o edificio.';if(rootMode()==='create'&&(!$('rootCode').value.trim()||!$('rootName').value.trim()))return'Completa la estructura principal.';}
    if(n===2){for(const t of towers){if(!t.code||!t.name)return'Cada torre debe tener código y nombre.';if(!t.levels.some(l=>l.enabled))return`${t.name} no tiene niveles habilitados.`;}}
    if(n===3){const floor=$('floorCodeTemplate').value,apt=$('apartmentCodeTemplate').value;if(!floor.includes('{NIVEL}')&&!floor.includes('{NIVEL_NUM}')&&!floor.includes('{PISO}'))return'La plantilla de niveles debe incluir {NIVEL}, {NIVEL_NUM} o {PISO}.';if(!['{APTO}','{LETRA}','{N}'].some(t=>apt.includes(t)))return'La plantilla de apartamentos debe incluir {APTO}, {LETRA} o {N}.';}
    if(n===4){for(const z of [...parkingZones,...storageZones])if(!z.code||!z.name||Number(z.count)<0)return'Revisa los códigos, nombres y cantidades de las zonas.';}
    if(n===5){for(const a of areas)if(!a.code||!a.name)return'Todas las áreas deben tener código y nombre.';}
    if(n===6){for(const e of elevators)if(!e.code||!e.name||!e.tower_refs.length||!e.stop_refs.length)return'Cada ascensor debe tener código, nombre, al menos una torre y una parada.';}
    return'';
  }
  function showStep(next){if(next>step){for(let n=step;n<next;n++){const error=validateStep(n);if(error){showValidation(error);return false;}}}clearValidation();step=Math.max(1,Math.min(7,next));panels.forEach(p=>p.hidden=Number(p.dataset.step)!==step);stepButtons.forEach(b=>b.classList.toggle('active',Number(b.dataset.stepTarget)===step));$('previousStep').disabled=step===1;$('nextStep').hidden=step===7;$('generateStructure').hidden=step!==7;if(step===4)renderZones();if(step===5)renderAreas();if(step===6)renderElevators();if(step===7)renderPreview();window.scrollTo({top:0,behavior:'smooth'});return true;}

  function hydrate(plan){if(!plan||typeof plan!=='object')return false;if(plan.schema_version!==2)return false;const r=plan.root||{},radio=document.querySelector(`input[name="root_mode"][value="${r.mode==='existing'?'existing':'create'}"]`);if(radio)radio.checked=true;if($('existingRootId')&&r.existing_id)$('existingRootId').value=r.existing_id;if(r.code)$('rootCode').value=r.code;if(r.name)$('rootName').value=r.name;if(r.property_type)$('rootType').value=r.property_type;toggleRootMode();if(plan.separator!==undefined)$('defaultSeparator').value=plan.separator;if(Array.isArray(plan.towers)&&plan.towers.length){$('towerCount').value=plan.towers.length;towers=plan.towers.map(t=>({...t,touchedCode:true,touchedName:true,levels:(t.levels||[]).map(l=>({...l,uid:l.uid||uid('level')}))}));renderTowers();}const n=plan.naming||{};[['floor','floor'],['apartment','apartment'],['parking','parking'],['storage','storage']].forEach(([key,prefix])=>{if(n[key]?.source)$(`${prefix}NamingSource`).value=n[key].source;if(n[key]?.preset)$(`${prefix}Preset`).value=n[key].preset;if(n[key]?.code_template)$(`${prefix}CodeTemplate`).value=n[key].code_template;if(n[key]?.name_template)$(`${prefix}NameTemplate`).value=n[key].name_template;});if(n.apartment?.unit_sequence)$('unitSequence').value=n.apartment.unit_sequence;if(n.apartment?.unit_digits)$('unitDigits').value=n.apartment.unit_digits;if(n.apartment?.letter_case)$('letterCase').value=n.apartment.letter_case;if(n.apartment?.floor_digits)$('floorDigits').value=n.apartment.floor_digits;parkingZones=Array.isArray(plan.parking_zones)?plan.parking_zones:[];storageZones=Array.isArray(plan.storage_zones)?plan.storage_zones:[];areas=Array.isArray(plan.areas)?plan.areas:[];elevators=Array.isArray(plan.vertical_transports)?plan.vertical_transports:[];renderZones();renderAreas();renderElevators();return true;}

  document.querySelectorAll('input[name="root_mode"]').forEach(i=>i.addEventListener('change',toggleRootMode));
  $('towerCount').addEventListener('change',syncTowerCount);$('towerNaming').addEventListener('change',()=>{refreshTowerNames();toggleNamingFields();renderTowers();});$('towerPrefix').addEventListener('input',()=>{refreshTowerNames();renderTowers();});$('customTowerNames').addEventListener('input',()=>{refreshTowerNames();renderTowers();});
  $('applyCommonLevels').addEventListener('click',()=>{if(!confirm('Se reemplazará la configuración de niveles de todas las torres. ¿Continuar?'))return;towers.forEach(t=>t.levels=commonLevels());renderTowers();});
  ['defaultSeparator','floorCodeTemplate','floorNameTemplate','apartmentCodeTemplate','apartmentNameTemplate','parkingCodeTemplate','parkingNameTemplate','storageCodeTemplate','storageNameTemplate','unitSequence','unitDigits','letterCase','floorDigits'].forEach(id=>$(id).addEventListener('input',updateAll));
  document.querySelectorAll('.naming-preset').forEach(select=>select.addEventListener('change',()=>{const key=select.dataset.namingKey,preset=select.value,values=namingPresets[key]?.[preset];if(values){$(`${key}CodeTemplate`).value=values[0];$(`${key}NameTemplate`).value=values[1];}updateAll();}));
  document.querySelectorAll('.naming-source').forEach(select=>select.addEventListener('change',updateAll));
  $('addParkingZone').addEventListener('click',()=>addZone('parking'));$('addStorageZone').addEventListener('click',()=>addZone('storage'));
  $('addArea').addEventListener('click',()=>addArea());$('addTypicalAreas').addEventListener('click',()=>{['lobby','reception','administration','party_room','gym','pool','garbage','pump_room','electrical_room','security_room'].forEach(k=>addArea(k));});
  $('addElevator').addEventListener('click',addElevator);
  $('previousStep').addEventListener('click',()=>showStep(step-1));$('nextStep').addEventListener('click',()=>showStep(step+1));stepButtons.forEach(b=>b.addEventListener('click',()=>showStep(Number(b.dataset.stepTarget))));
  $('structureWizardForm').addEventListener('submit',e=>{for(let n=1;n<=6;n++){const error=validateStep(n);if(error){e.preventDefault();showStep(n);showValidation(error);return;}}const p=generatedPreview();if(p.counts.conflicts){e.preventDefault();showStep(7);showValidation('La vista previa contiene códigos repetidos. Corrige las nomenclaturas antes de generar.');return;}renderPreview();});

  toggleRootMode();syncTowerCount();const restored=hydrate(cfg.initialPlan);updateAll();showStep(restored?7:1);
  window.PropertyWizardShowStep=showStep;
  document.documentElement.dataset.propertyWizardReady='1';
  const runtimeStatus=$('wizardRuntimeStatus');if(runtimeStatus){runtimeStatus.hidden=true;}
  } catch (error) {
    if(window.PropertyWizardReportError)window.PropertyWizardReportError(error,'initialization');else console.error(error);
  }
});
