# Property Hub 0.11.0 — Arquitectura unificada Central / Node / Edge

## Principio

Existe **un solo producto, un solo ZIP, un solo instalador y una sola versión**. Durante una instalación nueva se selecciona uno de tres roles.

### Property Hub Central (`central_repository`)

Administra la flota de instalaciones, registro/reconexión, versiones, manifiestos firmados, salud, órdenes remotas de mantenimiento y Backup Vault. No es la aplicación diaria del residente.

### Property Node (`building_node`)

Es la aplicación pública/completa de una propiedad. Es la fuente maestra de estructura, unidades, personas, residentes, visitantes, permisos, cámaras visibles, puntos de acceso y Resident App. Puede emparejar uno o más Edge Agents.

### Property Edge Agent (`edge_agent`)

Es el gateway local de una propiedad. Vive dentro de la LAN y conserva credenciales técnicas locales. Su consola es técnica, no una segunda administración del condominio. En 0.11.0 incluye:

- vínculo con Property Hub Central;
- emparejamiento con un Property Node mediante token `PHEDGE`;
- ZKBio CVSecurity: URL/token cifrado, prueba real de API, listado de dispositivos y puertas;
- ONVIF: WS-Discovery local;
- RTSP: prueba TCP/OPTIONS;
- heartbeat, actualización, diagnóstico, colas y backup;
- scheduler unificado para tareas periódicas.

## Identidades

Un Node y sus Agents son instalaciones diferentes. Ejemplo:

- `PREMIER-LM` → Property Node
- `PREMIER-EDGE-01` → Edge Agent, padre `PREMIER-LM`

Nunca deben compartir UUID ni credenciales de instalación.

## Respaldo

Cadena por defecto:

1. **Edge Agent** genera `.phbackup` cifrado localmente.
2. Edge envía la misma copia cifrada a su **Property Node**.
3. Edge envía la misma copia cifrada a **Property Hub Central**.
4. **Property Node** genera su propio `.phbackup` y lo envía a Central.
5. **Central** genera su propio backup; este incluye `storage/backups/offsite`, por lo que protege también el Vault cifrado que custodia.

La PHRK no viaja dentro del `.phbackup` ni se almacena en texto legible en Central/Node receptor.

## Scheduler

En Windows/XAMPP se instala con:

`tools\install-scheduler-task.bat`

Crea una única tarea cada 5 minutos para:

- heartbeat al Central;
- tareas de mantenimiento/backup solicitadas por Central;
- heartbeat Edge → Property Node;
- política de backup local.

El scheduler **no es el canal de tiempo real** para apertura remota ni video. Esa capa persistente/outbound queda para una fase posterior.

## Datos y secretos

- Property Node: datos maestros de la propiedad.
- Edge Agent: caché/estado técnico y secretos locales cifrados.
- Central: metadatos, salud, inventario técnico y copias cifradas; no contraseñas de cámaras/ZKBio en texto legible.
