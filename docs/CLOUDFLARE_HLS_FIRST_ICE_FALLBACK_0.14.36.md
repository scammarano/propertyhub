# Property Hub 0.14.36 · Cloudflare HLS First / ICE Fallback Fix

## Problema reproducido

Después de reiniciar el Edge, MediaMTX y ambos Quick Tunnels podían estar sanos y reportados al Node, pero el escritorio quedaba sin video. Los logs mostraban sesiones WHEP creadas por `127.0.0.1` y cerradas ~10 s después con `deadline exceeded while waiting connection`: la señalización HTTP atravesaba cloudflared, pero ICE no encontraba un camino de medios público.

El reproductor tenía además un falso positivo: `RTCPeerConnection.ontrack` puede dispararse al aplicar la SDP remota antes de que ICE esté conectado. Eso hacía que `startWebRtc()` retornara éxito y evitaba el fallback HLS.

## Corrección

1. `viewerConfig()` marca `prefer_hls` cuando el WHEP público termina en `.trycloudflare.com` y hay HLS disponible.
2. El reproductor usa HLS primero en ese escenario.
3. WebRTC sólo es exitoso si `connectionState=connected` o `iceConnectionState=connected/completed`.
4. Si WHEP responde pero ICE no conecta, se ejecuta fallback HLS.
5. Los mensajes de la UI explican que el Quick Tunnel se usa para HTTPS y que HLS es el transporte remoto seguro principal.

No se modifican migraciones ni esquema.
