# Control de accesos y credenciales

## Propósito

Abstrae puertas, portones, barreras, torniquetes, ascensores, cerraduras, credenciales y permisos.

## Capacidades

- `access_points`
- `credentials`
- `permissions`
- `remote_open`
- `events`
- `elevators`

## Estado 0.6.0

Modelo y vistas demo; no ejecuta operaciones físicas hasta incorporar conectores reales.

## Reglas

- Respetar permisos y alcance por instalación/unidad.
- Usar persistencia, auditoría y validaciones antes de declarar estable.
- Seguir las reglas de grids, modales, filtros y paginación.
- No presentar una vista demo como integración productiva.

## Documentación relacionada

- [Funcionalidad general](../../docs/readme/README_10_FUNCIONALIDAD_DEL_PRODUCTO.md)
- [Matriz de estado](../../docs/readme/README_09_MATRIZ_DE_ESTADO_FUNCIONAL.md)
