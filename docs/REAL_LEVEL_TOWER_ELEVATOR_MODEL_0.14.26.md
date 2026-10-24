# Property Hub 0.14.26 — Torres, niveles físicos y ascensores

## Objetivo

El gemelo digital distingue el **nivel físico real** de su nombre visible. Una propiedad puede tener torres con recorridos distintos: por ejemplo, Torre A con 6 pisos y Torre B con 7, además de sótanos o planta baja compartidos.

## Jerarquía recomendada

```text
Edificio
├── Sótano 2 (compartido)
├── Sótano 1 (compartido)
├── PB (compartido)
├── Torre A
│   ├── Piso 1
│   ├── ...
│   └── Piso 6
└── Torre B
    ├── Piso 1
    ├── ...
    └── Piso 7
```

Un nivel exclusivo debe ser hijo de su Torre. Un nivel compartido por varias torres puede ser hijo directo del Edificio.

## Orden físico

Cada nivel puede almacenar un `logical_number` en `metadata_json`, usado como orden vertical real:

- S2 = -2
- S1 = -1
- PB = 0
- Piso 1 = 1
- Piso 2 = 2

El nombre y la etiqueta visible pueden ser diferentes sin romper la secuencia física.

## Ascensores

Cada ascensor declara una o varias torres/núcleos del **mismo edificio**. Al editarlo, Property sólo ofrece:

- los niveles pertenecientes a esas torres;
- los niveles compartidos del mismo edificio.

No permite guardar una parada perteneciente a otra torre o a otro edificio.

Las paradas se guardan ordenadas por su orden físico, no por orden alfabético.

## Control de acceso por parada

Un ascensor puede indicar que tendrá control de acceso y, además, marcar paradas específicas como restringidas. Esto prepara el modelo para drivers ZKTeco, Hikvision, Dahua y futuros controladores de ascensor.

## Diseñador

La configuración común de niveles es sólo una plantilla. Cada torre puede editarse por separado antes de generar la estructura. En el paso Ascensores, la selección de torres filtra dinámicamente las paradas disponibles.
