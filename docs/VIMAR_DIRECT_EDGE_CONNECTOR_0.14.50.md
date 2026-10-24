# 0.14.50 · Vimar Universal Connector · Direct Edge Discovery

## Decisión de arquitectura

El programa independiente **VimarHub** se mantiene como laboratorio/desarrollo. Property Hub productivo no depende de él.

```text
Property Edge Agent
  └─ connector-vimar
       └─ LAN local
            ├─ Vimar VIEW
            ├─ Vimar By-me
            └─ KNX / KNXnet-IP
```

VimarHub y Home Assistant siguen disponibles como bridges opcionales/fallback, nunca como requisito del modo predeterminado.

## Discovery directo 0.4.0

El conector ejecuta discovery de solo lectura desde PHP/Property Edge, sin daemon adicional:

- mDNS en UDP 5353;
- anuncios HomeKit HAP `_hap._tcp.local` para identificar gateways VIEW compatibles;
- servicios Vimar/VIEW/By-me cuando estén anunciados;
- KNXnet/IP Search nativo sobre UDP 3671 / multicast 224.0.23.12;
- SSDP/UPnP como evidencia complementaria.

El usuario puede seleccionar la interfaz LAN del Edge y el tiempo de búsqueda.

## Adopción

Los gateways identificados se materializan en el inventario técnico del Edge y luego pueden sincronizarse al Node mediante `Adoptar / sincronizar con Node`.

No se convierten automáticamente en recursos lógicos. Un relay/switch descubierto no se interpreta como puerta o luz hasta que Property lo vincule explícitamente.

## Alcance seguro

Esta versión integra discovery y adopción técnica de gateways directamente dentro del Agent. No intenta romper ni reutilizar a ciegas un pairing HomeKit existente. La enumeración profunda de accesorios VIEW/By-me que requiera sesión emparejada/autenticación específica se implementará detrás de adapters dedicados sobre este mismo conector.

## Migración desde 0.3.0

`0041_vimar_direct_edge_discovery.sql` agrega `direct_auto`, interfaz LAN y tiempo de discovery. Si una instalación conservaba el bridge por defecto `http://127.0.0.1:47821` y nunca llegó a establecer un contrato, se migra automáticamente al modo directo. Configuraciones VimarHub remotas/personalizadas exitosas no se cambian.

## Bridges opcionales

- `vimarhub_bridge`: VimarHub externo para laboratorio/fallback.
- `home_assistant`: Home Assistant local.
- `view_direct` / `byme_direct`: reservados para adapters profundos específicos.
