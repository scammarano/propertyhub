# Validación 0.10.0

Validaciones estáticas y unitarias realizadas en el paquete:

- sintaxis PHP de todos los archivos;
- cifrado/descifrado AES-256-GCM por bloques;
- rechazo de clave incorrecta;
- presencia de migración 0016 en instalador, reparador y CLI;
- protección PRE_UPDATE también en el primer salto 0.9.x → 0.10.0, antes de que existan las tablas del módulo;
- rutas de backup/restore y navegación Central/Node;
- endpoint Central `backup-upload` y autenticación de nodo;
- redacción de recovery keys en el logger;
- versión 0.10.0 propagada al core e instalador.

La prueba definitiva de dump/restore MariaDB, `ZipArchive`, rutas UNC y tarea `schtasks` debe ejecutarse en el XAMPP real. El entorno de empaquetado no dispone de un servidor MariaDB activo ni de la extensión PHP ZipArchive, por lo que no se afirma una prueba transaccional que no se pudo realizar aquí.
