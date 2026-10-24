# 0.14.65 — Camera Access Contextual Controls

- Relación explícita many-to-many Cámara ↔ Puerta/Acceso.
- La asociación puede editarse desde Cámara o desde Puerta y ambas vistas modifican la misma relación.
- El visor en vivo administrativo muestra botones para los accesos asociados sin cerrar ni recargar el video.
- El visor residente ofrece la misma interacción, limitada a los accesos que su unidad/perfil realmente puede operar.
- La apertura valida permisos, política remota y ruta de ejecución compatible en el momento del comando.
- El visor sigue el estado de la apertura y muestra confirmación/error mientras el video continúa activo.
- Auditoría `camera.access.open` con cámara origen y acceso operado.
- Nueva migración `0046_camera_access_contextual_controls.sql`; no se modifican migraciones históricas.
- El Core distribuible elimina de sus `health_files` las rutas `resources/releases/**`, que pertenecen sólo al vault de Central y no viajan dentro del Core Node/Edge.

Ver `docs/CAMERA_ACCESS_CONTEXTUAL_CONTROLS_0.14.65.md`.

---

# 0.14.64 — Edge Update State Reconciliation

- Central distingue candidatos de distribución Edge de pendientes reales.
- El Property Node cruza cada release Edge-only con `installed_components` reportado por sus Agents.
- Un release desaparece de “Actualizaciones pendientes para Edge Agents” cuando todos los Edge activos alcanzan la versión objetivo.
- Si todos están actualizados se muestra un estado compacto “Componentes Edge al día”.
- El contador “Pendientes para Edge” usa el inventario local y no el conteo bruto de releases de Central.
- `disabled`/`retired` no bloquean el estado al día.
- Update Engine 1.2.1.
- Sin migración SQL nueva.

Ver `docs/EDGE_UPDATE_STATE_RECONCILIATION_0.14.64.md`.

---

# 0.14.63 — Connector Version Semantics Reconciliation

- Separa versión de catálogo, política desired-state y versión instalada.
- `latest_compatible` ya no transporta ni muestra una versión histórica como si fuera un pin.
- Desired state legacy se reconcilia automáticamente e idempotentemente en Central.
- Exacta/Mínimo/Rango conservan sus restricciones reales.
- El catálogo de integraciones etiqueta la versión como `catálogo vX`, no como versión instalada.
- La pantalla de actualizaciones muestra `última compatible`, `exacta`, `mínimo` o `rango` explícitamente.
- Sin migración SQL nueva.

Ver `docs/CONNECTOR_VERSION_SEMANTICS_RECONCILIATION_0.14.63.md`.

---

# 0.14.62 — Component Update Backlog & Edge Visibility

- Las actualizaciones publicadas no desaparecen por actualizar primero el Core.
- El Node muestra en una sección separada los releases Edge-only pendientes para la propiedad.
- Si existe un connector histórico instalado en Node, se identifica como legado local; las versiones nuevas siguen su destino Edge.
- El heartbeat Edge reporta versiones instaladas sin credenciales para mostrar estado pendiente/actualizado por Agent.
- Update Engine 1.2.0: el lote automático incluye Core + componentes locales aplicables y reconsulta después del Core para descubrir componentes recién habilitados.
- No se modifican migraciones existentes ni sus checksums.

# 0.14.61 · Automatic Sequential Update Batch

- Un solo clic descarga primero todos los Core de la ruta progresiva y luego los instala automáticamente en orden.
- El lote se persiste en `storage/updates/batches/`; cada salto usa una solicitud HTTP nueva para evitar timeouts y cargar el código recién instalado.
- Cada ZIP se valida por SHA-256 antes de iniciar la primera instalación y se revalida contra un manifiesto firmado fresco antes de aplicarlo.
- El proceso se reanuda automáticamente después de `update-finalize.php` en Windows.
- Un lote fallido puede reanudarse desde el punto exacto sin repetir descargas ya verificadas.
- Edge conserva distribución Central → Node → Edge y el Node autoriza la predescarga sólo de Core presentes en la ruta firmada.
- Update Engine 1.1.0.
- Sin migración SQL nueva.

Ver `docs/AUTOMATIC_SEQUENTIAL_UPDATE_BATCH_0.14.61.md`.

---

# 0.14.60 · Migration Runner Cursor Drain · Repair Bridge Update Path

