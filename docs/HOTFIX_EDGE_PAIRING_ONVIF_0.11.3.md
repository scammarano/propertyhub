# Property Hub 0.11.3 — Edge Pairing UX + ONVIF Manual Fix

## Correcciones

1. El flujo normal de un Edge Agent usa exclusivamente la URL del Property Node y un token PHEDGE generado por ese Node. La URL y credencial de Central se reciben automáticamente.
2. La pantalla de vínculo del Agent deja de exponer formularios BHR/URL Central para evitar confundir PHEDGE con credenciales del repositorio.
3. Se aceptan alias históricos de rol para instalaciones actualizadas.
4. Se corrige el alta manual ONVIF cuando `onvif_url` está vacía: se usa correctamente `host` para construir `/onvif/device_service`.
5. WS-Discovery automático envía probes por todas las interfaces IPv4 disponibles.

No requiere migración SQL.
