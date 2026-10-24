# Contrato de paquetes de actualización

## Componentes independientes

Cada paquete pertenece a un solo componente y versión. Ejemplos:

- Core 0.5.0
- UI Standard 1.2.1
- ZKTeco Connector 1.0.0
- TTLock Connector 1.1.0
- Property API v2

## Metadatos externos

Al publicar, la fuente central genera:

- código único de versión;
- SHA-256 del ZIP;
- manifiesto JSON;
- firma RSA-SHA256;
- tamaño del paquete;
- canal;
- compatibilidad de Core y API;
- destinos `central_repository` y/o `building_node`.

## Rutas protegidas

Un paquete nunca debe incluir ni reemplazar:

```text
.env
.env.*
storage/keys/
storage/uploads/
storage/backups/
storage/logs/
storage/repository/
storage/updates/rollback/
```

## Flujo previsto del nodo

1. Recibir manifiesto.
2. Verificar firma.
3. Seleccionar versiones compatibles.
4. Descargar a `storage/updates/downloads`.
5. Validar SHA-256.
6. Extraer a `storage/updates/staging`.
7. Crear respaldo.
8. Activar mantenimiento.
9. Aplicar migraciones.
10. Reemplazar únicamente archivos permitidos.
11. Ejecutar health checks.
12. Confirmar o revertir.

La versión 0.5.0 implementa publicación, consulta, firma, verificación y estructura de seguimiento. La aplicación automática y rollback se completarán en la siguiente fase.


## Compatibilidad hacia atrás

El paquete oficial del Core debe ser instalable por el `min_updater_version` declarado en `update-package.json`. En particular, un paquete destinado a Update Bootstrap 1.0.0 no puede contener `.env.example` ni ninguna ruta `.env.*`, aunque el archivo sea solo una plantilla. La plantilla puede existir en el paquete completo de instalación, pero no en el ZIP distribuible Central → Node.
