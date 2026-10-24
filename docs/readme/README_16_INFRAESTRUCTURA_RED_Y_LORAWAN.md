# Infraestructura, redes y LoRaWAN

## Modelo de urbanización distribuida

Una urbanización puede tener una sede principal con el servidor, varias garitas, parques, casa club y cuartos técnicos. Todos pertenecen a un solo Property Node.

```text
Servidor principal
├── Garita principal · fibra
├── Garita norte · fibra
├── Garita sur · UTP + respaldo LTE
├── Casa club · Wi‑Fi/Internet/TTLock
├── Parque · LoRaWAN
└── Equipos cloud · Internet
```

## Sin Property Edge obligatorio

No se instala una computadora adicional por garita. Los equipos se conectan al servidor o a sus plataformas por:

- Fibra.
- UTP/Ethernet.
- Wi‑Fi.
- VPN.
- Radioenlace.
- Internet directo.
- Red celular.
- API cloud.
- LoRaWAN/MQTT.

## Ubicaciones y enlaces

Cada equipo debe indicar:

- Ubicación física.
- Área y sede/garita.
- Segmento o red.
- Enlace principal y respaldo.
- Conector responsable.
- Dependencias y ruta de comunicación.
- Estado y último heartbeat.

## Dispositivos aislados

Un dispositivo aislado puede integrarse por Wi‑Fi, Internet o API cloud. No necesita pasar físicamente por el servidor principal, aunque lógicamente pertenezca a la instalación.

## LoRaWAN

Se utiliza para telemetría, sensores, alarmas y comandos pequeños:

- Nivel de tanques.
- Presión y fugas.
- Estado de bombas.
- Botones de pánico.
- Iluminación/riego.
- Sensores perimetrales.
- Batería y estado de gabinetes.

No se usa para video, audio, SIP, fotografías o tráfico de gran ancho de banda.

## Gateway LoRaWAN

Es un componente propio de la red de radio, no un servidor adicional de Property Hub. Recibe dispositivos y reenvía mensajes al servidor LoRaWAN/MQTT por IP.

## Resiliencia

- Controladoras y NVR deben continuar su función local si falla Internet.
- Enlaces críticos pueden tener LTE o VPN de respaldo.
- El nodo debe registrar pérdida/restablecimiento y reconciliar eventos.
- Las automatizaciones críticas requieren alternativa manual.
