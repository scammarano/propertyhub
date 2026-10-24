# Property Hub 0.10.7 — Corrección cPanel, diagnóstico y publicación del Core

Los logs reales del Central 0.10.6 mostraron dos errores encadenados:

1. `ErrorReporter::report()` aceptaba solo un `string`, pero varias rutinas nuevas enviaban un arreglo de contexto. El logger producía un `TypeError` y ocultaba el error original.
2. El ZIP distribuible del Core incluía `storage/uploads/.gitkeep`. Esa ruta está protegida deliberadamente por `ReleasePublisher`, por lo que la sincronización automática del Core fallaba al validar el ZIP.

0.10.7 corrige ambos puntos. No agrega migraciones SQL.

En Central Linux/cPanel no se ejecutan `.bat`. El diagnóstico operativo se abre desde la sesión administrativa en **Sistema central → Diagnóstico central**. Los logs se mantienen en `storage/logs/` y pueden descargarse por el Administrador de archivos de cPanel.
