# Repository Bundled Core / Release Gate Fix — 0.14.30

## Problema confirmado

El Central podía quedar actualizado a una versión nueva (por ejemplo 0.14.29) mientras el ZIP oficial seguía conteniendo únicamente un Core distribuible anterior (`property-hub-core-0.14.26.zip`). `RepositorySynchronizer` exige deliberadamente que la versión del Core embebido coincida con `config('app.version')`; por ello la sincronización fallaba con:

> El paquete oficial del Central no contiene el Core distribuible esperado para la versión instalada.

No era un problema de base de datos ni de migraciones: era un defecto de construcción del paquete oficial posterior a 0.14.26.

## Corrección

0.14.30 vuelve a entregar de forma contractual:

- `resources/releases/property-hub-core-0.14.30.zip`;
- `storage/repository/packages/property-hub-core-0.14.30.zip` como copia espejo del mismo archivo;
- `update-package.json` del Core con `version = 0.14.30`;
- todas las 36 migraciones históricas, sin modificación;
- todos los `health_files` declarados.

El Core distribuible no contiene `.env*`, claves, uploads, backups, logs, paquetes de repositorio ni `resources/releases/` anidados.

## Release gate obligatorio a partir de esta versión

Un paquete Central no se considera publicable si falla cualquiera de estas comprobaciones:

1. existe `resources/releases/property-hub-core-<VERSION>.zip`;
2. el `update-package.json` interno declara exactamente la misma `<VERSION>`;
3. el mirror `storage/repository/packages/property-hub-core-<VERSION>.zip` es byte-a-byte idéntico;
4. el Core no contiene otro `resources/releases/property-hub-core-*.zip` anidado;
5. todos los `health_files` existen dentro del Core;
6. migraciones históricas y su ledger permanecen inmutables.

## Actualización

Desde 0.14.29 puede subirse 0.14.30 directamente. Tras reemplazar los archivos del Central, **Sincronizar Core oficial** debe publicar `property-hub-core-0.14.30-stable` para Nodes/Edges compatibles.
