# Validación Property Hub 0.5.2

## Revisiones realizadas

- Archivos PHP revisados con `php -l`: **119**.
- Manifiestos y archivos JSON parseados: **24**.
- Migraciones incluidas: **8**.
- Migraciones 0001–0007 comparadas contra 0.5.1: checksums preservados.
- Nueva migración: `0008_installation_provisioning.sql`.
- Rutas verificadas: listado, nueva instalación, creación, cancelación, registro del nodo y carga de demo.
- Menú central verificado con acceso directo **Nueva instalación**.
- Demo central verificado con cuatro instalaciones registradas y dos preparaciones.
- Validación de estructura ZIP y ausencia de `.env`, `installed.lock` y claves privadas antes de entrega.

## Flujo cubierto

1. Crear expediente en el Central.
2. Generar token limitado al código, de un uso y con vigencia de 30 días.
3. Mostrar token una sola vez.
4. Instalar otra copia como Property Node.
5. Registrar el nodo mediante Repository API v1.
6. Aplicar al nodo el alcance de telemetría definido en el expediente.
7. Cambiar el expediente a estado registrado.
8. Revocar token al cancelar una preparación.

## Limitación del entorno de construcción

Este entorno no dispone de MariaDB ni del controlador PHP `pdo_mysql`. La sintaxis PHP, JSON, estructura de migraciones y división SQL fueron verificadas, pero la ejecución final de la migración y del flujo de registro debe probarse en XAMPP/MariaDB.
