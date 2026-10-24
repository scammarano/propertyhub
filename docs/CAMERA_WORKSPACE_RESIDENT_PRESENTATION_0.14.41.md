# Camera Workspace / Resident Presentation — 0.14.41

## Objetivo

Evitar que la pantalla de cámaras mezcle configuración funcional, transporte remoto e inventario técnico, y presentar cámaras/puertas al residente con nombres amigables y agrupación territorial.

## Workspace de cámaras

1. **Cámaras y ubicación**: nombre administrativo, nombre para residentes, área, visibilidad, orden, capacidades y prueba en vivo.
2. **Video remoto**: modo de transporte, MediaMTX/relay, endpoints Edge y prueba del transporte.
3. **Fuentes y canales**: origen Edge/Node, NVR/DVR/canal, stream/codec, estado y snapshot.

Los grupos de cámaras por Área empiezan colapsados para reducir scroll y pueden expandirse/colapsarse globalmente. La pestaña activa se conserva durante la sesión del navegador.

## Presentación al residente

Cámaras y accesos incorporan:

- `resident_name`: nombre amigable mostrado en Property App; si está vacío se usa `name`.
- `display_order`: orden dentro de la misma Área/Zona; menor número aparece primero.

Property App agrupa ambos dominios por Área/Zona.

## Autoridad de nombres

- Edge/fabricante: nombre técnico (`edge_name` / identificador externo).
- Property Hub Node: `name` administrativo.
- Property App: `resident_name` con fallback a `name`.

La sincronización técnica no debe sobrescribir el nombre para residentes.
