# Property App · Vista residente

## Propósito

Property App es la experiencia móvil/PWA de Property Hub para propietarios, inquilinos, residentes, representantes y otros usuarios autorizados. Comparte la base de datos del Property Node, pero utiliza una navegación, permisos y presentación diferentes de la Operations Console.

## Estado 0.9.0

La vista ya trabaja con datos persistentes reales:

- cuenta local de tipo `resident` vinculada con una persona;
- una o varias unidades mediante `bh_property_memberships`;
- permisos independientes para invitar, reservar, ver cámaras y operar accesos;
- áreas reservables;
- solicitudes de reserva y validación de solapamientos;
- invitaciones y códigos de acceso;
- cartelera publicada;
- notificaciones dentro de la aplicación;
- directorio de emergencias;
- cámaras visibles para residentes;
- puntos de acceso publicados para operación remota futura.

Las aperturas y transmisiones físicas permanecen bloqueadas hasta instalar y certificar los conectores correspondientes.

## Acceso

El administrador crea el acceso desde **Personas → Acceso a Property App**. El residente inicia sesión en la URL normal del Property Node y Property Hub lo dirige automáticamente a `resident-home`.

## Prueba sin compartir contraseña

La consola ofrece **Property App · Residentes → Abrir vista**, que permite al administrador navegar la experiencia móvil usando los datos reales de una persona seleccionada.

## PWA

La aplicación incluye `manifest.webmanifest`, service worker, iconos y `display: standalone`. Puede abrirse desde Chrome/Safari móvil y agregarse a la pantalla de inicio.

## Reglas

- No reutilizar el dashboard administrativo para residentes.
- Validar permisos siempre en el servidor.
- No exponer contraseñas RTSP, APIs ni credenciales técnicas.
- Mantener la operación física deshabilitada hasta certificar cada conector.
- No cargar información demo cuando `APP_DEMO_MODE=false`.
