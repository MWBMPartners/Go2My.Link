// Go2My.link PWA Service Worker (basic offline shell)
// (C) 2025–present MWBM Partners Ltd (d/b/a MW Services)
const CACHE_NAME = "go2mylink-pwa-v1";
const APP_SHELL = ["/", "/?source=pwa", "/pwa-bundle/pwa-index.html", "/pwa-bundle/manifest.json"];
self.addEventListener("install", (e)=>{ e.waitUntil(caches.open(CACHE_NAME).then(c=>c.addAll(APP_SHELL))); });
self.addEventListener("activate", (e)=>{ e.waitUntil(caches.keys().then(keys=>Promise.all(keys.map(k=>k!==CACHE_NAME?caches.delete(k):null)))); });
self.addEventListener("fetch", (e)=>{
  e.respondWith(caches.match(e.request).then(r=>r||fetch(e.request).catch(()=>caches.match("/"))));
});
