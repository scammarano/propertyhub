# Property Hub 0.14.58 · Ruta progresiva de actualización del Core

## Objetivo
Un Node o Edge no debe quedar bloqueado cuando Central avanza varias versiones y un Core intermedio es requisito del siguiente. Central conserva/publica los artefactos puente y calcula una ruta progresiva desde la versión instalada hasta la última publicada.

Ejemplo: `0.14.53 → 0.14.54 → 0.14.55 → 0.14.56 → 0.14.57 → 0.14.58`.

## Reglas
- El manifiesto entrega como instalable sólo el siguiente Core compatible con la versión actual.
- Diagnóstico muestra **Último Core publicado**, **Siguiente Core aplicable** y la **Ruta progresiva** completa.
- Tras instalar un paso, el operador vuelve a consultar y recibe el siguiente.
- Si un Core futuro declara un `min_core_version` que permite saltar versiones, el planificador puede tomar ese salto seguro.
- El paquete Central incluye artefactos puente oficiales 0.14.52–0.14.57 y los publica idempotentemente en canal stable.
- Los releases siguen siendo inmutables por `component + version + SHA-256`.
- La validación append-only de migraciones usa como ancla el Core publicado inmediatamente anterior por versión, no el último publicado cronológicamente, permitiendo restaurar puentes históricos sin exigirles migraciones futuras.

## Alcance operativo
0.14.58 no agrega migración SQL. El cambio está en repositorio, diagnóstico y planificación del updater.
