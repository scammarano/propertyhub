# Flujo de nueva instalación — Property Hub 0.5.2

## Responsabilidad del Central

El Central crea el expediente, define el código, registra las tecnologías previstas, genera el token y espera el registro del nodo.

Ruta:

```text
Red de instalaciones → Nueva instalación
```

## Responsabilidad del Property Node

El nodo se instala en otra carpeta, servidor o dominio usando el mismo ZIP. Durante `/install/` se selecciona **Property Node / instalación operativa** y se suministran:

- Código exacto creado en el Central.
- URL pública del Central terminada en `/public`.
- Token mostrado por el asistente central.
- Base de datos independiente.
- Administrador local.

## Estados

- `ready`: expediente creado sin token vigente.
- `token_issued`: token generado y esperando instalación.
- `registered`: Property Node instalado y registrado.
- `cancelled`: preparación cancelada y token revocado.

## Privacidad

Las tecnologías previstas son inventario preliminar. Las direcciones IP, usuarios, contraseñas y secretos de APIs se configuran únicamente en el Property Node.
