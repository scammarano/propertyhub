# Arquitectura de doble instalación — Property Hub 0.5.0

## Property Hub Central

Fuente oficial y laboratorio del producto:

- Instalaciones registradas.
- Versiones del Core, UI, módulos, conectores y APIs.
- Tokens, canales, paquetes y manifiestos firmados.
- Heartbeat, métricas agregadas y salud.
- Catálogo de APIs de fabricantes.

No opera residentes, visitantes, reservas ni accesos de los clientes.

## Property Node

Instalación independiente de una urbanización, edificio, oficinas, centro comercial o complejo mixto:

- Base y configuración propias.
- Personas, unidades, visitantes y reservas.
- Cámaras, accesos, intercom, red e IoT.
- Credenciales técnicas locales.
- Operación local cuando el central no esté disponible.

## Topología local

Cada nodo usa un servidor principal. Las garitas y zonas se conectan directamente por fibra, UTP, Wi-Fi, radio, VPN, Internet o red celular. No se requiere una computadora adicional por garita.

Los gateways que existan son los propios de tecnologías específicas, por ejemplo TTLock o LoRaWAN.

## Comunicación con el central

```text
Property Hub Central
        ↑ registro, heartbeat, catálogo y versiones
        ↓
Property Node
        ↓
Plataformas, controladores, cámaras, gateways y dispositivos
```

El nodo inicia la comunicación saliente. El central no se conecta directamente a los equipos internos del cliente.

## Separación obligatoria

Cada instalación debe utilizar carpeta, base, UUID, APP_KEY, secreto de API y respaldos independientes.
