# Property Hub 0.14.48 — Support Log ZIP + Core Artifact Self-Heal

## Descarga de logs

Los usuarios con permiso `system.manage` pueden descargar un ZIP desde la pantalla de error o desde **Diagnóstico**. El ZIP reúne logs recientes de `storage/logs/` y un `support-info.json`.

La creación del bundle no copia `.env`, claves, respaldos ni repositorios. Antes de incluir archivos de texto se aplica una redacción defensiva adicional para encabezados Authorization, tokens, passwords, cookies y credenciales embebidas en URLs RTSP/HTTP.

## Recuperación del Core distribuible

El flujo normal entrega el Core de Node/Edge en `resources/releases/property-hub-core-<version>.zip`. Si un despliegue del Central no conserva ese ZIP anidado, `RepositorySynchronizer` ya no falla inmediatamente. Reconstruye el Core desde la aplicación instalada mediante `CoreDistributionArtifactBuilder`, excluyendo:

- `.env` y `.env.*`
- `storage/**`
- `resources/releases/**`
- `.git/**`

El `update-package.json` del Core reconstruido se normaliza como `property-hub-core` y se limita a `building_node` y `edge_agent`. Después, `ReleasePublisher` ejecuta la validación normal de ZIP, metadata, health files y ledger de migraciones antes de publicar.

## Contrato de actualización

`storage/repository/**` continúa siendo estado runtime protegido y no debe viajar dentro de un update normal. El Central lleva el Core oficial en `resources/releases/**` y lo copia/publica hacia `storage/repository/**` únicamente en runtime.

No se agregan migraciones SQL en 0.14.48. Las migraciones `0001`–`0040` permanecen intactas.
