# Identidad, roles y permisos

## Entidades distintas

### Persona

Ser humano o representante registrado en una instalación. Puede existir sin cuenta digital.

### Usuario local

Cuenta autenticable dentro de un Property Node o Central.

### Identidad externa

Vínculo con Google, Apple u otro proveedor OpenID/OAuth.

### Membresía

Relación entre usuario/persona y una instalación o unidad, con rol, vigencia y estado.

### Identidad global futura

Cuenta capaz de descubrir varias instalaciones y solicitar tokens limitados para cada una. No sustituye las personas locales.

## Principio de autorización

Autenticar no equivale a autorizar. Google o Apple confirman identidad; Property Hub determina a qué propiedad, unidad, módulo y acción puede acceder.

## Roles base

- `SUPERADMIN`: Property Hub Central.
- `ADMIN`: administración local.
- `SECURITY`: vigilancia/recepción.
- `OPERATOR`: operación administrativa limitada.
- `RESIDENT`: Resident App.

Los roles base no deben convertirse en el único modelo. Se necesita permiso granular por módulo y, en el futuro, alcance por instalación, unidad, área, torre o empresa.

## Estados de membresía

- Invitada.
- Pendiente de aprobación.
- Aprobada.
- Suspendida.
- Vencida.
- Revocada.

## Login local

Debe mantenerse como contingencia y para administradores. Las contraseñas se guardan con hash seguro y nunca se convierten a mayúsculas ni se registran en logs.

## Google y Apple

La base incluye flujos de servidor preparados. Producción exige HTTPS, URIs exactas, secretos protegidos, validación de emisor/audiencia/nonce y pruebas con cuentas reales.

## Acciones sensibles

Aperturas remotas, visualización de cámaras restringidas, descarga de evidencia, administración de usuarios y soporte remoto pueden requerir segundo factor o reautenticación.

## Revocación

Revocar acceso a una propiedad no elimina la cuenta global ni otras membresías. Debe invalidar sesiones/tokens de ese nodo y desactivar credenciales según la política local.
