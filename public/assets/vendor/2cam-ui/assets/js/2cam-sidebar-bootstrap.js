/* 2CAM UI Standard 1.2.0 · cargar inmediatamente después de abrir body. */
(function(){
  'use strict';
  try{
    var nativeKey='lte.sidebar.state';
    var legacy=localStorage.getItem('2cam:sidebar-collapsed');
    if(localStorage.getItem(nativeKey)===null && legacy!==null){
      localStorage.setItem(nativeKey,legacy==='1'?'sidebar-collapse':'sidebar-open');
    }
    if(window.innerWidth>991.98 && localStorage.getItem(nativeKey)==='sidebar-collapse'){
      document.body.classList.add('sidebar-collapse');
    }
  }catch(_){ }
})();
