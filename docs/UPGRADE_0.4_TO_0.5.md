# Actualización de Property Hub 0.4.0 a 0.5.0

## Antes de actualizar

1. Respaldar la base de datos.
2. Respaldar la carpeta completa.
3. Conservar `.env` y `storage/`.

## Aplicar archivos y migraciones

Copiar el paquete 0.5.0 sobre la instalación y ejecutar:

```bat
tools\migrate.bat
```

La herramienta ejecuta únicamente las migraciones pendientes. La migración 0006 agrega tipos de instalación, topología territorial, enlaces de conectividad y LoRaWAN. En el central, la migración 0007 amplía los tipos admitidos por el portafolio.

## Completar demo de un Property Node

```bat
tools\reseed-node-demo.bat
```

Este comando es solo para pruebas. No debe ejecutarse en una instalación productiva con datos reales sin revisar previamente el contenido demo.

## Central

Para recargar el portafolio demo central:

```bat
tools\reset-demo.bat CODIGO_CENTRAL
```

## Compatibilidad

El identificador técnico interno `building_node` se conserva para no romper instalaciones previas. En la interfaz se muestra como Property Node.
