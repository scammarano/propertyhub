# Despliegue de Property Hub 0.5.0 en XAMPP

## Requisitos

- Apache.
- PHP 8.1 o superior.
- MariaDB/MySQL.
- Extensiones PDO MySQL, cURL, JSON y OpenSSL.
- ZIP recomendado para publicar paquetes desde Property Hub Central.

## Property Hub Central

1. Copiar el paquete en `C:\xampp\htdocs\property-hub-central`.
2. Iniciar Apache y MySQL.
3. Abrir `http://localhost/property-hub-central/install/`.
4. Seleccionar Property Hub Central.
5. Crear una base exclusiva.
6. Guardar el token inicial y proteger las claves RSA generadas.

## Property Node

1. Copiar otra instancia en una carpeta distinta.
2. Abrir su `/install/`.
3. Seleccionar Property Node.
4. Crear una base independiente.
5. Registrar el nodo contra el central mediante URL y token.

## Seguridad mínima

- Desactivar `APP_DEBUG`.
- Usar HTTPS fuera de una LAN controlada.
- Utilizar usuarios MySQL distintos por instalación en producción.
- No publicar cámaras, NVR, ZKBioSecurity, UniFi Console ni bases de datos directamente en Internet.
- Respaldar `.env`, `storage/` y la base antes de actualizar.
