# Property Node

## Finalidad

Property Node es la instalación operativa de una urbanización, conjunto, edificio, oficina, centro comercial, campus o complejo mixto. Es la fuente de verdad local de la propiedad.

## Responsabilidades

- Estructura territorial y unidades.
- Personas, usuarios, membresías y documentos.
- Visitantes, invitaciones, entradas y salidas.
- Credenciales y mapeos externos.
- Áreas comunes, técnicas, comerciales y de seguridad.
- Reservas, bloqueos y calendarios.
- Cartelera, notificaciones y emergencias.
- Accesos, cámaras, intercomunicación, red e IoT.
- Configuración de conectores y secretos.
- Auditoría, salud, respaldos y actualizaciones.

## Menú operativo

```text
Gestión de la propiedad
├── Perfil de la instalación
├── Estructura y unidades
├── Áreas y espacios
├── Personas
└── Visitantes

Servicios y comunidad
├── Reservas y calendario
├── Cartelera
├── Notificaciones
└── Emergencias

Infraestructura y tecnología
├── Sedes, garitas y conectividad
├── Inventario tecnológico
├── Conectores configurados
├── Catálogo de conectores
├── Control de accesos
├── Cámaras
├── Intercom y perifonía
├── Internet y vouchers
└── LoRaWAN e IoT

Sistema local
├── Privacidad y telemetría
├── Diagnóstico
├── Vínculo con Central
└── Actualizaciones
```

## Configuración de marcas

Las direcciones, autenticación, versiones y credenciales de ZKTeco, Hikvision, Dahua, TTLock, UniFi, Grandstream, Vimar, Sonoff y demás integraciones se configuran en el nodo. Central solo ve un resumen declarado si la política lo permite.

## Fuente local de verdad

Una persona, visitante, reserva o mensaje existe en el nodo. No se debe depender del Central para validar una entrada local, consultar una reserva o continuar la operación de vigilancia.

## Registro contra Central

El nodo utiliza:

- URL del repositorio terminada en `/public`.
- Código exacto preparado.
- Token de registro.
- UUID y clave de instalación generados localmente.

Después del registro, utiliza su clave para heartbeat, catálogo, manifiestos y descargas.

## Estado actual

La configuración tecnológica y el vínculo Central/Nodo tienen persistencia inicial. Parte de la operación comunitaria visible todavía utiliza datos demo de sesión y necesita convertirse a CRUD persistente antes de producción.
