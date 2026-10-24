# Property Hub 0.5.1 — Hotfix de redirecciones y esquema

Corrige el bucle `ERR_TOO_MANY_REDIRECTS` causado por redirigir una excepción del dashboard nuevamente hacia el mismo dashboard.

Incluye una pantalla de reparación en `public/index.php?route=system-repair`, ejecución web de migraciones pendientes, registro de incidentes en `storage/logs/` y consultas de métricas compatibles con MariaDB.

## Actualización

1. Reemplace los archivos de la aplicación.
2. Conserve `.env` y `storage/`.
3. Abra `public/index.php?route=system-repair`.
4. Pulse **Ejecutar migraciones pendientes** si aparece.
