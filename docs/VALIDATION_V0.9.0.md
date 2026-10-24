# Validación Property Hub 0.9.0

## Validaciones ejecutadas

- Sintaxis de todos los archivos PHP.
- Validez de archivos JSON y `manifest.webmanifest`.
- Sintaxis de JavaScript público.
- Registro de `ResidentRepository` en el bootstrap.
- Registro de `0015_resident_app_real_data.sql` en instalador, reparador y migrador.
- Separación de rutas de Resident App y Operations Console.
- Login local con resolución de `person_id` y portal residente.
- Creación/actualización de cuenta residente desde Personas.
- Vista previa administrativa por persona.
- Configuración de identidad y funciones de Property App.
- Navegación móvil, selector de unidad y PWA.
- Referencias documentales internas de la versión.

## Contratos revisados

```text
cuenta residente
→ bh_local_users.person_id
→ bh_people
→ bh_property_memberships
→ bh_properties
```

```text
Property App
→ ResidentRepository
→ tablas persistentes del Property Node
```

## Límites de la validación

El entorno de empaquetado no dispone de la base MariaDB productiva de Premier ni de dispositivos físicos. Por ello, deben probarse en el servidor real:

- ejecución de la migración 0015;
- creación de la primera cuenta residente;
- login desde Android e iPhone;
- operaciones de reserva e invitación;
- visualización según permisos;
- instalación PWA y comportamiento de caché.

Las acciones físicas de video y apertura permanecen bloqueadas deliberadamente.
