# Validación técnica 0.10.6

Validaciones de paquete:

- lint de PHP;
- parseo de JSON y webmanifest;
- sintaxis JavaScript;
- integridad ZIP;
- `0018_central_backup_vault.sql` registrado solo para Central;
- release Node sin 0017/0018 en su plan de migraciones;
- API de heartbeat entrega tareas de respaldo;
- carga remota vincula archivo recibido con la solicitud;
- token de recuperación es de un solo uso y no contiene PHRK;
- backup completo del Central incluye `storage/backups/offsite` y `storage/repository`, pero excluye sus propios backups y temporales;
- restauración del Central reconcilia rutas del Vault.

La prueba final de MariaDB, cURL, ZipArchive, tarea programada de Windows y transferencia de archivos debe ejecutarse en los servidores reales.
