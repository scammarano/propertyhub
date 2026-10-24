# Property Hub 0.14.54 · Property-first Portfolio & Migration Timeout Hardening

## Objetivo

Central deja de presentar Property Nodes y Edge Agents como si fueran instalaciones equivalentes. La **propiedad/instalación es el contenedor principal**; dentro de ella viven su Property Node y uno o varios Property Edge Agents.

## Portafolio

`Central → Instalaciones` muestra una sola tarjeta por propiedad. La expansión de la tarjeta muestra el Node y los Edge Agents. La ficha de la propiedad queda organizada en pestañas: Resumen, Node y Edge, Despliegue, Tokens, Integraciones y Administración.

La propiedad puede existir antes de que el Node sea instalado porque el expediente `bh_repo_installation_plans` sigue siendo el registro de preparación. Las instalaciones históricas sin expediente continúan visibles mediante su registro de Node.

## Tokens

La migración Central `0044_property_portfolio_token_history.sql` crea `bh_repo_edge_enrollment_tokens`. Un Edge conserva una identidad estable en `bh_repo_edge_deployments`, mientras cada token temporal emitido queda como registro independiente. Regenerar un token revoca el anterior en vez de sobrescribir su historia.

La ficha de la propiedad muestra tokens de registro/reconexión y enrolamientos Edge en todos sus estados: activo, usado, vencido y revocado. Los secretos siguen mostrándose una sola vez.

## Request Timeout durante migraciones

El timeout observado no requería una migración SQL compleja: el flujo web de reparación ejecutaba en la misma solicitud un respaldo completo (incluyendo storage) y, en Central, la sincronización/reconstrucción del Core oficial además de las migraciones.

0.14.54 cambia ese comportamiento:

- la reparación web usa un `schema_safety_snapshot` (BD + configuración, sin copiar storage persistente);
- si existe un `PRE_UPDATE` reciente y válido, se reutiliza en vez de generar otro;
- la sincronización pesada del repositorio oficial se difiere y puede realizarse desde Versiones y paquetes / Sincronizar repositorio;
- `0043` y `0044` son migraciones exclusivas de Central y ya no se declaran en el manifiesto de actualización distribuido a Node/Edge;
- la migración 0044 sólo crea la tabla de historial y materializa los pocos enrolamientos 0.14.53 existentes; evita actualizaciones masivas sobre tablas históricas.

## Compatibilidad

Los enlaces históricos `repository-installation?id=...` redirigen a la nueva ficha de propiedad, por lo que bookmarks y acciones anteriores no quedan rotos.
