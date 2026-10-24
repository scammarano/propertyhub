# Property Hub 0.11.5 — Update Package Contract & Publication Fix

## Incidente confirmado

En `PREMIER-LM` 0.11.3, Central anunció 0.11.4, el Node descargó correctamente `property-hub-core-0.11.4-stable`, pero `UpdateManager::resolvePayloadRoot()` abortó con `No se encontró la raíz del paquete de actualización`.

El ZIP distribuible 0.11.4 no contenía `update-package.json`; por tanto el fallo estaba en la construcción/publicación del Core, no en ping, heartbeat, credenciales ni transporte.

## Corrección

- El Core 0.11.5 contiene `update-package.json` en la raíz.
- `ReleasePublisher` valida la existencia y JSON del contrato antes de publicar/copiarlos al repositorio.
- El contrato debe declarar `schema=2cam-property-update-v1`, `component_code` y `version`.
- No hay migraciones SQL nuevas.
