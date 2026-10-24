# Política de actualización del estándar visual

El paquete visual común está en revisión continua. Por ello se trata como una dependencia versionada.

## Regla principal

No modificar archivos dentro de `public/assets/vendor/2cam-ui/`.

## Adaptaciones del proyecto

Toda diferencia de Property Hub debe vivir fuera de la dependencia:

- CSS de administración.
- CSS de residente.
- CSS de login.
- JavaScript propio.
- Layouts y vistas PHP.

## Procedimiento de actualización

1. Respaldar la versión actual.
2. Sustituir únicamente la carpeta `vendor/2cam-ui` por la nueva versión.
3. Actualizar `UI_VERSION.json` y `VERSION.json`.
4. Revisar el changelog del estándar.
5. Probar login, sidebar, tablas, modales, calendario y vista móvil.
6. Corregir solamente los adaptadores externos.
7. Nunca copiar cambios del producto hacia el paquete común sin evaluarlos como mejora general.


## Baseline actual

La referencia activa es 2CAM UI Standard 1.2.0. Toda próxima revisión debe compararse contra `UI_VERSION.json`, reemplazarse primero en una copia y validar persistencia de sidebar, rutas protegidas, calendario, tablas, modales y Resident App.
