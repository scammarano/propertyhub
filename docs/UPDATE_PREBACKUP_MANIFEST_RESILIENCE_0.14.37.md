# Property Hub 0.14.37 — Update PRE_UPDATE / Manifest Resilience Fix

Esta versión corrige dos fallos observados durante la actualización 0.14.35 → 0.14.36 en Property Node y Property Edge Agent.

## 1. Aplicar actualización sin depender de la sesión

`apply-update` ya no falla si `last_update_manifest` desapareció de la sesión entre **Buscar actualizaciones** y **Aplicar**. Si falta, vuelve a consultar el manifiesto firmado al Central y revalida `release_code` antes de instalar.

## 2. PRE_UPDATE robusto

- `storage/run/**` se trata como estado efímero y no entra en respaldos completos/PRE_UPDATE.
- Se comprueba `ZipArchive::addFile()`, `ZipArchive::close()` y que el ZIP temporal exista y tenga contenido antes de cifrarlo.
- Los respaldos antiguos que contengan `storage/run/**` no restauran ese estado runtime.

## 3. Rollback sólo después de empezar a modificar archivos

El snapshot interno se sigue creando antes del PRE_UPDATE. Sin embargo, si el PRE_UPDATE falla antes de `applyPayload()`, el motor declara **instalación no modificada** y no ejecuta un rollback innecesario. Esto evita un segundo fallo intentando reemplazar archivos vivos del Edge cuando aún no se había aplicado ningún cambio.

## Migraciones

No agrega migraciones y no modifica ninguna migración histórica.
