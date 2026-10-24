# Índice de documentación de Property Hub

Este directorio contiene la documentación canónica de **2CAM Property Hub**. El `README.md` ubicado en la raíz del proyecto funciona como portada y apunta a estos documentos especializados.

## Cómo utilizar esta documentación

- Para entender qué producto estamos construyendo, comenzar por **Visión y alcance**.
- Para instalar o desplegar, leer **Filosofía de instalación** y **Ciclo de vida de una instalación**.
- Para administrar la fuente oficial, leer **Property Hub Central**.
- Para administrar un cliente, leer **Property Node** y **Operations Console**.
- Para programar, revisar **Filosofía de desarrollo**, **Modelo de datos** y **Estándar visual**.
- Para saber qué funciona hoy y qué sigue siendo demostración o planificación, consultar obligatoriamente la **Matriz de estado funcional**.

## Documentos canónicos

| Documento | Contenido principal |
|---|---|
| [README_01_VISION_Y_ALCANCE.md](README_01_VISION_Y_ALCANCE.md) | Propósito, clientes, tipos de propiedad y límites del producto. |
| [README_02_ARQUITECTURA_GENERAL.md](README_02_ARQUITECTURA_GENERAL.md) | Central, nodos, Resident App, Operations Console y conectividad. |
| [README_03_FILOSOFIA_DE_INSTALACION.md](README_03_FILOSOFIA_DE_INSTALACION.md) | Un solo código, dos perfiles, bases independientes y operación local. |
| [README_04_PROPERTY_HUB_CENTRAL.md](README_04_PROPERTY_HUB_CENTRAL.md) | Portafolio, instalaciones, tokens, APIs, releases, salud y privacidad. |
| [README_05_PROPERTY_NODE.md](README_05_PROPERTY_NODE.md) | Operación de cada urbanización, edificio, oficina o complejo. |
| [README_06_CICLO_DE_VIDA_DE_INSTALACIONES.md](README_06_CICLO_DE_VIDA_DE_INSTALACIONES.md) | Preparación, instalación, registro, producción, suspensión y retiro. |
| [README_07_ACTUALIZACIONES_VERSIONES_Y_APIS.md](README_07_ACTUALIZACIONES_VERSIONES_Y_APIS.md) | Repositorio, componentes, canales, firmas, compatibilidad y rollback. |
| [README_08_FILOSOFIA_Y_REGLAS_DE_DESARROLLO.md](README_08_FILOSOFIA_Y_REGLAS_DE_DESARROLLO.md) | Principios técnicos, módulos, migraciones, CRUD, errores y calidad. |
| [README_09_MATRIZ_DE_ESTADO_FUNCIONAL.md](README_09_MATRIZ_DE_ESTADO_FUNCIONAL.md) | Diferencia entre funcional, base preparada, demo y futuro. |
| [README_10_FUNCIONALIDAD_DEL_PRODUCTO.md](README_10_FUNCIONALIDAD_DEL_PRODUCTO.md) | Todos los ámbitos funcionales del sistema. |
| [README_11_RESIDENT_APP.md](README_11_RESIDENT_APP.md) | Experiencia móvil/PWA, propiedades múltiples y funciones del usuario. |
| [README_12_OPERATIONS_CONSOLE.md](README_12_OPERATIONS_CONSOLE.md) | Administración, vigilancia, recepción, soporte y reglas de interfaz. |
| [README_13_IDENTIDAD_ROLES_Y_PERMISOS.md](README_13_IDENTIDAD_ROLES_Y_PERMISOS.md) | Persona, usuario, membresía, Google/Apple y autorización por propiedad. |
| [README_14_MODELO_DE_DATOS_Y_PRIVACIDAD.md](README_14_MODELO_DE_DATOS_Y_PRIVACIDAD.md) | Fuentes de verdad, tablas, telemetría y separación de información. |
| [README_15_CONECTORES_E_INTEGRACIONES.md](README_15_CONECTORES_E_INTEGRACIONES.md) | Contrato multimarca y hoja de ruta de fabricantes/protocolos. |
| [README_16_INFRAESTRUCTURA_RED_Y_LORAWAN.md](README_16_INFRAESTRUCTURA_RED_Y_LORAWAN.md) | Sedes, garitas, enlaces, dispositivos aislados e IoT de largo alcance. |
| [README_17_SEGURIDAD_AUDITORIA_Y_RESPALDOS.md](README_17_SEGURIDAD_AUDITORIA_Y_RESPALDOS.md) | Protección, secretos, auditoría, soporte remoto y recuperación. |
| [README_18_OPERACION_MANTENIMIENTO_Y_SOPORTE.md](README_18_OPERACION_MANTENIMIENTO_Y_SOPORTE.md) | Diagnóstico, logs, migraciones, demo, reparaciones y producción. |
| [README_19_PRUEBAS_Y_CALIDAD.md](README_19_PRUEBAS_Y_CALIDAD.md) | Estrategia de pruebas, criterios de aceptación y demo controlada. |
| [README_20_ESTANDAR_VISUAL_Y_EXPERIENCIAS.md](README_20_ESTANDAR_VISUAL_Y_EXPERIENCIAS.md) | UI común 1.2.0, Resident App, grids, modales y responsive. |
| [README_21_RUTA_DE_DESARROLLO.md](README_21_RUTA_DE_DESARROLLO.md) | Prioridades para pasar de base funcional a producto operativo. |
| [README_22_GLOSARIO.md](README_22_GLOSARIO.md) | Términos oficiales del proyecto. |
| [README_23_GOBIERNO_DE_DOCUMENTACION.md](README_23_GOBIERNO_DE_DOCUMENTACION.md) | Reglas para mantener README, changelog y documentación por versión. |