- Corrige MySQL/PDO error 2014 al ejecutar SQL dinámico que produce result sets durante migraciones.
- `MigrationRunner` usa `query()` + `closeCursor()` por statement y fuerza consultas MySQL bufferizadas.
- Si un rollback encuentra una conexión PDO contaminada por error 2014, reintenta la restauración de BD con una conexión nueva y bufferizada.
- No se modifica `0045`; su SHA-256 histórico permanece intacto.
- Se incorpora `property-hub-core-0.14.56.1.zip`, bridge técnico sin migraciones que repara el updater antes de 0.14.57.
- El bridge completo también revierte a base 0.14.56 conocida archivos parcialmente sustituidos por un intento fallido de 0.14.57.
- El manifiesto entrega un solo siguiente Core y prioriza `repair_bridge`, evitando saltarse la reparación.
- Sin migración SQL nueva.

Ver `docs/MIGRATION_RUNNER_REPAIR_BRIDGE_0.14.60.md`.

---

# 0.14.59 · Immutable Core Reconciliation Fix

- La sincronización oficial deja de reinterpretar un release ya publicado como una publicación nueva.
- Si el Core/version ya tiene un SHA-256 canónico válido, Central conserva esos bytes y nunca los sobrescribe.
- Un ZIP local/embebido distinto de la misma versión genera `source_conflict`, pero no tumba el dashboard ni el manifest.
- Si el paquete canónico falta, sólo se restaura desde una fuente byte-idéntica; una fuente distinta sigue bloqueada por seguridad.
- El estado del repositorio distingue `repository ready` de `fully in sync`.
- La ruta progresiva 0.14.52+ continúa publicándose aunque un bridge local difiera del hash canónico ya publicado.
- Sin migración SQL nueva.

Ver `docs/IMMUTABLE_CORE_RECONCILIATION_0.14.59.md`.

---

# 0.14.58 · Progressive Core Update Path

- Central publica automáticamente los Core puente oficiales incluidos en `resources/releases/`, además del Core actual.
- Un Node atrasado recibe siempre el **siguiente salto compatible**, no queda con 0 actualizaciones sólo porque el último Core exige una versión intermedia.
- Diagnóstico separa **último Core publicado** de **último Core inmediatamente compatible** y muestra **Siguiente Core aplicable**.
- La UI enseña la ruta completa, por ejemplo `0.14.53 → 0.14.54 → 0.14.55 → 0.14.56 → 0.14.57 → 0.14.58`.
- Si falta un artefacto puente, la pantalla identifica la ruta como incompleta en vez de presentar simplemente “0 actualizaciones”.
- Corregida la validación append-only para poder re-publicar un Core histórico byte-idéntico después de haber publicado una versión mayor.
- Sin migración SQL nueva.

Ver `docs/PROGRESSIVE_CORE_UPDATE_PATH_0.14.58.md`.

---

# 0.14.57 · Signed Property Deployment File · Zero-Typing Enrollment

- Central genera un único archivo `<PROPERTY>.propertyhub-deploy` desde la ficha de la propiedad.
- El mismo archivo se importa en Property Node y en todos los Edge Agents de la propiedad.
- Transporta identidad, URLs y tokens temporales de un solo uso; no guarda credenciales permanentes.
- Firma RSA-SHA256, hash canónico, huella de clave y vencimiento se validan antes de consumir cualquier token.
- Después del enrolamiento, Node/Edge comprueban que la clave pública real de Central coincide con la huella del archivo.
- Si hay varios Edge, el instalador sólo pide seleccionar cuál se está instalando; no se escriben códigos, URLs ni tokens.
- Central puede preparar Edge Agents antes de registrar el Property Node mediante la migración `0045_property_deployment_file_pre_node_edges.sql` (operativa en Central y no-op seguro en Node/Edge).
- El flujo manual queda disponible como alternativa de soporte.
- Descargar un archivo no altera Edge Agents ya enrolados; sólo rota/prepara credenciales pendientes.

Ver `docs/SIGNED_PROPERTY_DEPLOYMENT_FILE_0.14.57.md`.

# 0.14.56 · Central Property Identity · Heartbeat Observability · Edge Enrollment Claim Fix

