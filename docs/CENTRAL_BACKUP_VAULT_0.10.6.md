# Property Hub 0.10.6 — Central Backup Vault y recuperación remota

## Objetivo

Property Hub Central deja de limitarse a versiones, telemetría y catálogo técnico: también puede custodiar copias completas cifradas de los Property Nodes y coordinar su generación.

## Solicitar respaldo desde Central

En **Sistema central → Backup Vault de instalaciones** se puede:

- solicitar un respaldo completo de un Node específico;
- solicitar respaldo de todos los Nodes activos;
- ver solicitudes en cola, entregadas, completadas o fallidas;
- consultar la última copia remota, cantidad de archivos y almacenamiento por instalación.

La solicitud no intenta conectarse desde Internet hacia el edificio. Se entrega al Node en su siguiente heartbeat. El runner local de mantenimiento revisa el Central periódicamente y procesa la solicitud. Esto conserva el modelo de conectividad saliente del Node.

El Node genera el `.phbackup` con su propia clave PHRK, verifica su integridad y envía solamente el archivo cifrado al Central. El Vault puede programarse diario o semanal; la política se evalúa al recibir heartbeats y conserva un número configurable de copias por Node.

## Recuperar un Node desde Central

Desde una copia del Vault, el Central puede emitir un token temporal `PHREC-...` de un solo uso.

En un servidor nuevo:

1. instalar Property Hub como Property Node básico;
2. abrir **Respaldo y recuperación**;
3. usar **Recuperar desde Property Hub Central**;
4. indicar URL del Central, token PHREC y clave PHRK de la instalación;
5. el Node descarga el `.phbackup` directamente, verifica SHA-256 y manifiesto;
6. revisar la identidad y ejecutar **Recuperar la misma instalación**.

El Central no necesita exponer el archivo mediante FTP ni subirlo manualmente al nuevo servidor.

## Privacidad y clave de recuperación

El Vault es de conocimiento cero respecto al contenido del Node:

- Central recibe el `.phbackup` ya cifrado;
- la PHRK no se envía al Central;
- el token PHREC permite descargar una copia concreta, pero no descifrarla;
- para recuperar sigue siendo necesaria la PHRK conservada fuera del servidor original.

Si también se pierde la PHRK, el Central no puede descifrar el respaldo por diseño.

## Respaldo completo del propio Central

Desde 0.10.6 el `.phbackup` del Central incluye:

- base de datos del Central;
- identidad y configuración;
- claves RSA del repositorio;
- paquetes/releases publicados en `storage/repository`;
- copias remotas cifradas en `storage/backups/offsite`.

No incluye sus propios `.phbackup`, temporales, logs, cache o staging. Al restaurar Central en otro servidor, las rutas físicas de las copias del Vault se recalculan.

## Operación automática

`tools/install-backup-task.bat` continúa instalando el runner local. El runner ahora hace dos trabajos:

1. consulta el Central para recoger tareas de mantenimiento pendientes;
2. ejecuta el respaldo automático local si corresponde por horario.

Por ello una solicitud del Central normalmente es recogida dentro del siguiente ciclo del runner. También puede procesarse al enviar un heartbeat manual.
