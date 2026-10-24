# Ruta de desarrollo

## Situación actual

Property Hub 0.6.1 incorpora el primer núcleo operativo persistente del Property Node, además del instalador dual, Central, registro de nodos y contratos de conectores. Todavía no opera físicamente controladores ni cámaras, pero ya permite cargar y administrar la estructura, personas e infraestructura real del inmueble.

## Prioridad 1 — Persistencia funcional y CRUD

- Reemplazar `DemoRepository` por repositorios de base.
- CRUD completo de estructura, personas, membresías y documentos.
- Visitantes, invitaciones, entradas/salidas y tarjetas provisionales.
- Áreas, reglas, reservas y calendario persistente.
- Cartelera, notificaciones y emergencias.
- Grids server-side y permisos granulares.

## Prioridad 2 — Instalación y actualización productivas

- Heartbeat programado y reintentos.
- Descarga/staging/aplicación automática.
- Respaldo y rollback.
- Health checks.
- Rotación de claves y tokens.
- Compatibilidad de APIs.

## Prioridad 3 — Primeras integraciones reales

1. ZKTeco de lectura y conciliación.
2. ZKTeco de escritura controlada.
3. ONVIF/RTSP y gateway WebRTC/HLS.
4. TTLock.
5. UniFi Access/Protect/Network.
6. Grandstream.
7. Hikvision y Dahua.
8. Vimar, Sonoff y relés.
9. LoRaWAN/MQTT.

## Prioridad 4 — Identidad multiinstalación

- Servicio global de identidad.
- Descubrimiento de propiedades.
- Tokens limitados por nodo.
- Notificaciones unificadas.
- Cambio real entre instalaciones.
- Apps personalizadas basadas en el mismo producto.

## Prioridad 5 — Seguridad y operación

- Cifrado de secretos.
- Segundo factor.
- Rate limiting.
- Jobs/colas.
- Backups automáticos.
- Auditoría completa.
- Soporte remoto aprobado.

## Prioridad 6 — Extensiones

- Pagos y depósitos de reservas.
- Correspondencia y paquetería.
- Incidencias/mantenimiento.
- Vouchers automáticos.
- Perifonía y automatizaciones.
- Reportes y analítica.
- Aplicaciones nativas si la PWA no cubre la necesidad.

Cada prioridad debe cerrarse con documentación, migraciones, pruebas y una entrega versionada.
