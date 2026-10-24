# Estructura territorial y unidades

## Propósito

Modela urbanizaciones, edificios, sedes, sectores, calles, torres, pisos, casas, oficinas, locales y garitas.

## Capacidades

- `sites`
- `sectors`
- `streets`
- `towers`
- `floors`
- `units`
- `guard_posts`
- `connectivity`

## Estado 0.6.0

Esquema territorial y demo inicial; CRUD jerárquico completo está pendiente.

## Reglas

- Respetar permisos y alcance por instalación/unidad.
- Usar persistencia, auditoría y validaciones antes de declarar estable.
- Seguir las reglas de grids, modales, filtros y paginación.
- No presentar una vista demo como integración productiva.

## Documentación relacionada

- [Funcionalidad general](../../docs/readme/README_10_FUNCIONALIDAD_DEL_PRODUCTO.md)
- [Matriz de estado](../../docs/readme/README_09_MATRIZ_DE_ESTADO_FUNCIONAL.md)

## Asistente de estructura 0.6.4

El módulo incorpora generación masiva persistente de raíz, torres, pisos, apartamentos y estacionamientos. Permite nomenclaturas configurables, áreas comunes estándar, vista previa, transacción única, historial y deshacer condicionado a que no exista actividad operativa relacionada.

Ruta: `Gestión de la propiedad → Estructura → Asistente de estructura`.


## Edición masiva 0.6.5

La pantalla `Estructura y unidades` incorpora selección múltiple para mover, cambiar estado, cambiar tipo, renombrar y eliminar de forma segura. Esto permite corregir pisos con cantidades diferentes de apartamentos, dejar pisos sin unidades y reorganizar la jerarquía después de usar el asistente.

## Diseñador de estructura 0.7.0

Los pisos son contenedores reales; admiten cantidades distintas de unidades o cero unidades. Se incorporan plantillas flexibles, selección masiva funcional y duplicación completa de torres o edificios con áreas y componentes planificados.

## Diseñador topológico 0.8.0

El módulo admite sótanos, PB, pisos, mezzaninas, terrazas, azoteas y pisos técnicos. Las reglas de código y nombre son independientes para niveles, unidades, estacionamientos y maleteros. Las áreas y ascensores se ubican sobre referencias exactas de la estructura.
