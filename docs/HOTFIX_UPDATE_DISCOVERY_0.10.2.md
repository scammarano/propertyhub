# Property Hub 0.10.2 — Update Discovery Fix

Corrige los problemas detectados durante la primera prueba real Central → Node con PREMIER-LM.

## Correcciones

- El Core embebido publicado por Central acepta nodos desde **0.9.1** cuando ya tienen Update Bootstrap 1.0.0.
- Los canales son jerárquicos: `stable` recibe stable; `beta` recibe stable+beta; `development` recibe stable+beta+development; `pinned` permanece aislado.
- Se corrige la expresión regular de rutas Windows/UNC de `BackupManager`.
- El nodo registra `update_manifest_analyzed` con cantidad de releases, versiones disponibles y canales elegibles.
- El Central registra en el delivery log los canales elegibles y la versión Core reportada por el nodo.

## Causa encontrada en Premier

Los logs del nodo reportaban `app_version=0.9.1`. La release 0.10.1 publicada automáticamente exigía `min_core_version=0.9.3`, por lo que el Central filtraba correctamente la release y devolvía cero paquetes aunque la conexión y la firma funcionaban.
