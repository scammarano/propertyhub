# Property Hub 0.14.34 · Bundled Core Dotenv Artifact Gate

## Corrección

0.14.33 podía transportar `.env.example` dentro del Core distribuible. `ReleasePublisher` lo rechaza correctamente porque `.env` y cualquier variante `.env.*` son rutas protegidas y nunca deben formar parte de una actualización de Core.

0.14.34 reconstruye el Core oficial excluyendo, antes de comprimir:

- `.env` y cualquier `.env.*`, incluido `.env.example`;
- todo `storage/**`, incluso directorios vacíos;
- todo `resources/releases/**`, para evitar Core anidado.

## Gate del artefacto final

La validación se hace sobre el ZIP final que se embebe y publica, no sólo sobre el árbol fuente. El release se considera válido únicamente si:

- `update-package.json` declara exactamente la misma versión que el Central;
- `component_code=property-hub-core`;
- no existe ninguna entrada `.env` o `.env.*`;
- no existe ninguna entrada `storage/**`;
- no existe ninguna entrada `resources/releases/**`;
- todos los `health_files` están presentes;
- el ledger de migraciones conserva los checksums históricos;
- el Core en `resources/releases` y el mirror en `storage/repository/packages` son byte-idénticos.

No agrega migraciones ni modifica migraciones históricas.
