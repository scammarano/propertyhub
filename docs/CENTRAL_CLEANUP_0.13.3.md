# Property Hub 0.13.3 — Limpieza controlada de Central

## Objetivo

Limpiar instalaciones Demo, Prueba y Laboratorio acumuladas durante desarrollo sin tocar los Property Nodes/Edge Agents de Producción ni sus copias cifradas del Backup Vault.

## Flujo

1. Abrir **Red de instalaciones → Limpieza de pruebas**.
2. Verificar la sección **Instalaciones protegidas**. Producción no es seleccionable.
3. Seleccionar únicamente los expedientes Demo/Prueba/Laboratorio a eliminar.
4. Mantener desmarcada **Eliminar copias del Backup Vault** salvo que también se quieran destruir esos archivos cifrados.
5. Escribir el código de Property Hub Central y ejecutar la limpieza.

Si se selecciona un Property Node de prueba con Edge Agents hijos, deben seleccionarse también los Agents. Los Agents se eliminan primero para no dejar vínculos huérfanos.

## Limpieza complementaria

La pantalla puede eliminar preparaciones Prueba/Laboratorio que nunca llegaron a registrarse y tokens inactivos/expirados/usados que ya no pertenecen a una instalación ni a una preparación.

Los tokens activos de instalaciones vigentes no se eliminan.
