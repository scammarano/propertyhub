# 2CAM UI Standard 1.2.0 — paquete definitivo consolidado

Paquete visual común para **WebGuest**, **NEWPROFIT Express**, WhatAgent, WhatManager, PassManager, Proyectos y desarrollos futuros del ecosistema 2CAM.

Esta versión se reconstruyó y validó incorporando toda la línea correcta:

- **1.1.4:** pantalla de apariencia autocontenida (`cam-appearance-*`).
- **1.1.5:** puente de compatibilidad para grillas heredadas `.col-*` mediante `.cam-legacy-grid`.
- **1.1.6:** grupos de sidebar realmente cerrables, incluso cuando contienen la ruta activa.
- **NP Visual Common 1.1.0:** jerarquía visual y widgets responsive.
- **1.2.0:** runtime único, navegación `GROUP / SECTION / ITEM`, sidebar de altura dinámica y corrección del último bloque cortado.

No contiene credenciales, conexiones, datos funcionales ni fuentes tipográficas.

## Correcciones críticas de sidebar

1. **Menús largos:** el `aside` funciona como columna flex de `100dvh`; solamente `.sidebar-wrapper` se desplaza, por lo que la última sección nunca queda debajo del pie.
2. **Grupo activo cerrable:** el usuario puede cerrar el grupo que contiene la página actual. El runtime sincroniza `menu-open`, `hidden`, `display`, `aria-expanded` y `aria-hidden`.
3. **Persistencia explícita:** cada grupo guarda `true` o `false` en `2cam:sidebar-group-state:v2`. Se migra automáticamente la clave anterior `2cam:sidebar-open-groups`.
4. **Tres niveles:** la misma regla se aplica a grupos y secciones anidadas.

## Orden de carga

```html
<link rel="stylesheet" href="assets/adminlte/adminlte.min.css">
<link rel="stylesheet" href="assets/css/2cam-adminlte.css">
<link rel="stylesheet" href="assets/css/2cam-modern-ui.css">
<link rel="stylesheet" href="assets/css/2cam-dashboard-responsive.css">
```

```html
<body class="layout-fixed sidebar-expand-lg sidebar-mini bg-body-tertiary cam-adminlte cam-ui-v1">
<script src="assets/js/2cam-sidebar-bootstrap.js"></script>
```

```html
<script src="assets/adminlte/adminlte.min.js"></script>
<script src="assets/plugins/2cam-vendor-ui.js"></script>
<script src="assets/plugins/chartjs-2.7.2.bundle.js"></script>
<script src="assets/js/2cam-ui.js"></script>
```

No cargar simultáneamente `np-sidebar.js`, otra copia de `2cam-ui.js` ni controladores propios que modifiquen `sidebar-collapse`, `sidebar-open`, `menu-open`, `hidden` o el `display` de los submenús.

## Aplicación piloto

1. Respaldar los archivos visuales del proyecto.
2. Copiar primero en una instalación de prueba.
3. No reemplazar `.env`, conexiones, autenticación, permisos ni configuración funcional.
4. Aplicar `sql/migrations/1.2.0_sidebar_sections.sql` solo cuando la tabla de preferencias ya exista con el enum anterior.
5. Seguir `docs/PILOT_WEBGUEST_NEWPROFIT.md`.
6. Probar `demo/sidebar-long-menu.html`: cerrar **Sistema**, recargar y confirmar que sigue cerrado aunque **Usuarios** permanezca activo.

## NEWPROFIT

Además de los assets comunes, NEWPROFIT puede usar `adapters/newprofit/widget-library.json`. Ese adaptador no debe copiarse a WebGuest.

## Regla no destructiva

La capa visual no modifica tablas operativas, API keys, credenciales, empresas, inmuebles, huéspedes, reservas, artículos, documentos ni transacciones.
