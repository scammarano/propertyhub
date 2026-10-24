# Property Hub 0.14.21 — ZKBio Reconciliation / Profile Policy Completion

## Objetivo

Cerrar tres huecos operativos detectados durante las pruebas reales de Premier:

1. La prueba administrativa de apertura ZKBio no debe depender de un permiso recién agregado ni del perfil residente de la persona.
2. Debe existir una pantalla clara para vincular las unidades/apartamentos Property con los departamentos ZKBio y, al hacerlo, ver inmediatamente las personas que ZK ya tiene asociadas al departamento.
3. Los permisos normales de cámaras y accesos deben provenir de Perfiles de Acceso y alcances jerárquicos, no de asignaciones manuales persona por persona.

## Conciliación ZKBio

En **Infraestructura y tecnología → ZKBio API Lab** aparece una matriz `Unidad Property ↔ Departamento ZKBio`.

- Se sugiieren coincidencias exactas por código/nombre (por ejemplo `P6A ↔ P6A`).
- La vinculación es explícita y reversible.
- Una vez vinculada la unidad, la misma pantalla muestra las personas ZK cuyo `deptCode` pertenece a ese departamento.
- `Adoptar usuarios` crea o concilia personas Property y sus relaciones con la unidad, sin duplicar registros ya mapeados.
- En modo **Inferir por niveles ZK**, nombres como PROPIETARIOS/INQUILINOS/CHOFERES/MANTENIMIENTO/SEGURIDAD se utilizan como sugerencia de relación; Property mantiene su propio perfil como autoridad funcional.

## Perfiles de acceso

La relación Persona ↔ Unidad conserva un `Access Profile`. El perfil es la fuente normal de:

- cámaras permitidas;
- accesos permitidos;
- horarios;
- alcance común / toda la propiedad / su torre / su piso / su unidad;
- invitaciones, reservas y otras capacidades.

Los grants individuales `membership_camera_grants` y `membership_access_grants` se mantienen por compatibilidad, pero la UI los presenta como **Excepciones individuales avanzadas**, no como el flujo normal.

## Evaluación jerárquica

La app residente resuelve el linaje de la unidad activa y compara el recurso con el alcance del perfil:

- `common`: recursos comunes;
- `tower`: sólo recursos marcados para la torre de esa unidad;
- `floor`: sólo recursos marcados para su piso;
- `unit`: sólo recursos de esa unidad;
- `property`: recursos de la propiedad/edificio;
- `all`: todos los alcances.

Las excepciones individuales siguen sumándose cuando exista una necesidad puntual.

## Apertura administrativa

`Abrir prueba` en el laboratorio ZKBio requiere `access.operate`, un permiso operativo ya existente en el perfil ADMIN, y no `access.manage`. La prueba técnica no depende de que el administrador sea además un residente con unidad/perfil.
