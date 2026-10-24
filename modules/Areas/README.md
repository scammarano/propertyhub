# Áreas y espacios de la propiedad

## Propósito

Administra la clasificación física y funcional de áreas comunes, reservables, técnicas, comerciales y de seguridad. Incluye el modelo territorial para parques, garitas y zonas.

## Alcance

- Catálogo y clasificación de áreas.
- Capacidad, horario, tarifa, depósito y reglas de reserva.
- Relación con dispositivos, cámaras y accesos.
- Áreas de urbanizaciones, edificios, oficinas y comercios.

## Estado 0.6.0

Las tablas y vistas base existen. El catálogo mostrado es principalmente demo; el CRUD persistente completo y sus reglas aún deben implementarse.

## Reglas

- Respetar permisos en servidor.
- Mantener migraciones y manifiesto versionados.
- Aplicar reglas de grids, modales, filtros y paginación del estándar del proyecto.
- Actualizar este README cuando cambie el estado funcional.

## Documentación relacionada

- [Funcionalidad general](../../docs/readme/README_10_FUNCIONALIDAD_DEL_PRODUCTO.md)
- [Matriz de estado](../../docs/readme/README_09_MATRIZ_DE_ESTADO_FUNCIONAL.md)
- [Filosofía de desarrollo](../../docs/readme/README_08_FILOSOFIA_Y_REGLAS_DE_DESARROLLO.md)

## Áreas 0.7.0

Se incorporan selección múltiple, mover, cambiar estado/tipo, duplicar con componentes y eliminación segura. Varias áreas del mismo tipo pueden ubicarse en pisos diferentes mediante códigos únicos.

## Ubicación topológica 0.8.0

Un mismo tipo de área puede existir varias veces en ubicaciones diferentes. Cada instancia debe tener código único y vincularse con el conjunto, torre o nivel correspondiente.
