<?php
declare(strict_types=1);

define('PROPERTY_HUB_UPDATE_FINALIZER', true);
$root = dirname(__DIR__);
if (!is_file($root.'/storage/installed.lock') || !is_file($root.'/.env')) {
    header('Location: ../install/');
    exit;
}
require_once $root.'/app/bootstrap.php';
require_auth();
require_permission('system.manage');

$markerPath = $root.'/storage/updates/deferred/update-finalize.json';
if (!is_file($markerPath)) {
    flash('info', 'No hay archivos pendientes de finalizar.');
    header('Location: index.php?route=updates');
    exit;
}

try {
    $marker = json_decode((string)file_get_contents($markerPath), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($marker) || ($marker['schema'] ?? '') !== '2cam-property-deferred-update-v1') {
        throw new RuntimeException('El marcador de finalización diferida no es válido.');
    }
    $replacements = array_values(array_filter((array)($marker['replacements'] ?? []), 'is_array'));
    if ($replacements === []) throw new RuntimeException('No hay reemplazos declarados en la finalización diferida.');

    $done = 0;
    foreach ($replacements as $item) {
        $sourceRelative = ltrim(str_replace('\\','/', (string)($item['source'] ?? '')), '/');
        $targetRelative = ltrim(str_replace('\\','/', (string)($item['target'] ?? '')), '/');
        $expectedHash = strtolower(trim((string)($item['sha256'] ?? '')));
        if ($sourceRelative === '' || $targetRelative !== 'public/index.php' || str_contains($sourceRelative, '..')) {
            throw new RuntimeException('El reemplazo diferido contiene una ruta no permitida.');
        }
        $source = $root.'/'.str_replace('/', DIRECTORY_SEPARATOR, $sourceRelative);
        $target = $root.'/public/index.php';
        if (!is_file($source)) throw new RuntimeException('Falta el archivo diferido para public/index.php.');
        $actualHash = strtolower((string)(hash_file('sha256', $source) ?: ''));
        if ($expectedHash === '' || $actualHash === '' || !hash_equals($expectedHash, $actualHash)) {
            throw new RuntimeException('El archivo diferido de public/index.php no superó SHA-256.');
        }

        $tmp = $target.'.phfinalize.'.bin2hex(random_bytes(4));
        $backup = $target.'.phfinalize.bak';
        if (!@copy($source, $tmp)) throw new RuntimeException('No se pudo preparar el nuevo public/index.php.');
        @chmod($tmp, 0644);
        if (is_file($target) && !@copy($target, $backup)) { @unlink($tmp); throw new RuntimeException('No se pudo crear respaldo temporal de public/index.php.'); }
        if (is_file($target) && !@unlink($target)) { @unlink($tmp); @unlink($backup); throw new RuntimeException('Windows aún mantiene public/index.php bloqueado. Reintente la finalización.'); }
        if (!@rename($tmp, $target)) {
            @unlink($tmp);
            if (is_file($backup)) @rename($backup, $target);
            throw new RuntimeException('No se pudo activar el nuevo public/index.php.');
        }
        @unlink($backup);
        @unlink($source);
        $done++;
    }

    @unlink($markerPath);
    clearstatcache(true, $root.'/public/index.php');
    ErrorReporter::runtimeLog('update_deferred_finalize_success', ['files'=>$done, 'version'=>(string)config('app.version','')]);
    flash('success', 'Actualización finalizada correctamente. Se activó el front controller pendiente en una solicitud segura.');
    header('Location: index.php?route=updates&finalized=1');
    exit;
} catch (Throwable $e) {
    try { ErrorReporter::report($e, ['operation'=>'update_deferred_finalize']); } catch (Throwable) {}
    http_response_code(500);
    ?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Finalización pendiente</title>
    <style>body{font-family:system-ui,sans-serif;background:#f5f7fb;color:#20283a;margin:0;padding:32px}.card{max-width:760px;margin:auto;background:#fff;border:1px solid #dde3ee;border-radius:16px;padding:24px;box-shadow:0 8px 30px rgba(0,0,0,.06)}a{display:inline-block;margin-top:16px}</style></head><body><div class="card"><h1>No se pudo finalizar la actualización</h1><p><?=htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8')?></p><p>La instalación conserva el marcador para reintentar sin volver a descargar el paquete.</p><a href="index.php?route=updates">Reintentar desde Actualizaciones</a></div></body></html><?php
}
