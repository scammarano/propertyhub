# Modelo de integración ZKBioSecurity — Property Hub 0.6.1

## Decisión arquitectónica

En Premier, Property Hub **no se conecta directamente a los paneles inBio**. La integración se establece con el servidor donde funciona ZKBioSecurity. Ese servidor conserva la responsabilidad de descubrir, configurar, sincronizar y supervisar controladoras, lectores, puertas, torniquetes y ascensores.

```text
Property Hub Premier
        ↓
Conector ZKTeco ZKBioSecurity Server
        ↓
Servidor ZKBioSecurity Premier
        ↓
Paneles inBio, lectores y puntos de acceso
```

## Tres niveles separados

### Sistema o plataforma conectada

Es el endpoint con el que Property Hub establece comunicación. En Premier será una sola instancia denominada, por ejemplo, `Servidor ZKBioSecurity Premier`.

### Equipo administrado

Es un activo informado o administrado por la plataforma externa. Los inBio 160, 260 y 460 pertenecen a este nivel. Sus IP, seriales, firmware y ubicaciones pueden registrarse para inventario y diagnóstico, pero no se convierten en endpoints directos del conector.

### Punto de acceso funcional

Es el elemento que entiende el operador: puerta principal, torniquete, barrera, portón o ascensor. Se vinculará con el equipo técnico y con el identificador externo que informe ZKBioSecurity.

## Política para Premier

- Destino del conector: `platform_server`.
- Administra equipos aguas abajo: sí.
- Política de conexión directa: prohibida.
- Rol de los inBio: `managed_device`.
- Ruta de los inBio: `via_platform`.
- Escritura hacia ZKBioSecurity: deshabilitada hasta validar API, licencia, versión y sincronización.
- Base de datos: inicialmente solo lectura y únicamente si la API oficial no cubre el levantamiento requerido.

## Información pendiente de verificar

1. Nombre exacto del producto instalado.
2. Versión y build.
3. Licencia y disponibilidad de API.
4. Motor y versión de base de datos.
5. Servicios y endpoints documentados para esa edición.
6. Objetos disponibles: personas, credenciales, departamentos, niveles, eventos, puertas, controladoras y ascensores.
7. Mecanismo oficial de creación, actualización, revocación y sincronización.

## Alcance de esta versión

Property Hub 0.6.1 implementa el modelo de datos, formularios, validaciones y documentación. Todavía no realiza llamadas reales a ZKBioSecurity.
