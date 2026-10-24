# Actualización 0.9.2 → 0.9.3

## Instalación que todavía no terminó

1. Sustituir los archivos de la aplicación por el paquete 0.9.3.
2. Si existe `.env` generado por un intento incompleto, no asumir que la instalación terminó: comprobar `storage/installed.lock`.
3. Si **no existe** `storage/installed.lock`, volver a abrir `/install/` y repetir el asistente.
4. No borrar manualmente las tablas creadas por las migraciones; `MigrationRunner` las reconoce al reintentar.

## Instalación ya operativa

1. Respaldar base y carpeta.
2. Conservar `.env` y `storage/`.
3. Sustituir los demás archivos.
4. Ejecutar `tools\migrate.bat`.

No existen migraciones SQL nuevas en 0.9.3.
