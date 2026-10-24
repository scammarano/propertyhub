# Actualización Property Hub 0.7.0 → 0.8.0

1. Respaldar la base de datos y la carpeta de la instalación.
2. Sustituir los archivos de aplicación.
3. Conservar `.env` y `storage/`.
4. Ejecutar `tools\migrate.bat` o abrir `Sistema local → Diagnóstico → Reparación y migraciones`.
5. Confirmar la ejecución de `0014_topology_designer.sql`.
6. Abrir `Gestión de la propiedad → Estructura → Asistente de diseño`.

La migración no elimina la estructura existente. Amplía los tipos de elementos y crea las tablas de ascensores, paradas y puertas estructurales.

Las ejecuciones antiguas del asistente permanecen en el historial. Para utilizar las nuevas nomenclaturas y ubicaciones debe iniciarse una nueva generación o editar manualmente la estructura existente.
