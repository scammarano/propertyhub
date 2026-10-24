# Property Hub 0.14.9 — Multi-Path Access + Unified Audit

Property Hub mantiene la puerta como activo lógico y permite varias rutas físicas de apertura simultáneamente.

Ejemplo para una misma puerta:

1. ZKBio API → inBIO → salida de puerta.
2. Vimar Relay → entrada REX/Button de inBIO (depende de la controladora).
3. Vimar Relay → contacto en paralelo/directo sobre cerradura (ruta independiente de inBIO).

La interfaz del residente sigue mostrando una sola **Puerta Principal**. La ruta física es detalle técnico.

## Comando vs. evento observado

Property Hub separa:

- **Comando:** Property decidió/ordenó abrir.
- **Evento:** ZKBio, sensor, lector u otro conector informó lo que ocurrió físicamente.

Esto permite importar por API eventos de huella, tarjeta, QR, apertura remota, etc., aunque Property no haya iniciado la acción. Los eventos pueden correlacionarse con comandos Property para evitar duplicados.

## Auditoría unificada

Una apertura por huella ZK y una apertura por App→Vimar terminan en el mismo historial lógico del punto de acceso, conservando origen, método, ejecutor y resultado.