- Se agregó edición del nombre visible de la propiedad desde la ficha Central.
- `bh_repo_installation_plans` y `bh_repo_installations` se actualizan juntos; la reconexión del Node ya no pisa el nombre decidido en Central.
- La lista Property-first diferencia **Node conectado** de la salud de negocio reportada y muestra el último heartbeat real.
- `heartbeat.php` devuelve el expediente actualizado (`installation_code`, `recorded_last_seen_at`, versión y URL reportada), para que una prueba manual confirme exactamente qué registro recibió el heartbeat.
- El Scheduler deja una marca de su última ejecución y la pantalla del Node aclara que el heartbeat manual no mantiene conectividad continua.
- El front-controller Central ahora expone `repository-api-edge-enrollment-claim` y `repository-api-edge-bootstrap-token`, corrigiendo el canje de tokens `PHEDGE-C-*` en hosting donde la ruta PHP directa no es accesible.
- No se introduce una migración SQL nueva.

# 0.14.55 · Edge Enrollment Contract Hardening · Safe Property Deletion

- Corrige el escenario diagnosticado por log donde un Edge se estaba instalando con código 0.14.52 que todavía exigía `parent_property_code`.
- El contrato Edge vigente queda fingerprinted como `property-node-url-plus-token-v2`; los incidentes registran versión de paquete y contrato.
- Detecta paquetes/carpetas mezcladas y pide extracción limpia en lugar de continuar con archivos legacy.
- Central permite eliminar desde la ficha propiedades creadas por error o inactivas; bloquea si Node/Edge reportaron actividad en los últimos 5 minutos.
- Los respaldos externos se conservan por defecto.

# Property Hub 0.14.54 — Property-first Portfolio & Migration Timeout Fix

- Portafolio Central: una entrada por propiedad; Node y Edge Agents aparecen al expandirla.
- Nueva ficha de propiedad por pestañas: Resumen, Node/Edge, Despliegue, Tokens, Integraciones y Administración.
- Vista ejecutiva puede filtrarse por propiedad y deja de mezclar Edge Agents como instalaciones de primer nivel.
- Nuevo historial durable `bh_repo_edge_enrollment_tokens`; regenerar un token Edge revoca el anterior sin borrarlo.
- La ficha muestra tokens activos, usados, vencidos y revocados de la propiedad.
- Los enlaces históricos a fichas de Node redirigen a la nueva ficha de propiedad.
- `0044_property_portfolio_token_history.sql` es exclusiva de Central.
- Reparación web de schema usa snapshot BD/configuración sin storage, reutiliza PRE_UPDATE reciente y difiere sincronización pesada del Core para reducir Request Timeout.
- Las migraciones 0043/0044 exclusivas de Central no se distribuyen en el manifiesto de actualización de Node/Edge.

Ver `docs/PROPERTY_FIRST_PORTFOLIO_MIGRATION_TIMEOUT_0.14.54.md`.

# Property Hub 0.14.53 — Installation Deployment & Enrollment

- Centro de despliegue permanente en la ficha de cada propiedad de Central.
- Muestra Property Code, Node Code, URL Central, URL Node y estado del vínculo.
- Soporte explícito para múltiples Edge Agents por propiedad.
- Central emite tokens `PHEDGE-C-*` de un solo uso y 24 horas.
- El Property Node reclama/valida esos tokens ante Central y luego crea la credencial local del Edge.
- El Edge nunca recibe credenciales Central ni se registra directamente contra Central.
- El instalador Edge deja de exigir `parent_property_code`; el código padre se obtiene de la respuesta autenticada del Node.
- Nueva migración `0043_installation_deployment_enrollment.sql`, segura en todos los roles y operativa únicamente en Central.

Ver `docs/INSTALLATION_DEPLOYMENT_ENROLLMENT_0.14.53.md`.

# Property Hub 0.14.52 — Component Registry · Immutable Release Lifecycle

- Nuevo registro maestro `bh_repo_components` con aliases explícitos para identidad estable de Core/conectores/componentes.
- `bh_repo_releases.component_id` enlaza cada versión con el mismo componente; `component_id + version` queda protegido por unicidad en la BD.
- Publicación manual toma identidad, versión, roles y compatibilidad desde `update-package.json` dentro del ZIP; el formulario ya no puede contradecir el artefacto.
- Un release publicado queda inmutable por SHA-256. La misma versión con bytes distintos se rechaza.
- Nuevo `bh_repo_release_channels`: un único artefacto puede promoverse de beta a stable sin duplicar ni re-subir el ZIP.
- Desired connectors soporta `latest_compatible`, `exact`, `minimum` y `range`; Central filtra releases según la política efectiva.
- Dependencias del paquete se materializan y Update Manager las valida antes de ofrecer instalación.
- `bh_repo_assignments` participa ahora en el rollout del manifiesto.
- Fresh install/migrate incluyen 0041 para Node/Edge y la nueva 0042 exclusivamente para Central.
- El catálogo Vimar incluido en Core queda alineado a 0.5.1; el ZIP `connector-vimar 0.5.1` permanece byte-idéntico.
- Ver `docs/COMPONENT_REGISTRY_RELEASE_LIFECYCLE_0.14.52.md`.

