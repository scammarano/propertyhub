# Property Hub 0.10.3 — Ciclo completo Central → Premier → Backup → Recovery

## Objetivo

Probar el ciclo real sin reconstruir manualmente Premier:

1. Actualizar **solo Property Hub Central** subiendo 0.10.3 por FTP.
2. Central sincroniza/publica automáticamente el Core 0.10.3 para Property Nodes.
3. Premier, que ya tiene Update Bootstrap 1.0.0, busca y encuentra 0.10.3.
4. Premier instala 0.10.3 desde Central.
5. Premier genera un `.phbackup` cifrado.
6. En un servidor local nuevo se instala un Property Node 0.10.3 básico.
7. Ese Node restaura el `.phbackup` de Premier.

## 1. Property Hub Central

Suba por FTP el contenido del ZIP oficial 0.10.3 sobre el Central existente. Conserve `.env` y toda la carpeta `storage/` existente.

No use **Publicar paquete adicional** para publicar Property Hub Core.

Al entrar en **Versiones y paquetes**, el bloque **Property Hub Core oficial** debe mostrar la versión instalada. Cuando cualquier nodo solicita el manifiesto, Central ejecuta el reconciliador del repositorio y comprueba automáticamente:

- `VERSION.json` / versión runtime;
- `resources/releases/property-hub-core-0.10.3.zip`;
- SHA-256 del ZIP fuente;
- registro publicado en `bh_repo_releases`;
- copia servida desde `storage/repository/packages`;
- manifiesto y firma.

Si alguno no coincide, lo repara/publica. El botón **Sincronizar** realiza la misma reconciliación manualmente como contingencia.

No hay migración 0017. La ausencia de migraciones pendientes no impide la publicación del Core.

## 2. Premier

No copie el ZIP completo 0.10.3 a Premier.

Premier ya requiere una sola pieza heredada: Update Bootstrap 1.0.0. Después:

`Sistema local → Actualizaciones → Buscar actualizaciones`

La pantalla debe indicar la versión del nodo, versión del Central, canal, cantidad publicada, compatible/excluida y la última versión Core disponible.

Si 0.10.3 es aplicable, pulse **Actualizar ahora**. El Node:

1. descarga el ZIP desde Central con credenciales de la instalación;
2. valida el manifiesto firmado y SHA-256;
3. crea snapshot interno de base y archivos;
4. crea PRE_UPDATE cifrado cuando la clave de recuperación ya existe;
5. copia archivos;
6. ejecuta migraciones pendientes (0016 si todavía falta);
7. actualiza metadatos de versión;
8. hace health checks;
9. revierte archivos y base automáticamente si falla.

## 3. Crear backup de Premier

Después de confirmar 0.10.3:

`Sistema local → Respaldo y recuperación → Crear respaldo ahora`

Guarde dos cosas fuera del servidor:

- archivo `PREMIER-...phbackup`;
- clave de recuperación `PHRK-...`.

La clave no va dentro del backup.

## 4. Servidor nuevo/local

Use el ZIP completo 0.10.3 y cree un **Property Node básico**. Puede usar una base MariaDB vacía nueva y una cuenta administrativa temporal. No recree la estructura de Premier.

Después:

`Sistema local → Respaldo y recuperación → Restaurar desde archivo`

Cargue el `.phbackup`, escriba la clave de recuperación, valide el contenido y seleccione **Recuperar la misma instalación**.

Antes de tocar la BD, el Node nuevo crea un PRE_RESTORE local. La restauración valida los checksums del contenido antes de importar `database.sql`.

## 5. Qué se restaura y qué se conserva del servidor nuevo

Se restaura la instalación de Premier: estructura, unidades, personas, relaciones, áreas, inventario, usuarios, conectores, mapeos externos, configuración de Property App e historial incluido en la BD/storage persistente.

Se preserva del servidor nuevo la configuración local que no debe copiarse a ciegas, entre ella:

- `APP_URL`;
- host/puerto/base/usuario/password de MariaDB;
- configuración local de diagnóstico/log;
- URLs OAuth dependientes del host.

En modo **copia segura/laboratorio**, además se conserva la identidad del Node nuevo y se desactivan conectores, apertura remota, cámaras operativas, notificaciones externas, repositorio Central y backups remotos.

## 6. Cambio físico definitivo

Para recuperar **la misma instalación**, no deje simultáneamente dos Nodes activos con el UUID de Premier. Antes del primer heartbeat del servidor recuperado, apague/aisle el Property Hub del servidor anterior.

## Criterio de éxito de la prueba

La prueba queda aprobada cuando:

- Central 0.10.3 muestra Core oficial sincronizado;
- Premier 0.9.1 detecta 0.10.3 sin copiar el Core manualmente;
- actualización termina instalada;
- Premier produce un `.phbackup` válido;
- un Node local limpio acepta e inspecciona ese backup;
- la restauración recupera los datos de Premier manteniendo la conexión local a la nueva BD/URL.
