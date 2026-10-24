const CACHE='property-hub-v0.14.60-static';
const SHELL=[
  'assets/css/building-resident.css',
  'assets/css/building-login.css',
  'assets/js/building-resident.js',
  'assets/img/icon-192.png',
  'assets/img/icon-512.png'
];
self.addEventListener('install',event=>{
  event.waitUntil(caches.open(CACHE).then(cache=>cache.addAll(SHELL)).catch(()=>{}));
  self.skipWaiting();
});
self.addEventListener('activate',event=>{
  event.waitUntil(caches.keys().then(keys=>Promise.all(keys.filter(key=>key!==CACHE).map(key=>caches.delete(key)))));
  self.clients.claim();
});
self.addEventListener('fetch',event=>{
  if(event.request.method!=='GET')return;
  const url=new URL(event.request.url);
  if(url.origin!==self.location.origin)return;
  // Las páginas autenticadas y los datos personales siempre se consultan en red.
  if(url.pathname.endsWith('/index.php')||url.searchParams.has('route')||url.pathname.endsWith('/assets/js/property-webrtc.js')){
    event.respondWith(fetch(event.request,{cache:'no-store'}));
    return;
  }
  event.respondWith(caches.match(event.request).then(cached=>cached||fetch(event.request).then(response=>{
    if(response.ok){const clone=response.clone();caches.open(CACHE).then(cache=>cache.put(event.request,clone));}
    return response;
  })));
});
