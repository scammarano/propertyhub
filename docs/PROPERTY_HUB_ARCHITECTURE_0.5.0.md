# Arquitectura Property Hub 0.5.0

## Tres conceptos distintos

### Property Hub Central

Fuente oficial del producto. Registra instalaciones, versiones, conectores, APIs, tokens, salud y métricas agregadas.

### Property Node

Instalación operativa de una urbanización, edificio, oficina, comercio o complejo. Contiene el servidor principal, la base local y la operación diaria.

### Dispositivos y gateways tecnológicos

Cámaras, NVR, controladores, intercomunicadores, relés, AP, cerraduras, sensores y gateways se conectan al servidor principal. Un gateway TTLock o LoRaWAN pertenece a su tecnología y no constituye otro Property Node.

## Sin Property Edge

Property Hub 0.5.0 no exige computadoras ni agentes intermedios por garita. La topología predeterminada es:

```text
Garitas / zonas / dispositivos
          ↓
Fibra, UTP, Wi-Fi, VPN, Internet, red celular o LoRaWAN
          ↓
Servidor principal de la instalación
          ↓
Property Hub + plataformas locales + conectores
```

Los equipos autónomos cloud pueden comunicarse mediante la API del fabricante, sin pasar físicamente por la LAN principal, pero siguen perteneciendo lógicamente a la instalación.

## Ejemplo: Urbanización Terraza del Ávila

- Sede principal con Property Hub, ZKBioSecurity, NVR, UniFi y servicios locales.
- Garita principal conectada por fibra.
- Garita norte conectada por fibra.
- Garita sur conectada por UTP, con router LTE de respaldo.
- Casa club con TTLock, cámaras y Wi-Fi de invitados.
- Parque y perímetro con sensores LoRaWAN.
- Cuarto de bombas con nivel de tanque, presión y fuga.

## LoRaWAN

LoRaWAN se usa para sensores y comandos de bajo volumen. El flujo previsto es:

```text
Sensor LoRaWAN
    ↓ radio
Gateway LoRaWAN
    ↓ Ethernet, Wi-Fi, fibra o red celular
Servidor LoRaWAN / MQTT
    ↓
Conector Property Hub
```

No se usa para video, audio, SIP o tráfico de gran ancho de banda.

## Privacidad

El nodo conserva la información personal y las credenciales técnicas. El central recibe solo metadatos, conteos, versiones, salud e inventario resumido.
