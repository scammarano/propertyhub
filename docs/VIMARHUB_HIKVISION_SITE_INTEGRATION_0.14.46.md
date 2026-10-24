# Property Hub 0.14.46 — VimarHub Local Bridge + prueba Hikvision multimarca

## Objetivo

Integrar VimarHub dentro de Property Edge Agent y separar la operación de video ONVIF/RTSP en una pantalla propia para probar dos instalaciones reales:

- **Premier:** descubrir qué gateways/dispositivos Vimar existen realmente en la LAN sin ejecutar acciones físicas.
- **Portal del Ávila 2:** descubrir Vimar para puertas/automatización y conectar cámaras/NVR Hikvision mediante ONVIF/RTSP.

## Vimar local-first

La ruta principal queda:

`Property Node → Property Edge → VimarHub Local Bridge → VIEW / By-me / KNX`

URL local por defecto observada: `http://127.0.0.1:47821`.

El Edge prueba endpoints de salud/inventario compatibles y registra cuál respondió. El discovery es conservador: GET primero; un POST solo se intenta sobre rutas llamadas `discover` con `read_only=true` y `commands=false`.

No se asume que `On/Off = luz`. Se conserva el tipo explícito entregado por VimarHub y, cuando no es concluyente, se registra `other` hasta que el instalador lo clasifique.

## Modelo físico vs. lógico

El discovery materializa **equipos físicos** y objetos externos. No crea automáticamente puertas, luces ni cámaras lógicas.

Ejemplo:

- `Relay 7` → equipo físico `relay`, capabilities `read_state/pulse/...`
- después: `Puerta Servicio → binding → Relay 7`

## Sincronización Edge → Node

El inventario Vimar se sincroniza al mismo `bh_devices` que usa el resto de infraestructura. El Node conserva el nombre/ubicación de negocio si el equipo ya existe y el Edge refresca metadatos técnicos.

Se corrigió además el cierre de inventario ONVIF: una sincronización completa de cámaras ya no puede archivar accidentalmente equipos Vimar del mismo Edge.

## Hikvision

No se introduce una dependencia especial de Hikvision para video. La primera ruta sigue siendo estándar:

`Hikvision NVR/cámara → ONVIF → Property Edge → RTSP → MediaMTX → Property Node/Property App`

Nueva pantalla Edge: **Integraciones locales → Video local · ONVIF / RTSP**.

Desde allí se puede:

1. buscar ONVIF en todas las interfaces IPv4;
2. agregar NVR/cámara manualmente;
3. autenticar ONVIF;
4. enumerar canales/perfiles;
5. escoger solo canales deseados;
6. preferir perfil H264 para navegador;
7. probar RTSP;
8. sincronizar al Node.

## Instalación limpia

Se corrigió también el instalador/migrador acumulativo: instalaciones Node/Edge nuevas ejecutan ahora toda la secuencia no-Central hasta `0040`, no se quedan detenidas en migraciones antiguas.

## Migración nueva

`0040_vimarhub_local_bridge.sql` amplía `bh_vimar_edge_config` con modo `vimarhub_bridge`, ruta de inventario y metadatos de último endpoint/discovery. No modifica migraciones ya distribuidas.
