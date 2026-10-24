# Property Hub 0.14.3 · Node Secure HLS Proxy

## Objetivo

Permitir la prueba de video móvil sin VPS manteniendo el Property Node en cPanel y el Edge dentro de la LAN.

## Flujo

```text
Cámara/NVR → RTSP → MediaMTX Edge → Quick Tunnel HLS → Property Node → iPhone/Android
```

El navegador móvil **no** recibe credenciales MediaMTX ni necesita autenticarse directamente contra el túnel HLS. Solicita `camera-hls` al Node. El Node valida la cámara autorizada y usa cURL + Basic Auth únicamente entre Node y MediaMTX.

## Reproductor

- iPhone/iPad: HLS nativo cuando está disponible.
- Android/otros navegadores con MSE: HLS.js.
- Desktop: WebRTC/WHEP directo se mantiene como primera opción.

## Seguridad

La URL directa del Edge permanece protegida por credenciales por cámara. La ruta HLS del Node exige sesión y permiso `cameras.view` y vuelve a resolver la visibilidad de la cámara antes de proxyar cada recurso.

## Alcance

Este proxy es para laboratorio cPanel. Cada segmento atraviesa PHP/cPanel, por lo que no es el diseño recomendado para operación masiva de producción.
