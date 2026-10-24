# Property Hub 0.14.0 · Video cPanel en iPhone

## Flujo

- PC/desktop: WebRTC/WHEP cuando ICE funciona.
- iPhone/iPad: HLS sobre HTTPS por un segundo Cloudflare Quick Tunnel.
- El Node autoriza la cámara antes de entregar el `live_path`.
- El Edge conserva RTSP/ONVIF y las credenciales físicas.

## Después de actualizar

1. Node: mantener `WebRTC directo desde Edge · sin VPS`.
2. Agent: `Actualizar configuración desde Node`.
3. Agent: `Preparar WebRTC cPanel` de nuevo. Esto genera dos túneles: WHEP y HLS.
4. Agent: verificar que las cámaras estén `ready`.
5. PC: probar `Ver en vivo` en Node.
6. iPhone: abrir Property App > Cámaras > En vivo. iOS debe seleccionar HLS automáticamente.

## Nota

Los Quick Tunnels son solo de laboratorio. Producción usará TURN/relay estable.
