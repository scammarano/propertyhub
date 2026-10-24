# Property Hub 0.14.60 · Migration Runner Repair Bridge

## Incidente corregido

La migración `0045_property_deployment_file_pre_node_edges.sql` usa SQL dinámico y, en Node/Edge donde las tablas Central no existen, puede ejecutar un `SELECT 1` como no-op. `MigrationRunner` utilizaba `PDO::exec()`, que no entrega un `PDOStatement` con el cual cerrar el result set. En ciertos PDO MySQL/MariaDB el siguiente statement falla con error 2014: `Cannot execute queries while other unbuffered queries are active`.

## Corrección

- Cada statement se ejecuta con `PDO::query()` y se libera con `closeCursor()`.
- Las conexiones MySQL activan `PDO::MYSQL_ATTR_USE_BUFFERED_QUERY`.
- Se cierran explícitamente los cursores de consultas internas del ledger.
- El rollback de base de datos reintenta con una conexión nueva si la conexión original quedó contaminada por error 2014/unbuffered.
- Las migraciones publicadas no se modifican; `0045` conserva su checksum.

## Recuperación de Nodes 0.14.56

El Core oficial 0.14.57 es inmutable y contiene el ejecutor anterior. Por eso 0.14.60 incluye un release técnico `0.14.56.1` basado en el Core oficial 0.14.56. Este bridge no ejecuta migraciones, repara el runtime y restaura una base conocida si un intento fallido de 0.14.57 dejó archivos parcialmente aplicados.

La ruta pasa a ser:

`0.14.56 -> 0.14.56.1 -> 0.14.57 -> 0.14.58 -> 0.14.59 -> 0.14.60`

El manifiesto expone un solo Core futuro por vez y prioriza releases marcados `repair_bridge`.
