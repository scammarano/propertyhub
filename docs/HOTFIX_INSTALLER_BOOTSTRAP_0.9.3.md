# Property Hub 0.9.3 — Hotfix de arranque del instalador

## Problema

Al ejecutar una instalación nueva, `InstallerService` utiliza `RepositoryTransport::normalizeBaseUrl()` antes de que el instalador haya cargado la clase. El `app/bootstrap.php` sí la carga, pero ese bootstrap no se utiliza durante la fase previa a la creación de `.env`. Por eso el error aparecía específicamente dentro de `/install`.

## Corrección

`install/index.php` carga explícitamente:

- `app/Install/InstallerLogger.php`
- `app/Deployment/RepositoryTransport.php`
- `app/Deployment/EnvironmentEditor.php`
- `app/Install/InstallerService.php`

`EnvironmentEditor` incorpora `setFile()` para poder actualizar el `.env` recién creado sin depender de `base_path()` ni del bootstrap normal de la aplicación.

## Seguridad de una instalación fallida

Las inserciones principales del nodo se ejecutan dentro de una transacción y se revierten si ocurre una excepción antes del `commit`. Las migraciones ya aplicadas permanecen, pero son administradas por `MigrationRunner` y pueden reutilizarse al reintentar. El `installed.lock` se escribe únicamente al final de una instalación exitosa.

## Diagnóstico

Los errores del instalador quedan registrados en:

`storage/logs/property-hub-installer-AAAA-MM-DD.log`

La pantalla muestra el `incident_id` correspondiente. No se registran los valores POST ni contraseñas/tokens.
