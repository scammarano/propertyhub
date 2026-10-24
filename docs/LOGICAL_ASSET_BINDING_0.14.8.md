# Property Hub 0.14.8 — Activos lógicos y mapeo físico

## Principio

Property Hub describe **qué existe en la propiedad**. Property Edge y los conectores descubren **qué hardware está realmente instalado**. Ningún fabricante define la estructura funcional del edificio.

## Dos flujos válidos

1. **Diseñar → descubrir → mapear**: se crea Lobby → Puerta Principal → Cámara Lobby antes de instalar equipos y luego se vinculan a ZKBio, Vimar, ONVIF, etc.
2. **Descubrir → adoptar → ubicar**: en una instalación existente se aprende el inventario físico y se enlaza o crea el activo lógico correspondiente.

## Punto de acceso

Un punto de acceso Property puede tener varias piezas físicas:

- **Autenticador**: QR, tarjeta, NFC, PIN, rostro, huella, LPR, Property App, intercom.
- **Actuador**: ZKBio Door, Vimar relay, Sonoff, KNX, LoRa, Hikvision, Dahua.

El componente que valida una credencial no tiene por qué ser el mismo que abre físicamente la puerta.

Ejemplo:

`Lector QR IP → Property Edge → Policy Engine → Vimar Relay 7`

Otro:

`QR ZKTeco → ZKBio → inBIO → Relay ZK`

## Capacidades

Las políticas deben compararse con capacidades efectivas. Una puerta puede admitir apertura desde app y no QR; otra puede admitir QR para visitantes pero no rostro. Las capacidades pueden provenir de descubrimiento y también ser corregidas por el integrador.

## UX

El usuario final ve siempre `Puerta Principal`, `Portón`, `Cámara Lobby`, etc. El detalle `ZKBio Door 1`, `Vimar Relay 7` o `Hikvision Channel 3` queda en la capa técnica de mapeo.
