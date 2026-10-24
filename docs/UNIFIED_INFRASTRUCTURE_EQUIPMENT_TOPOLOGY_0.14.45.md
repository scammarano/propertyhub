# Property Hub 0.14.45 — Inventario unificado de infraestructura y topología

## Objetivo

Eliminar la duplicación entre **Equipos administrados y activos**, **Mapeo físico de activos** e inventarios crudos de conectores. La UI principal deja de reflejar tablas internas y presenta una sola vista coherente de infraestructura.

## Un solo workspace: Equipos y activos

`Infraestructura y tecnología → Configuración técnica → Equipos y activos` contiene cuatro vistas:

1. **Equipos físicos** — hardware real: controladoras, lectores, NVR, cámaras, switches, gateways, relés, etc.
2. **Recursos Property** — puertas/accesos y cámaras lógicas que usa el negocio/App.
3. **Relaciones / topología** — vínculos entre recursos lógicos y hardware/objetos de conectores, con compatibilidad por tipo.
4. **Descubiertos** — inventario crudo recibido desde conectores para diagnóstico/adopción.

La antigua ruta `asset-bindings` redirige al tab **Relaciones / topología** para conservar deep-links anteriores.

## ZKBio: controladoras y lectores en el inventario físico

Los objetos ZKBio `device` y `reader` descubiertos por el conector se materializan idempotentemente en `bh_devices`:

- `device` → `access_controller`
- `reader` → `reader`
- proveedor → `ZKTeco`
- administración → instancia ZKBio
- ruta → `via_platform`
- modelo, serial, IP y estado se refrescan desde el payload cuando existen
- ubicación y nombre personalizados en Property se conservan al refrescar

Las **puertas** ZKBio no se convierten en equipos: siguen siendo recursos/actuadores de acceso y se adoptan en Control de accesos.

La conciliación se ejecuta después de persistir inventario ZKBio y también de forma idempotente al abrir Equipos y activos, de modo que inventario ya descubierto en versiones anteriores aparezca sin necesidad de recrearlo manualmente.

## Compatibilidad de relaciones

La UI y el servidor bloquean combinaciones absurdas:

- Una **cámara Property** no puede vincularse a controladoras, puertas o lectores ZKBio.
- Una cámara acepta fuentes de video/canales/streams compatibles.
- Un **punto de acceso** acepta puertas/actuadores compatibles.
- Un lector se vincula como autenticador/telemetría, no como actuador principal.
- Una controladora se relaciona como topología/telemetría; la operación principal debe apuntar a la puerta ZKBio correspondiente.

La validación no depende sólo del navegador: `AssetBindingService` aplica la matriz en servidor.

## Filtros

Equipos físicos permite filtrar por:

- texto libre (incluye nombre, marca, modelo, serial, IP, plataforma y ubicaciones),
- categoría: Acceso, Video, Red, IoT/técnica, Vertical, Comunicaciones,
- tipo técnico,
- sistema/conector responsable,
- estado.

## Modelo

La separación conceptual queda:

- **Equipo físico**: inBio, lector, NVR, cámara IP, switch, gateway, relé.
- **Recurso Property**: Puerta Lobby, Cámara Lobby, Ascensor 1, etc.
- **Objeto descubierto**: representación del fabricante/conector.
- **Relación/topología**: explica qué objeto/equipo implementa cada recurso lógico.

No se agrega migración SQL en 0.14.45; se reutilizan `bh_devices`, `bh_external_objects`, `bh_external_mappings` y `bh_asset_bindings` existentes.
