# Property Hub 0.14.2 — Video seguro en laboratorio cPanel

## Objetivo

Mantener el Node en cPanel y el Edge dentro de la LAN, usando MediaMTX + Cloudflare Quick Tunnel únicamente para laboratorio, sin dejar las cámaras públicamente legibles por URL.

## Seguridad

- No existe usuario anónimo con permiso `read` sobre paths de cámaras.
- Cada `live_path` recibe usuario/clave específicos derivados de la clave maestra provisionada por el Node.
- El Node entrega esas credenciales solo después de validar sesión, permiso `cameras.view` y visibilidad de la cámara.
- Copiar el URL `trycloudflare.com/<path>/` ya no permite ver la cámara sin autenticación.

## Móvil

- iPhone y Android prefieren HLS sobre HTTPS en modo `edge_direct_webrtc`.
- HLS usa hls.js para poder adjuntar `Authorization` a playlist y segmentos.
- Desktop conserva WebRTC/WHEP como transporte preferido.

## Después de actualizar

En el Edge ejecute una vez **Preparar WebRTC cPanel** para regenerar `mediamtx.yml` con la nueva política de autenticación y levantar ambos túneles: WebRTC/WHEP y HLS.
