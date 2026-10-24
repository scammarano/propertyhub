# Identidad, usuarios y membresías

## Propósito

Gestiona personas autenticables, identidades externas, membresías por instalación/unidad, roles y permisos.

## Capacidades

- `local_users`
- `social_identities`
- `memberships`
- `roles_permissions`
- `global_identity_future`

## Estado 0.6.0

Login local es persistente; Google/Apple están preparados. Identidad global multiinstalación y administración completa de membresías están pendientes.

## Reglas

- Respetar permisos y alcance por instalación/unidad.
- Usar persistencia, auditoría y validaciones antes de declarar estable.
- Seguir las reglas de grids, modales, filtros y paginación.
- No presentar una vista demo como integración productiva.

## Documentación relacionada

- [Funcionalidad general](../../docs/readme/README_10_FUNCIONALIDAD_DEL_PRODUCTO.md)
- [Matriz de estado](../../docs/readme/README_09_MATRIZ_DE_ESTADO_FUNCIONAL.md)
