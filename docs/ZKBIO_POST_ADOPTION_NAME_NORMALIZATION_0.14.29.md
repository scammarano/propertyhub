# ZKBio Post-Adoption Name Normalization — 0.14.29

## Objetivo

Permitir que la conciliación/adopción de identidades ZKBio deje los nombres de las **Personas Property** en el formato estándar del sistema, incluso cuando la persona ya existía antes de la adopción.

El estándar es: cada palabra con inicial mayúscula y el resto en minúscula. Ejemplo: `JUAN CARLOS PÉREZ GÓMEZ` pasa a `Juan Carlos Pérez Gómez`. El fallback del Core también conserva correctamente acentos y `Ñ` cuando PHP no dispone de `mbstring`.

## Flujo de adopción

En **ZKBio · Integración operativa**:

- **Adoptar todo** incluye el check `Normalizar nombres`, activado por defecto.
- **Adoptar seleccionados** incluye `Normalizar nombres al finalizar`, también activado por defecto.
- La normalización se ejecuta únicamente sobre las identidades procesadas como **Persona**. Los vehículos/RFID no se renombran por esta rutina.
- Si la persona ya estaba conciliada, también puede ser normalizada; no es necesario que haya sido creada en esa misma ejecución.

## Personas ya adoptadas

Cada unidad Property vinculada a un departamento ZKBio presenta la acción **Nombres**. Esta acción normaliza las personas Property de esa unidad que ya tienen un mapping de Persona con ZKBio.

Esto permite corregir adopciones realizadas en versiones anteriores sin repetir la importación.

## Alcance y seguridad

La normalización:

- actualiza `bh_people.first_name`;
- actualiza `bh_people.last_name`;
- recompone `bh_people.display_name`;
- actualiza `bh_external_mappings.external_name` como etiqueta local coherente;
- registra auditoría `person.name_normalized` cuando existe un cambio real.

La normalización **no**:

- escribe nombres de regreso en ZKBio;
- modifica `bh_external_objects`, que conserva el dato observado del fabricante;
- modifica vehículos, RFID, placas, credenciales, PIN, membresías o perfiles de acceso;
- crea migraciones nuevas.

## Compatibilidad

0.14.29 es acumulativa sobre 0.14.28 y conserva el flujo `Adoptar todo` de personas + vehículos/RFID y la reclasificación segura Persona → Vehículo incorporada en 0.14.28.
