# Property Hub 0.12.0 — Edge Inventory Sync + Resident Camera Preview

## Contrato de topología

Para una propiedad con Node público y Agent dentro de la LAN:

- Central: `https://2cam.services/property-hub/public/`
- Property Node: URL pública de la propiedad.
- Edge Agent: URL local de la sonda.

El administrador solo empareja el Edge con su Property Node usando **URL del Node + PHEDGE**. El Node ya conoce Central y aprovisiona al Agent la ruta necesaria. Las conexiones operativas se guardan en `bh_remote_connections`; `.env` queda como bootstrap/compatibilidad.

## Inventario sin doble carga

Una cámara ONVIF se crea una sola vez en el Edge Agent. El Edge conserva localmente IP/hostname, endpoint ONVIF, RTSP y credenciales cifradas. Al sincronizar, el Node recibe identidad técnica, fabricante/modelo/serie, estado, capacidades, Agent propietario y disponibilidad de snapshot/RTSP, pero nunca la contraseña de la cámara.

En el Node la cámara se administra como objeto funcional: nombre visible, área y visibilidad para residentes. Si la cámara deja de existir en un inventario completo del Edge, el Node la retira del inventario activo sin convertirla en una cámara administrada localmente.

## Prueba de visualización para propietario

0.12.0 implementa una primera ruta segura de **snapshot**, no video en vivo:

1. Edge obtiene `GetSnapshotUri` por ONVIF y descarga una imagen autenticada dentro de la LAN.
2. Edge sube esa imagen al Node usando su credencial de Agent.
3. Node almacena la imagen fuera de `public` y la sirve mediante `camera-snapshot` solo a usuarios con permiso de cámaras.
4. El residente nunca recibe la IP privada, usuario, contraseña ni URI RTSP/ONVIF.

### Prueba

1. Actualizar Node y Agent a 0.12.0 y aplicar migración 0022.
2. En Edge: **Integraciones locales → ONVIF → Sincronizar cámaras ahora**.
3. En Node: **Infraestructura y tecnología → Cámaras**. La cámara debe aparecer con badge `EDGE · <AGENT>`.
4. Editarla en Node y configurar `Visibilidad residente = Todos los residentes autorizados`.
5. Verificar que la relación del propietario/residente con su unidad tenga `Puede ver cámaras` activo.
6. Abrir la vista de ese propietario/residente desde Property App / vista residente.
7. Entrar en **Cámaras → Ver**. Debe mostrarse el último snapshot recibido desde el Edge.

Si el inventario aparece pero no hay imagen, la sincronización de catálogo funcionó y el siguiente diagnóstico debe centrarse en `GetSnapshotUri`/autenticación del snapshot de esa cámara; no se debe recrear la cámara en el Node.

## Siguiente capa multimedia

El snapshot valida autenticación, autorización y transporte Edge → Node → residente. Video en vivo requerirá una ruta multimedia apropiada (por ejemplo WebRTC/HLS/gateway), sin hacer que el Node cPanel retransmita RTSP bruto ni exponer la LAN.
