# Property Hub 0.10.5 — Respaldo UX e instalaciones de prueba

## Respaldo y recuperación

La pantalla se reorganiza para reducir altura y eliminar ambigüedad:

- resumen superior con último respaldo, versión, política y runner;
- acciones manuales separadas de la política automática;
- configuración automática colapsable;
- tabla de respaldos a ancho completo;
- nombre amigable, versión de origen y contenido resumido;
- acciones separadas: descargar, ver contenido, preparar restauración y eliminar;
- restauración colapsada hasta que el administrador la necesite.

Los respaldos nuevos registran en el manifiesto:

- tamaño del dump SQL;
- tablas, vistas y filas;
- cantidad y tamaño de archivos persistentes;
- desglose por carpeta de `storage`;
- sistemas externos declarados;
- destinos del respaldo.

### Exclusiones

Un `.phbackup` de Property Node no debe crecer por haber actualizado el software. Por eso 0.10.5 excluye explícitamente:

- `storage/backups/`
- `storage/logs/`
- `storage/tmp/`
- `storage/cache/`
- `storage/sessions/`
- `storage/updates/`
- `storage/installed.lock`
- paquetes de repositorio locales en Property Nodes

El Central puede conservar su `storage/repository/` porque allí se encuentra el repositorio oficial distribuible.

## Instalaciones de prueba en Property Hub Central

Se añade una clasificación independiente de los datos demo:

- `production`: Producción
- `test`: Prueba
- `lab`: Laboratorio

La clasificación puede cambiarse desde la ficha del nodo.

### Duplicar expediente

Duplicar desde Central crea:

- nuevo código;
- nuevo expediente;
- nuevo token de registro;
- mismo tipo de inmueble;
- mismo canal;
- mismo alcance de telemetría;
- tecnologías declaradas equivalentes.

No copia personas, apartamentos, credenciales, visitantes ni otros datos privados porque el Central no los almacena. Para copiar datos reales se restaura un `.phbackup` en modo `safe_clone`.

### Eliminar instalación de prueba

La eliminación física solo está disponible para `test`, `lab` o registros demo. Requiere escribir el código exacto y, si el nodo reportó actividad reciente, confirmar que se entiende que quedará desvinculado.

Se eliminan del Central:

- identidad del nodo;
- tokens;
- métricas;
- conectores declarados;
- soporte;
- historial de entrega y asignaciones.

El expediente de preparación queda cancelado para conservar trazabilidad y liberar un futuro registro con el mismo código.

Opcionalmente se pueden eliminar también las copias cifradas offsite guardadas por Central.

Las instalaciones de Producción no pueden eliminarse físicamente. Primero deben reclasificarse explícitamente si realmente eran pruebas.
