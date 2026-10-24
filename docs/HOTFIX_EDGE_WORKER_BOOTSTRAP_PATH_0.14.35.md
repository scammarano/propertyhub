# Property Hub 0.14.35 · Edge Worker Bootstrap Path Fix

## Síntoma

El supervisor Windows sí arrancaba y reintentaba el worker cada 15 segundos, pero `tools/edge-command-worker.php` terminaba con código 255 porque intentaba cargar `app/config.php`, archivo inexistente en la arquitectura actual.

## Causa

El entrypoint CLI conservó una referencia histórica a `app/config.php`. La aplicación actual inicializa configuración mediante `app/bootstrap.php` → `app/Support/helpers.php` → `config/*.php` y `.env`.

## Corrección

- Se elimina cualquier `require app/config.php` del worker.
- El worker valida que exista `app/bootstrap.php` y lo carga con `require_once`.
- Se conserva `edge-command-worker-runner.cmd` como supervisor persistente, con reintento a los 15 s.
- Se conserva el log `storage/logs/edge-command-worker-bootstrap.log`.
- No hay migraciones nuevas ni modificaciones a migraciones históricas.

## Gate adicional

La validación del release comprueba expresamente que `tools/edge-command-worker.php` no referencie `app/config.php`, que su bootstrap literal exista en el artefacto Core final y que el archivo pase PHP lint.
