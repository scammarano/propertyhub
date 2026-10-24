# Property Hub 0.14.7 — perfiles, políticas y adopción de fabricantes

## Principio
Property Hub es el maestro de negocio. El residente y el administrador trabajan con personas, unidades, perfiles, cámaras y puntos de acceso. ZKBio, Vimar, Hikvision, Dahua, Sonoff, LoRa u otros son implementaciones físicas detrás de esos objetos.

## Perfiles
El perfil se asigna a la relación **persona ↔ unidad**, no al login global. Una misma persona puede ser Propietario en 4-A con perfil Propietario e Inquilino en 8-C con perfil Inquilino.

Los perfiles pueden heredarse y pueden limitarse a una propiedad/unidad concreta. Definen invitaciones, reservas, notificaciones, documentos, domótica y alcances de cámaras/accesos: común, propiedad, torre, piso, unidad o grupo.

Las autorizaciones individuales preexistentes se conservan como excepciones de compatibilidad; ya no son el mecanismo principal.

## Punto de acceso universal
Una puerta para Property Hub siempre es un punto de acceso. La tabla `bh_access_point_bindings` resuelve cómo se acciona: ZKBio door, Vimar relay/entity, Sonoff switch, LoRa relay, etc. El modo puede ser `provisioned`, `realtime` o `hybrid`.

## Adopción ZKBio
La primera fase es estrictamente de lectura. Edge usa el token local para aprender:
- dispositivos;
- puertas;
- lectores;
- departamentos;
- niveles de acceso;
- personas;
- tarjetas.

Los objetos se guardan en `bh_external_objects` para revisión y conciliación. El descubrimiento NO modifica ZKBio.

Después vendrán adopción y provisión controlada. `bh_external_mappings` registra la equivalencia entre objeto Property Hub y objeto externo, con Property Hub como autoridad prevista.

## Horarios sin explosión
Los horarios se normalizan y deduplican mediante un SHA-256 del calendario. `bh_vendor_schedule_bindings` mantiene un horario lógico por conector, no uno por persona.

El manual oficial aportado documenta creación de Access Levels referenciando un `timeSegName`, asociación de puertas al nivel y asignación del nivel a personas. En el manual entregado no se localizó un endpoint oficial para crear/editar el Time Segment. Por eso 0.14.7 no inventa una llamada no documentada: puede reutilizar/mapping un horario existente y marca `manual_mapping_required` hasta que la API real instalada confirme una operación soportada.

## Vimar
Se mantiene el laboratorio local vía Home Assistant para probar gateways VIEW/By-me ya integrados en la red. La capa de binding permite que posteriormente una puerta se ejecute como relé Vimar sin que el usuario tenga que saberlo.

## Video
No se sustituye el trabajo de video 0.14.x. ONVIF/RTSP se descubre en Edge y Node conserva la semántica/visibilidad. Admin y Resident siguen usando la misma capa de reproducción segura.
