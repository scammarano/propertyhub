# Conector ZKTeco ZKBioSecurity Server

## Estado

- Versión del conector: **0.1.1**
- Ciclo de vida: **research**
- Destino de conexión: **servidor/plataforma ZKBioSecurity**
- Conexión directa a paneles inBio en Premier: **prohibida por diseño**

En Property Hub 0.6.1 este directorio contiene el contrato, manifiesto y documentación del conector. Todavía no ejecuta operaciones sobre la instalación real.

## Arquitectura obligatoria para Premier

```text
Property Hub Premier
        ↓ API/servicio documentado o lectura controlada
Servidor ZKBioSecurity Premier
        ↓ comunicación administrada por ZKTeco
Paneles inBio, lectores, puertas, torniquetes y ascensores
```

Property Hub se conecta al servidor ZKBioSecurity. Los paneles inBio se registran como **equipos administrados por la plataforma** y pueden conservar IP, serial, firmware y ubicación como inventario o diagnóstico, pero esas direcciones no se convierten en endpoints directos del conector.

## Modos de integración previstos

1. API oficial correspondiente a la versión y licencia instaladas.
2. Servicios documentados por ZKTeco.
3. Conciliación de base de datos en solo lectura.
4. Escritura únicamente después de validar colas, sincronización y reglas internas de ZKBioSecurity.

## Capacidades a verificar

- Personas.
- Credenciales.
- Departamentos y niveles de acceso.
- Eventos.
- Dispositivos administrados.
- Puertas y torniquetes.
- Ascensores.
- Estado de sincronización.

## Información requerida antes de beta

- Nombre exacto del producto.
- Versión y build.
- Tipo de licencia y licencia API.
- Motor y versión de base de datos.
- Manual/API correspondiente a esa versión.
- Inventario de paneles registrado en ZKBioSecurity.
- Entorno de prueba y usuario de solo lectura.

## Privacidad

Las credenciales, endpoints y datos personales permanecen en el Property Node. Property Hub Central recibe solo metadatos, versión, capacidades, conteos y salud cuando la política local lo autorice.

## Documentación relacionada

- [Modelo servidor/plataforma y equipos administrados](../../docs/ZKBIO_SERVER_INTEGRATION_MODEL_0.6.1.md)
- [Conectores e integraciones](../../docs/readme/README_15_CONECTORES_E_INTEGRACIONES.md)
- [Seguridad y auditoría](../../docs/readme/README_17_SEGURIDAD_AUDITORIA_Y_RESPALDOS.md)
