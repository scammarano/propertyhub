# Filosofía de instalación

## Un solo paquete, dos perfiles

Property Hub mantiene una sola base de código. El instalador solicita el perfil:

- `central_repository`: Property Hub Central.
- `building_node`: Property Node o instalación operativa.

La denominación técnica `building_node` se mantiene temporalmente por compatibilidad interna; en la interfaz el nombre oficial es **Property Node**.

## Por qué no existen dos proyectos separados

Usar el mismo paquete evita que Central y nodos se desalineen, permite compartir seguridad, migraciones, autenticación, UI, APIs y herramientas, y reduce retrabajo. Las diferencias se habilitan por configuración y permisos, no mediante copias manuales del código.

## Independencia obligatoria

Cada instalación debe tener:

- Carpeta o despliegue independiente.
- Base de datos independiente.
- UUID propio.
- `APP_KEY` y secreto de instalación propios.
- Nombre, código, tipo y URL propios.
- Respaldo y logs propios.
- Canal de actualización propio.

Nunca se debe apuntar dos instalaciones operativas a la misma base de datos.

## Instalación Central

Ejemplo XAMPP:

```text
C:\xampp\htdocs\property-hub-central
Base: property_hub_central
URL: https://dominio/property-hub-central/public
Perfil: Property Hub Central
```

Debe estar protegida, usar HTTPS y conservar sus claves RSA fuera del área pública. Allí se crean expedientes y tokens para los nodos.

## Instalación Node

Ejemplo:

```text
C:\xampp\htdocs\property-terraza-avila
Base: property_terraza_avila
URL: https://cliente/property-terraza-avila/public
Perfil: Property Node
Código: TERRAZA-AVILA
```

El código debe coincidir exactamente con el preparado en Central. El token de registro es de un solo uso y no debe guardarse permanentemente después del registro.

## Operación local

Un nodo debe seguir operando si el Central o Internet dejan de estar disponibles, siempre que los sistemas locales continúen accesibles. En una falla externa pueden quedar temporalmente no disponibles:

- Login social dependiente de Internet.
- Acceso remoto desde fuera de la propiedad.
- Descarga de actualizaciones.
- APIs cloud de fabricantes.
- Notificaciones externas.

La operación de vigilancia y los sistemas físicos locales deben diseñarse para degradación controlada.

## Configuración separada del código

Las actualizaciones no deben sobrescribir:

```text
.env
storage/keys/
storage/uploads/
storage/backups/
storage/logs/
storage/repository/
storage/updates/rollback/
```

La identidad visual y los adaptadores del proyecto deben permanecer fuera de la carpeta vendor del estándar visual.

## Instalación de laboratorio y producción

Se recomienda al menos:

1. Central de desarrollo/laboratorio.
2. Property Node de pruebas.
3. Uno o varios nodos beta controlados.
4. Nodos de producción en canal `stable`.

Ningún conector nuevo debe probarse por primera vez en el cliente productivo.
