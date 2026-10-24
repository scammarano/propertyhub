# Operación, mantenimiento y soporte

## Diagnóstico

Cada instalación debe mostrar:

- Versión de Core/API/UI.
- Perfil Central o Node.
- Estado de base y migraciones.
- Estado del repositorio.
- Último heartbeat.
- Conectores y dispositivos.
- Colas/errores.
- Requisitos PHP y permisos de carpetas.

## Incidentes

Una excepción genera un identificador. El usuario reporta ese código; el soporte consulta el archivo correspondiente en `storage/logs`. No se deben mostrar detalles técnicos sensibles a perfiles sin permiso.

## Reparación de esquema

La ruta `system-repair` detecta migraciones pendientes. También puede ejecutarse:

```bat
tools\migrate.bat
```

Una migración aplicada no debe ejecutarse nuevamente ni editarse.

## Demo

- Central: cargar/restablecer desde Vista ejecutiva o Laboratorio.
- Node: `tools\reseed-node-demo.bat` para laboratorio.
- Demo nunca se activa en producción.

## Factory reset

`tools\factory-reset.bat` elimina tablas, `.env`, bloqueo y claves. Solo es válido para una instalación de prueba y requiere respaldo. No es un mecanismo de actualización.

## Salud y heartbeat

El nodo debe enviar un resumen periódico con versiones, salud, métricas permitidas e inventario no sensible. Los reintentos no deben bloquear la operación local.

## Mantenimiento programado

Las actualizaciones que cambian base o archivos pueden activar modo mantenimiento. Vigilancia debe conocer la ventana y los sistemas físicos deben conservar permisos descargados.

## Soporte de primer nivel

1. Confirmar URL y perfil.
2. Consultar incidente/log.
3. Revisar migraciones.
4. Revisar `.env` sin compartir secretos.
5. Validar base y extensiones PHP.
6. Confirmar conexión al Central.
7. Revisar conector afectado.
8. Escalar con evidencia y versión exacta.
