# 2CAM UI Standard 1.2.0 — consolidación definitiva 2026-07-24

## Base correcta

- Reconstruida con **1.1.4 + 1.1.5 + 1.1.6**.
- Integra las mejoras válidas de NP Visual Common 1.1.0.
- Mantiene un solo runtime de navegación en `assets/js/2cam-ui.js`.

## Sidebar

- Persistencia general mediante `lte.sidebar.state`.
- Estado explícito por grupo mediante `2cam:sidebar-group-state:v2`.
- Migración automática de `2cam:sidebar-open-groups` y `2cam:sidebar-collapsed`.
- Un grupo activo puede cerrarse y permanecer cerrado después de recargar.
- Sincronización de `menu-open`, `hidden`, `display`, `aria-expanded` y `aria-hidden`.
- Neutralización del `display:block` en línea dejado por AdminLTE.
- Navegación de uno, dos o tres niveles (`GROUP`, `SECTION`, `ITEM`).
- Reflujo de gráficos y widgets al cambiar la sidebar.
- Cierre móvil al seleccionar una ruta.
- Corrección de menús largos mediante `100dvh`, flex y scroll exclusivo en `.sidebar-wrapper`.
- La opción activa se lleva al área visible con `scrollIntoView({block:'nearest'})` cuando sus padres no fueron cerrados explícitamente.

## Apariencia, dashboard y grillas

- Conserva la pantalla de apariencia autocontenida 1.1.4.
- Conserva `.cam-legacy-grid` de 1.1.5.
- Mantiene widgets responsive y adaptador exclusivo de NEWPROFIT.

## Pruebas añadidas

- Cierre del grupo activo.
- Persistencia explícita `false` después de recargar.
- Reapertura y persistencia explícita `true`.
- Migración del arreglo heredado de grupos abiertos.
- Verificación del último bloque visible en escritorio y móvil.
