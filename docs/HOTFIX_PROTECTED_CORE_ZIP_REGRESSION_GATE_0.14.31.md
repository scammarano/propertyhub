# Protected Core ZIP Regression Gate — 0.14.31

## Problema confirmado

El Core distribuible generado para 0.14.30 volvió a contener dos entradas de directorio vacías: `storage/` y `storage/repository/`. Aunque no llevaban datos, `ReleasePublisher` las bloquea correctamente porque `storage/` pertenece al estado persistente/runtime de cada instalación y nunca debe viajar dentro de un Core de actualización.

La versión 0.14.26 ya cumplía esta regla: su Core distribuible contenía **cero** entradas bajo `storage/`. Por tanto, 0.14.30 fue una regresión del proceso de empaquetado, no una migración ni un problema de la base instalada.

## Corrección

0.14.31:

- regenera `property-hub-core-0.14.31.zip` sin ningún archivo **ni entrada de directorio** bajo `storage/`;
- mantiene `.env.example`, `storage/.htaccess`, el Core embebido y su mirror únicamente en el paquete completo del Central, no dentro del Core distribuible;
- usa el mismo validador de `ReleasePublisher` para comprobar el Core embebido antes de considerarlo válido;
- exige `component_code=property-hub-core` y versión exactamente igual a la instalada en el Central;
- hace que **Versiones y distribución** distinga `incluido y válido` de `incluido pero inválido`;
- conserva el bloqueo de rutas protegidas (`.env`, `storage/**`) al publicar.

## Release gate obligatorio

Para declarar PASS, el Core distribuible debe cumplir simultáneamente:

1. ZIP íntegro.
2. `update-package.json` válido.
3. versión del Core = versión del Central.
4. `component_code=property-hub-core`.
5. cero entradas bajo `storage/`.
6. cero `.env` / `.env.*` protegidos.
7. cero `resources/releases` anidados.
8. todos los `health_files` presentes.
9. ledger de migraciones válido e histórico inmutable.
10. mirror del Core idéntico byte a byte al Core de `resources/releases/`.

No se agrega ninguna migración SQL y no se modifica ninguna migración histórica.
