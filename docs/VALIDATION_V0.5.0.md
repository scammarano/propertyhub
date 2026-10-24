# Validación Property Hub 0.5.0

## Validaciones completadas

- 114 archivos PHP sin errores de sintaxis.
- 24 archivos JSON y manifiestos válidos.
- 49 referencias de vistas verificadas, sin archivos faltantes.
- 7 migraciones procesadas correctamente por el separador SQL.
- Checksums de las migraciones 0001–0005 idénticos a Property Hub 0.4.0.
- Migración 0006 registrada para topología territorial, conectividad y LoRaWAN.
- Migración 0007 registrada para tipos de propiedad en el portafolio central.
- Seeder de catálogo: 12 conectores y 14 familias de API.
- Seeder de Property Node: 8 conectores, 9 ubicaciones, 7 enlaces, 11 equipos, 2 gateways LoRaWAN y 6 dispositivos IoT.
- Seeder central: 4 instalaciones demo y 30 registros de conectores.
- Referencias directas al servidor principal; no existe módulo Property Edge.
- Ausencia de `.env`, `installed.lock`, claves PEM/P8 y otros secretos en el paquete.
- Integridad del ZIP verificada y checksum SHA-256 generado.

## Limitaciones del entorno de construcción

No se dispone de un servidor MariaDB ni de dispositivos físicos en este entorno. La instalación y migración deben probarse en XAMPP antes de utilizar el paquete en producción. Tampoco se verificaron todavía APIs reales de ZKTeco, TTLock, Grandstream, UniFi, Hikvision, Dahua o LoRaWAN.
