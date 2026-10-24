# Property Hub 0.13.0 — Edge Update Topology Repair

## Contrato definitivo Node / Edge / Central

- **Property Node**: se registra manualmente una vez contra Property Hub Central y conserva esa conexión.
- **Property Edge Agent**: el operador solo configura `Property Node URL + PHEDGE`.
- El **Property Node** aprovisiona automáticamente al Agent con la URL de Central y una credencial propia del Agent.
- El **Agent consulta y descarga actualizaciones directamente desde Central**, usando la credencial aprovisionada por el Node.
- Inventario físico, cámaras, ZKBio y eventos locales se sincronizan **Agent → Node**. No se duplican credenciales de equipos en el Node.

## Reparación automática de credenciales Central

Si Central responde `401 invalid_installation_credentials`, el Agent no pide un BHR al operador. El Agent autenticado solicita un bootstrap fresco al Property Node; el Node lo solicita a Central; el Agent rota su credencial y reintenta la operación una vez.

Endpoint privado nuevo en el Property Node:

`POST /api/edge/v1/central-bootstrap.php`

Requiere las credenciales Edge ya emparejadas.

## Actualizaciones

Desde 0.13.0 `UpdateManager` deja de leer `CENTRAL_REPOSITORY_URL` e `INSTALLATION_API_KEY` del `.env` para descargar el paquete. Usa `bh_remote_connections`, igual que manifest y heartbeat.

Esto corrige el caso en que heartbeat/manifest podían usar la conexión nueva de BD pero la descarga del ZIP seguía usando una credencial histórica del `.env`.

## Recuperación de nombre de base de datos

Si `DB_DATABASE` falta o cae al valor genérico `property_hub`, la configuración consulta `storage/installed.lock` y recupera el nombre de la BD instalada. Esto evita que una actualización manual convierta una instalación Edge existente en una conexión contra una BD inexistente.

## UI

En Property Node se muestra una sola URL de Central. El formulario de BHR queda bajo **Registro / reconexión** y no se presenta como operación diaria.

En Edge Agent no existe configuración manual de Central. Se muestran sus vínculos y existe un botón de reparación automática que no requiere tokens.
