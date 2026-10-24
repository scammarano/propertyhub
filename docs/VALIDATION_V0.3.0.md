# Validación de Property Hub 0.3.0

## Comprobaciones realizadas

- Sintaxis de 91 archivos PHP.
- Validez de todos los manifiestos JSON.
- Renderizado HTTP de la portada del instalador.
- Redirección de una instalación nueva hacia `/install/`.
- Bloqueo del instalador cuando existe `storage/installed.lock`.
- Renderizado del login después de una instalación simulada.
- Navegación del asistente desde requisitos hasta configuración de base de datos.
- Carga condicional del menú de repositorio únicamente en modo central.
- Separación SQL en 34 sentencias de base funcional, 9 de despliegue y 10 de repositorio.
- Generación y verificación de una firma RSA-SHA256.
- Ausencia de `.env`, `installed.lock`, claves privadas o credenciales reales en el entregable.
- Integridad del ZIP final y checksum SHA-256.

## Limitación del entorno de validación

El contenedor de construcción no dispone de `pdo_mysql`, MariaDB ni MySQL. Por esa razón no se ejecutó el asistente contra una base real en este entorno. El instalador verifica y exige `pdo_mysql`, y las migraciones fueron revisadas y separadas correctamente. La primera prueba en XAMPP debe confirmar creación de la base, usuario administrador y tablas en ambos modos antes de promover la versión a un cliente.

## Prueba recomendada

1. Instalar como central en `building-central` y base `property_hub_central`.
2. Guardar el token inicial.
3. Instalar un nodo en `building-test` y base `property_hub_test`.
4. Registrar el nodo con el token.
5. Confirmar heartbeat en la central.
6. Publicar un ZIP de prueba en canal `development`.
7. Consultar el manifiesto desde el nodo.
8. Confirmar que la firma sea aceptada.
