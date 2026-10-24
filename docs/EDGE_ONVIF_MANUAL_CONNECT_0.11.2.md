# Property Hub 0.11.2 — Edge Auto Enrollment + ONVIF Manual Connect

## Objetivo

Permitir que un Property Edge Agent se instale/empareje sin administrar dos tokens visibles y permitir conectar una cámara ONVIF incluso cuando WS-Discovery no devuelve resultados.

## Emparejamiento Edge

1. El Property Node debe estar registrado en Property Hub Central.
2. En **Property Edge Agents**, el administrador indica código y nombre del Agent y genera un **PHEDGE**.
3. El Node solicita a Central un bootstrap de un solo uso para ese código y lo guarda cifrado dentro del registro PHEDGE.
4. El Agent recibe únicamente URL del Property Node + PHEDGE.
5. El Agent se empareja con el Node y luego se registra automáticamente en Central.

El BHR manual de Edge queda reservado para recuperación/soporte avanzado, no para el flujo normal.

## ONVIF

El Agent ofrece dos caminos:

- **Descubrir en la LAN** mediante WS-Discovery multicast, con selección de interfaz IPv4.
- **Agregar manualmente** por IP/hostname/URL, puerto, usuario y contraseña.

La conexión manual prueba el Device Management Service, consulta información del equipo y capacidades, intenta localizar Media Service, perfiles, URI de stream y URI de snapshot. Las credenciales se guardan cifradas exclusivamente en la BD local del Agent.

## Prueba recomendada en Premier

- IP de cámara accesible desde el servidor XAMPP.
- ONVIF habilitado en la cámara.
- Usuario ONVIF válido.
- En Agent → Integraciones locales, agregar manualmente la cámara y pulsar **Guardar y conectar ONVIF**.
- Confirmar fabricante/modelo/serie, perfiles y RTSP.

## Nota

WS-Discovery puede no responder por VLAN, firewall, multicast o selección de interfaz. Un resultado de 0 dispositivos no impide el alta manual.
