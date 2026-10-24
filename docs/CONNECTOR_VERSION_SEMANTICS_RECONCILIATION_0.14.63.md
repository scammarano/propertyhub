# Property Hub 0.14.63 — Connector Version Semantics Reconciliation

## Objetivo
Separar de forma explícita tres conceptos que antes podían confundirse visualmente:

1. **Versión de catálogo**: versión de la definición de integración incluida con el Core.
2. **Política de versión deseada**: latest_compatible / exact / minimum / range.
3. **Versión instalada**: versión real reportada por el Node/Edge para el componente distribuible.

## Corrección
- `latest_compatible` ya no almacena ni expone un número de versión como si fuera un pin.
- Desired state legacy que arrastraba una versión descriptiva (por ejemplo Vimar 0.4.0) se reconcilia automáticamente al pedir el manifiesto.
- Las políticas Exacta y Mínimo conservan su versión; Rango conserva su constraint.
- El catálogo de integraciones etiqueta su versión como **catálogo**, no como versión instalada.
- La pantalla de actualizaciones muestra la política deseada en lenguaje explícito.

## Resultado esperado
Una instalación antigua y una nueva con `vimar` en política `latest_compatible` deben mostrar el mismo estado lógico: `vimar · última compatible`. La versión 0.5.1 del catálogo se mantiene visible sólo como referencia de catálogo, mientras la versión realmente instalada se consulta en el inventario/update backlog del rol correspondiente.

No agrega migraciones SQL. La reconciliación es idempotente y ocurre sobre `metadata_json` existente.
