# Conector LoRaWAN / IoT

## Estado

- Versión del conector: **0.1.0**
- Ciclo de vida: **planned**
- Dominios: automation, iot

En Property Hub 0.6.0 este directorio contiene el contrato, manifiesto y documentación del conector. **No ejecuta todavía operaciones sobre hardware real.**

## Propósito

No requiere Property Edge. Usa gateways de radio y un servidor LoRaWAN/MQTT. Está limitado a telemetría, alarmas y comandos de bajo volumen.

## Modos de integración previstos

- ChirpStack local
- The Things Stack
- MQTT
- Webhook
- Vendor Cloud

## Capacidades declaradas

- `networks`
- `gateways`
- `sensors`
- `telemetry`
- `battery_status`
- `alerts`
- `low_bandwidth_commands`
- `automation_rules`

## Requisitos antes de pasar a beta

1. Identificar modelo, firmware, plataforma y licencia.
2. Obtener documentación oficial y entorno de prueba.
3. Implementar autenticación y almacenamiento local seguro de secretos.
4. Implementar health check, timeout, reintentos e idempotencia.
5. Mapear IDs externos sin convertir el sistema del fabricante en la base principal.
6. Auditar operaciones sensibles.
7. Probar desconexión, credenciales inválidas y compatibilidad.
8. Publicar como componente independiente y probar primero en canal beta.

## Privacidad

Las credenciales y endpoints se configuran en el Property Node. Property Hub Central solo puede recibir versión, capacidades, cantidad de equipos y salud cuando la política local lo permita.

## Documentación relacionada

- [Conectores e integraciones](../../docs/readme/README_15_CONECTORES_E_INTEGRACIONES.md)
- [Seguridad y auditoría](../../docs/readme/README_17_SEGURIDAD_AUDITORIA_Y_RESPALDOS.md)
- [Matriz de estado](../../docs/readme/README_09_MATRIZ_DE_ESTADO_FUNCIONAL.md)
