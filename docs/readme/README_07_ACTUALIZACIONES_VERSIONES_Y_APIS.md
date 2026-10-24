# Actualizaciones, versiones y APIs

## Filosofía

Property Hub adopta el modelo de distribución por componentes utilizado en NEWPROFIT: una instalación puede actualizar todo el producto o solo el componente necesario.

## Componentes versionables

- Core.
- Estándar visual.
- Resident App.
- Operations Console.
- Módulos funcionales.
- Conectores.
- Property API.
- Repository API.
- Migraciones.
- Reportes, plantillas, idiomas y formatos futuros.

## Canales

- `development`: laboratorio interno.
- `beta`: instalaciones piloto.
- `stable`: producción.
- `pinned`: versión fijada manualmente.

## Release

Cada release debe declarar:

- Código y versión.
- Tipo de componente.
- Canal.
- Destino Central, Node o ambos.
- Core/API mínimos y máximos.
- Dependencias.
- Migraciones.
- Notas y riesgos.
- SHA‑256.
- Manifiesto JSON.
- Firma RSA-SHA256.

## APIs de Property Hub

Las APIs se versionan separadamente del Core. Una aplicación móvil o conector debe declarar el rango de API compatible. Publicar una nueva app no puede inutilizar inmediatamente nodos que aún no se han actualizado.

## APIs de fabricantes

Central mantiene un catálogo documental de familias y versiones externas. No almacena las credenciales de cada cliente. Un conector puede necesitar variantes según modelo, firmware, plataforma local o cloud.

## Flujo seguro previsto

1. Nodo solicita catálogo/manifiesto.
2. Verifica firma con la clave pública del Central.
3. Resuelve compatibilidad y dependencias.
4. Descarga a una carpeta no pública.
5. Verifica SHA‑256.
6. Prepara staging.
7. Respalda archivos y base.
8. Activa mantenimiento.
9. Ejecuta migraciones.
10. Sustituye solo rutas permitidas.
11. Ejecuta health checks.
12. Confirma o hace rollback.

## Estado actual

Actualmente están disponibles la base del repositorio, publicación de releases, manifiestos, firmas, APIs de registro/heartbeat/catálogo y estructura de seguimiento. La aplicación automática del ZIP, health checks completos y rollback transaccional aún deben completarse.

## Reglas obligatorias

- Nunca sobrescribir `.env`, claves, uploads, logs ni respaldos.
- No ejecutar una migración sin registrarla en `bh_schema_migrations`.
- No editar una migración ya distribuida; crear una nueva.
- Probar primero en laboratorio, luego beta y después stable.
- Mantener un changelog y una guía de actualización por versión relevante.
