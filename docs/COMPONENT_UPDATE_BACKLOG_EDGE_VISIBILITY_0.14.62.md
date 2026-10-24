# Property Hub 0.14.62 — Component Update Backlog & Edge Visibility

## Objetivo

Una actualización publicada de un componente no desaparece porque el operador haya decidido actualizar primero el Core. El Update Center conserva y vuelve a mostrar el backlog aplicable por rol.

## Regla de roles

Los conectores declarados `target_roles: [edge_agent]` no se instalan en Property Node. El Node actúa como fuente inmediata de distribución y cache para sus Edge Agents.

Para evitar que esto parezca una actualización perdida, el manifiesto Central -> Node incluye `diagnostics.downstream_edge_releases` con la última versión deseada de cada componente Edge-only habilitado para la propiedad.

El Update Center del Node muestra esos paquetes en una sección separada **Actualizaciones pendientes para Edge Agents**. Si el Node conserva una versión histórica del mismo connector, se muestra como legado local, pero no se intenta actualizar allí.

## Inventario de versiones Edge

El heartbeat Edge -> Node reporta únicamente el mapa de versiones instaladas (`property-hub-core`, connectors, módulos) sin credenciales ni parámetros LAN. El Node conserva ese inventario dentro de `bh_edge_agents.metadata_json` y puede mostrar por agente si un componente está pendiente o actualizado.

## Lote automático 1.2

El lote automático deja de ser Core-only:

1. predescarga todos los paquetes locales actualmente aplicables y valida SHA-256;
2. aplica la ruta progresiva del Core en orden;
3. aplica conectores/módulos locales compatibles;
4. con el nuevo runtime vuelve a consultar el manifiesto;
5. agrega automáticamente componentes que solo se hicieron elegibles después del upgrade del Core;
6. repite la conciliación hasta no encontrar más componentes pendientes.

Un componente Edge-only nunca se instala en Node. Se instalará mediante el mismo lote cuando el proceso se ejecute en el Edge correspondiente.

## Caso Vimar

`connector-vimar 0.5.1` es Edge-only. Un Node que tenga `connector-vimar 0.5.0` en su historial puede seguir mostrando 0.5.0 como instalación histórica, pero 0.5.1 se presenta como **pendiente para Edge** y permanece visible hasta que los Edge reporten esa versión o una superior.
