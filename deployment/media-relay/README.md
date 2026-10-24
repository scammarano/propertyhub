# Property Hub Media Relay · WebRTC

Este componente público recibe por RTSP/TCP los streams que publica cada Property Edge Agent y los expone a los navegadores autorizados mediante WebRTC/WHEP. El Property Node conserva autenticación, permisos y reglas de negocio; el relay solo enruta media.

## Flujo

`Cámara RTSP LAN -> Edge MediaMTX -> RTSP/TCP saliente -> Media Relay público -> WHEP/WebRTC -> navegador`

No se requiere IP pública ni puertos entrantes en el Edge Agent.

## Requisitos del host público

- Docker / EasyPanel / servidor equivalente.
- DNS, por ejemplo `media.example.com`.
- Si usa Cloudflare DNS, deje el hostname de media en **DNS only** para que ICE `8189/UDP` llegue directamente al relay (salvo que disponga de un producto L4/UDP equivalente).
- HTTPS público que haga reverse proxy hacia el puerto TCP `8889` del contenedor.
- Puerto `8554/TCP` accesible desde los Edge Agents para publicación RTSP.
- Puerto `8189/UDP` accesible desde Internet para ICE/WebRTC.
- `8189/TCP` es un fallback útil para redes donde UDP no funciona.

## Instalación

1. En el Property Node abra **Infraestructura y tecnología -> Cámaras -> Media Relay**.
2. Configure:
   - URL WebRTC/WHEP pública: `https://media.example.com`
   - URL RTSP de publicación: `rtsp://media.example.com:8554`
3. Guarde. Property Hub genera las credenciales de publicación y visualización si todavía no existen.
4. Pulse **Descargar mediamtx.yml**. Use ESE archivo; `mediamtx.yml.example` es solo una plantilla.
5. Copie `docker-compose.yml` y el archivo descargado como `mediamtx.yml` en el servidor público.
6. Inicie el stack: `docker compose up -d` o su equivalente en EasyPanel.
7. Configure el proxy HTTPS de `media.example.com` hacia `http://property-hub-media-relay:8889` (o `http://127.0.0.1:8889` según su despliegue). Debe preservar los métodos HTTP, el encabezado `Authorization` y las respuestas CORS.
8. Abra/encamine `8554/TCP`, `8189/UDP` y opcionalmente `8189/TCP`.
9. Regrese al Property Node y pulse **Probar relay**.

## Edge Agent

Después de configurar el relay en el Node:

1. Habilite **Video en vivo WebRTC** en la cámara del Node.
2. Sincronice las cámaras en el Agent o espere al scheduler.
3. En el Agent, abra **Media Gateway · WebRTC**.
4. Pulse **Instalar MediaMTX 1.20.0**.
5. Pulse **Generar configuración**.
6. Pulse **Iniciar gateway** y luego **Comprobar estado**.
7. Cuando funcione, ejecute como Administrador `tools\install-media-gateway-task.bat` una sola vez para que el gateway inicie con Windows.

El Agent conserva IP, usuario y contraseña ONVIF/RTSP. El Node solo recibe catálogo, estado y una ruta aleatoria de video.

## Códecs

Para navegador use preferentemente un perfil H.264. El Agent intenta seleccionar automáticamente un perfil H.264 cuando ONVIF publica varios perfiles. Si la cámara solo entrega H.265/HEVC, Property Hub la marcará como incompatible para esta ruta WebRTC y deberá habilitar un substream H.264 en la cámara/NVR o agregar transcoding en una fase posterior.

## Seguridad

- No publique el Control API `9997`; queda enlazado a localhost.
- No exponga las credenciales RTSP/ONVIF de cámaras fuera del Edge Agent.
- Use HTTPS para el endpoint WHEP público.
- El archivo `mediamtx.yml` generado contiene credenciales del relay y debe tratarse como secreto operativo.

## Watchdog local desde 0.14.25

En modo Edge directo de laboratorio, el worker local supervisa MediaMTX y los Quick Tunnels HLS/WHEP. Si `trycloudflare.com` deja de resolver o devuelve un error 5xx/530, el Edge genera una URL nueva y la reporta al Node automáticamente. Para que funcione desde el boot de Windows, instale una sola vez `tools\install-edge-command-worker.bat` como Administrador.
