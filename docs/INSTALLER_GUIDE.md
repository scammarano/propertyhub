# Guía del instalador Property Hub 0.5.0

## Modalidad 1 — Property Hub Central

Ruta sugerida:

```text
C:\xampp\htdocs\property-hub-central
```

URL:

```text
http://localhost/property-hub-central/install/
```

Use una base exclusiva, por ejemplo `property_hub_central`. Esta modalidad habilita portafolio, APIs, conectores, versiones, paquetes, tokens, salud y laboratorio.

## Modalidad 2 — Property Node

Ruta sugerida:

```text
C:\xampp\htdocs\property-terraza-avila
```

URL:

```text
http://localhost/property-terraza-avila/install/
```

Use una base independiente, por ejemplo `property_terrazas_avila`. Seleccione el tipo real de la instalación: urbanización, conjunto residencial, edificio, oficinas, centro comercial, campus, industrial o mixto.

## Arquitectura

Cada Property Node tiene un servidor principal. Las garitas y equipos se conectan directamente por su red disponible. Property Hub no exige computadoras adicionales por garita.

## Después de instalar

- Guarde `.env` y `storage/` fuera de los paquetes de actualización.
- En el nodo, configure el vínculo con Property Hub Central.
- Envíe un heartbeat manual.
- Revise **Sedes, garitas y conectividad** y **LoRaWAN e IoT**.
