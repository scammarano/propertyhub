# Funcionalidad del producto

## 1. Instalaciones y portafolio

- Crear expedientes de edificios, urbanizaciones, oficinas, comercios y complejos.
- Generar tokens de registro.
- Controlar canal, URL, tipo, tecnologías previstas y estado.
- Ver salud, versiones, conectores y métricas agregadas.

## 2. Estructura territorial

- Sede principal y sedes secundarias.
- Sectores, calles, manzanas, parcelas y casas.
- Torres, pisos y apartamentos.
- Niveles, pasillos, oficinas y locales.
- Garitas, parques, estacionamientos y perímetros.
- Áreas comunes, técnicas, comerciales, de seguridad y reservables.

## 3. Personas y relaciones

- Propietarios, inquilinos, familiares y residentes.
- Administración, vigilancia y mantenimiento del conjunto.
- Empleados domésticos, choferes, escoltas, cuidadores y contratistas vinculados a una unidad.
- Empresas, representantes y personal de locales/oficinas.
- Documentos, fotografías y vigencias.

## 4. Visitantes

- Invitación previa por residente.
- Registro manual en recepción.
- Visita programada, inmediata o recurrente.
- Vehículos y acompañantes.
- QR, PIN o credencial provisional según capacidades.
- Entrada, salida, no presentación y tarjeta no devuelta.

## 5. Credenciales y permisos

- Tarjeta, tag vehicular, PIN, QR, huella, rostro, NFC, móvil y credencial temporal.
- Vigencia, estado, devolución, bloqueo y mapeo externo.
- Niveles de acceso y piso de ascensor según conector.

## 6. Áreas y reservas

- Salón de eventos, cowork, parrillera, canchas y otros espacios.
- Horarios, aforo, tarifa, depósito, anticipación y cancelación.
- Calendario de solicitudes.
- Aprobación, rechazo, bloqueo por mantenimiento y validación de solapamiento.
- Futuro: pago, voucher Wi‑Fi y acceso temporal asociado a la reserva.

## 7. Cartelera y comunicaciones

- Racionamiento de agua.
- Cortes eléctricos.
- Mantenimiento programado.
- Convocatorias, emergencias y avisos generales.
- Audiencias, prioridad, publicación, vencimiento y lectura.
- Canales previstos: app, push, correo, WhatsApp, SMS, voz y perifonía.

## 8. Cámaras y video

- Catálogo de cámaras por área y permisos.
- ONVIF/RTSP y APIs de fabricantes.
- Gateway futuro para WebRTC/HLS.
- Video en vivo, grabaciones, PTZ, audio y evidencias según capacidad.

## 9. Accesos y automatización

- Puertas, portones, barreras, torniquetes, ascensores y cerraduras.
- Controladores ZKTeco, Hikvision, Dahua, UniFi Access y TTLock.
- Relés Vimar, Sonoff, HTTP, MQTT, Modbus o Home Assistant.
- Apertura remota validada y auditada.

## 10. Intercomunicación y perifonía

- Grandstream UCM, GDS y GSC.
- Videoporteros Vimar, Hikvision, Dahua o SIP.
- Extensiones, grupos de llamada, directorio, apertura y zonas de perifonía.
- Alarmas y mensajes de emergencia.

## 11. Internet de invitados

- UniFi Network y portal cautivo.
- Vouchers vinculados a visita, reserva, evento, oficina o local.
- Duración, cuota, límite y auditoría.

## 12. LoRaWAN e IoT

- Redes, gateways, sensores y telemetría.
- Tanques, bombas, fugas, presión, iluminación, botones de pánico y perímetro.
- Automatizaciones y alertas de bajo ancho de banda.

## 13. Operación y soporte

- Diagnóstico, incidentes y logs.
- Privacidad y telemetría.
- Migraciones y reparación.
- Actualizaciones y versiones.
- Auditoría, respaldos y recuperación.

La matriz de estado real de cada ámbito está en [README_09_MATRIZ_DE_ESTADO_FUNCIONAL.md](README_09_MATRIZ_DE_ESTADO_FUNCIONAL.md).

## Generación masiva de estructura

Desde la versión 0.6.4, un Property Node puede construir torres, pisos, apartamentos, puestos de estacionamiento y áreas estándar mediante un asistente por pasos. La generación es persistente, se valida antes de guardar y se ejecuta en una transacción completa. El historial permite deshacer una ejecución mientras sus registros no tengan relaciones operativas.


## Ajuste de estructuras atípicas — 0.6.5

Después de generar la estructura base, el administrador puede seleccionar múltiples pisos, apartamentos, estacionamientos u otros elementos y moverlos, reclasificarlos, renombrarlos o eliminarlos. Esto contempla edificios donde cada piso posee una distribución diferente o no contiene apartamentos.
