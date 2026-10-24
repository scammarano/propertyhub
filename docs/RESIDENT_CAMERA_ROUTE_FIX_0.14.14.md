# Resident Camera Route Fix 0.14.14

## Causa encontrada

El front controller contenía una redirección general para cuentas `RESIDENT` antes de los endpoints de video. Cualquier ruta que no comenzara por `resident-` se enviaba a `resident-home`. Por ello `camera-live-config`, `camera-hls` y `camera-snapshot` nunca llegaban a ejecutarse para un residente real.

Los logs del 20-08-2026 lo demostraron: la vista administrativa solicitó `camera-live-config` y continuó reproduciendo; la Property App solicitó el mismo endpoint y, en menos de un segundo, apareció una petición a `resident-home` seguida del fallback a snapshot. Chrome iOS repitió el mismo patrón.

## Corrección

Se mantiene el guard de portal, pero se permite únicamente la allowlist técnica `camera-live-config`, `camera-hls`, `camera-snapshot`. Cada endpoint conserva su propia autenticación, permiso `cameras.view` y validación `cameraForViewing()`.

## Diagnóstico agregado

`runtime-*.jsonl` registra `camera_live_config_result` sin credenciales. La interfaz residente reporta fases del reproductor a `frontend-*.jsonl`.
