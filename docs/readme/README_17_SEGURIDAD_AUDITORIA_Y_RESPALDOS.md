# Seguridad, auditoría y respaldos

## Principios

- Mínimo privilegio.
- Separación estricta entre instalaciones.
- Secretos fuera del área pública.
- Autorización en servidor.
- Auditoría de acciones sensibles.
- Respaldos verificables.
- Degradación segura ante fallas.

## Controles mínimos de producción

- HTTPS obligatorio.
- `APP_DEBUG=false`.
- Demo desactivado.
- Contraseñas con hash seguro.
- Sesiones con expiración y regeneración.
- CSRF en escrituras.
- Protección contra intentos repetidos.
- Rotación de claves/tokens.
- Permisos de archivos restrictivos.
- Base no expuesta a Internet.

## Secretos técnicos

- `.env`, claves RSA y Apple `.p8` fuera de `public`.
- Contraseñas de cámaras/controladores cifradas en reposo antes de producción.
- Nunca incluir secretos en heartbeat, logs, capturas o paquetes.
- No entregar URLs RTSP con credenciales al navegador.

## Aperturas remotas

Flujo esperado:

```text
solicitud → sesión → rol → propiedad → horario → política
→ auditoría previa → conector → resultado → auditoría final
```

Puede requerir reautenticación, segundo factor, presencia local o aprobación de vigilancia.

## Auditoría

Registrar:

- Usuario e identidad.
- Instalación, unidad, área y entidad.
- Acción y motivo.
- Antes/después.
- Fecha, IP y dispositivo.
- Conector y respuesta externa.
- Resultado e incidente.

## Respaldos

Cada nodo y el Central deben tener:

- Respaldo de base.
- Respaldo de `.env` y claves por canal seguro.
- Respaldo de uploads/documentos.
- Política de retención.
- Prueba periódica de restauración.
- Respaldo previo a migraciones y actualizaciones.

## Soporte remoto

Debe ser deshabilitado por defecto. Una sesión futura requiere aprobación local, alcance, vencimiento, justificación y auditoría. Nunca implica compartir automáticamente toda la base del nodo.

## Brechas pendientes

La base actual prepara políticas, pero antes de producción debe completar cifrado de secretos de conectores, segundo factor, rate limiting, jobs de respaldo, restauración guiada y pruebas de penetración.
