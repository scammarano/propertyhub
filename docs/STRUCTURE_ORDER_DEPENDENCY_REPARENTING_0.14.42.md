# Property Hub 0.14.42 — Structure Order / Dependency Reparenting

## Problema de orden

Versiones anteriores interpretaban cualquier nivel de tipo `basement` cuya etiqueta no fuera `S#`/`B#` con el fallback `-1`. En instalaciones con estacionamientos elevados `E1..E6`, todos esos niveles empataban con `S1`; el desempate alfabético colocaba `S1` después de los “Elevado”.

0.14.42 reconoce `E#` y `Elevado #` como orden positivo y repara en tiempo de lectura el patrón legado inequívoco `basement + logical_number=-1 + E#`, sin alterar órdenes explícitos distintos. El orden natural resultante es `S3, S2, S1, PB, E1, E2...`.

## Dependencias visibles

En **Diseño y unidades**, un contenedor con contenido muestra **Ver qué contiene**. El inspector lista:

- subelementos estructurales directos;
- áreas/espacios funcionales vinculados;
- conteos de activos, personas, accesos y cámaras relacionados.

Desde el inspector se pueden mover todos los subelementos a otro elemento superior y reubicar todas las áreas a otro elemento estructural, conservando su contenido. El botón de eliminar abre primero este inspector cuando existen dependencias.
