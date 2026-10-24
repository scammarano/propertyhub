# Diseñador de estructura Property Hub 0.7.0

## Objetivo

Representar el inmueble como un plano lógico y jerárquico sobre el cual se ubican comunidad, áreas, accesos, cámaras, red, automatización y demás componentes del BMS multimarca.

## Jerarquía

Los pisos son entidades persistentes aunque no tengan apartamentos. Un piso puede contener simultáneamente:

- apartamentos, oficinas o locales;
- cowork, lavandería o salón;
- cuarto de fibra, rack, electricidad o servicios;
- switches, AP, PoE, cámaras, lectores y sensores;
- puertas, torniquetes y control de ascensores.

## Nomenclatura

El asistente admite una torre única sin sufijo automático y múltiples torres con letras, números, prefijo o nombres personalizados. Las unidades usan plantillas con variables como `{TORRE}`, `{TORRE_LETRA}`, `{PISO_NUM}`, `{LETRA}`, `{APTO}` y `{N}`.

## Excepciones por piso

En “Configurar pisos” se define para cada piso:

- si se crea;
- número o etiqueta;
- cantidad de apartamentos;
- cero apartamentos para conservarlo como grupo técnico u operativo.

## Edición masiva

La selección múltiple permite mover, reclasificar, cambiar estado, renombrar y eliminar. La operación se ejecuta en transacción, valida códigos y conserva las relaciones internas de ramas completas.

## Duplicación de diseño

Una torre o edificio se puede duplicar con toda su jerarquía y, opcionalmente:

- áreas;
- activos técnicos;
- puntos de acceso;
- cámaras.

Los equipos copiados no conservan serial, IP ni identificador externo y quedan en etapa `planned`.

## Áreas repetidas

El tipo no debe ser único. Se pueden registrar varios cowork, lavanderías o cuartos técnicos en diferentes ubicaciones. El código sí debe ser único, por ejemplo `COWORK-P2` y `COWORK-P7`.

## Ubicación tecnológica

Un activo puede tener ubicación estructural, área específica y ubicación técnica de red. Esto permite saber no solo qué equipo existe, sino dónde está, qué alimenta y qué plataforma lo administra.
