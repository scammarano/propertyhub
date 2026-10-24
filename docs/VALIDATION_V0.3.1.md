# Validación 0.3.1 — Corrección MariaDB

## Resultado

- 91 archivos PHP validados con `php -l`.
- 22 archivos JSON decodificados correctamente.
- Eliminado el patrón incompatible `SHOW TABLES LIKE ?`.
- Confirmada la nueva consulta parametrizada sobre `information_schema.tables`.
- Conservados sin cambios los tres archivos SQL de migración y sus checksums.
- Actualizados los metadatos operativos a la versión 0.3.1.

## Checksums de migraciones

```text
a68ab2c716668690fb3809f1d1aec8e3b63f7200c1cbadf65ed1ea0147de3aee  /mnt/data/PROPERTY_HUB_V0.3.1_MARIADB_INSTALLER_FIX/database/migrations/0001_functional_foundation.sql
e18b4fb94c485e1a973d874155205372da1c4287844707e5b6ba049de7c156e1  /mnt/data/PROPERTY_HUB_V0.3.1_MARIADB_INSTALLER_FIX/database/migrations/0002_deployment_foundation.sql
35467e3be6d46f21ecd9fd7a77b0ab99fca2c6eaed1354961324aa27bc1711ab  /mnt/data/PROPERTY_HUB_V0.3.1_MARIADB_INSTALLER_FIX/database/migrations/0003_central_repository.sql
```

## Alcance de la prueba

La validación disponible en este entorno es estática porque no hay servidor MariaDB ni extensión `pdo_mysql` activa. La corrección elimina la sentencia que producía exactamente el error 1064 reportado. La prueba final debe ejecutarse en XAMPP con MariaDB.
