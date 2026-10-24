# Hotfix 0.10.9 — Windows / XAMPP Self-Update

## Incidente reproducido

En XAMPP 8.2.12, un nodo 0.10.4 descubre 0.10.8 y descarga el paquete correctamente, pero al fallar la instalación el rollback intenta extraer el snapshot sobre `public/index.php` y Windows devuelve `Permission denied`. El ErrorReporter antiguo puede ocultar el error inicial.

## Solución de compatibilidad

El paquete Core 0.10.9 no sustituye `public/index.php` dentro de la solicitud `apply-update` ejecutada por el bootstrap 1.0.0. En su lugar instala el nuevo front controller en `storage/updates/deferred/`, deja un manifiesto SHA-256 y, en la siguiente solicitud autenticada de administrador, `app/bootstrap.php` redirige a `public/update-finalize.php`. Ese endpoint se ejecuta sin tener abierto `public/index.php`, valida SHA-256, hace respaldo temporal y activa el archivo de forma atómica.

A partir de 0.10.9 el Update Engine aplica esta política automáticamente en Windows y el rollback restaura archivo por archivo, difiriendo el front controller cuando corresponde.
