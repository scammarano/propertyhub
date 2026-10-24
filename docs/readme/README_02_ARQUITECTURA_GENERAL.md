# Arquitectura general

## Componentes principales

### Property Hub Central

Es la fuente oficial del producto y el plano superior de administración. Registra instalaciones, prepara despliegues, publica versiones, mantiene catálogos de APIs/conectores, recibe salud y métricas agregadas y controla la distribución.

### Property Node

Es la instalación operativa de cada cliente. Contiene su base de datos, usuarios, estructura territorial, personas, visitantes, reservas, comunicaciones, dispositivos, conectores y secretos técnicos.

### Resident App

Experiencia web/PWA móvil destinada a residentes, propietarios, inquilinos, empleados o usuarios autorizados. Presenta solo acciones personales y comprensibles.

### Operations Console

Experiencia administrativa para administración, vigilancia, recepción, operadores y soporte técnico. Incluye dashboard, tablas, calendarios, aprobaciones, configuración local, diagnóstico y actualizaciones.

## Relación entre componentes

```text
                        PROPERTY HUB CENTRAL
             instalaciones · versiones · APIs · salud
                               ↑     ↓
              registro · heartbeat · manifiestos · paquetes
                               ↑     ↓
                         PROPERTY NODE
        datos locales · reglas · usuarios · módulos · conectores
             ↓             ↓             ↓              ↓
         ZKTeco       Cámaras/NVR      Grandstream    UniFi/IoT
```

El nodo inicia las comunicaciones hacia el Central. El Central no debe conectarse directamente a las controladoras, cámaras, NVR o bases internas del cliente.

## Un servidor operativo por instalación

La arquitectura predeterminada utiliza un servidor principal por propiedad o complejo. Las garitas y zonas remotas se conectan a ese servidor mediante fibra, UTP, Wi‑Fi, VPN, radioenlace, Internet o red celular. No existe un requisito de “Property Edge” por garita.

Los gateways que aparezcan pertenecen a tecnologías que realmente los necesitan, por ejemplo:

- Gateway TTLock para operación remota de cerraduras.
- Gateway LoRaWAN para recibir radio de sensores.
- Gateway de video para convertir RTSP a WebRTC/HLS.

## Arquitectura multimarca

El núcleo define conceptos independientes:

- Persona.
- Unidad o propiedad.
- Credencial.
- Permiso.
- Punto de acceso.
- Cámara.
- Área.
- Invitación.
- Evento.
- Conector.

Cada conector traduce esos conceptos a la terminología y capacidades del fabricante. La interfaz debe consultar capacidades antes de mostrar una acción.

## Datos centralizados y distribuidos

La arquitectura es federada:

- **Centralizado:** identidad futura global, directorio de instalaciones, versiones, APIs, catálogos, métricas agregadas y notificaciones de producto.
- **Distribuido:** residentes, visitantes, credenciales, accesos, cámaras, reservas, mensajes, documentos y secretos de cada propiedad.

## Separación de experiencias

Resident App y Operations Console usan las mismas reglas y fuentes de datos, pero no comparten el mismo layout. El residente no recibe un dashboard administrativo reducido; obtiene una experiencia táctil, rápida y enfocada en acciones frecuentes.

## Estado actual

La arquitectura, instalador dual, registro Central/Nodo, catálogos y esquemas están presentes. Varias funciones operativas visibles continúan usando información demo en sesión y los conectores físicos todavía son contratos/manifiestos sin ejecución real. Consultar [README_09_MATRIZ_DE_ESTADO_FUNCIONAL.md](README_09_MATRIZ_DE_ESTADO_FUNCIONAL.md).
