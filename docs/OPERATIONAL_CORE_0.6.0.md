# Núcleo operativo Property Hub 0.6.0

## Objetivo

Convertir el Property Node en la primera base real de un BMS multimarca propio, comenzando por la información maestra que después utilizarán visitantes, reservas, control de acceso, video, intercomunicación, red e IoT.

## CRUD persistentes incorporados

### Estructura y unidades

Permite crear jerarquías flexibles para urbanizaciones y edificios: conjunto, sector, calle, manzana, parcela, edificio, torre, piso, apartamento, casa, oficina, local, depósito, estacionamiento, garita, parque y áreas generales.

### Áreas y espacios

Permite distinguir espacios comunes, reservables, de seguridad, técnicos, comerciales, estacionamientos y circulación. Incluye horario, capacidad, tarifa, depósito, reglas y parámetros para reservas futuras.

### Personas y vinculaciones

La persona se registra una sola vez. Puede vincularse a múltiples unidades con relaciones y permisos distintos: propietario, inquilino, residente, familiar, representante, empleado, personal doméstico, chofer, seguridad privada, mantenimiento o autorizado.

### Topología técnica

Permite registrar sede principal, garitas, edificios, parques, cuartos técnicos y puntos remotos, además de enlaces físicos o lógicos por fibra, UTP, Wi‑Fi, VPN, Internet, celular y LoRaWAN.

### Conectores del inmueble

Representa las plataformas realmente utilizadas en el Property Node. No se confunde con el catálogo de conectores disponibles. Incluye modalidad, familia/versión de API, endpoint, autenticación prevista, entorno, número de equipos y estado.

### Inventario tecnológico

Registra equipos reales y los relaciona con ubicaciones y conectores: controladoras, lectores, cerraduras, relés, cámaras, NVR, intercomunicadores, teléfonos, altavoces, controladores de red, AP, gateways y sensores.

## Reglas de interfaz

- La tabla se muestra primero.
- El formulario se abre mediante el botón **Nuevo** o **Editar**.
- No se muestran IDs internos al usuario.
- Las relaciones muestran nombres y códigos comprensibles.
- Filtros y paginación 10, 25, 50 y 100.
- Acciones condicionadas por permisos.
- Eliminación segura: cuando hay dependencias se desactiva o archiva el registro.

## Auditoría

Las altas, modificaciones, eliminaciones, desactivaciones y vinculaciones se registran en `bh_audit_logs`, incluyendo usuario local, fecha, dirección IP, agente, entidad y resultado.

## Pendientes inmediatos

1. Persistencia completa de visitantes e invitaciones.
2. Reservas reales y calendario con solapamiento transaccional.
3. Cartelera, emergencias y notificaciones persistentes.
4. Almacén cifrado de secretos de conectores.
5. Asistente y conector ZKTeco en solo lectura.
6. Cámaras ONVIF/RTSP mediante gateway de video.
7. Puntos de acceso y credenciales.
