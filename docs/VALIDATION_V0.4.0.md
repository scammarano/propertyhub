# Validación 0.4.0 — Separación Property Hub Central / Property Node

## Resultado

La entrega fue validada estáticamente y quedó lista para prueba funcional en XAMPP.

- 111 archivos PHP: sintaxis válida.
- 23 archivos JSON: estructura válida.
- 46 relaciones ruta/vista comprobadas.
- 5 migraciones SQL procesadas correctamente por `SqlStatementSplitter`.
- Migraciones 0001, 0002 y 0003 conservan exactamente los checksums de 0.3.1.
- No existe el patrón incompatible `SHOW TABLES LIKE ?`.
- Metadatos de Core, instalador y manifiesto consistentes con 0.4.0.
- No se incluyen `.env`, `installed.lock`, claves privadas, certificados ni paquetes anidados.

## Separación comprobada

### Property Hub Central

El menú central contiene exclusivamente:

- Portafolio de instalaciones.
- Salud y sincronización.
- Métricas consolidadas.
- APIs de Property Hub.
- APIs de fabricantes.
- Conectores y compatibilidad.
- Versiones, paquetes y tokens.
- Privacidad, soporte y laboratorio.

No expone como módulos centrales personas, visitantes, reservas, cartelera, notificaciones o cámaras operativas.

### Property Node

El nodo conserva:

- Operación del inmueble.
- Resident App.
- Personas, visitantes y reservas.
- Cartelera, notificaciones y emergencias.
- Configuración local de marcas, APIs, endpoints y dispositivos.
- Vínculo con Property Hub Central y heartbeat.

## Privacidad

El heartbeat transmite versiones, salud, conteos e inventario técnico no sensible. La interfaz 0.4.0 no permite activar un espejo operativo ni enviar datos personales o contenido de mensajes.

## Limitación de esta validación

El entorno de construcción no dispone de un servidor MariaDB activo ni de credenciales reales de ZKTeco, Hikvision, Dahua, TTLock, Grandstream o UniFi. La prueba final de migraciones, registro central/nodo y heartbeat debe ejecutarse en XAMPP.

## Prueba recomendada

1. Actualizar o reinstalar Property Hub Central.
2. Ejecutar `tools/migrate.bat` si se conserva la instalación 0.3.1.
3. Restablecer la demo central con `tools/reset-demo.bat CODIGO_CENTRAL`.
4. Crear un token limitado a `PREMIER-LM`.
5. Instalar un nodo limpio para Conjunto Premier Las Mercedes.
6. Registrar el nodo contra la URL central terminada en `/public`.
7. Configurar sus conectores locales.
8. Enviar heartbeat y confirmar la ficha en el portafolio central.
