# ZKBio API Lab — Property Hub 0.14.15

## Objetivo

Validar la API oficial de ZKBio CVSecurity desde la instalación real antes de activar sincronizaciones automáticas de escritura. El laboratorio permite comprobar qué devuelve cada instalación, comparar la respuesta con el inventario existente y construir posteriormente las reglas de conciliación Property ↔ ZKBio.

## Custodia del token

El `access_token` se configura **únicamente en Property Edge Agent** y se guarda dentro de `bh_connector_instances.configuration_encrypted` usando `EdgeSecretStore`.

El token:

- no se copia al Property Node;
- no se copia a Property Hub Central;
- no se envía al navegador después de guardarlo;
- no se registra en el historial del laboratorio;
- se inyecta del lado servidor en cada llamada a ZKBio.

El Node recibe solo telemetría no sensible: si ZKBio está configurado, estado, presencia de token, cantidad de dispositivos, última prueba y último error.

## Edge — ZKBio API Lab

Ruta visual:

`Integraciones locales → ZKBio API Lab`

Primero configure:

- URL del servidor ZKBio CVSecurity, por ejemplo `http://127.0.0.1:8098` cuando Edge comparte servidor con ZKBio;
- `access_token` generado desde ZKBio.

### Presets iniciales de lectura

- Dispositivos: `GET /api/device/accList`
- Puertas: `GET /api/v2/door/list`
- Lectores: `GET /api/v2/reader/list`
- Departamentos: `POST /api/department/getDepartmentList`
- Access Levels: `GET /api/v2/accLevel/list`
- Personas: `POST /api/v2/person/getPersonList`
- Transacciones: `GET /api/v2/transaction/list`

El laboratorio permite página/tamaño y los filtros relevantes de personas/transacciones.

## Aprendizaje de instalación

**Aprender instalación completa** mantiene el flujo de adopción ya definido:

1. leer dispositivos;
2. leer puertas;
3. leer lectores;
4. leer departamentos;
5. leer Access Levels;
6. leer personas;
7. derivar tarjetas principales/secundarias y placas presentes en la respuesta de personas;
8. guardar el inventario en `bh_external_objects`;
9. no crear ni modificar automáticamente puertas, personas o unidades lógicas de Property Hub.

Después se realizará conciliación/adopción/binding.

## Explorador manual

Permite probar rutas `/api/...` con GET o POST para endpoints del manual que todavía no tengan preset.

- El usuario escribe la ruta y query **sin** `access_token`.
- Property Edge agrega el token en servidor.
- POST manual requiere escribir `EJECUTAR`.
- Campos de token se redactan.
- Fotos/imágenes/Base64 grandes se omiten de la copia de respuesta almacenada para evitar inflar la base.

## Remote Open de laboratorio

El Edge incorpora una prueba separada de:

`POST /api/door/remoteOpenById`

Requiere:

- `doorId` exacto obtenido de la API;
- intervalo de 1 a 30 segundos;
- confirmación textual `ABRIR`.

Esta prueba debe utilizarse únicamente con una puerta conocida durante el laboratorio. En 0.14.15 **no se publica remote-open desde el Node**.

## Node → Edge → ZKBio

Ruta visual en Property Node:

`Infraestructura y tecnología → ZKBio API Lab`

El Node puede encolar solamente los siete presets de lectura definidos arriba.

Flujo:

```text
Property Node
  ↓ comando zkbio.read_preset
vínculo Edge autenticado / heartbeat
  ↓
Property Edge Agent
  ↓ token local cifrado
ZKBio CVSecurity LAN
  ↓
respuesta JSON
  ↓ saneamiento Edge
Property Node
```

El Node nunca necesita conectividad directa con el servidor ZKBio ni conocer su token.

## Historial

Edge guarda cada prueba en `bh_connector_api_tests` con:

- operación;
- método/ruta;
- query/body saneados;
- respuesta saneada;
- código de fabricante cuando existe;
- latencia;
- resultado/error;
- fecha y usuario local.

El Node guarda sus solicitudes en `bh_edge_commands` con estado:

- `queued`;
- `claimed`;
- `completed`;
- `failed`;
- `cancelled`.

Los comandos de lectura reclamados que no reportan resultado durante cinco minutos pueden volver a cola.

## Orden recomendado de primera prueba

1. Actualizar Central a 0.14.15 y sincronizar/publicar el Core oficial.
2. Actualizar Property Node.
3. Actualizar Property Edge Agent.
4. En Edge abrir **ZKBio API Lab**.
5. Guardar URL + token.
6. Probar `Dispositivos / controladoras` con página 1, tamaño 20.
7. Probar Puertas, Lectores, Departamentos y Access Levels.
8. Probar Personas con tamaño pequeño.
9. Probar Transacciones con tamaño pequeño/fecha acotada.
10. Ejecutar **Aprender instalación completa**.
11. Revisar conteos y comparar con la instalación real.
12. Elegir una puerta conocida y, si procede, probar Remote Open desde el Edge.
13. Abrir el laboratorio del Node y encolar una lectura; esperar el heartbeat y verificar que la respuesta regrese sin que el token salga del Edge.

## Siguiente etapa

Una vez validadas las respuestas reales se incorporará la capa común de conciliación/provisión para ZKTeco, Hikvision y Dahua: personas, credenciales, perfiles/niveles, horarios, puertas, lectores, transacciones e invitaciones. Property Hub seguirá siendo el maestro de negocio; cada fabricante actuará como proveedor físico detrás de su driver.