## Documentación histórica o específica de una versión

La carpeta `docs/` también conserva guías de migración, validaciones y decisiones de versiones anteriores. Esos documentos sirven para trazabilidad, pero cuando exista una diferencia, prevalece este conjunto canónico y el `CHANGELOG.md` de la versión instalada.

## Documentación de la versión 0.7.0

- [Diseñador de estructura](../STRUCTURE_DESIGNER_0.7.0.md)
- [Actualización 0.6.7 → 0.7.0](../UPGRADE_0.6.7_TO_0.7.0.md)
- [Validación 0.7.0](../VALIDATION_V0.7.0.md)

## Documentación de la versión 0.10.0

- [Respaldo y recuperación](../BACKUP_RECOVERY_0.10.0.md)
- [Actualización 0.9.3 → 0.10.0](../UPGRADE_0.9.3_TO_0.10.0.md)
- [Prueba real de recuperación — Premier](../PREMIER_DISASTER_RECOVERY_TEST_0.10.0.md)
- [Validación 0.10.0](../VALIDATION_V0.10.0.md)

## Documentación de la versión 0.9.3

- [Hotfix de arranque del instalador](../HOTFIX_INSTALLER_BOOTSTRAP_0.9.3.md)
- [Actualización 0.9.2 → 0.9.3](../UPGRADE_0.9.2_TO_0.9.3.md)
- [Validación 0.9.3](../VALIDATION_V0.9.3.md)

## Documentación de la versión 0.9.1

- [Corrección del Asistente de diseño](../HOTFIX_WIZARD_NAVIGATION_0.9.1.md)
- [Actualización 0.9.0 → 0.9.1](../UPGRADE_0.9.0_TO_0.9.1.md)
- [Validación 0.9.1](../VALIDATION_V0.9.1.md)

## Documentación de la versión 0.9.0

- [Property App con datos reales](../RESIDENT_APP_REAL_DATA_0.9.0.md)
- [Prueba desde teléfono](../MOBILE_TEST_GUIDE_0.9.0.md)
- [Actualización 0.8.0 → 0.9.0](../UPGRADE_0.8.0_TO_0.9.0.md)
- [Validación 0.9.0](../VALIDATION_V0.9.0.md)

## Documentación de la versión 0.10.5

- [Respaldo UX e instalaciones de prueba](../BACKUP_UI_TEST_INSTALLATIONS_0.10.5.md)
- [Actualización 0.10.4 → 0.10.5](../UPGRADE_0.10.4_TO_0.10.5.md)
- [Validación 0.10.5](../VALIDATION_V0.10.5.md)

## Documentación de la versión 0.10.6

- [Central Backup Vault y recuperación remota](../CENTRAL_BACKUP_VAULT_0.10.6.md)
- [Actualización 0.10.5 → 0.10.6](../UPGRADE_0.10.5_TO_0.10.6.md)
- [Validación 0.10.6](../VALIDATION_V0.10.6.md)

## Documentación de la versión 0.10.7

- [Hotfix Central/cPanel y repositorio](../HOTFIX_CPANEL_REPOSITORY_0.10.7.md)
- [Actualización 0.10.6 → 0.10.7](../UPGRADE_0.10.6_TO_0.10.7.md)
- [Validación 0.10.7](../VALIDATION_V0.10.7.md)

## Documentación de la versión 0.10.8

- [Hotfix de compatibilidad del actualizador](../HOTFIX_UPDATE_COMPATIBILITY_0.10.8.md)
- [Actualización a 0.10.8](../UPGRADE_TO_0.10.8.md)
- [Validación 0.10.8](../VALIDATION_V0.10.8.md)

## Documentación de la versión 0.10.9

- [Hotfix Windows / XAMPP self-update](../HOTFIX_WINDOWS_SELF_UPDATE_0.10.9.md)
- [Validación 0.10.9](../VALIDATION_V0.10.9.md)
