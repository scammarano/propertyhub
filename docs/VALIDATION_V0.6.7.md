# Validación Property Hub 0.6.7

## Validaciones realizadas

- Sintaxis PHP de todos los archivos del paquete.
- Sintaxis JavaScript del asistente mediante `node --check`.
- JSON válido para metadatos y manifiestos.
- Verificación estática de que las migraciones 0001–0012 no fueron modificadas.
- Verificación del flujo de áreas existentes:
  - detección por `installation_id + code`;
  - exclusión del plan de inserción;
  - revalidación dentro de la transacción;
  - contador separado `areas_existing`;
  - ausencia en `bh_structure_wizard_items` para impedir que Deshacer las elimine.
- Verificación de retorno funcional ante `InvalidArgumentException`.
- Verificación de restauración del payload del asistente.

## Limitación del entorno

La ejecución completa debe probarse en MariaDB/XAMPP de Premier porque el entorno de construcción no contiene la base de datos real de la instalación.

## Resultado del empaquetado

- Archivos PHP validados: 131.
- Archivos JSON validados: 33.
- JavaScript del asistente: sintaxis válida.
- Migraciones 0001–0012: idénticas a 0.6.6.
- ZIP: prueba de integridad ejecutada.
