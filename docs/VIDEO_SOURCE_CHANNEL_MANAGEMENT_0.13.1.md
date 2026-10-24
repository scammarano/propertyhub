# Property Hub 0.13.1 · Fuentes de video y canales NVR/DVR

## Objetivo

Evitar doble trabajo entre Property Edge Agent y Property Node. Una cámara, NVR o DVR local se configura técnicamente una sola vez en el Edge. El Node recibe únicamente el inventario lógico que debe usar la propiedad.

## Edición sin recrear

En el Edge, cada fuente ONVIF/RTSP dispone de **Editar**. Se pueden corregir nombre técnico, tipo de fuente, IP/hostname, puerto, URL ONVIF, usuario, contraseña, RTSP y validación TLS. Al editar, dejar la contraseña vacía conserva la existente. Después de guardar se vuelve a probar ONVIF y se sincroniza con el Node.

## Bajas sincronizadas

Eliminar una fuente en Edge crea una baja lógica (tombstone) y en la misma sincronización retira del inventario activo del Node todos sus canales. El Node conserva trazabilidad interna, pero la cámara deja de mostrarse a administradores y residentes.

Si existe una copia huérfana de una cámara que fue eliminada antes de 0.13.1, el Node permite **Retirar copia**. Si el Edge todavía la reporta, reaparecerá en la siguiente sincronización; si realmente ya no existe en Edge, permanecerá retirada.

## NVR, DVR y encoder ONVIF

ONVIF puede publicar varios perfiles por una misma fuente física. Property Hub agrupa los perfiles usando el `VideoSourceConfiguration/SourceToken` cuando el equipo lo informa.

Una fuente física puede generar varios canales lógicos:

```text
NVR Lobby
├── Canal 1 · Entrada
├── Canal 2 · Garaje
├── Canal 3 · Ascensor
└── Canal 4 · Piscina
```

Los canales nuevos de una fuente multicanal quedan **deshabilitados por defecto**. En Edge se decide, por canal:

- si se publica o no al Node;
- nombre técnico;
- perfil ONVIF a utilizar;
- codec/resolución que quedará preparado para video remoto.

El Node recibe cada canal habilitado como una cámara lógica independiente, pero mantiene una sola ficha física para el NVR/DVR.

## Permisos por canal

En el Node cada canal se configura independientemente:

- nombre visible;
- área;
- visibilidad residente;
- audio;
- PTZ;
- grabaciones;
- preparación para video en vivo.

Por tanto, publicar un NVR de 16 canales no obliga a exponer los 16. El Edge puede publicar solo 4, y el Node puede hacer visibles a residentes solo 2 de esos 4.

## Prueba cPanel

0.13.1 agrega el modo **Prueba cPanel** en Video remoto. Este modo permite probar sin VPS:

- inventario Edge → Node;
- snapshots reales;
- canales NVR/DVR;
- permisos y visibilidad del residente;
- selección de perfiles H.264 para dejar los canales preparados.

El hosting cPanel no se utiliza como relay RTSP/WebRTC. El video continuo WebRTC se activa posteriormente seleccionando **WebRTC externo** y configurando un relay público apropiado.

## Propiedad de los datos

Edge conserva en forma local y cifrada:

- IP/host;
- usuario y contraseña ONVIF;
- contraseña/URI RTSP;
- configuración técnica de cada fuente.

Node recibe:

- UUID de fuente y canal;
- fabricante/modelo/serie;
- tipo de fuente;
- canal;
- disponibilidad ONVIF/RTSP/snapshot;
- estado y capacidades;
- metadatos necesarios para permisos y visualización.

Las contraseñas de la cámara/NVR no se envían al Node.
