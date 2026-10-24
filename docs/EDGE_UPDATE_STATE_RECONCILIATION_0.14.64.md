# Property Hub 0.14.64 — Edge Update State Reconciliation

## Problema corregido

Central conoce qué releases Edge-only están habilitados para una propiedad, pero no conoce por sí solo el inventario vivo de cada Edge. En 0.14.62/0.14.63 esos releases se etiquetaban como “pendientes para Edge” antes de reconciliarlos con el inventario que el Edge reporta al Property Node.

El síntoma era contradictorio: la tarjeta podía estar bajo **Actualizaciones pendientes para Edge Agents** y al mismo tiempo mostrar el único Edge como **Actualizado** en la versión objetivo.

## Semántica desde 0.14.64

- Central entrega **candidatos de distribución Edge**; no afirma que estén pendientes.
- El Property Node reconcilia cada candidato contra `bh_edge_agents.metadata_json.installed_components`.
- Un release aparece en **Actualizaciones pendientes para Edge Agents** sólo si al menos un Edge activo no reporta inventario o tiene una versión inferior.
- Si todos los Edge activos reportan una versión igual o superior al objetivo, el release sale de pendientes y la pantalla muestra **Componentes Edge al día**.
- Edge Agents `disabled` o `retired` no bloquean el estado al día.
- Se conserva compatibilidad con Nodes antiguos mediante `downstream_edge_pending_count`, pero 0.14.64 utiliza `downstream_edge_candidate_count` + reconciliación local.

## Ejemplo

Release objetivo: `connector-vimar 0.5.1`.

- `PREMIER-LM-EDGE-01 = 0.5.0` → pendiente.
- `PREMIER-LM-EDGE-01 = 0.5.1` → al día; la tarjeta deja de figurar entre pendientes.
- Dos Edge: uno 0.5.1 y otro 0.5.0 → sigue pendiente y la tabla identifica cuál falta.

No se agregan migraciones SQL.
