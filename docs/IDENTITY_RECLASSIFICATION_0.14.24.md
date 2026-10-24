# Property Hub 0.14.24 — Identidad, normalización y reclasificación

## Objetivo

Esta versión endurece la conciliación ZKBio → Property Hub para instalaciones donde ZKBio mezcla personas humanas y vehículos dentro del mismo catálogo de Personnel.

## Nombres de personas

Toda creación o edición de persona a través del repositorio operativo normaliza nombres y apellidos a formato título antes de persistirlos. La misma regla se aplica a adopciones desde ZKBio.

Ejemplos:

- `lUIS ALBERTO` → `Luis Alberto`
- `BANGO PEREZ` → `Bango Perez`

## Persona vs. vehículo

Un Vehicle es una identidad propia. No se identifica exclusivamente por placa y puede existir sin placa conocida cuando su autenticador real es una etiqueta RFID.

La adopción ZKBio permite seleccionar identidades y decidir:

- Clasificación automática.
- Forzar Persona.
- Forzar Vehículo/RFID.

En Premier, una identidad cuyos Access Levels son exclusivamente vehiculares puede sugerirse como Vehicle, pero el administrador puede corregir la clasificación antes de adoptarla.

## Reclasificación segura

Una Persona creada por error puede reclasificarse como Vehicle desde su edición o mediante selección múltiple. La operación es deliberadamente conservadora y se detiene cuando la identidad ya tiene elementos que podrían corresponder a una persona humana real, como cuenta de Property App, identidad global, credenciales, documentos, reservas, visitas, invitaciones o auditoría como actor.

Cuando la conversión es segura se conservan, cuando existen:

- PIN / binding externo ZKBio.
- RFID / `cardNo`.
- Placa.
- Access Levels ZK observados.
- Unidad Property seleccionada.

RFID y placa se validan para no crear duplicados silenciosos.

## Relaciones persona ↔ unidad

La pantalla de Personas permite seleccionar relaciones concretas y cambiar en masa su tipo, por ejemplo:

- Inquilino → Residente.
- Residente → Propietario.
- Familiar → Autorizado.

La acción cambia únicamente las relaciones seleccionadas. Si no se selecciona un perfil explícito, Property intenta usar el perfil activo cuyo `relationship_hint` corresponde al nuevo tipo de relación.

## Dirección de sincronización

Esta versión mejora la adopción ZKBio → Property Hub. La sincronización inversa Property Hub → ZKBio existe en flujos de laboratorio/operación anteriores, pero su comportamiento de actualización masiva de personas/vehículos no se considera validado por este cambio y debe probarse separadamente.
