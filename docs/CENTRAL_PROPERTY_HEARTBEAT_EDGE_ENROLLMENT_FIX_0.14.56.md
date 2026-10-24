# Property Hub 0.14.56 · Identidad Central, heartbeat y enrolamiento Edge

## Problemas corregidos

### Nombre de instalación
El nombre visible de una propiedad se administra desde Central. Cambiarlo no modifica `installation_code`, UUID ni las relaciones Node/Edge. Una reconexión del Node conserva el nombre central.

### Estado de conexión
Central considera un Property Node **conectado** cuando `last_seen_at` es menor a 20 minutos. La salud (`healthy`, `warning`, `critical`) se muestra por separado. La API de heartbeat devuelve el código exacto y la hora almacenada por Central.

El botón **Enviar un heartbeat ahora** sólo ejecuta una llamada. Para mantener el Node conectado en forma continua debe ejecutarse `tools\install-scheduler-task.bat` en Windows/XAMPP (intervalo de 5 minutos) o el equivalente cron en Linux. El Scheduler registra su última ejecución en `storage/backups/scheduler-last-run.json`.

### Token Edge emitido por Central
El flujo `PHEDGE-C-*` es `Central → Property Node → Edge`. El Node reclama el token contra `edge-enrollment-claim`. 0.14.56 agrega esa acción y `edge-bootstrap-token` al front-controller, además de conservar los endpoints PHP directos. Esto evita fallas cuando el hosting bloquea o reescribe el acceso directo a `/api/repository/v1/*.php`.

## Compatibilidad
No hay migración SQL nueva. La versión se instala acumulativamente sobre 0.14.55.
