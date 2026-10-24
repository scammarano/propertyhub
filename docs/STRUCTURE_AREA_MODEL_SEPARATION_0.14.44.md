# Property Hub 0.14.44 — Structure / Area Model Separation

## Objetivo

0.14.44 separa explícitamente tres capas que antes se mezclaban en el gemelo digital:

1. **Estructura física / organizativa = DÓNDE** está algo.
2. **Área / espacio funcional = QUÉ espacio o uso existe** en esa ubicación.
3. **Recurso = QUÉ equipo o capacidad** está ubicado allí (cámara, puerta, IoT, activo técnico, etc.).

La meta es que una propiedad compleja no tenga que forzarse al molde `edificio → torre → piso → área`, ni crear áreas ficticias únicamente para ordenar o para poder ubicar un recurso.

## Estructura física y contenedores organizativos

Los registros de `bh_properties` pueden actuar como:

- **Ubicación física**: edificio, torre, nivel, sótano, apartamento, estacionamiento, parcela, etc.
- **Contenedor organizativo**: existe sólo para ordenar una rama del árbol.

Cada elemento permite definir, mediante `metadata_json`:

- `structure_role`: `physical` u `organizer`.
- `accepts_resources`: si cámaras, puertas, áreas y activos pueden ubicarse directamente allí.
- `operations_visible`: si aparece en navegación administrativa/operativa.
- `resident_visible`: si puede formar parte de la presentación al usuario final cuando corresponda.

Un contenedor que no admite recursos queda fuera de los selectores de ubicación de cámaras, accesos y activos técnicos. Si ya contiene recursos directos, Property Hub exige reubicarlos antes de desactivar esa capacidad.

## Áreas y espacios: ámbito y uso ya no son la misma cosa

`bh_areas.area_type` se conserva por compatibilidad histórica, pero 0.14.44 agrega dos dimensiones explícitas:

- `scope_type`: `common`, `private`, `technical`, `service`, `restricted`, `public`.
- `usage_type`: `general`, `lobby`, `circulation`, `parking`, `office`, `commercial`, `technical`, `security`, `recreation`, `amenity`, `reservable`, `storage`, `residential`, `other`.

Ejemplo correcto:

- **Lobby Ascensores E1**
  - Ubicación estructural: `Elevado 1`
  - Ámbito: `Común`
  - Uso: `Lobby` o `Circulación`

Por tanto, “Común” y “Circulación” ya no compiten como si fueran tipos excluyentes.

## Recursos con ubicación en dos capas

Una cámara, puerta o activo técnico puede quedar:

- directamente en una ubicación estructural; o
- en una ubicación estructural + un área específica perteneciente a esa ubicación.

Ejemplos:

- Cámara Sótano 3 → `Sótano S3`, sin área específica.
- Cámara Lobby → `Elevado 1` + `Lobby Ascensores E1`.

La selección de Área se filtra por la ubicación estructural elegida. Esto elimina listas interminables y evita seleccionar accidentalmente un espacio de otro nivel.

## Cámaras

Se agrega `bh_cameras.property_id` para que una cámara no dependa obligatoriamente de `area_id` para conocer su ubicación estructural.

Las cámaras existentes con área heredan automáticamente `property_id` desde dicha área.

## Invariantes de ubicación

Cuando un Área se mueve a otra ubicación estructural, los recursos que dependen de esa área mantienen la coherencia y actualizan su `property_id`:

- cámaras;
- puntos de acceso;
- activos técnicos.

Los formularios rechazan combinaciones `estructura + área` que no pertenecen al mismo lugar.

## ZKBio e inventario técnico

La adopción/ubicación de puertas ZKBio también presenta por separado:

- ubicación estructural;
- área opcional;
- ámbito del área nueva;
- uso del área nueva.

El inventario técnico aplica el mismo patrón `DÓNDE + área opcional`.

## Compatibilidad

- Se preserva `area_type` y se recalcula desde ámbito/uso para rutas antiguas.
- Las migraciones `0001` a `0038` no se modifican.
- Nueva migración: `0039_structure_area_model_separation.sql`.
- No se fusionan automáticamente áreas históricas con nombres repetidos; requieren decisión explícita del operador para no mover recursos al registro equivocado.
