# Plan de prueba Property Hub 0.5.0

## Prueba 1 — Property Hub Central

1. Instalar una copia limpia como Property Hub Central.
2. Activar demo.
3. Confirmar cuatro instalaciones en el portafolio.
4. Abrir Urbanización Terraza del Ávila.
5. Verificar métricas de garitas, enlaces, LoRaWAN y conectores.
6. Crear un token limitado a `TERRAZA-AVILA`.

## Prueba 2 — Property Node

1. Instalar otra copia como Property Node.
2. Nombre: Urbanización Terraza del Ávila.
3. Código: `TERRAZA-AVILA`.
4. Tipo: Urbanización.
5. Activar demo.
6. Revisar:
   - Perfil de la instalación.
   - Sedes, garitas y conectividad.
   - Inventario tecnológico.
   - LoRaWAN e IoT.
   - Personas, visitantes, reservas y cartelera.
   - Resident App.
7. Enviar heartbeat al central.
8. Confirmar que el central reciba conteos e infraestructura, pero no datos personales.

## Prueba 3 — Sin Property Edge

Confirmar que:

- No exista un módulo o requisito de Property Edge.
- Las garitas figuren como ubicaciones, no como servidores.
- Los equipos muestren conectividad directa al servidor principal.
- Los gateways TTLock y LoRaWAN estén clasificados como dispositivos tecnológicos.
