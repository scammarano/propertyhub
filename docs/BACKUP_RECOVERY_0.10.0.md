# Property Hub 0.10.0 — Respaldo y recuperación

## Objetivo

Property Hub no asume que el servidor local sea permanente. Un Property Node puede perderse y reconstruirse sobre otro Windows/XAMPP sin volver a diseñar torres, pisos, unidades, personas, áreas, inventario, mapeos, usuarios ni conectores.

El respaldo operativo se almacena como `*.phbackup`. El archivo contiene:

- dump completo de la base MariaDB de Property Hub;
- configuración `.env` exportada dentro del contenedor cifrado;
- `storage/` persistente, incluyendo claves y documentos, excepto `backups`, `logs`, `tmp`, `cache`, `sessions` e `installed.lock`;
- manifiesto de identidad, versión, base y sistemas externos declarados;
- checksums de los componentes del respaldo.

El código PHP de la aplicación no se incluye: el procedimiento de desastre parte de una instalación básica de la versión actual o posterior y luego restaura el `phbackup`.

## Cifrado y clave de recuperación

Los respaldos se cifran por bloques con AES-256-GCM. La clave se deriva con PBKDF2-SHA256. Property Hub genera una `BACKUP_RECOVERY_KEY` de alta entropía.

La clave debe guardarse fuera del servidor local. El Hub Central puede almacenar una copia cifrada del archivo, pero nunca recibe la clave.

## Dos modos de restauración

### Misma instalación

Pensado para pérdida/reemplazo del servidor.

Restaura la identidad original, UUID, código, usuarios, historial y credenciales de vínculo. Conserva del servidor destino:

- `APP_URL`;
- host, puerto, base, usuario y contraseña MariaDB;
- configuración local de diagnóstico;
- URL pública del repositorio central cuando corresponda;
- redirect URIs sociales del servidor nuevo.

En el primer heartbeat, el Central actualiza la URL pública reportada por el nodo recuperado.

### Copia segura / laboratorio

Restaura los datos pero conserva la identidad de la instalación básica destino. Además:

- deshabilita `bh_connector_instances`;
- deshabilita políticas de apertura remota;
- deshabilita cámaras;
- deshabilita fuentes de actualización;
- deshabilita notificaciones externas y login social;
- deshabilita la API de repositorio si se clonó un Central;
- deshabilita backups automáticos/copias externas hasta revisión.

## Punto de seguridad antes de restaurar

Toda restauración crea primero un respaldo local `PRE_RESTORE`. Si la restauración falla después de modificar el schema, ese archivo es el punto de retorno de la instalación que existía justo antes de comenzar.

## Destinos

- local: `storage/backups` por defecto;
- ruta absoluta local;
- carpeta UNC/NAS;
- copia cifrada al Property Hub Central para nodos registrados.

S3/Google Drive no se implementan todavía en 0.10.0; se agregarán como destinos sobre el mismo contrato de archivo cifrado.

## Retención

La política mantiene ventanas diarias, semanales y mensuales. Los puntos `PRE_UPDATE` y `PRE_RESTORE` no se eliminan por la poda ordinaria.

## Programación Windows

Ejecutar una sola vez como administrador:

```bat
tools\install-backup-task.bat
```

La tarea consulta cada 15 minutos si corresponde respaldar según la política de la base. El runner evita generar más de un respaldo programado el mismo día.

## Central

Cuando un nodo tiene `Enviar copia cifrada al Property Hub Central`, el archivo se transmite autenticado con la identidad técnica del nodo. El Central valida SHA-256, almacena el archivo en `storage/backups/offsite/<CODIGO>/` y registra el recibo. El administrador central puede descargar la copia cifrada, pero no descifrarla sin la clave de recuperación del inmueble.
