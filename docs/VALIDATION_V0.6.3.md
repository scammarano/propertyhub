# Validación técnica — Property Hub 0.6.3

## Verificaciones realizadas

- Sintaxis PHP de todos los archivos del paquete.
- Validez de todos los manifiestos JSON.
- Normalización de URL con `/public/index.php?route=...`.
- Generación de candidatos de endpoint directo y front controller.
- Registro inicial separado de reconexión.
- Regeneración de token sin eliminación del expediente.
- Reconexión asociada a una instalación existente.
- Revocación y reemplazo de tokens anteriores.
- Token de reconexión de un uso y vencimiento de 72 horas.
- Preservación del ID central durante rotación de credenciales.
- Validación de código de instalación y colisión de UUID.
- Logs de entrega para token reemitido y credenciales rotadas.
- Integridad del ZIP y checksum SHA-256.

## Limitación del entorno de construcción

No se dispone de un servidor MariaDB activo en este entorno. La migración `0011_registration_token_lifecycle.sql` debe probarse en el XAMPP del Central antes de utilizar el flujo con Premier.

## Prueba recomendada

1. Actualizar Central y ejecutar migraciones.
2. Abrir Premier en el Central y generar token de reconexión.
3. Actualizar Premier.
4. Probar endpoint.
5. Renovar vínculo con el token.
6. Enviar heartbeat.
7. Confirmar en el Central que la ficha conserva su ID e historial y actualiza `last_seen_at`.

## Conteos del paquete validado

- Archivos PHP: 130
- Archivos JSON: 33
- Documentos Markdown: 96
- Migraciones SQL: 11
- Enlaces Markdown internos verificados: 116
- Migraciones 0001–0010: checksums idénticos a 0.6.2
- Migración nueva: 0011
