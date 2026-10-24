# Property Hub 0.14.17 — ZKBio Operational Bridge

## Objetivo
Convertir el laboratorio ZKBio en un puente funcional sin romper la arquitectura Central → Node → Edge. El token y la URL LAN permanecen exclusivamente en Edge.

## Pruebas funcionales Premier

1. **Puerta:** Node → ZKBio Integración operativa → Actualizar inventario → Adoptar puerta. Después asignar esa puerta a un Perfil/relación Property y abrir desde Property App → Accesos. La orden viaja Node → cola Edge → `/api/door/remoteOpenById`.
2. **Visitante:** desde Property App → Invitaciones, crear visitante. Property conserva la invitación y, si ZKBio está habilitado y el anfitrión tiene PIN vinculado, Edge ejecuta `/api/visRegistration/add`.
3. **Persona:** Node → Personas → Nueva persona, seleccionar unidad y niveles ZK, marcar Provisionar ZKBio. Property crea su identidad/relación y Edge ejecuta `/api/person/add` usando el departamento conciliado.
4. **Vehículo Premier:** Node → Vehículos → registrar placa + etiqueta RFID del parabrisas. `Vehicle` y `VehicleCredential` son objetos separados. Para Premier, Edge crea una identidad vehicular ZK mediante `/api/person/add` y coloca el RFID en `cardNo`; la placa sigue guardada en Property y puede actuar como LPR en otros drivers. Los niveles VEHICULOS se seleccionan explícitamente; nunca se otorgan todos automáticamente.

## API Matrix adaptativa
- Mantiene todos los endpoints del manual oficial en el catálogo.
- Corrige QR de persona a POST según el manual.
- Prueba V1/V2 y variantes body/query cuando corresponda.
- Harvest de fixtures por módulo: ACC, PSG, Parking, Elevator, Visitor, biometría, etc.
- Clasificación: WORKING, WORKING_EMPTY, ROUTE_NOT_FOUND, BAD_FIXTURE, CONTRACT_VARIANT/REJECTED, SKIPPED_* y UNKNOWN.
- RUN general sigue siendo read-only seguro: mutaciones, aperturas físicas y operaciones financieras se catalogan pero no se disparan automáticamente.

## Huellas
El API permite leer templates y subirlos mediante `/api/bioTemplate/add`. 0.14.17 profundiza las pruebas con PINs que realmente registran huella. La captura física desde el enrolador USB ZK queda separada: requiere estudiar el SDK/plugin usado por la propia interfaz ZK antes de declararla funcional.

## Seguridad
- Token ZK cifrado y sólo en Edge.
- Passwords/hashes y material sensible se redactan de logs/ZIP.
- Aperturas y escrituras de negocio requieren permisos y auditoría Property.
