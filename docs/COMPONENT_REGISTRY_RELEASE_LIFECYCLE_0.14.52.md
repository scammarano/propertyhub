# Property Hub 0.14.52 · Component Registry & Release Lifecycle

## Objetivo

0.14.52 formaliza la identidad y el ciclo de publicación de Core, conectores y demás componentes. Una versión ya no se identifica por textos repetidos en el formulario de publicación: pertenece a un registro maestro de componente y el artefacto ZIP es la fuente de verdad de su identidad.

## Identidad canónica

Central incorpora `bh_repo_components` y `bh_repo_component_aliases`.

- `component_code` identifica permanentemente al componente, por ejemplo `connector-vimar`.
- Los releases se vinculan mediante `component_id`.
- Para conectores, `connector_code` (`vimar`) es un alias explícito y estable usado por el catálogo/desired state; ya no depende de quitar prefijos por heurística.
- Un componente existente no puede cambiar de `component_type` ni de `connector_code`.

La combinación `component_id + version` es única en la base de datos. Por tanto, `connector-vimar 0.5.1` no puede volver a crearse como un release diferente.

## Artefactos inmutables

Al publicar, Central abre y valida `update-package.json` dentro del ZIP y obtiene de allí:

- `component_type`
- `component_code`
- `display_name`
- `version`
- compatibilidad
- roles destino
- dependencias

El operador selecciona únicamente un componente esperado como guard opcional, el canal inicial y notas humanas. Si el componente seleccionado no coincide con el paquete, la publicación se rechaza.

Una vez publicada una combinación componente/versión, su SHA-256 queda bloqueado. Si se intenta publicar la misma versión con bytes distintos, Central rechaza el paquete y exige incrementar la versión. Si el ZIP es byte-idéntico, el release existente se reutiliza.

## Canales como promoción, no como artefactos diferentes

`bh_repo_release_channels` permite que el mismo artefacto inmutable sea promovido a `development`, `beta`, `stable` o `pinned` sin volver a subir ni duplicar el ZIP.

Ejemplo:

1. `connector-vimar 0.6.0` se publica una sola vez.
2. Se promueve a `beta`.
3. Después puede promoverse a `stable` conservando exactamente el mismo SHA-256 y `release_code`.

## Desired state de conectores

Cada conector habilitado por instalación puede usar una política de versión:

- `latest_compatible`: la versión publicada más alta que cumpla rol/Core/dependencias.
- `exact`: sólo una versión concreta.
- `minimum`: exige una versión mínima.
- `range`: admite expresiones simples como `>=0.5.0,<0.7.0`.

Central aplica la política al generar el manifiesto para Node/Edge. El Node conserva además la política efectiva en los metadatos de la integración provisionada.

## Dependencias y asignaciones

Las dependencias declaradas por el paquete se materializan en `bh_repo_release_dependencies`. Para componentes no-Core, `min_core_version` se expresa además como dependencia obligatoria de `property-hub-core`.

El Update Manager comprueba dependencias instaladas antes de ofrecer un release. `bh_repo_assignments` también participa ahora en el filtrado del manifiesto para rollout por instalación, tipo de instalación o rango de versión.

## Migración 0042

`0042_component_registry_release_lifecycle.sql` es exclusiva de Property Hub Central. Hace backfill de releases ya existentes al nuevo registro maestro, crea aliases y canales, y aplica la unicidad `component_id + version`.

No se agrega 0042 a la lista de migraciones del Core distribuible para Node/Edge: el archivo viaja con el código común, pero SchemaManager/InstallerService/tools/migrate sólo lo ejecutan cuando el rol es `central_repository`.

## Vimar 0.5.1

El paquete inmutable `property-hub-connector-vimar-0.5.1.zip` se conserva byte-idéntico. Se corrigió el manifest del catálogo incluido en el Core para que también declare `connector_version: 0.5.1`, eliminando la discrepancia anterior con 0.4.0.
