# Property Hub 0.8.0 — Diseñador topológico del inmueble

## Objetivo

El diseñador deja de asumir que un edificio es una repetición uniforme de pisos y apartamentos. Modela la ubicación física y lógica que luego utilizará el BMS multimarca para asociar personas, áreas, cámaras, switches, AP, puertas, lectoras, controladoras, torniquetes, ascensores y sensores.

## Jerarquía

La estructura puede contener:

- conjunto, urbanización o predio;
- edificios y torres;
- sótanos, planta baja, pisos, mezzaninas, terrazas, azoteas y pisos técnicos;
- apartamentos, oficinas, locales, estacionamientos y maleteros;
- áreas comunes, reservables, técnicas y de seguridad;
- ascensores y sus paradas;
- puertas planificadas por parada.

Un nivel puede existir con cero apartamentos y funcionar como contenedor de áreas y equipos.

## Nomenclaturas

Las reglas se configuran por separado para:

1. pisos y niveles;
2. apartamentos o unidades;
3. puestos de estacionamiento;
4. maleteros o depósitos.

El separador de códigos se selecciona antes de generar. Las plantillas usan variables completas; el motor reemplaza primero las variables más largas para evitar que `{TORRE}` altere `{TORRE_LETRA}`.

Variables principales:

- `{SEP}`
- `{TORRE}`, `{TORRE_NOMBRE}`, `{TORRE_LETRA}`, `{TORRE_N}`
- `{NIVEL}`, `{NIVEL_NUM}`, `{PISO}`, `{PISO_NUM}`, `{TIPO_NIVEL}`
- `{APTO}`, `{UNIDAD}`, `{LETRA}`, `{N}`, `{NUMERO}`, `{CODIGO}`
- `{ZONA}`, `{ZONA_NOMBRE}` para estacionamientos y maleteros.

## Áreas ubicadas

Cada área se crea como una instancia independiente con código, nombre y ubicación exacta. Es válido tener:

- piscina en PB general;
- gimnasio en PB de Torre A;
- cowork en PB de Torre B;
- cowork en terraza;
- salón de fiestas en sótano de Torre A;
- cuartos de fibra por piso;
- áreas de maleteros diferentes por torre.

El catálogo de áreas sirve como plantilla, no como limitación. Se pueden repetir tipos con códigos diferentes.

## Estacionamientos y maleteros

Se crean zonas independientes con ubicación, clasificación y nomenclatura propias:

- propietarios techado;
- propietarios al aire libre;
- visitantes techado;
- visitantes al aire libre;
- zonas mixtas;
- maleteros de propietarios, comunes o técnicos.

Cada zona puede estar en un sótano, nivel, torre o área general.

## Ascensores y circulación vertical

Cada elemento registra:

- código y nombre;
- tipo: pasajeros, servicio, carga, escalera mecánica o escalera;
- torres atendidas;
- paradas exactas;
- puertas de acceso planificadas por parada.

Así puede modelarse que los ascensores de C y D lleguen al sótano mientras los de A y B no lo hagan.

## Duplicación

La duplicación de una torre o edificio puede copiar:

- jerarquía completa;
- áreas;
- activos como planificación;
- cámaras y accesos;
- ascensores, paradas y puertas planificadas.

Los activos físicos duplicados no copian seriales, IP ni identificadores externos.

## Persistencia

La generación se realiza en una sola transacción y queda registrada en `bh_structure_wizard_runs`. La migración 0014 agrega niveles especiales, ubicación de accesos y tablas de circulación vertical.

## Nomenclaturas documentales y operativas

Cada familia de elementos mantiene reglas separadas para código y nombre visible:

- niveles y pisos;
- apartamentos o unidades;
- puestos de estacionamiento;
- maleteros o depósitos.

El diseñador pregunta el separador y ofrece formatos frecuentes, sin impedir una plantilla totalmente personalizada. También registra el origen de cada nomenclatura: documento de condominio, regla operativa interna, criterio mixto o pendiente de confirmación. Esta trazabilidad permite diferenciar el identificador legal del inmueble de un código interno creado para operar cámaras, accesos, redes o mantenimiento.

Las variables se sustituyen de mayor a menor longitud, por lo que `{TORRE}` no altera `{TORRE_LETRA}` ni `{TORRE_NOMBRE}`. Los ejemplos se recalculan antes de guardar y los códigos repetidos bloquean la generación.
