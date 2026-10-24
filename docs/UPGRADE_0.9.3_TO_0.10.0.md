# Actualización 0.9.3 → 0.10.0

1. Copiar el paquete 0.10.0 sobre la instalación conservando obligatoriamente `.env` y `storage/`.
2. Ejecutar `tools\migrate.bat` o `Sistema → Diagnóstico → Reparación y migraciones`.
   - Si la instalación viene de 0.9.x, 0.10.0 genera automáticamente una **clave de recuperación** y un respaldo local **PRE_UPDATE antes de ejecutar 0016**.
   - Guarde inmediatamente la clave fuera del servidor. El CLI la muestra en consola y la reparación web la muestra en la pantalla de resultado.
   - Si no puede crearse el PRE_UPDATE, las migraciones se detienen.
3. Verificar `0016_backup_recovery.sql = applied` y que el PRE_UPDATE figure en el historial.
4. Abrir `Sistema → Respaldo y recuperación`.
5. Configurar retención y destinos.
6. Ejecutar un respaldo manual y descargarlo.
7. Si es un Property Node, opcionalmente habilitar copia cifrada al Central. El Central debe actualizarse primero a 0.10.0.
8. Ejecutar como administrador `tools\install-backup-task.bat` si se desean respaldos automáticos.

La actualización no borra estructura, personas, áreas, inventario ni vínculos existentes.

Desde la primera actualización 0.9.x → 0.10.0 y en las versiones posteriores, tanto el reparador web como `tools\migrate.php` crean un respaldo local `PRE_UPDATE` antes de aplicar migraciones pendientes. Si todavía no existe la clave de recuperación, se genera antes de ese respaldo y debe guardarse fuera del servidor.
