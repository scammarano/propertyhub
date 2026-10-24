# Property Hub 0.14.38 — Update Volatile Storage Cache Fix

Corrige una regresión introducida al endurecer el respaldo PRE_UPDATE. El Edge genera snapshots temporales ONVIF/RTSP en `storage/uploads/edge-camera-cache/`; esos archivos pueden desaparecer mientras se crea el respaldo y nunca son datos recuperables.

## Cambios

- `storage/uploads/edge-camera-cache/**` queda fuera de respaldos completos y PRE_UPDATE.
- Los respaldos antiguos que contengan ese directorio no lo restauran.
- Se mantienen las exclusiones de `storage/run/**`, logs, tmp, cache, sessions y updates.
- Si PRE_UPDATE falla antes de aplicar archivos, no se ejecuta rollback.
- Sin migraciones nuevas ni cambios a migraciones históricas.
