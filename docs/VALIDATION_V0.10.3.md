# Validación técnica — Property Hub 0.10.3

## Resultado de construcción

- **145 archivos PHP**: `php -l` sin errores.
- **34 JSON/webmanifest**: parseo correcto.
- **15 JavaScript**: `node --check` sin errores.
- **135 enlaces Markdown relativos** comprobados, sin destinos faltantes.
- **16 migraciones (0001–0016)**: byte a byte idénticas a 0.10.2. No existe migración 0017.
- ZIP completo Central: prueba de integridad correcta.
- ZIP Core distribuible: prueba de integridad correcta.
- El ZIP Core no incluye `.env*`, claves, uploads, backups, logs, repository storage, rollback storage ni `resources/releases/` anidados.
- El paquete completo Central sí contiene `resources/releases/property-hub-core-0.10.3.zip` y sus bytes coinciden exactamente con el release distribuible validado.
- `update-package.json`: Core 0.10.3, Core mínimo 0.9.1, Update Engine mínimo 1.0.0.
- Cálculo del instalador probado para rutas `/property-hub/install/`, `/install/` y rutas anidadas; deriva correctamente el hermano `/public`.
- Cifrado/descifrado `PHBACKUP1` probado sobre múltiples bloques; el SHA-256 recuperado coincide con el original y una clave errónea es rechazada.

## Controles funcionales incorporados

- `RepositorySynchronizer` desacopla la publicación del Core de las migraciones.
- `manifest.php` intenta sincronizar/reparar el Core oficial en cada consulta autenticada de un Node.
- Abrir **Versiones y paquetes** en Central también ejecuta la reconciliación automática; **Sincronizar** queda como contingencia.
- El formulario manual bloquea `core/property-hub-core` y queda reservado a componentes adicionales.
- Diagnóstico de Node muestra versión Central, canal, publicadas, compatibles, excluidas, último Core y el resultado de la sincronización oficial.
- Instalador crea carpetas runtime antes de generar claves/repositorio/backups.
- `BACKUP_RECOVERY_KEY` no se incluye dentro del contenido cifrado que ella misma protege.
- `inspect()` y `restore()` validan `checksums.json` antes de restaurar `database.sql`.
- La versión post-restore se toma del runtime instalado y no de un número histórico fijo.

## Limitación del entorno de construcción

El PHP disponible en este entorno no carga `pdo_mysql`, `zip` ni `curl`. Por ello **no se puede certificar aquí** una ejecución real contra MariaDB/XAMPP, `ZipArchive` o la descarga HTTP autenticada Central → Premier. Esa es precisamente la prueba que debe realizarse en los tres entornos reales: Central, Premier y el servidor local nuevo.
