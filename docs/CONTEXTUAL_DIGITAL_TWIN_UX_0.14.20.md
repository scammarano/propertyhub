# Property Hub 0.14.20 — Contextual Digital Twin UX

## Objetivo

Property Hub deja de obligar al administrador a recordar en qué módulo se configura cada relación. La misma entidad puede alcanzarse y vincularse desde varios puntos de vista, conservando una sola relación de datos.

## Mapa jerárquico

`Gestión de la propiedad → Estructura → Mapa jerárquico`

La vista mezcla, según permisos del usuario:

- edificio / conjunto / torre;
- pisos y niveles;
- apartamentos, oficinas y otras unidades;
- áreas y espacios;
- cámaras;
- puertas / puntos de acceso;
- activos IoT / dispositivos;
- personas y vehículos asociados a la unidad;
- ascensores que tienen parada en el nivel.

Cada icono es navegable. Los recursos sin ubicación se muestran por separado para que puedan corregirse.

## Navegación bidireccional

- Área → cámaras / accesos / IoT.
- Cámara → área.
- Acceso → área.
- Dispositivo → área / propiedad.
- Estructura → áreas / recursos / personas / vehículos / ascensores.
- Persona → unidades → permisos de cámaras/puertas.

Vincular desde un área modifica el mismo `area_id` utilizado por el recurso; no se crean relaciones paralelas.

## Ficha contextual de Área

Desde una área se puede:

- ver cámaras, accesos e IoT ya vinculados;
- abrir cada recurso;
- crear un recurso preubicado en esa área;
- vincular o desvincular recursos existentes;
- ver personas y vehículos de la unidad/elemento estructural al que pertenece el área cuando el rol lo permite.

## Persona y permisos

Editar Persona muestra todas sus relaciones Persona ↔ Unidad. Para cada relación se puede:

- editar;
- desvincular;
- abrir `Cámaras y puertas`;
- ir a Perfiles de acceso.

Los permisos detallados permiten seleccionar cámaras y puertas exactas y horarios. Son por relación, no globales a la persona.

## Ascensores

El diseñador sólo crea el borrador. Cada ascensor puede editarse después con:

- nombre y código;
- tipo y uso;
- torre(s);
- conjunto exacto de paradas;
- control de acceso sí/no;
- proveedor previsto;
- notas.

Esto permite, por ejemplo, tres ascensores de torre completa y dos ascensores Sótano→PB dentro del mismo edificio.

## Seguridad por rol

Ver una entidad no implica poder modificarla o eliminarla.

- Las secciones contextuales se ocultan si falta el permiso `*.view` correspondiente.
- ADMIN puede administrar relaciones y configuración funcional.
- La eliminación de recursos técnicos sensibles usa `technical.delete`, reservada por defecto a SUPERADMIN.

Así un administrador puede ubicar una cámara o relacionarla con un área sin tener permiso para eliminarla accidentalmente.

## Modales

Todos los modales administrativos respetan la altura de la pantalla:

- body con scroll;
- footer visible;
- Guardar/Cancelar siempre alcanzables;
- comportamiento responsive para ventanas bajas y móviles.
