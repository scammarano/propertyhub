(function(){
  'use strict';
  const builder=document.querySelector('[data-cam-sidebar-editor]');
  if(!builder)return;
  let dragged=null,kind='';

  function setOrder(scope,selector,attribute){
    [...scope.querySelectorAll(selector)].forEach((node,index)=>{
      const input=node.querySelector(`:scope > [${attribute}], :scope > * [${attribute}]`);
      if(input)input.value=String((index+1)*100);
    });
  }

  function refresh(){
    [...builder.querySelectorAll(':scope > [data-cam-nav-group]')].forEach((group,groupIndex)=>{
      const groupOrder=group.querySelector('[data-cam-group-order]');
      if(groupOrder)groupOrder.value=String((groupIndex+1)*100);
      const groupCode=group.dataset.camNavGroup||'';

      const sectionList=group.querySelector(':scope [data-cam-section-list]');
      if(sectionList){
        [...sectionList.querySelectorAll(':scope > [data-cam-nav-section]')].forEach((section,sectionIndex)=>{
          const sectionOrder=section.querySelector('[data-cam-section-order]');
          const sectionParent=section.querySelector('[data-cam-section-parent]');
          if(sectionOrder)sectionOrder.value=String((sectionIndex+1)*100);
          if(sectionParent)sectionParent.value=groupCode;
          const sectionCode=section.dataset.camNavSection||'';
          const itemList=section.querySelector(':scope [data-cam-item-list]');
          if(itemList)refreshItems(itemList,sectionCode);
        });
      }
      [...group.querySelectorAll(':scope [data-cam-direct-item-list]')].forEach(list=>refreshItems(list,groupCode));
      if(!sectionList){
        const legacyList=group.querySelector(':scope [data-cam-item-list]');
        if(legacyList)refreshItems(legacyList,groupCode);
      }
    });
  }

  function refreshItems(list,parentCode){
    [...list.querySelectorAll(':scope > [data-cam-nav-item]')].forEach((item,index)=>{
      const order=item.querySelector('[data-cam-item-order]');
      const parent=item.querySelector('[data-cam-item-parent]');
      if(order)order.value=String((index+1)*100);
      if(parent)parent.value=parentCode;
    });
  }

  builder.addEventListener('click',event=>{
    const toggle=event.target.closest('[data-cam-group-toggle],[data-cam-section-toggle]');
    if(!toggle)return;
    const container=toggle.closest('[data-cam-nav-group],[data-cam-nav-section]');
    const body=container?.querySelector(':scope > [data-cam-group-body], :scope > [data-cam-section-body]');
    if(!container||!body)return;
    const expanded=container.dataset.expanded!=='1';
    container.dataset.expanded=expanded?'1':'0';
    body.hidden=!expanded;
    toggle.setAttribute('aria-expanded',expanded?'true':'false');
    toggle.textContent=expanded?'Contraer':'Editar';
  });

  builder.addEventListener('dragstart',event=>{
    const item=event.target.closest('[data-cam-nav-item]');
    const section=event.target.closest('[data-cam-nav-section]');
    const group=event.target.closest('[data-cam-nav-group]');
    if(item){dragged=item;kind='item';}
    else if(section){dragged=section;kind='section';}
    else if(group){dragged=group;kind='group';}
    else{event.preventDefault();return;}
    dragged.classList.add('is-dragging');
    event.dataTransfer.effectAllowed='move';
    event.dataTransfer.setData('text/plain','2cam-nav-'+kind);
  });

  builder.addEventListener('dragend',()=>{
    dragged?.classList.remove('is-dragging');dragged=null;kind='';refresh();
  });

  builder.addEventListener('dragover',event=>{
    if(!dragged)return;
    event.preventDefault();
    if(kind==='group'){
      const target=event.target.closest(':scope > [data-cam-nav-group], [data-cam-nav-group]');
      if(!target||target===dragged||target.parentElement!==builder)return;
      const rect=target.getBoundingClientRect();
      builder.insertBefore(dragged,event.clientY>rect.top+rect.height/2?target.nextSibling:target);
      return;
    }
    if(kind==='section'){
      const list=event.target.closest('[data-cam-section-list]');
      if(!list)return;
      const target=event.target.closest('[data-cam-nav-section]');
      if(!target||target===dragged||target.parentElement!==list){list.appendChild(dragged);return;}
      const rect=target.getBoundingClientRect();
      list.insertBefore(dragged,event.clientY>rect.top+rect.height/2?target.nextSibling:target);
      return;
    }
    const list=event.target.closest('[data-cam-item-list],[data-cam-direct-item-list]');
    if(!list)return;
    const target=event.target.closest('[data-cam-nav-item]');
    if(!target||target===dragged||target.parentElement!==list){list.appendChild(dragged);return;}
    const rect=target.getBoundingClientRect();
    list.insertBefore(dragged,event.clientY>rect.top+rect.height/2?target.nextSibling:target);
  });

  refresh();
})();
