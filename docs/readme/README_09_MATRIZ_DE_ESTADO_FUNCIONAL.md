# Matriz de estado funcional

## Cómo leer los estados

- **Funcional persistente:** usa base de datos y puede probarse en el flujo actual.
- **Base funcional parcial:** existe flujo o persistencia inicial, pero faltan casos de producción.
- **Demo de sesión:** funciona únicamente con información temporal cuando `APP_DEMO_MODE=true`.
- **Esquema preparado:** existen tablas o contratos, pero no un flujo operativo completo.
- **Planificado / investigación:** diseño reservado, sin ejecución física real.

## Plataforma e instalación

| Ámbito | Estado 0.6.1 | Observación |
|---|---|---|
| Instalador Central/Node | Funcional persistente | Crea base, `.env`, administrador, claves, migraciones y bloqueo. |
| Reparación de migraciones | Funcional persistente | Ruta web y herramienta CLI. |
| Expediente de nueva instalación | Funcional persistente | Central crea plan y token de un uso. |
| Registro Node en Central | Base funcional parcial | Registro real; faltan pruebas prolongadas de red, TLS y rotación. |
| Heartbeat y telemetría | Base funcional parcial | Endpoint y servicio presentes; falta programador/reintentos robustos. |
| Demo Central | Funcional persistente | Seeder de laboratorio claramente separado. |
| Node real sin demo | Funcional persistente | Queda vacío y recibe solo registros creados por el usuario. |

## Central

| Ámbito | Estado | Observación |
|---|---|---|
| Portafolio, fichas y métricas | Funcional persistente | Basado en tablas del repositorio. |
| Tokens | Funcional persistente | Hash, alcance, usos, vencimiento y revocación. |
| Catálogo de APIs/conectores | Base funcional parcial | Administración inicial; falta gobierno avanzado. |
| Publicación de releases | Base funcional parcial | Guarda paquete, hash y firma; aplicación automática pendiente. |
| Soporte remoto | Esquema preparado | Acceso detallado todavía no implementado. |

## Operación del Property Node

| Ámbito | Estado | Observación |
|---|---|---|
| Perfil de instalación | Funcional persistente | Nombre, tipo, dirección, contacto, zona horaria y notas. |
| Estructura y unidades | Funcional persistente | CRUD jerárquico con filtros, paginación y desactivación segura. |
| Áreas y espacios | Funcional persistente | CRUD para áreas comunes, reservables, técnicas y de seguridad. |
| Personas | Funcional persistente | CRUD con documento y contacto. |
| Relaciones persona/unidad | Funcional persistente | Múltiples vínculos, rol, vigencia y permisos por unidad. |
| Sedes, garitas y ubicaciones | Funcional persistente | CRUD de topología técnica. |
| Enlaces de conectividad | Funcional persistente | CRUD de fibra, UTP, Wi‑Fi, VPN, Internet, celular y LoRaWAN. |
| Sistemas y plataformas conectadas | Funcional persistente | CRUD de servidores, plataformas, nubes, servicios o endpoints declarados; secretos y pruebas aún pendientes. |
| Catálogo de conectores | Funcional persistente de lectura | Biblioteca del producto; no representa equipos instalados. |
| Equipos administrados y activos técnicos | Funcional persistente | CRUD con rol del activo y ruta de integración: vía plataforma, directa, cloud, gateway o referencia manual. |
| Dashboard operativo | Funcional persistente | Métricas reales y auditoría reciente. |
| Visitantes e invitaciones | Demo de sesión / esquema | Próxima fase de persistencia. |
| Reservas y calendario | Demo de sesión / esquema | Próxima fase de persistencia transaccional. |
| Cartelera y notificaciones | Demo de sesión / esquema | Persistencia y canales externos pendientes. |
| Emergencias | Demo de sesión / esquema | Administración persistente pendiente. |
| Control físico de accesos | Esquema / planificado | No abre puertas reales todavía. |
| Cámaras | Esquema / planificado | No reproduce ONVIF/RTSP real todavía. |
| UniFi vouchers | Esquema / planificado | No crea vouchers reales todavía. |
| LoRaWAN | Esquema preparado | No consume MQTT/API real todavía. |

## Identidad y experiencias

| Ámbito | Estado | Observación |
|---|---|---|
| Login local | Funcional persistente | Contraseña hasheada. |
| Google OIDC | Base funcional parcial | Requiere credenciales y pruebas reales. |
| Sign in with Apple | Base funcional parcial | Requiere Apple Developer y pruebas reales. |
| Operations Console | Funcional parcial avanzado | Maestros principales ya persistentes. |
| Resident App PWA | Demo funcional | Datos federados reales pendientes. |
| Cambio multi-propiedad | Demo de sesión | Federación entre nodos pendiente. |

## Conectores físicos

Los conectores actuales siguen en estados `planned`, `research` o base de configuración. Registrar una instancia no significa que Property Hub ya esté operando físicamente ese fabricante. El primer objetivo es ZKTeco en modo de lectura y conciliación.

## Regla de comunicación

En pruebas y presentaciones debe indicarse explícitamente qué es persistente, qué es demostración y qué está planificado. Una pantalla o un manifiesto no equivale a una integración física terminada.

## Modelo ZKBioSecurity 0.6.1

Property Hub conecta con el servidor ZKBioSecurity. Los paneles inBio son equipos administrados por ese servidor y no endpoints directos del conector de Premier.

## Incorporación 0.6.4

| Función | Estado |
|---|---|
| Asistente de torres, pisos y apartamentos | Persistente y operativo |
| Nomenclatura configurable | Persistente y operativa |
| Generación de estacionamientos | Persistente y operativa |
| Selección de áreas estándar | Persistente y operativa |
| Vista previa antes de guardar | Operativa |
| Historial y deshacer seguro | Persistente y operativo |


## Incorporación 0.6.5

La estructura jerárquica dispone de edición masiva persistente: selección por página, mover conservando descendientes, cambio de estado/tipo, renombrado por reglas y eliminación segura. Estado: **funcional persistente; pendiente prueba final en MariaDB real**.

## Incorporación 0.7.0

| Función | Estado |
|---|---|
| Pisos como contenedores de unidades, áreas y activos | Funcional persistente |
| Distribución diferente por piso y pisos sin apartamentos | Funcional persistente |
| Nomenclatura flexible de unidades | Funcional persistente |
| Eliminación masiva con confirmación y resultado visible | Funcional persistente |
| Acciones masivas de áreas | Funcional persistente |
| Duplicación de torre/edificio con jerarquía | Funcional persistente |
| Copia de componentes como diseño planificado | Base funcional persistente; pendiente prueba extensa en MariaDB |
| Ubicación estructural de activos | Funcional persistente |
| Inventario BMS ampliado | Funcional persistente |

## Actualización 0.8.0

| Ámbito | Estado | Alcance |
|---|---|---|
| Diseñador topológico v2 | Funcional persistente | Torres, niveles especiales, unidades, estacionamientos, maleteros y áreas ubicadas. |
| Motor de nomenclatura | Funcional persistente | Variables corregidas, separador y plantillas independientes. |
| Ascensores y paradas | Funcional persistente | Torres atendidas, niveles servidos y puertas planificadas. |
| Duplicación vertical | Funcional persistente | Copia ascensores, paradas y puertas como diseño. |
| Control físico de ascensores | Planificado | Requiere conector real con ZKBioSecurity, Hikvision, Dahua u otro sistema. |
