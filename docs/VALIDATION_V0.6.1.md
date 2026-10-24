# Validación técnica — Property Hub 0.6.1

## Alcance validado

- Separación entre plataforma conectada, equipo administrado y punto funcional.
- Modelo ZKBioSecurity dirigido al servidor y no a paneles inBio.
- Migración `0010_platform_managed_assets.sql`.
- Persistencia de tipo de destino, producto, versión y política de conexión directa.
- Persistencia de rol del activo y ruta de integración.
- Validaciones de servidor que fuerzan `platform_server` y `via_platform` para ZKBioSecurity.
- Conteo automático de activos relacionados con cada sistema conectado.
- Formularios y listados actualizados.
- Instalador, reparación web y herramienta CLI con la nueva migración.

## Pruebas estáticas

- 123 archivos PHP: sintaxis válida.
- 33 archivos JSON: estructura válida.
- 110 enlaces Markdown internos revisados.
- Migraciones 0001–0009: checksums sin cambios respecto a 0.6.0.
- Migración 0010: separada correctamente en 8 sentencias por el parser SQL del proyecto.
- Manifiesto ZKTeco 0.1.1: JSON válido.
- ZIP: validación de integridad requerida al empaquetar.

## Límites del entorno de construcción

El entorno de construcción no dispone de MariaDB ni del controlador `pdo_mysql`; por ello no se ejecutó la migración contra una base real. La prueba definitiva debe realizarse en XAMPP/MariaDB mediante:

```bat
tools\migrate.bat
```

Después se debe verificar:

1. Crear `Servidor ZKBioSecurity Premier` en Sistemas conectados.
2. Confirmar destino `Servidor o plataforma local`.
3. Confirmar política directa `Prohibida`.
4. Registrar un inBio como equipo administrado por plataforma.
5. Confirmar que la interfaz fuerza la ruta `A través de plataforma`.
6. Confirmar que el conteo del sistema conectado aumenta automáticamente.

## Funcionalidad no declarada como terminada

- Llamadas reales a la API de ZKBioSecurity.
- Lectura de la base de datos ZKTeco.
- Descubrimiento automático de paneles.
- Sincronización de personas, tarjetas o niveles.
- Apertura remota.
- Escritura hacia ZKBioSecurity.
