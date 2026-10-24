# Property Hub 0.14.28 — Adopción completa ZKBio y conciliación vehicular

## Problema observado

En la conciliación de una unidad como `Apt 1A`, Property podía detectar identidades cuyo conjunto de Access Levels era exclusivamente `VEHICULOS*` y mostrarlas como **Sugerido: vehículo**, pero la acción rápida de la fila ejecutaba `adoptDepartmentPeople()` y por diseño importaba sólo humanos. El resultado era confuso: el administrador veía vehículos sugeridos pero la acción rápida no los creaba.

Además, `peopleByMappedProperty()` ocultaba el mapping de Persona cuando la identidad era clasificada como vehículo (y viceversa). Eso impedía representar correctamente casos históricos donde una identidad ya había sido conciliada con el tipo equivocado y debía pasar por la reclasificación segura.

## Corrección

- Nueva acción de unidad **Adoptar todo**.
- Usa todos los PIN encontrados en el departamento vinculado y ejecuta `adoptSelectedSubjects(..., 'auto', ...)`.
- Persona → crea/concilia Persona y membresía.
- Vehículo/RFID → crea/concilia Vehicle.
- Si el PIN vehicular ya apunta a una Persona Property, se utiliza `reclassifyPersonToVehicle()` y se mantienen sus bloqueos conservadores.
- Los mappings reales de persona y vehículo se exponen simultáneamente a la UI; no se enmascaran según la sugerencia automática.
- La vista indica claramente `En Property como persona`, `Vehículo Property`, `En Property como vehículo`, etc.

## Compatibilidad

No se añade migración. No se modifica ninguna migración histórica. 0.14.28 es acumulativa sobre 0.14.27 y conserva el hotfix `bh_account_invitations`.
