# Property Hub 0.13.7 · Inmutabilidad de migraciones

## Incidente corregido

La versión 0.13.6 cambió el comentario de cabecera de `0025_cpanel_direct_webrtc.sql` de `0.13.5` a `0.13.6`. No cambió SQL ejecutable, pero sí cambió el SHA-256. `MigrationRunner` hizo lo correcto y rechazó la actualización porque una migración ejecutada nunca debe cambiar.

## Regla permanente

Una vez publicada una migración, el archivo completo queda inmutable, incluyendo comentarios, espacios y saltos de línea. Cualquier cambio de esquema posterior debe ir en una migración nueva.

## Checksum canónico 0025

`52790b3fc4c4176387e0c04d7bea1d97bb6e2cce4be5c0470ad07ecff3af3b31`

## Protección adicional

`database/migrations/MIGRATION_CHECKSUMS.json` conserva los hashes canónicos. Central valida la consistencia del ZIP y, cuando existe un release previo con ledger, no permite cambiar hashes históricos.
