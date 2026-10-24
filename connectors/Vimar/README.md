# Vimar Universal Connector 0.4.0

El conector Vimar productivo vive dentro de Property Edge y **no requiere VimarHub como servicio**.

## Modo predeterminado

`direct_auto`

El Edge hace discovery LAN de solo lectura mediante:

- mDNS / anuncios HomeKit HAP (`_hap._tcp.local`) para candidatos VIEW;
- KNXnet/IP Search nativo sobre UDP 3671;
- SSDP/UPnP como señal complementaria;
- servicios mDNS Vimar/VIEW/By-me/KNX cuando estén anunciados.

El resultado se materializa primero como inventario técnico (`gateway`, `relay`, `switch`, etc.). Property no decide automáticamente que un relay sea una puerta o una luz.

## Bridges opcionales

- VimarHub externo: laboratorio/fallback, no dependencia obligatoria.
- Home Assistant: bridge opcional.

## Alcance de 0.4.0

0.4.0 integra en el Agent el **discovery y la adopción técnica de gateways**. La enumeración profunda de accesorios VIEW/By-me que requiera una sesión HomeKit emparejada o autenticación específica queda detrás de adapters dedicados; no se inventan credenciales ni se rompe un pairing existente.
