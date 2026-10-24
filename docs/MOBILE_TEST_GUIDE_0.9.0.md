# Property Hub 0.9.0 — Prueba de Property App desde un teléfono

## Requisitos previos

- Property Node accesible desde el teléfono por HTTPS o por la red local.
- Migración `0015_resident_app_real_data.sql` aplicada.
- Al menos una unidad creada.
- Una persona vinculada con esa unidad.
- Una cuenta residente activa.

## 1. Crear la persona y su relación

En la Operations Console:

```text
Gestión de la propiedad
→ Personas
```

1. Registrar la persona.
2. Pulsar **Vincular a unidad**.
3. Seleccionar apartamento, casa, oficina o local.
4. Definir la relación: propietario, inquilino, residente, familiar u otra.
5. Activar los permisos que correspondan:
   - invitar;
   - reservar;
   - ver cámaras;
   - operar accesos.

## 2. Crear la cuenta móvil

En la fila de la persona, pulsar el botón con icono de teléfono.

Registrar:

- correo de acceso;
- nombre de usuario opcional;
- contraseña inicial de al menos ocho caracteres;
- estado **Activo**.

La cuenta queda vinculada a la persona. No debe crearse una cuenta genérica compartida por apartamento.

## 3. Configurar la Property App

Abrir:

```text
Servicios y comunidad
→ Property App · Residentes
```

Configurar nombre, mensaje, portada, color y funciones visibles. En esta misma pantalla puede abrirse una vista previa con los datos reales de una persona.

## 4. Probar desde el móvil

Abrir en el teléfono la URL del Property Node, por ejemplo:

```text
https://2cam.services/property-premier/public/
```

El sistema presentará el login. Usar el correo/usuario y contraseña configurados. Al reconocer una cuenta de tipo `resident`, Property Hub abre automáticamente la Property App y no la consola administrativa.

## 5. Instalar como aplicación

### Android / Chrome

1. Abrir la URL.
2. Usar el menú del navegador.
3. Seleccionar **Instalar aplicación** o **Agregar a pantalla principal**.

### iPhone / Safari

1. Abrir la URL.
2. Pulsar **Compartir**.
3. Seleccionar **Agregar a inicio**.

La PWA utiliza el nombre **Property App** y conserva la sesión según las políticas del navegador.

## 6. Verificación de datos reales

- La cabecera debe mostrar la instalación y la unidad vinculada.
- La lista de áreas debe provenir de `bh_areas`.
- Las reservas deben pertenecer a la persona y unidad activas.
- Las invitaciones deben haber sido creadas por la persona.
- Las notificaciones deben estar dirigidas a su cuenta.
- Las cámaras y accesos solo aparecen con permisos activos.
- Al tener varias unidades, el selector superior debe permitir cambiar de contexto.

## Límites de esta versión

- Las cámaras se muestran como inventario autorizado; el stream en vivo requiere el futuro gateway WebRTC/HLS.
- El botón de apertura no envía comandos físicos hasta certificar el conector de control de acceso.
- Google y Apple no sustituyen todavía la aprobación y vinculación local de la persona.
