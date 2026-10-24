# Visión y alcance de Property Hub

## Qué es Property Hub

**2CAM Property Hub** es una plataforma modular, multimarca y multiinstalación para administrar propiedades y complejos físicos. Unifica en una experiencia comprensible la operación de residentes, propietarios, inquilinos, empleados, visitantes, áreas comunes, comunicaciones, cámaras, accesos, intercomunicadores, redes de invitados, automatización e IoT.

Property Hub no se diseña exclusivamente para edificios residenciales. Debe servir para:

- Urbanizaciones y conjuntos de casas.
- Edificios residenciales y conjuntos de torres.
- Edificios de oficinas y centros empresariales.
- Locales comerciales y centros comerciales.
- Campus, clubes, parques empresariales y complejos industriales.
- Propiedades vacacionales y complejos mixtos.

## Problema que resuelve

Los fabricantes suelen ofrecer aplicaciones técnicas separadas: ZKTeco para control de acceso, Hikvision o Dahua para video y accesos, Vimar para videoportero/automatización, UniFi para red, TTLock para cerraduras, Grandstream para telefonía y perifonía, entre otros. Sus interfaces están orientadas a técnicos, contienen campos difíciles de interpretar y no comparten una visión común de la propiedad.

Property Hub crea una capa propia que habla el lenguaje del usuario:

- “Crear visitante” en lugar de configurar manualmente un departamento y un nivel externo.
- “Abrir portón” en lugar de operar un canal de relé.
- “Ver cámara de la garita” sin exponer la contraseña RTSP.
- “Reservar parrillera” con calendario y aprobación.
- “Avisar corte de agua” a una audiencia concreta.

## Principios del producto

1. **La propiedad es el centro; la marca es un conector.** Una persona o una puerta no pertenece conceptualmente a ZKTeco, Hikvision o TTLock: pertenece a Property Hub y puede mapearse a uno o varios sistemas externos.
2. **Operación local e independencia.** Cada cliente conserva su base, configuración, secretos y capacidad de operar localmente.
3. **Central superior, no espejo invasivo.** Property Hub Central supervisa instalaciones, versiones, conectores, salud y métricas agregadas, sin copiar por defecto la operación privada.
4. **Una sola aplicación para el usuario.** La arquitectura contempla que una persona pueda acceder a varias propiedades con una identidad común y cambiar rápidamente de contexto.
5. **Dos experiencias, un mismo núcleo.** Resident App es móvil y simple; Operations Console es administrativa y completa.
6. **Actualizaciones por componentes.** Core, UI, módulos, conectores, APIs, reportes y migraciones pueden evolucionar de forma controlada.
7. **Integración segura y auditable.** Toda apertura, asignación de permiso, visualización sensible o soporte remoto debe tener autorización y trazabilidad.

## Lo que Property Hub no pretende hacer inicialmente

- No reemplaza la programación técnica de controladoras, NVR o PBX cuando el fabricante ya la resuelve adecuadamente.
- No administra plantillas biométricas propietarias de forma directa.
- No transmite RTSP directamente al navegador ni expone credenciales de cámaras.
- No obliga a instalar una computadora adicional en cada garita.
- No convierte el Central en una base consolidada de residentes y visitantes de todos los clientes.

## Modelo territorial

El modelo debe admitir estructuras diferentes sin obligar a utilizar niveles innecesarios:

```text
Instalación
├── Sede principal
├── Sectores / etapas / calles / manzanas
├── Torres / pisos / unidades
├── Parcelas / casas
├── Oficinas / locales / depósitos
├── Garitas
├── Parques y áreas comunes
├── Áreas técnicas
└── Zonas perimetrales
```

## Resultado esperado

El producto final permitirá desplegar la misma plataforma en muchos clientes, personalizar identidad y módulos por instalación, conectar tecnologías distintas, conservar la operación local y mantener todo el ecosistema actualizado desde una fuente oficial.
