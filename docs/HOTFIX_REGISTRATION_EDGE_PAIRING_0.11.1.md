# Property Hub 0.11.1 — Registration & Edge Pairing Fix

## Incidentes reproducidos

1. Un Property Node ya registrado recibe un `BHR-RELINK-*`. Central acepta la rotación, pero el Node intentaba borrar `bh_update_sources` antes de recrearlo. Si existía historial en `bh_update_jobs`, MySQL rechazaba el `DELETE` por `fk_bh_update_job_source` (SQLSTATE 23000 / error 1451).
2. Un mismo token BHR de un solo uso podía terminar siendo probado manualmente tanto en el Node como en el Agent. El primer uso lo consume; el segundo recibe `invalid_or_expired_registration_token`.
3. Durante una prueba Edge, la URL de Property Node podía confundirse con la URL de Central y el PHEDGE se enviaba al host incorrecto.

## Correcciones

- `NodeRegistrationService` actualiza la fuente central existente en el mismo `source_id`; solo inserta si no existe.
- Central ofrece desde la ficha de un Property Node un generador de BHR inicial exclusivo para el Edge Agent.
- `EdgeNodeClient` ejecuta un preflight contra `api/edge/v1/ping.php` antes del registro con PHEDGE.
- El ping confirma `instance_role=building_node` y el código del Property Node padre.
- La consola Edge muestra de forma explícita que la URL del Node y la URL de Central son diferentes.

## Contrato de credenciales

- `BHR-RELINK-*`: reconexión de una instalación ya registrada en Central. No se comparte.
- `BHR-*` limitado a `PREMIER-LM-EDGE-01`: registro inicial de ese Edge Agent en Central.
- `PHEDGE-*`: emparejamiento Edge Agent → Property Node. Se genera en el Property Node, no en Central.

## Flujo Premier

1. `PREMIER-LM` se registra/reconecta con Central usando su propio BHR/BHR-RELINK.
2. Central → ficha `PREMIER-LM` → **Registrar un Edge Agent en Central** → genera BHR para `PREMIER-LM-EDGE-01`.
3. El Agent usa ese BHR contra `https://2cam.services/property-hub/public`.
4. `PREMIER-LM` → Property Edge Agents → genera PHEDGE para `PREMIER-LM-EDGE-01`.
5. El Agent usa PHEDGE contra `https://2cam.services/property-premier/public`.
