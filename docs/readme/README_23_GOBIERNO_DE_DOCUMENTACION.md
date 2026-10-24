# Gobierno de documentación

## Objetivo

La documentación es parte del producto y debe actualizarse en la misma entrega que el código. Ninguna versión debe depender únicamente de conversaciones o notas externas.

## Estructura obligatoria

- `README.md`: portada, instalación rápida e índice.
- `CHANGELOG.md`: cambios por versión.
- `VERSION.json`: versión y capacidades publicadas.
- `docs/readme/`: documentación canónica por ámbito.
- `docs/`: validaciones, migraciones, hotfixes y decisiones específicas.
- `modules/*/README.md`: alcance y estado del módulo.
- `connectors/*/README.md`: alcance, capacidades y estado del conector.

## Regla del README principal

El README principal no debe intentar contener todo. Debe:

- Explicar qué es el producto.
- Advertir el estado funcional real.
- Dar el flujo rápido de instalación.
- Enlazar cada README especializado.
- Indicar versión y requisitos.

## Cambios que obligan a actualizar documentación

- Nueva migración.
- Nuevo módulo/conector.
- Cambio de menú o ruta.
- Cambio de perfil Central/Node.
- Cambio de API o manifiesto.
- Función que pasa de demo a persistente.
- Nueva política de privacidad/seguridad.
- Cambio del estándar visual.
- Nuevo procedimiento de instalación, respaldo o recuperación.

## Etiquetas de estado

Toda función y conector debe usar una etiqueta consistente: `planned`, `research`, `prototype`, `beta`, `stable` o `deprecated`. La documentación debe distinguir explícitamente demo, esquema y funcionalidad real.

## Trazabilidad

Los documentos específicos de una versión se conservan. No se reescriben para aparentar que una función anterior ya existía. Los documentos canónicos reflejan el estado actual y enlazan la historia cuando sea necesario.

## Validación

Antes de empaquetar:

- Verificar enlaces Markdown.
- Validar JSON.
- Ejecutar lint PHP.
- Confirmar versión consistente.
- Confirmar que no se incluyeron `.env`, claves ni datos privados.
- Generar checksum del ZIP.
