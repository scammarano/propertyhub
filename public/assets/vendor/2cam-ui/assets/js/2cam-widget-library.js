(function(){
  'use strict';
  function render(container,payload,variant){
    if(!container||!variant)return;
    container.dataset.camRenderer=variant.renderer||'';
    container.dispatchEvent(new CustomEvent('cam:widget:render',{detail:{payload:payload||{},variant},bubbles:true}));
  }
  window.CamWidgetLibrary={render};
})();
