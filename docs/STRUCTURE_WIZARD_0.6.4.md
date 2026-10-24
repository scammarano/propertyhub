# Property Hub 0.6.4 — Asistente de estructura

## Objetivo

Crear en minutos la base territorial de un edificio o conjunto sin registrar manualmente cada torre, piso, apartamento, puesto y área.

## Ruta

`Gestión de la propiedad → Estructura → Asistente de estructura`

También aparece el botón **Generar estructura** en el listado `Estructura y unidades`.

## Flujo

1. **Base:** crea una estructura raíz o utiliza una existente y define la cantidad y nomenclatura de torres.
2. **Torres y pisos:** indica si todas tienen la misma cantidad de pisos y apartamentos, o configura cada torre individualmente.
3. **Nomenclatura:** define el primer piso, cantidad de dígitos y plantillas para apartamentos y estacionamientos.
4. **Áreas:** selecciona áreas estándar existentes en el inmueble.
5. **Vista previa:** revisa todos los códigos, nombres, dependencias y totales antes de guardar.

## Variables de nomenclatura

- `{TORRE}`: código de la torre.
- `{TORRE_N}`: número secuencial de la torre.
- `{PISO}`: etiqueta formateada del piso.
- `{PISO_NUM}`: número real del piso.
- `{APTO}`: número de unidad con ceros a la izquierda.
- `{N}`: secuencia sin formato.
- `{CODIGO}`: código final, disponible para el nombre visible.

Ejemplo de código: `{TORRE}-{PISO_NUM}{APTO}` → `A-101`.

## Áreas estándar

El catálogo inicial contempla lobby, recepción, administración, salón de eventos, cowork, parrillera, gimnasio, piscina, lavandería, salón de usos múltiples, cancha, parque infantil, áreas verdes, zona de mascotas, terraza, cuartos técnicos y centro de seguridad.

Cada área queda como registro real en `bh_areas` y puede editarse después para completar aforo, horarios, tarifas, depósitos y reglas.

## Seguridad del proceso

- Máximo de 10.000 elementos por ejecución.
- Validación de códigos duplicados antes de iniciar.
- Generación dentro de una transacción SQL.
- Si falla un registro, no se conserva una estructura parcial.
- Historial en `bh_structure_wizard_runs`.
- Detalle de elementos generados en `bh_structure_wizard_items`.
- Auditoría de generación y deshacer.

## Deshacer

Una ejecución puede deshacerse mientras sus elementos no tengan:

- Personas vinculadas.
- Reservas.
- Cámaras o puntos de acceso asociados.
- Áreas o subelementos creados después fuera del asistente.

Property Hub revisa las dependencias antes de eliminar. Si encuentra actividad real, bloquea el deshacer y conserva la información.

## Uso recomendado para Premier

1. Crear la raíz `Conjunto Premier Las Mercedes`.
2. Indicar la cantidad real de torres.
3. Revisar si todas tienen los mismos pisos.
4. Definir la nomenclatura que se utiliza actualmente en el condominio.
5. Crear los puestos de estacionamiento solo si serán gestionados individualmente.
6. Seleccionar exclusivamente las áreas que existen.
7. Revisar la vista previa antes de generar.
8. Completar posteriormente personas, relaciones, conectores y equipos reales.
