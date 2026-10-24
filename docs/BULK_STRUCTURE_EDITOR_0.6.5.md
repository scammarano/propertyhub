# Property Hub 0.6.5 — Edición masiva de estructura

## Objetivo

El asistente de estructura crea una base rápida, pero un inmueble real puede tener pisos atípicos, penthouses, niveles técnicos o pisos sin apartamentos. La versión 0.6.5 añade herramientas para corregir esa base sin editar registro por registro.

## Ruta

`Gestión de la propiedad → Estructura y unidades`

Cada fila incluye una casilla de selección. Al marcar uno o más registros aparece una barra de acciones masivas.

## Acciones disponibles

### Mover

Cambia el elemento superior de varios registros. Si se selecciona una torre junto con sus pisos y apartamentos, Property Hub mueve únicamente la torre y conserva la jerarquía interna. Se bloquean movimientos que producirían ciclos.

### Cambiar estado

Aplica `Activo`, `Mantenimiento` o `Inactivo` a toda la selección.

### Cambiar tipo

Corrige la clasificación de varios elementos, por ejemplo convertir registros cargados como `Otro` en `Piso`, `Apartamento`, `Estacionamiento` u otro tipo válido.

### Renombrar

Permite aplicar reglas sobre códigos y nombres:

- buscar y reemplazar;
- agregar prefijo;
- agregar sufijo.

La operación valida previamente que los códigos finales no estén repetidos. Usa códigos temporales dentro de la transacción para admitir intercambios seguros.

### Eliminar

Los elementos sin relaciones se eliminan físicamente. Los que tengan hijos, personas, áreas o reservas se marcan `Inactivos`, evitando pérdida de historial y errores de integridad.

## Pisos con cantidades diferentes

El asistente continúa siendo un generador de estructura base. Después de generarla se puede:

1. filtrar por torre o piso;
2. seleccionar los apartamentos sobrantes de un piso;
3. eliminarlos masivamente;
4. dejar un piso sin apartamentos;
5. mover apartamentos a otro piso;
6. renombrar códigos y nombres en lote.

Una fase posterior podrá incorporar excepciones por piso directamente dentro del asistente; la edición masiva ya permite resolver el escenario real sin reconstruir toda la estructura.

## Seguridad

- Máximo 500 registros por operación.
- Toda acción se limita a la instalación actual.
- Todas las operaciones se ejecutan dentro de una transacción.
- Los movimientos circulares se rechazan.
- La eliminación nunca ejecuta cascadas destructivas.
- Cada acción se registra en auditoría.
- La selección actual se limita a la página visible del grid.

## Base de datos

No se agregan tablas ni columnas. La versión reutiliza `bh_properties` y el registro de auditoría existente. Por ello no existe una migración SQL 0013.
