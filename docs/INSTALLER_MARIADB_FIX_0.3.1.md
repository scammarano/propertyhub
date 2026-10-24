# Corrección del instalador para MariaDB — 0.3.1

## Error corregido

```text
SQLSTATE[42000]: Syntax error or access violation: 1064 ... near '?' at line 1
```

El error ocurría antes de ejecutar la primera migración. `MigrationRunner::tableExists()` utilizaba `SHOW TABLES LIKE ?` con preparaciones nativas de PDO. MariaDB no admite de forma fiable ese marcador en la sentencia administrativa `SHOW TABLES` y recibía el signo `?` como parte literal del SQL.

## Solución

La verificación ahora consulta `information_schema.tables`:

```sql
SELECT 1
FROM information_schema.tables
WHERE table_schema = DATABASE()
  AND table_name = ?
LIMIT 1
```

## Cómo continuar después del error

1. Sustituya la carpeta 0.3.0 por la entrega 0.3.1, o copie sus archivos conservando la carpeta de instalación.
2. No es necesario borrar la base de datos: el error sucedía antes de crear las tablas. La base vacía puede reutilizarse.
3. Vuelva a abrir `/install/` y ejecute nuevamente el asistente.
4. Si usted creó manualmente tablas antes de este intento, use una base nueva o revise esas tablas antes de reinstalar.
5. Confirme que no exista `storage/installed.lock`; en este fallo normalmente no llega a crearse.

Los archivos SQL de las migraciones no fueron alterados, para conservar sus checksums.
