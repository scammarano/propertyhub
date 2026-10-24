# Property Hub Central

## Finalidad

Property Hub Central es la consola superior del producto. No es un edificio adicional ni una copia completa de la operación de los clientes.

## Menú canónico

```text
Property Hub Central
├── Vista ejecutiva
├── Red de instalaciones
│   ├── Instalaciones y conjuntos
│   ├── Nueva instalación
│   ├── Salud y sincronización
│   ├── Métricas consolidadas
│   ├── Política de datos
│   └── Soporte remoto
├── Catálogo técnico
│   ├── APIs de Property Hub
│   ├── Conectores y versiones
│   ├── APIs de fabricantes
│   └── Matriz de compatibilidad
├── Versiones y distribución
│   ├── Paquetes y releases
│   └── Tokens de instalación
└── Sistema central
    ├── Laboratorio y pruebas
    ├── Diagnóstico central
    └── Identidad del repositorio
```

## Nueva instalación

El asistente central crea un **expediente de instalación**, no la carpeta ni la base del cliente. Registra:

- Nombre y código único.
- Tipo de propiedad.
- URL prevista.
- Canal de actualización.
- Alcance de datos compartidos.
- Tecnologías previstas.
- Notas de levantamiento.

Luego genera un token de un solo uso, limitado al código y con vencimiento.

## Estados del expediente

- `ready`: preparación creada, sin token vigente.
- `token_issued`: token emitido y esperando al nodo.
- `registered`: el nodo se registró correctamente.
- `cancelled`: preparación cancelada y token revocado.

## Datos que recibe

Por defecto, Central puede recibir:

- Versión de Core, API y UI.
- Canal de actualización.
- Estado y última comunicación.
- Conteos de usuarios, unidades, dispositivos y conectores.
- Inventario técnico no sensible.
- Salud y colas.

## Datos que no debe recibir por defecto

- Nombres, cédulas o fotografías.
- Visitantes identificados.
- Historial individual de acceso.
- Contenido de cartelera o notificaciones operativas.
- Biometría.
- Contraseñas, tokens o secretos de fabricantes.
- URL RTSP con credenciales.

## Catálogo técnico

Central debe diferenciar:

- **APIs de Property Hub:** identidad, operación, repositorio, registro, heartbeat, manifiestos y paquetes.
- **APIs de fabricantes:** familias, versiones, compatibilidad y documentación necesaria para conectores.
- **Conectores:** componente de software nuestro que implementa una o varias APIs/protocolos.

## Soporte remoto

El soporte detallado futuro debe ser temporal, explícitamente aprobado por el nodo, limitado por alcance y auditado. El valor predeterminado es no permitir acceso a datos operativos.

## Demo

El Central puede cargar escenarios demostrativos para validar interfaz y métricas. Los códigos demo deben estar claramente identificados y nunca confundirse con nodos reales.

## Gestión de tokens y reconexión

Central no puede recuperar el texto de un token emitido porque guarda únicamente SHA-256. Debe ofrecer siempre acciones de reemplazo:

- `Regenerar token` para instalaciones en preparación.
- `Generar token de reconexión` desde la ficha de un Property Node registrado.
- `Revocar` para invalidar un token sin eliminar el expediente.

La reconexión actualiza las credenciales de la misma instalación y conserva su ID, métricas, historial, conectores y configuración de privacidad.
