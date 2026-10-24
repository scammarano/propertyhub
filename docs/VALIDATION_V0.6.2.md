# Validación técnica — Property Hub 0.6.2

## Alcance validado

- Sintaxis PHP de todo el paquete.
- JSON de metadatos y manifiestos.
- Tratamiento diferenciado de errores de autenticación esperados.
- Escritura estructurada de logs con redacción de secretos.
- Generación de request ID e incidente.
- Registro de errores fatales mediante shutdown handler.
- Panel de diagnóstico protegido por localhost o token.
- Herramientas para activar, desactivar y visualizar diagnóstico.
- Conservación de migraciones 0001–0010 sin cambios.
- Actualización de metadatos a 0.6.2.

## Limitaciones del entorno de validación

No se ejecutó el login contra la base MariaDB real de Premier. El diagnóstico definitivo del incidente debe realizarse en ese servidor mediante el log existente o el panel protegido.

## Resultado esperado

- Credenciales incorrectas: mensaje en el login, sin página 500.
- Error SQL/PDO: página 500 con incidente, request ID y detalle controlado.
- Error fatal: registro en `php-runtime.log` y `runtime-AAAA-MM-DD.jsonl`.
- Diagnóstico remoto: inaccesible sin token.

## Prueba HTTP local ejecutada

Se levantó el paquete con el servidor integrado de PHP y una configuración de prueba sin MariaDB disponible:

- `GET route=login`: respondió correctamente.
- `POST route=local-login` con campos vacíos: respondió `303` hacia el login.
- El login mostró `Indique usuario y contraseña.` sin generar página 500.
- La respuesta incluyó `X-Request-ID`.
- El panel `diagnostics.php` abrió con token y mostró el fallo de PDO de forma controlada.
- Después de la prueba se eliminaron `.env`, `installed.lock` y logs temporales del paquete.

## Resultado final del paquete

- 128 archivos PHP sin errores de sintaxis.
- 33 archivos JSON válidos.
- 321 archivos incluidos.
- Migraciones 0001–0010 idénticas a 0.6.1; no se alteraron checksums SQL.
- ZIP verificado sin errores de compresión.
