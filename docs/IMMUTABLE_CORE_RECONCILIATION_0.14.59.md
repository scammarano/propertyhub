# Property Hub 0.14.59 · Reconciliación segura del Core inmutable

## Incidente que corrige

Central podía tener `property-hub-core X` ya publicado con un SHA-256 canónico y, posteriormente, encontrar en `resources/releases/` otro ZIP local válido con la misma versión pero bytes distintos. La sincronización trataba esa reconciliación como si fuera una publicación nueva y lanzaba una excepción de inmutabilidad, bloqueando la página/manifest.

## Regla

La base de datos + el paquete almacenado del release publicado son la referencia canónica e inmutable. Una sincronización nunca reemplaza esos bytes.

Si el release ya existe y su paquete canónico está íntegro:

1. se conserva el SHA-256 publicado;
2. se promueve al canal solicitado si corresponde;
3. se registra `source_conflict=true` cuando el ZIP local de la misma versión difiere;
4. la sincronización continúa y no bloquea el dashboard ni la ruta progresiva;
5. la divergencia se muestra como advertencia y se normaliza instalando una versión posterior.

Si el paquete canónico falta, sólo puede restaurarse desde una fuente con el mismo SHA-256. Si la fuente difiere, Property Hub se niega a reconstruirlo para no violar la inmutabilidad.

## Diagnóstico

`RepositorySynchronizer::status()` diferencia ahora:

- `ok`: el repositorio canónico está íntegro y puede distribuirse;
- `fully_in_sync`: además, el ZIP local coincide byte por byte con el canónico;
- `source_conflict`: el repositorio está operativo, pero la fuente local de esa misma versión tiene otro SHA-256.

## Migraciones

0.14.59 no agrega migraciones SQL.
