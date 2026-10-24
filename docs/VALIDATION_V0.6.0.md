# Validación técnica — Property Hub 0.6.0

## Alcance validado

Esta validación cubre el primer núcleo operativo persistente del Property Node:

- perfil de la instalación;
- estructura y unidades;
- áreas y espacios;
- personas y relaciones con unidades;
- sedes, garitas y ubicaciones técnicas;
- enlaces de conectividad;
- conectores del inmueble;
- inventario tecnológico;
- métricas reales, paginación, permisos y auditoría.

## Pruebas ejecutadas

- **123 archivos PHP** revisados con `php -l`, sin errores de sintaxis.
- **33 archivos JSON** cargados y validados.
- **123 enlaces Markdown internos** verificados, sin destinos faltantes.
- Las migraciones `0001` a `0008` conservan exactamente sus checksums de Property Hub 0.5.4.
- La migración `0009_operational_core_crud.sql` está incluida en:
  - instalador inicial;
  - migrador por consola;
  - pantalla web de reparación y migraciones.
- Separación SQL comprobada para las nueve migraciones.
- Revisión estática de rutas: formularios, acciones POST, vistas y permisos del nuevo núcleo.
- Verificación de aislamiento: las acciones operativas del nodo quedan bloqueadas en Property Hub Central.
- Verificación de empaquetado: no se incluyen `.env`, `installed.lock`, claves privadas, certificados ni secretos.

## Compatibilidad de base de datos

La versión utiliza las tablas existentes del esquema funcional y agrega únicamente campos de perfil mediante la migración `0009`. Los enums territoriales y de conectores ya habían sido ampliados por la migración `0006`.

La prueba definitiva de consultas y transacciones debe ejecutarse contra MariaDB/MySQL en XAMPP, porque el entorno de construcción no dispone de `pdo_mysql` ni de un servidor MariaDB activo.

## Funcionalidad que todavía no se declara terminada

- Integración física con ZKTeco, Hikvision, Dahua, TTLock, UniFi, Grandstream, Vimar, Sonoff, ONVIF o RTSP.
- Almacén cifrado y asistente de credenciales por fabricante.
- Visitantes persistentes.
- Reservas y calendario persistentes.
- Cartelera y notificaciones persistentes.
- Gestión real de accesos, cámaras, vouchers, intercomunicación o LoRaWAN.
- Aplicación automática de actualizaciones y rollback completo.

Estas áreas continúan visibles como base funcional o catálogo, pero no deben confundirse con conectores de hardware terminados.
