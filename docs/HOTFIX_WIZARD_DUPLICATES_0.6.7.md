# Hotfix 0.6.7 — Duplicados en el asistente de estructura

## Problema

Cuando el Property Node ya tenía un área estándar —por ejemplo `PISCINA`— y el asistente volvía a seleccionarla, la generación se cancelaba con `InvalidArgumentException` y se mostraba como un incidente técnico.

Ese comportamiento era incorrecto: una coincidencia con un área estándar existente es una situación funcional normal, no una falla del servidor.

## Comportamiento nuevo

1. El asistente consulta las áreas existentes de la instalación.
2. Las tarjetas muestran **Ya creada · se conservará**.
3. La vista previa diferencia:
   - **Se creará**: registro nuevo.
   - **Se conservará**: registro ya existente.
4. El servidor vuelve a verificar dentro de la transacción.
5. El área existente no se modifica, no se duplica y no se registra como elemento creado por la ejecución.
6. La función **Deshacer** nunca elimina un área reutilizada.

## Conflictos de estructura

Los códigos repetidos de torres, pisos, apartamentos, contenedores o estacionamientos continúan bloqueándose, porque no es seguro asumir qué jerarquía desea el usuario. Ahora se muestran como advertencia funcional y el formulario se restaura para corregirlos.

## Actualización

- Conservar `.env` y `storage/`.
- Sustituir los demás archivos.
- Ejecutar `tools\migrate.bat` solamente para registrar la versión 0.6.7.
- No hay migraciones SQL nuevas.
