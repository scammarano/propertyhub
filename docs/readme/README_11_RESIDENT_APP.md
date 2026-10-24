# Resident App / Property App

## Propósito

La Property App es la experiencia móvil/PWA para propietarios, inquilinos, residentes, familiares, empleados y usuarios autorizados. No es una versión encogida de la Operations Console: tiene navegación táctil, iconos grandes, flujos personales y acceso únicamente a la información de la persona autenticada.

## Fuente de datos

Desde la versión 0.9.0 utiliza datos persistentes del Property Node:

```text
cuenta local o identidad social
→ persona
→ relaciones con unidades
→ permisos por relación
→ funciones y datos visibles
```

Una persona puede tener varias unidades en la misma instalación. La aplicación permite cambiar el contexto activo sin mezclar permisos ni información.

## Funciones actuales

- Inicio móvil con resumen personal.
- Selector de unidad.
- Creación y consulta de invitaciones.
- Consulta y solicitud de reservas.
- Áreas comunes y reservables.
- Cartelera informativa.
- Notificaciones internas.
- Directorio de emergencias.
- Cámaras declaradas y autorizadas.
- Puntos de acceso permitidos.
- Perfil personal.
- Instalación como PWA.

## Administración

La identidad visual y las funciones se configuran desde:

```text
Servicios y comunidad
→ Property App · Residentes
```

La cuenta móvil se crea desde el CRUD de Personas. Antes de crearla, la persona debe vincularse con una unidad y recibir los permisos correspondientes.

## Seguridad

- Autenticarse no otorga automáticamente acceso a una propiedad.
- La autorización proviene de las relaciones locales aprobadas.
- Las cámaras y accesos se filtran según permisos.
- Un usuario residente no puede entrar en la Operations Console.
- La vista previa administrativa está identificada y auditada como una simulación de contexto.

## Límites actuales

La visualización real de video y la apertura física requieren conectores certificados. La Property App no expone RTSP, contraseñas de cámaras ni endpoints de controladores al navegador.

Véanse también:

- [Property App 0.9.0](../RESIDENT_APP_REAL_DATA_0.9.0.md)
- [Guía de prueba móvil](../MOBILE_TEST_GUIDE_0.9.0.md)
