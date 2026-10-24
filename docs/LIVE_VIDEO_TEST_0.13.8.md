# Property Hub 0.13.9 · Prueba de video en vivo

## Objetivo

Separar la prueba en tres tramos para saber exactamente dónde falla el video:

1. Cámara/NVR → RTSP → MediaMTX local.
2. MediaMTX → WebRTC local en el servidor Edge.
3. Edge → túnel HTTPS → Node/Resident App.

## Edge Agent

1. Seleccione el perfil H.264 de la cámara/canal cuando exista.
2. Guarde canales y sincronice.
3. En **Estado y laboratorio → Media Gateway**, pulse **Preparar WebRTC cPanel**.
4. Compruebe que MediaMTX esté activo y que el canal aparezca `ready`.
5. Use **Ver local**. Se abre `http://127.0.0.1:8889/<path>` desde el mismo servidor.
6. Si local funciona pero un móvil externo no recibe video, ejecute una sola vez como Administrador `tools\configure-media-gateway-firewall.bat` y repita la prueba.

## Property Node

En **Infraestructura y tecnología → Cámaras**, cada cámara preparada muestra **Ver en vivo**. La prueba usa el mismo endpoint `camera-live-config` y el mismo reproductor WHEP que la vista residente. Si WebRTC falla, se muestra el snapshot y el modal conserva el estado de signaling/ICE para diagnóstico.

## NVR/DVR Media2

Algunos NVR omiten `VideoSourceToken`. 0.13.9 infiere el canal usando, en orden:

1. `VideoSourceToken` / `VideoSourceConfigurationToken` si existe.
2. `chID=N` del URI RTSP.
3. patrón `Profile_N_X` del token/nombre.
4. canal codificado en el snapshot.

Por tanto, dos perfiles Main/Sub del mismo `chID` forman un solo canal lógico.
