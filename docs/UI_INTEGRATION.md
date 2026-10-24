# Integración del estándar visual

Versión activa: **2CAM UI Standard 1.2.0**.

## Consola de operaciones

Orden de carga:

1. `adminlte.min.css`
2. `2cam-adminlte.css`
3. `2cam-modern-ui.css`
4. `2cam-dashboard-responsive.css`
5. estilos propios de Property Hub

Al abrir `body` se carga `2cam-sidebar-bootstrap.js`. Al final se cargan AdminLTE, vendor UI, Chart.js, `2cam-ui.js` y finalmente el JavaScript propio.

La consola usa `SidebarRenderer` y el menú estable definido en `config/navigation.php`. Los permisos se filtran en PHP antes de renderizar.

## Resident App

La aplicación móvil comparte tokens y componentes visuales básicos, pero conserva su encabezado, drawer y navegación inferior. No debe adoptar el sidebar administrativo.

## Regla de actualización

Nunca modificar archivos dentro de `public/assets/vendor/2cam-ui/`. Toda adaptación local pertenece a los CSS/JS de Property Hub o a sus layouts.
