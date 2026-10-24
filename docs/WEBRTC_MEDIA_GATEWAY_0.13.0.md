# Property Hub 0.13.0 · WebRTC Media Gateway

## Objetivo

Convertir una cámara ONVIF/RTSP ya configurada en un Property Edge Agent en video en vivo autorizado dentro de Property App sin exponer la IP, usuario o contraseña de la cámara.

## Arquitectura

```text
Cámara ONVIF/RTSP (LAN)
        |
        v
Property Edge Agent + MediaMTX local
        |
        | RTSP/TCP saliente
        v
Media Relay público (MediaMTX)
        |
        | WHEP / WebRTC
        v
Navegador del propietario
```

El Property Node administra la configuración del relay, la habilitación de cada cámara y los permisos de residentes. El Edge Agent conserva los secretos LAN y ejecuta el Media Gateway local.

## Configuración del Node

1. Abra **Infraestructura y tecnología -> Cámaras**.
2. En **Media Relay · WebRTC** configure la URL HTTPS pública del WHEP y la URL RTSP pública para publicación.
3. Guarde. Si no existen credenciales, el Node genera automáticamente una pareja para publicación Edge y otra para lectura WebRTC.
4. Descargue el `mediamtx.yml` generado y despliegue el relay público con `deployment/media-relay/`.
5. Edite la cámara sincronizada desde Edge y active **Video en vivo WebRTC** y la visibilidad correspondiente.

## Configuración del Edge

1. Pulse **Sincronizar cámaras ahora** para recibir del Node el relay y la asignación de path.
2. En **Media Gateway · WebRTC**, instale MediaMTX 1.20.0.
3. Genere la configuración.
4. Inicie el gateway y compruebe estado.
5. Cuando el path aparezca listo, vuelva a sincronizar o espere al scheduler para que el Node reciba `live_status=ready`.
6. Ejecute una sola vez `tools\install-media-gateway-task.bat` como Administrador para inicio automático con Windows.

## Códec

Property Hub prefiere automáticamente un perfil ONVIF H.264 cuando existe. H.265/HEVC se marca incompatible para la primera ruta WebRTC de navegador. Si una cámara solo ofrece H.265, habilite un substream H.264 en cámara/NVR o incorpore transcoding en una etapa posterior.

## Residente

La ruta `camera-live-config` valida primero la autorización normal de la cámara en el Node. Solo si la cámara está habilitada y `ready`, devuelve los datos WHEP necesarios al navegador. Ante fallo WebRTC, la vista vuelve al snapshot seguro existente.

## Seguridad

- Credenciales ONVIF/RTSP: solo Edge.
- Credencial de publicación del relay: Node -> Edge, cifrada localmente.
- Credencial de lectura del relay: Node -> navegador autorizado al abrir la cámara.
- Paths de video: aleatorios por cámara.
- Control API de MediaMTX: localhost únicamente.
- WHEP público: HTTPS obligatorio fuera de laboratorio.
