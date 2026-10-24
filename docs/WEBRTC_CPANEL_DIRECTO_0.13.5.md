# Property Hub 0.13.6 · Prueba WebRTC sin VPS

## Objetivo

Probar video en vivo desde la Property App usando la infraestructura actual:

- Property Hub Central: repositorio y plataforma.
- Property Node en cPanel: autenticación, permisos, catálogo de cámaras y entrega de configuración al navegador.
- Property Edge Agent en la LAN: acceso RTSP/ONVIF a la cámara y MediaMTX.
- Cloudflare Quick Tunnel: expone temporalmente por HTTPS el endpoint WHEP local de MediaMTX.
- STUN/WebRTC: intenta establecer el canal de medios directamente entre el navegador y el Edge Agent.

El cPanel NO transporta el video RTSP ni actúa como relay de medios.

## Flujo

Cámara RTSP -> MediaMTX en Edge -> WHEP por Quick Tunnel -> navegador autorizado por Property Node.

La señalización WHEP viaja por HTTPS. El canal de medios WebRTC intenta conexión directa mediante ICE/STUN. Si la red usa NAT/firewall restrictivo y no permite hole punching, será necesario TURN/relay en una etapa posterior.

## Configuración

1. Actualice Central, Node y Agent a 0.13.6.
2. En el Node: Infraestructura y tecnología -> Cámaras -> Video remoto.
3. Seleccione `WebRTC directo desde Edge · sin VPS` y guarde.
4. En la cámara/canal del Node active `Preparar video en vivo`. Para mayor compatibilidad use un perfil H.264.
5. Sincronice el Agent con el Node.
6. En el Agent, Estado y laboratorio, sección `Media Gateway · WebRTC`, pulse `Preparar WebRTC cPanel`.
7. Property Hub instalará MediaMTX/cloudflared si hace falta, generará configuración, iniciará MediaMTX, abrirá un Quick Tunnel y reportará el endpoint al Node.
8. El Agent debe mostrar MediaMTX activo, túnel online y al menos una cámara lista.
9. En el Node pulse `Comprobar video remoto`.
10. Abra la vista del propietario/residente -> Cámaras -> En vivo.

## No configurar manualmente

No introduzca URL WHEP, URL RTSP pública, BHR, credenciales de cámara ni URL de Central para esta prueba. El Node y el Agent intercambian automáticamente la configuración necesaria.

## Limitaciones del laboratorio

- La URL `trycloudflare.com` cambia al reiniciar el Quick Tunnel.
- Quick Tunnel es solo para pruebas/desarrollo.
- El éxito del canal de medios depende de ICE/STUN y del NAT/firewall de la red.
- H.264 suele ser la opción más compatible. H.265 y H.264 con B-frames pueden fallar en algunos navegadores.
- Para producción se deberá usar un túnel/hostname estable y TURN o relay de medios cuando la topología de red lo requiera.

## Logs útiles

En el Edge Agent:

- `storage/logs/mediamtx.log`
- `storage/logs/cloudflared-media.log`
- `storage/logs/property-hub-YYYY-MM-DD.log`

