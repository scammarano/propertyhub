# Validación técnica — Property Hub 0.6.5

## Resultado

- 131 archivos PHP sin errores de sintaxis.
- 33 archivos JSON válidos.
- 12 migraciones SQL conservadas con el mismo checksum de 0.6.4.
- JavaScript inline de la selección masiva validado con `node --check`.

## Revisiones realizadas

- Sintaxis PHP de todos los archivos.
- Validez de archivos JSON.
- Integridad de rutas POST y protección CSRF.
- Restricción de acciones masivas a Property Node.
- Selección por página y selector general con estado indeterminado.
- Movimiento con preservación de jerarquía interna.
- Bloqueo de movimientos circulares.
- Cambio masivo de estado y tipo.
- Renombrado con detección de códigos duplicados.
- Eliminación segura: borrado sin dependencias y desactivación con relaciones.
- Auditoría de la operación masiva.
- Conservación de migraciones 0001–0012 sin cambios.
- Integridad del paquete ZIP y checksum SHA-256.

## Limitación del entorno

La prueba final contra MariaDB debe realizarse en la instalación XAMPP de Premier. El entorno de construcción valida código y estructura, pero no contiene la base operativa del cliente.