---

# Property Hub 0.14.51 — Connector Desired State · Edge-only Distribution

- Fuente única de verdad para conectores habilitados por instalación.
- Si una instalación histórica no tiene `desired_connectors`, el plan inicial se materializa automáticamente en `metadata_json`.
- Una selección explícita, incluso vacía, es autoritativa y no vuelve a poblarse desde el plan.
- El API de manifiestos y la ficha Central consumen la misma política.
- Diagnóstico de actualizaciones muestra conectores habilitados, fuente efectiva y exclusiones legibles.
- Los conectores locales pueden ser Edge-only; Vimar 0.5.1 declara `target_roles: [edge_agent]`.
- El Node sigue autorizando, cacheando y distribuyendo el ZIP sin instalar el runtime de discovery.
- Perfil provisionado de Vimar deja de describirse como VimarHub obligatorio y pasa a Vimar Universal / discovery local directo.
- Update Engine 1.0.3.
- Sin migraciones nuevas; 0001–0041 permanecen intactas.

---

# Property Hub 0.14.50 — Vimar Universal Connector · Direct Edge Discovery

- Elimina la dependencia obligatoria de VimarHub para el conector productivo.
- VimarHub continúa como programa/laboratorio independiente y bridge opcional.
- `connector-vimar` sube a 0.4.0 y el modo predeterminado pasa a `direct_auto`.
- Property Edge descubre directamente por mDNS/HomeKit advertisement, KNXnet/IP Search y SSDP.
- Permite seleccionar interfaz LAN y ventana de discovery.
- Los gateways identificados se adoptan primero como inventario técnico; no se convierten automáticamente en puertas/luces.
- `Adoptar / sincronizar con Node` mantiene la cadena Edge → Node.
- Nueva migración `0041_vimar_direct_edge_discovery.sql`; 0001–0040 permanecen intactas.
- La enumeración profunda que requiera pairing HomeKit/credenciales específicas queda en adapters dedicados, sin inventar ni romper pairing existente.

# Property Hub 0.14.49 — Connector Catalog + Central Desired Provisioning

- Corrige un defecto de 0.14.48: Vimar 0.3.0 estaba en `connectors/Vimar/manifest.json` pero no se publicaba como release independiente ni se refrescaba el catálogo si no había migraciones pendientes.
- Central publica automáticamente los componentes embebidos `property-hub-connector-*.zip` junto con el Core.
- Agrega activación de conectores por instalación en la ficha Central.
- El manifiesto Central incluye `desired_connectors` y filtra releases de conectores no habilitados para esa propiedad.
- El Property Node reconcilia ese desired state en `Sistemas conectados`; luego el Edge sólo ve los drivers habilitados por su Node.
- Vimar Connector 0.3.0 se entrega como componente `connector-vimar`, canal estable por distribución explícita, catálogo beta, mínimo Core 0.14.46.
- Sin migraciones nuevas; 0001–0040 intactas.

# Property Hub 0.14.48 — Support Log ZIP + Core Artifact Self-Heal

- Agrega **Descargar logs ZIP** en la vista de error 500 para administradores/superadmins.
- Agrega descargas de logs de 24 h, 7 días y 30 días en Diagnóstico.
- El bundle de soporte añade `support-info.json` con versión, rol, incidente/request ID y listado de archivos.
- Aplica una segunda redacción defensiva a passwords, authorization headers, cookies, tokens y credenciales embebidas en RTSP/HTTP.
- Corrige la falla `El paquete oficial del Central no contiene el Core distribuible esperado`: `RepositorySynchronizer` puede reconstruir el Core distribuible si el ZIP embebido no quedó físicamente desplegado.
- El Core reconstruido excluye `.env*`, `storage/**`, `.git/**` y `resources/releases/**` y fuerza `target_roles = building_node, edge_agent`.
- El paquete de actualización del Central ya no necesita transportar `storage/repository/**`; ese directorio sigue siendo runtime/protegido.
- Sin migraciones nuevas. `0001`–`0040` sin modificaciones.
