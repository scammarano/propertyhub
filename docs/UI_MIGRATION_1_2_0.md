# Migración de Property Hub a 2CAM UI Standard 1.2.0

## Alcance aplicado

- Reemplazo completo de los assets visuales comunes.
- Incorporación del `SidebarRenderer` oficial.
- Navegación administrativa con códigos estables `GROUP / SECTION / ITEM`.
- Carga temprana de `2cam-sidebar-bootstrap.js`.
- Carga de `2cam-dashboard-responsive.css`.
- Un único controlador de sidebar: `assets/js/2cam-ui.js`.

## No modificado

- `.env` y conexiones.
- Autenticación local, Google o Apple.
- Repositorio de demostración.
- Rutas y permisos del servidor.
- Resident App y su navegación inferior.
- Conectores o lógica de dispositivos.

## Persistencia local

El runtime migra automáticamente las claves antiguas del navegador y utiliza:

- `lte.sidebar.state` para la barra contraída o expandida.
- `2cam:sidebar-group-state:v2` para cada grupo o sección.

## Base de datos

No se aplicó la migración SQL del paquete visual porque Property Hub todavía no persiste preferencias de sidebar en base de datos. Cuando se active el constructor SUPERADMIN deberá incorporarse el esquema UI correspondiente.
