# Property Hub 0.14.33 · Compact Cards / Edge Worker Self-Heal

## Objetivo

Esta versión corrige dos puntos observados durante las pruebas reales de 0.14.32:

1. La tipografía refinada de tarjetas debía conservarse **más fina y ligeramente más pequeña**, no más grande.
2. En Property Edge podía verse `Runtime sano` junto a `Worker no detectado` aun existiendo una tarea Windows. La mera existencia de la tarea no demuestra que su acción corresponda al runner actual ni que el worker siga vivo.

## Tarjetas

Se conserva el peso visual ligero, pero se reducen las escalas globales de etiquetas, títulos y valores de tarjetas/KPI para ganar densidad vertical y horizontal.

## Worker Edge

- El estado de autoarranque valida el XML real de `PropertyHub Edge Command Worker`: debe existir, estar habilitado, usar `BootTrigger` y apuntar a `tools\\edge-command-worker-runner.cmd`.
- En Windows, `EdgeCommandWorkerService::startDetached()` intenta primero `schtasks /Run`, que ejecuta el supervisor bajo el mismo contexto configurado por la tarea, y sólo usa lanzamiento directo como fallback.
- `edge-command-worker-runner.cmd` permanece activo como supervisor: si `edge-command-worker.php` termina inesperadamente, registra el código de salida, espera 15 segundos y vuelve a levantarlo.
- El bootstrap queda registrado en `storage/logs/edge-command-worker-bootstrap.log` para poder diagnosticar un reinicio sin depender del navegador.
- El lock del worker PHP sigue evitando procesos duplicados.

## Base de datos

No hay migraciones nuevas y ninguna migración histórica fue modificada.
