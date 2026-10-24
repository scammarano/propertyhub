# Property Hub 0.14.32 — Bulk Adoption / UI Cache / Edge Boot Reliability

## Adopción ZKBio
Se conserva la adopción granular por unidad y se restaura una acción global **Adoptar todo lo encontrado**. Puede vincular previamente coincidencias inequívocas y procesa personas y vehículos/RFID con normalización opcional de nombres.

## Caché de interfaz
`asset()` agrega la versión de Property Hub como query string a assets locales. Esto invalida CSS/JS cacheados por navegadores o Service Workers de versiones anteriores. El Service Worker también cambia su namespace a 0.14.32.

## Encabezados
El layout admin suprime automáticamente su encabezado cuando la vista ya comienza con un h1–h6 cuyo texto coincide con el título de la ruta. Se evita el título doble sin eliminar acciones locales.

## Edge en Windows
Apache y MariaDB/XAMPP deben iniciar como servicios. El worker de Property Hub sigue siendo una tarea ONSTART separada; desde 0.14.32 el instalador aplica una demora de 45 segundos. El worker es quien supervisa MediaMTX y los Quick Tunnels.
