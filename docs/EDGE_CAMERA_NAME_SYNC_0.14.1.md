# Property Hub 0.14.1 · Sincronización de nombres Edge → Node

## Regla
- El Agent es dueño del **nombre técnico** del equipo/canal.
- El Node es dueño del **nombre visible** para administración y residentes.
- Por defecto, una cámara Edge usa `name_sync_mode=edge`: al renombrarla en el Agent, el Node actualiza su nombre visible.
- Si en el Node se desmarca **Mantener sincronizado con el nombre del Agent**, queda `name_sync_mode=node`; futuros renombres técnicos se muestran como referencia pero no pisan el nombre visible.

## Flujo
1. Renombrar cámara/canal en Edge.
2. Guardar/probar/sincronizar.
3. `inventory-sync` actualiza `edge_name` en el Node.
4. Si `name_sync_mode=edge`, también actualiza `bh_cameras.name`.
5. Si `name_sync_mode=node`, conserva `bh_cameras.name` y muestra el nuevo nombre técnico aparte.
