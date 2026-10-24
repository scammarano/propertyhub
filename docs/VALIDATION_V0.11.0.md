# Validación Property Hub 0.11.0

## A. Central

- [ ] Subir 0.11.0 al Central conservando `.env` y `storage`.
- [ ] Ejecutar **Reparar / migraciones**.
- [ ] Confirmar migraciones `0019` y `0020`.
- [ ] Confirmar que el Core oficial 0.11.0 queda sincronizado.
- [ ] Confirmar que el portafolio y Backup Vault distinguen `NODE` y `EDGE`.

## B. Property Node público

- [ ] Actualizar el Node a 0.11.0 desde Central.
- [ ] Ejecutar migraciones.
- [ ] Confirmar que aparece **Property Edge Agents**.
- [ ] Generar token `PHEDGE` limitado al código `PREMIER-EDGE-01`.
- [ ] Confirmar política de backup: copia a Central activa.

## C. Edge Agent local

- [ ] Eliminar solo la instalación/BD de Property Hub local anterior; no reinstalar XAMPP.
- [ ] Instalar el mismo ZIP 0.11.0 y elegir **Property Edge Agent**.
- [ ] Código `PREMIER-EDGE-01`; padre `PREMIER-LM`.
- [ ] Registrar Agent en Central con token BHR para ese código.
- [ ] Emparejar Agent con el Node usando token PHEDGE.
- [ ] Ejecutar `tools\install-scheduler-task.bat` como Administrador.
- [ ] Confirmar tarea cada 5 minutos y `storage/backups/scheduler-installed.json`.

## D. Integraciones locales

### ZKBio
- [ ] Configurar URL local (ej. `http://127.0.0.1:8088`) y `access_token`.
- [ ] Probar API.
- [ ] Confirmar conteo/muestra de dispositivos.
- [ ] Confirmar conteo/muestra de puertas.

### ONVIF / RTSP
- [ ] Activar extensión PHP `sockets` si WS-Discovery la requiere.
- [ ] Ejecutar descubrimiento ONVIF.
- [ ] Probar RTSP por IP/host y puerto 554.

## E. Respaldo encadenado

### Edge
- [ ] Generar backup manual del Edge.
- [ ] Confirmar copia local.
- [ ] En Property Node → **Property Edge Agents**, confirmar copia del Agent.
- [ ] En Central → **Backup Vault**, confirmar la misma copia identificada como `EDGE` y con padre `PREMIER-LM`.

### Node
- [ ] Generar backup manual del Property Node.
- [ ] Confirmar copia local.
- [ ] Confirmar copia `NODE` en Central.

### Solicitud desde Central
- [ ] Solicitar respaldo del Edge desde Central.
- [ ] Forzar/esperar scheduler.
- [ ] Confirmar solicitud `completed`.
- [ ] Confirmar copia Edge local + Node + Central.

## F. Restauración

- [ ] Subir un `.phbackup` de Node a una instalación Node del mismo rol.
- [ ] Validar PHRK una sola vez y completar restauración sin volver a escribirla en la misma sesión.
- [ ] Verificar integridad/checksums y migraciones.
- [ ] Repetir con backup Edge sobre una instalación Edge de prueba.

## Fuera de alcance de 0.11.0

- Apertura remota de puerta de extremo a extremo desde móvil.
- Canal persistente de comandos en tiempo real.
- Retransmisión WebRTC/TURN/SFU de video hacia Internet.

0.11.0 prepara la separación de responsabilidades y las pruebas locales; no debe presentar esas funciones futuras como terminadas.
