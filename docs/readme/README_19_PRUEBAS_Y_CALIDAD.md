# Pruebas y calidad

## Estrategia

Property Hub requiere pruebas separadas para Central, Node, Resident App, Operations Console, instalador, migraciones, APIs, actualizaciones y conectores.

## Matriz mínima por release

### Instalador

- Instalación Central limpia.
- Instalación Node limpia, online y offline.
- Base existente vacía.
- Credenciales inválidas.
- URL sin `/public`.
- Bloqueo de reinstalación.

### Migraciones

- Desde versión anterior soportada.
- Reejecución segura.
- Fallo intermedio y recuperación.
- Checksums inmutables.

### Central/Node

- Crear expediente y token.
- Registrar nodo.
- Token vencido, revocado o reutilizado.
- Heartbeat válido/inválido.
- Aislamiento de datos y permisos.

### Interfaces

- Login y logout.
- Resident App móvil.
- Operations Console escritorio/tablet.
- Sidebar persistente y operativo.
- Grids, filtros, paginación y modales.
- Rutas 403/404/500.

### Seguridad

- CSRF.
- Acceso por rol.
- Secretos no expuestos.
- Sesión y cookies.
- Traversal en paquetes.
- Firma/hash alterados.

### Conectores

- Sandbox/mocks.
- Timeout, desconexión y credencial inválida.
- Reintento/idempotencia.
- Compatibilidad por versión/modelo.
- No ejecutar acciones si la capacidad no existe.

## Datos demo

El demo debe ser realista, identificado y restablecible. No debe utilizar datos personales reales ni mezclarse con instalaciones registradas. Cada pantalla demo debe indicar cuando una acción no opera hardware.

## Definición de aceptación

Una entrega se aprueba cuando:

- Pasa lint/validación de JSON.
- Migra desde la versión soportada.
- Mantiene configuración y datos.
- Cumple permisos.
- Documenta cambios y pendientes.
- Se prueba en XAMPP/MariaDB real.
- No rompe Resident App ni Operations Console.

## Automatización pendiente

Se deben añadir pruebas unitarias, integración con MariaDB efímero, pruebas HTTP de APIs, escaneo de dependencias y pruebas end-to-end de navegador.
