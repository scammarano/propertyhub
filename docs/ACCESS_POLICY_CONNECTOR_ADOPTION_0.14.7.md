# Property Hub 0.14.7 · Perfiles, motor de políticas y adopción de conectores

## Principio

Property Hub es el maestro de negocio. ZKBio, Vimar, Hikvision, Dahua, Sonoff, LoRa y otros sistemas son destinos técnicos de descubrimiento, conciliación, provisión o ejecución.

El administrador define en Property Hub personas, relación con unidades, perfiles, horarios, cámaras y puntos de acceso. El Edge traduce esa intención al sistema físico disponible.

## Adopción de una instalación existente

La primera conexión a un sistema existente es de solo lectura:

1. descubrir/inventariar objetos del fabricante;
2. almacenarlos como objetos externos sin conceder permisos;
3. conciliar personas, credenciales, vehículos, puertas y niveles con Property Hub;
4. adoptar los objetos confirmados;
5. desde ese momento, Property Hub pasa a ser la autoridad de negocio y conserva el mapeo técnico.

### ZKBio CVSecurity

La lectura inicial usa la API oficial y pagina instalaciones grandes. Se inventarían:

- dispositivos;
- puertas;
- lectores;
- departamentos;
- niveles de acceso;
- personas;
- tarjeta principal y tarjetas secundarias derivadas de la ficha de persona;
- placas de vehículo derivadas de `carPlate`.

La lectura no modifica ZKBio.

## Perfiles de acceso

Los perfiles son propios de la instalación, no roles administrativos del software. Se asignan a la relación Persona ↔ Unidad, porque la misma persona puede ser propietaria de una unidad e inquilina o residente de otra.

Se incluyen perfiles base: Propietario, Inquilino, Residente, Familiar y Empleado doméstico. Pueden crearse perfiles derivados y perfiles limitados a una unidad.

Las reglas usan alcances `common`, `property`, `tower`, `floor`, `unit`, `custom` o recursos específicos, con días y horarios.

## Puntos de acceso universales

Para el usuario existe un `Punto de acceso`: puerta, portón, barrera, torniquete, ascensor, etc. El binding técnico define cómo se ejecuta:

- `provisioned`: controlador inteligente como ZKBio almacena reglas localmente;
- `realtime`: Property Hub evalúa y Edge pulsa un relé/actuador;
- `hybrid`: parte se provisiona y parte se decide en tiempo real.

## Horarios sin explosión de objetos

Property Hub normaliza cada horario y lo identifica por `schedule_hash`. Un mismo conjunto días+horas se reutiliza para múltiples perfiles/personas. `bh_vendor_schedule_bindings` conserva el objeto equivalente del fabricante cuando exista.

El manual ZKBio 1.2 documenta que los Access Levels referencian `timeSegName`, pero la documentación suministrada no expone en las secciones revisadas un endpoint general para crear/editar Time Segments. Por eso 0.14.7 prepara deduplicación y mapeo de horarios, pero no inventa llamadas no documentadas: la escritura de horarios ZK se habilitará después de verificar el endpoint disponible en la instalación real.

## Vimar / VIEW / By-me

La ruta ejecutable de laboratorio sigue siendo local-first:

Vimar / VIEW / By-me → Home Assistant local → Property Edge → Property Node.

El Edge puede descubrir entidades compatibles. Un relé Vimar puede después vincularse a un Punto de acceso Property Hub sin que el usuario final tenga que conocer el gateway o el número de relé.

## Seguridad

- Tokens y credenciales de equipos permanecen en Edge.
- El Node conserva semántica, perfiles y autorización.
- El residente nunca recibe IP LAN, credenciales ONVIF/RTSP ni token ZKBio.
- La adopción inicial de ZKBio es de solo lectura.
