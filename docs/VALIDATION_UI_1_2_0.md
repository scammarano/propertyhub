# Validación de Property Hub 0.2.1 con 2CAM UI Standard 1.2.0

Fecha de validación: 24 de julio de 2026.

## Validaciones ejecutadas

- 63 archivos PHP sin errores de sintaxis en PHP 8.4.
- 21 archivos JSON válidos.
- Login de demostración para administración con redirección al dashboard.
- Login de residente con redirección independiente a Resident App.
- Resident App sin `data-cam-sidebar` ni shell administrativo.
- Consola con `sidebar-mini`, bootstrap temprano y runtime único.
- Menú administrativo con `GROUP / SECTION / ITEM`.
- Activación correcta de una ruta de tercer nivel (`Reservas`).
- Filtrado de menú por permisos para el perfil de vigilancia.
- Presencia de todos los assets requeridos por 1.2.0.
- Ausencia de `np-sidebar.js` y de controladores propios de estado del sidebar.

## Resultado

La adaptación visual conserva las rutas, permisos, datos de demostración, autenticación y separación entre Resident App y Operations Console. No se habilitaron integraciones físicas ni persistencia en base de datos durante esta actualización visual.

## Pendientes de validación en la instalación del usuario

- 1366×720 con zoom 100 %, 110 % y 125 %.
- 390×844 en dispositivos móviles reales.
- Persistencia tras recargar en los navegadores usados por el cliente.
- Tema oscuro cuando se habilite configuración de apariencia.
- Tablas, modales y calendario con datos reales.
