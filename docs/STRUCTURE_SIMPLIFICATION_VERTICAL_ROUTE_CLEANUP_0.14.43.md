# Property Hub 0.14.43 — Structure Simplification / Vertical Route Cleanup

## Objetivo

Reducir el ruido del gemelo digital y hacer que la estructura explique el inmueble en vez de exponer artefactos técnicos del Diseñador.

## Ascensores y paradas

- Las puertas planificadas creadas automáticamente para una parada de ascensor ya no se muestran como puertas normales en el mapa jerárquico.
- Cada nivel muestra un único resumen **Ascensores con parada**, con los ascensores que realmente tienen una parada vigente en ese nivel.
- La pantalla de ascensores muestra el recorrido comprimido; la lista completa de paradas queda bajo **Ver paradas**.
- El recorrido editado es la fuente de verdad. Al quitar una parada se elimina sólo la puerta planificada huérfana creada por el Diseñador cuando no tiene dispositivo ni binding físico.
- Eliminar un ascensor aplica la misma limpieza segura.

## Limpieza histórica

La migración `0038_vertical_stop_artifact_cleanup.sql` elimina artefactos de acceso de ascensor generados por el Diseñador que ya no están usados por ninguna parada, no tienen dispositivo y no tienen binding físico. No toca puertas reales ni accesos adoptados.

## Áreas con nombres repetidos

- El árbol y la tabla de Áreas marcan nombres repetidos dentro de la misma ubicación estructural.
- El árbol muestra además tipo y código para distinguir registros existentes.
- Property bloquea la creación de un nuevo duplicado exacto en la misma ubicación; el mismo nombre sigue permitido en pisos/ubicaciones diferentes.

## Compatibilidad de niveles elevados

Registros heredados `basement + E1/E2/...` se presentan como **Nivel elevado** para evitar la etiqueta contradictoria `Sótano · E1`, sin alterar automáticamente la clasificación persistida.
