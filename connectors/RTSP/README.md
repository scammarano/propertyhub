# Conector RTSP Video Gateway

## Estado

- Versión del conector: **0.1.0**
- Ciclo de vida: **planned**
- Dominios: video

En Property Hub 0.6.0 este directorio contiene el contrato, manifiesto y documentación del conector. **No ejecuta todavía operaciones sobre hardware real.**

## Propósito

Representa el gateway de video que recibe streams internos y entrega sesiones compatibles con navegador. No debe entregar URLs RTSP ni secretos al cliente.

## Modos de integración previstos

- RTSP input
- WebRTC output
- HLS output

## Capacidades declaradas

- `live_stream`
- `substream`
- `session_control`

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
