<?php
$standalone = !current_user();
$details = is_array($details ?? null) ? $details : [];
if ($standalone):
?><!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Error interno · Property Hub</title>
<style>
:root{color-scheme:light dark}*{box-sizing:border-box}body{margin:0;font-family:system-ui,-apple-system,"Segoe UI",sans-serif;background:#f4f5f8;color:#20222a}.error-shell{min-height:100vh;display:grid;place-items:center;padding:24px}.error-card{width:min(760px,100%);background:#fff;border:1px solid #dfe2ea;border-radius:18px;padding:28px;box-shadow:0 18px 50px rgba(30,35,55,.10)}.mark{width:54px;height:54px;border-radius:14px;display:grid;place-items:center;background:#fff0f0;color:#b42318;font-size:28px;font-weight:700}h1{font-size:1.55rem;margin:18px 0 6px}p{line-height:1.5}.muted{color:#667085}.ids{display:grid;gap:8px;margin:20px 0;padding:14px;background:#f8f9fc;border-radius:12px}.ids code,.details code{overflow-wrap:anywhere}.details{margin:18px 0;padding:14px;border:1px solid #f0caca;background:#fff8f8;border-radius:12px}.details dl{display:grid;grid-template-columns:130px 1fr;gap:8px;margin:0}.details dt{font-weight:650}.details dd{margin:0;overflow-wrap:anywhere}.actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:22px}.btn{display:inline-flex;align-items:center;justify-content:center;text-decoration:none;border-radius:10px;padding:10px 14px;font-weight:650;border:1px solid #cfd4df;color:#303443;background:#fff}.btn.primary{background:#5a40d6;border-color:#5a40d6;color:#fff}.log-note{font-size:.92rem;background:#f6f7fa;border-radius:10px;padding:12px}@media(prefers-color-scheme:dark){body{background:#11131a;color:#eef0f7}.error-card{background:#1b1e28;border-color:#343847}.muted{color:#aab0c0}.ids,.log-note{background:#242835}.details{background:#2b2022;border-color:#6b363c}.btn{background:#222633;color:#fff;border-color:#464b5c}}
</style></head><body><main class="error-shell"><section class="error-card">
<?php endif; ?>
<div class="mark">!</div>
<h1>No se pudo completar la solicitud</h1>
<p class="muted">Property Hub registró el error para diagnóstico. No se continuará redirigiendo ni se ocultará la causa técnica cuando el modo de diagnóstico esté activo.</p>
<div class="ids">
  <div><strong>Incidente:</strong> <code><?=e($incident ?? '')?></code></div>
  <div><strong>Request ID:</strong> <code><?=e($requestId ?? '')?></code></div>
</div>
<?php if ($details !== []): ?>
<div class="details">
  <strong>Detalle técnico controlado</strong>
  <dl class="mt-2">
    <dt>Excepción</dt><dd><code><?=e($details['exception'] ?? '')?></code></dd>
    <dt>Mensaje</dt><dd><?=e($details['message'] ?? '')?></dd>
    <dt>Archivo</dt><dd><code><?=e($details['file'] ?? '')?>:<?=e($details['line'] ?? '')?></code></dd>
    <dt>Ruta</dt><dd><code><?=e($details['method'] ?? '')?> <?=e($details['route'] ?? '')?></code></dd>
  </dl>
</div>
<?php endif; ?>
<p class="log-note"><strong>Log del servidor:</strong> <code><?=e($logPath ?? 'storage/logs/')?></code>. Busca el número de incidente para obtener la traza completa.</p>
<div class="actions">
  <a class="btn primary" href="<?=url('login')?>">Volver al inicio de sesión</a>
  <?php if (current_user()): ?><a class="btn" href="<?=url('system-repair')?>">Revisar migraciones</a><?php endif; ?>
  <?php if (current_user() && can('system.manage')): ?><a class="btn" href="<?=url('support-logs-download',['days'=>7,'incident'=>(string)($incident??''),'request_id'=>(string)($requestId??'')])?>"><span aria-hidden="true">⇩</span> Descargar logs ZIP</a><?php endif; ?>
  <?php if (current_user()): ?><a class="btn" href="<?=url('system-check')?>">Diagnóstico</a><?php else: ?><?php if (current_user()): ?><a class="btn" href="<?=url('system-check')?>">Diagnóstico</a><?php else: ?><a class="btn" href="diagnostics.php">Diagnóstico</a><?php endif; ?><?php endif; ?>
</div>
<?php if ($standalone): ?>
</section></main></body></html>
<?php endif; ?>
