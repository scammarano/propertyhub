# Property Hub 0.11.4 — Edge Pairing Parent Auto-detect Fix

## Incidente reproducido

Un Edge Agent 0.11.3 podía recibir `HTTP 409 wrong_property_node` aun apuntando a la URL correcta. El Node respondía `instance_code=PREMIER-LM`, pero el Agent enviaba en el preflight un `PARENT_PROPERTY_CODE` histórico almacenado localmente.

## Corrección

El flujo normal queda estrictamente en dos datos: **URL pública del Property Node + PHEDGE**. El preflight verifica el rol del servidor y obtiene el código canónico del Node. Ese código se usa luego en el registro y se persiste localmente.

El PHEDGE se consulta únicamente después de superar el preflight, por lo que un intento fallido por `wrong_property_node` no lo consume.

No hay migraciones SQL nuevas.
