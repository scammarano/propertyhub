# Actualización a Property Hub 0.10.8

## Central

Para el Central oficial, subir por FTP el contenido completo de 0.10.8 sobre la instalación existente, conservando `.env` y `storage/`. En la siguiente consulta de manifiesto, `RepositorySynchronizer` detecta `config('app.version') = 0.10.8` y publica/reconcilia el Core embebido `resources/releases/property-hub-core-0.10.8.zip`.

## Property Node

No copiar archivos manualmente al Node. Desde **Sistema local → Actualizaciones**, buscar versiones y aplicar 0.10.8. El nodo debe conservar `.env`, claves, uploads, respaldos, logs, repositorio local y rollback.

## Migraciones

0.10.8 no agrega migraciones SQL. El paquete conserva las migraciones de Node hasta `0016_backup_recovery.sql` para permitir actualización directa desde versiones soportadas. Las migraciones `0017` y `0018` son exclusivas del Central y no forman parte del ZIP distribuible para Nodes.
