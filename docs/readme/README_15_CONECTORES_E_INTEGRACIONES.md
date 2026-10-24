# Conectores e integraciones

## Qué es un conector

Un conector es un componente desacoplado que traduce capacidades de Property Hub a una API, SDK, protocolo o plataforma externa.

## Contrato mínimo

Un conector debe declarar:

- Código y versión.
- Core mínimo.
- Dominios: acceso, video, intercom, red, automatización o IoT.
- Modos de integración.
- Capacidades.
- Estado de ciclo de vida.
- Requisitos y secretos.
- Estrategia de salud, reintentos y auditoría.

## Capacidades, no supuestos

No todos los modelos de una marca hacen lo mismo. La interfaz debe consultar capacidades antes de mostrar:

- Crear persona.
- Emitir credencial.
- Abrir puerta.
- Ver grabación.
- Usar audio/PTZ.
- Generar PIN.
- Crear voucher.

## Integraciones previstas

### ZKTeco

Conector al servidor ZKBioSecurity. Los inBio, lectores, puertas, torniquetes y ascensores son activos administrados por ese servidor. Primera fase: lectura y conciliación; escritura controlada después de validar versión, licencia y API.

### Hikvision

ISAPI, HikCentral, ONVIF y RTSP para acceso, cámaras e intercom.

### Dahua

DSS, CGI, NetSDK, ONVIF y RTSP.

### TTLock

Cerraduras compatibles, gateways, PIN, eKeys, registros y vigencias.

### Grandstream

SIP, UCM, GDS y GSC para intercom, llamadas, grupos y perifonía.

### Ubiquiti UniFi

UniFi Access, Protect y Network: puertas, cámaras, AP, clientes, portal cautivo y vouchers.

### Vimar

Videoportero y automatización según modelo, ecosistema y disponibilidad real de API/SIP/relé.

### Sonoff/eWeLink y relés

Cloud/local, escenas, pulsos y estados, además de MQTT, Modbus, HTTP o Home Assistant para relés genéricos.

### ONVIF/RTSP

Descubrimiento, perfiles, eventos y video, con gateway de navegador futuro.

### LoRaWAN

ChirpStack, The Things Stack, MQTT, webhook o nube para sensores y comandos de bajo volumen.

## Secretos

Se configuran y almacenan localmente. Central recibe como máximo nombre, familia, versión, capacidades, cantidad de equipos y salud.

## Proceso para incorporar una marca

1. Identificar producto, modelo, firmware y plataforma.
2. Obtener documentación oficial/licencia.
3. Definir capacidades y limitaciones.
4. Crear manifiesto y README.
5. Implementar cliente sandbox.
6. Añadir mapeos y reconciliación.
7. Probar errores, desconexión y reintentos.
8. Auditar acciones.
9. Publicar beta por componente.
10. Promover a stable después de pruebas reales.

## Estado actual

Los conectores presentes son contratos `planned` o `research`; ninguno debe considerarse operativo sobre hardware real en 0.6.1.

## Tres niveles de integración

1. **Sistema o plataforma conectada:** endpoint real del conector.
2. **Equipo administrado:** activo informado o gestionado por la plataforma.
3. **Punto funcional:** puerta, cámara, torniquete, ascensor, zona de perifonía u otro elemento operativo.

En Premier, ZKBioSecurity pertenece al primer nivel y los inBio al segundo.
