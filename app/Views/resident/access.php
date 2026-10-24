<?php
$items=$accessPoints??[];$groups=[];
foreach($items as $point){$area=trim((string)($point['area']??''));if($area==='')$area='Área general';$groups[$area][]=$point;}
uksort($groups,static fn(string $a,string $b):int=>strnatcasecmp($a,$b));
?>
<section class="resident-page-title"><span>Seguridad</span><h1>Accesos</h1><p>Puertas y accesos autorizados, organizados por zona o área.</p></section>
<?php foreach($groups as $areaName=>$groupItems):?>
<section class="resident-resource-group"><div class="resident-resource-group-title"><i class="bi bi-geo-alt"></i><div><strong><?=e($areaName)?></strong><small><?=count($groupItems)?> acceso(s)</small></div></div><section class="access-grid-mobile">
<?php foreach($groupItems as $point):$ready=!empty($point['remote_ready']);$displayName=(string)($point['resident_display_name']??$point['resident_name']??$point['name']);?>
<article data-access-card="<?=e($point['id'])?>"><span class="access-icon"><i class="bi bi-door-open"></i></span><div><strong><?=e($displayName)?></strong><small><?=e($point['connector'])?></small><small class="access-command-state text-body-secondary"><?=$ready?'Lista para apertura remota':'Sin ruta de apertura vinculada'?></small></div><button type="button" class="js-open-access" data-id="<?=e($point['id'])?>" <?=$ready?'':'disabled'?>> <i class="bi bi-unlock"></i> Abrir</button><span class="device-state state-<?=e($point['status'])?>"><?=e(status_label((string)$point['status']))?></span></article>
<?php endforeach;?></section></section>
<?php endforeach;?>
<?php if(!$items):?><div class="resident-empty">No hay puntos de acceso remoto habilitados para esta cuenta.</div><?php endif;?><div class="safety-note"><i class="bi bi-shield-check"></i><p>Property valida tu perfil y horario antes de cada apertura. La orden viaja Node → Edge → controlador local; las credenciales ZKBio nunca llegan al móvil.</p></div>
<style>.resident-resource-group{margin-bottom:1rem}.resident-resource-group-title{display:flex;align-items:center;gap:.65rem;margin:0 0 .55rem;padding:.15rem .1rem}.resident-resource-group-title>i{font-size:1.05rem}.resident-resource-group-title strong{display:block}.resident-resource-group-title small{display:block;opacity:.65;font-size:.76rem}</style>

<script>
(()=>{
 const csrf='<?=e(csrf_token())?>';
 const sleep=ms=>new Promise(r=>setTimeout(r,ms));
 document.querySelectorAll('.js-open-access').forEach(btn=>btn.addEventListener('click',async()=>{
   if(btn.disabled)return; const card=btn.closest('[data-access-card]'); const state=card?.querySelector('.access-command-state'); const original=btn.innerHTML;
   btn.disabled=true;btn.innerHTML='<span class="spinner-border spinner-border-sm"></span> Abriendo';if(state)state.textContent='Enviando orden segura al Edge…';
   try{
     const body=new URLSearchParams({_csrf:csrf,access_point_id:btn.dataset.id});
     const r=await fetch('<?=url('resident-access-open')?>',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded','Accept':'application/json'},body});
     const j=await r.json();if(!r.ok||!j.ok)throw new Error(j.message||'No se pudo solicitar la apertura.');
     if(state)state.textContent='Orden aceptada · esperando controlador…';
     let final=null;
     for(let i=0;i<18;i++){await sleep(700);const q=await fetch('<?=url('resident-access-command-status')?>&correlation='+encodeURIComponent(j.correlation_uuid),{credentials:'same-origin',headers:{'Accept':'application/json'}});const s=await q.json();if(!q.ok)throw new Error(s.message||'No se pudo confirmar la apertura.');if(['confirmed','failed','denied','timeout'].includes(s.status)){final=s;break;}}
     if(!final)throw new Error('La orden quedó enviada pero no se recibió confirmación a tiempo.');
     if(final.status!=='confirmed')throw new Error(final.error||'El controlador no confirmó la apertura.');
     btn.innerHTML='<i class="bi bi-check-circle-fill"></i> Abierta';if(state)state.textContent='Apertura confirmada por ZKBio · '+new Date().toLocaleTimeString();
     setTimeout(()=>{btn.innerHTML=original;btn.disabled=false;},2200);return;
   }catch(e){if(state)state.textContent=e.message||'Error de apertura';btn.innerHTML='<i class="bi bi-exclamation-triangle"></i> Error';setTimeout(()=>{btn.innerHTML=original;btn.disabled=false;},2600);}
 }));
})();
</script>
