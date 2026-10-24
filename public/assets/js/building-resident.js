document.addEventListener('click',function(event){
  const copyButton=event.target.closest('[data-copy]');
  if(copyButton){
    const value=copyButton.getAttribute('data-copy')||'';
    navigator.clipboard?.writeText(value).then(()=>{const old=copyButton.innerHTML;copyButton.innerHTML='<i class="bi bi-check"></i> Copiado';setTimeout(()=>copyButton.innerHTML=old,1400);});
  }
  const area=event.target.closest('[data-area-id]');
  if(area){
    document.querySelectorAll('[data-area-id]').forEach(el=>el.classList.remove('selected'));
    area.classList.add('selected');
    const select=document.getElementById('reservationArea');if(select)select.value=area.getAttribute('data-area-id');
  }
});
if('serviceWorker' in navigator){window.addEventListener('load',()=>navigator.serviceWorker.register('sw.js?v=0.14.60',{updateViaCache:'none'}).then(r=>r.update().catch(()=>{})).catch(()=>{}));}
