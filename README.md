# Property Hub 0.14.65

Esta acumulativa incorpora **controles de acceso contextuales dentro del visor de cámaras**. Una cámara puede asociarse a una o varias puertas y una puerta a una o varias cámaras. El operador o residente autorizado puede abrir el acceso sin abandonar el video y observar el paso en tiempo real.

La relación es explícita y bidireccional; compartir ubicación no concede asociación ni permisos. La apertura respeta `cameras.view`, `access.operate` / `access.operate.allowed`, la política remota del acceso y la ruta de ejecución disponible, y genera auditoría.

Ver `docs/CAMERA_ACCESS_CONTEXTUAL_CONTROLS_0.14.65.md`.

---

# Property Hub 0.14.64

Esta acumulativa corrige el estado de actualizaciones Edge: Central publica candidatos de distribución y el Property Node los reconcilia contra el inventario real reportado por sus Edge Agents. Un componente ya actualizado deja de aparecer como pendiente.

Ver `docs/EDGE_UPDATE_STATE_RECONCILIATION_0.14.64.md`.

---

0.14.63 separa versión de catálogo, política desired-state y versión instalada. Ver `docs/CONNECTOR_VERSION_SEMANTICS_RECONCILIATION_0.14.63.md`.

---

Esta acumulativa corrige la visibilidad persistente del backlog de componentes y amplía la actualización automática para que un update omitido no se pierda detrás de una actualización de Core. Los conectores Edge-only continúan instalándose exclusivamente en Edge, pero el Node muestra claramente qué versión está pendiente para sus Agents.

# 2CAM Property Hub

**Versión acumulativa actual: 0.14.65 — Camera Access Contextual Controls.**

0.14.60 corrige el fallo de actualización `SQLSTATE[HY000] 2014 Cannot execute queries while other unbuffered queries are active` observado al ejecutar `0045` en Property Node/Edge. La migración histórica no se altera: se repara el ejecutor PDO y se agrega un bridge inmutable `0.14.56.1` sin migraciones para que los Nodes reparen primero su Update Engine y después continúen a 0.14.57.

Ruta de recuperación: `0.14.56 → 0.14.56.1 → 0.14.57 → 0.14.58 → 0.14.59 → 0.14.60`. El rollback también puede abrir una conexión PDO limpia si la conexión de la migración quedó bloqueada por el error 2014.

Ver `docs/MIGRATION_RUNNER_REPAIR_BRIDGE_0.14.60.md`.

---

# 2CAM Property Hub 0.14.59

0.14.59 corrige un choque entre **inmutabilidad de releases** y **sincronización del repositorio**. Si Central ya publicó un Core/version con un SHA-256 canónico y luego encuentra otro ZIP local de la misma versión con bytes distintos, conserva el release publicado, registra la divergencia y continúa atendiendo nodos en lugar de lanzar una excepción que tumbe la página.

La inmutabilidad no se relaja: un paquete publicado nunca se reemplaza. Si el ZIP canónico falta, sólo puede restaurarse desde una copia con el mismo SHA-256.

Ver `docs/IMMUTABLE_CORE_RECONCILIATION_0.14.59.md`.

---

# 2CAM Property Hub 0.14.58

0.14.58 incorpora **actualización progresiva del Core**. Si Central está en una versión muy superior al Node, el repositorio conserva/publica los puentes y ofrece el siguiente paso compatible hasta alcanzar el último Core.

Ejemplo: `0.14.53 → 0.14.54 → 0.14.55 → 0.14.56 → 0.14.57 → 0.14.58`.

Ver `docs/PROGRESSIVE_CORE_UPDATE_PATH_0.14.58.md`.

---

# 2CAM Property Hub 0.14.57

0.14.57 introduce el **archivo único de despliegue firmado**. Desde Central se descarga `<PROPERTY>.propertyhub-deploy` y el mismo archivo se importa en el instalador del Property Node y luego en cada Property Edge Agent. No es necesario copiar manualmente `property_code`, códigos Edge, URLs de Central/Node ni tokens.

El archivo contiene exclusivamente datos de despliegue y tokens temporales de un solo uso; las credenciales permanentes se negocian durante el enrolamiento. La firma RSA-SHA256 y la huella de la clave de Central se verifican localmente y contra la cadena real al completar el vínculo. También se permite preparar Edge Agents antes de que exista el registro del Node.

Ver `docs/SIGNED_PROPERTY_DEPLOYMENT_FILE_0.14.57.md`.

## 0.14.57

- Un solo archivo descargable por propiedad para Node y Edge.
- Importación desde el wizard con autocompletado de identidad, URLs y tokens.
- Selección visual del Edge cuando hay más de uno.
- Firma RSA, hash canónico, expiración y verificación de huella contra Central.
- Nueva migración `0045_property_deployment_file_pre_node_edges.sql`: operativa en Central y no-op seguro en Node/Edge para actualización automática.
- Configuración manual continúa disponible para diagnóstico/soporte.
- Descargar un archivo no altera Edge Agents ya enrolados; sólo rota/prepara credenciales pendientes.

## 0.14.56

- Central permite editar el nombre visible de una instalación sin cambiar su código, UUID, Node ni Edge.
- El nombre de Central queda autoritativo y no vuelve a ser pisado por el nombre local del Node al reconectarse.
- La vista de instalaciones separa **conectividad** (heartbeat reciente) de **salud reportada** y muestra la hora del último heartbeat.
- El heartbeat manual confirma explícitamente qué código de instalación actualizó Central.
- El Node muestra si el Scheduler está instalado y la última ejecución registrada; el botón de heartbeat manual queda identificado como una prueba de una sola vez.
- Se agrega el fallback del front-controller para `edge-enrollment-claim` y `edge-bootstrap-token`, necesario en hosting donde no funcionan las rutas PHP directas.

## 0.14.55
El enrolamiento Edge usa únicamente URL del Property Node + token; la propiedad padre es descubierta por el Node. Central permite eliminar propiedades preparadas por error o inactivas desde la pestaña Administración con protección por heartbeat reciente.

## Desarrollo en GitHub

El repositorio canónico de Property Hub es `scammarano/propertyhub`.

- `main` es la fuente de verdad del código fuente.
- La baseline inicial importada a GitHub es Property Hub `0.14.65`.
- El desarrollo normal se realiza en ramas `feat/*` o `fix/*` y se integra mediante Pull Request.
- Las migraciones publicadas son append-only: no se modifica una migración ya distribuida.
- Los releases publicados son inmutables: un cambio de bytes requiere una versión nueva.
- `storage/`, secretos, respaldos y ZIP generados de distribución no se versionan en Git.

Vea `docs/GITHUB_DEVELOPMENT_MODEL.md` para el flujo completo.
