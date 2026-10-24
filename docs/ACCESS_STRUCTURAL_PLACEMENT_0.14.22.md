# Property Hub 0.14.22 — Ubicación estructural de accesos

## Objetivo

Una puerta física descubierta por ZKBio no debe obligar al administrador a crear primero un Área. Puede ubicarse directamente en cualquier elemento de la estructura real del inmueble: edificio, torre, piso, sótano, estacionamiento estructural, unidad u otro contenedor.

## Flujo de adopción

Desde **ZKBio · Integración operativa → Puertas descubiertas**:

1. Seleccionar **Ubicar y adoptar**.
2. Elegir la ubicación física mínima en el árbol Property (por ejemplo `Edificio Premier › Sótano 1`).
3. Opcionalmente seleccionar un Área/Espacio ya existente.
4. Si falta el área, crearla en el mismo formulario sin abandonar ZKBio Lab.
5. Opcionalmente indicar los dos espacios que la puerta separa (Lado A y Lado B), por ejemplo `Estacionamiento S1 ↔ Lobby ascensores S1`.

La puerta puede quedar correctamente ubicada sólo en `Sótano 1`; los lados/áreas son una capa adicional de precisión, no un requisito.

## Alcances de acceso

La ubicación estructural determina automáticamente el alcance lógico inicial:

- edificio/site → `property`
- torre → `tower`
- piso/sótano/mezzanina/terraza → `floor`
- apartamento/oficina/local/unidad → `unit`
- sin ubicación → `common`

Esto permite que los Perfiles de Acceso (`común`, `su torre`, `su piso`, `su unidad`) funcionen sin configurar persona por persona.

## Lados de una puerta

La migración `0035_access_point_structural_placement.sql` incorpora `bh_access_point_sides`.

Cada punto de acceso puede registrar opcionalmente:

- Lado A
- Lado B
- propiedad estructural
- área/espacio
- etiqueta
- pista de dirección futura

Esto prepara la normalización de lectores Entrada/Salida de ZKTeco, Hikvision, Dahua y otros proveedores.

## Edición posterior

Una puerta ya adoptada puede volver a abrir **Ubicar** desde ZKBio Lab para cambiar su piso/sótano/área o completar sus lados, sin recrear el binding físico.

La pantalla general **Control de accesos** muestra ahora por separado la ubicación estructural, el área específica y, cuando existen, los dos espacios que separa la puerta.
