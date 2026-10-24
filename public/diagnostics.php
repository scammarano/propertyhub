<?php
declare(strict_types=1);
$root=dirname(__DIR__);
require_once $root.'/app/Support/helpers.php';
require_once $root.'/app/Support/ErrorReporter.php';
ErrorReporter::bootstrap();

function diag_local_request(): bool {
    $ip=(string)($_SERVER['REMOTE_ADDR']??'');
    return in_array($ip,['127.0.0.1','::1'],true);
}
function diag_h(string|int|float|null $v): string { return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }
function diag_tail(string $file,int $lines=120): string {
    if(!is_file($file))return '';
    $content=@file($file,FILE_IGNORE_NEW_LINES);
    if(!is_array($content))return '';
    return implode("\n",array_slice($content,-$lines));
}

$token=(string)($_GET['token']??'');
$configured=(string)config('app.diagnostic_token','');
$authorized=diag_local_request() || ($configured!=='' && $token!=='' && hash_equals($configured,$token));
if(!$authorized){
    http_response_code(403);
    $isWindows=DIRECTORY_SEPARATOR==='\\';
    ?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Diagnóstico protegido</title><style>body{font-family:system-ui;margin:0;background:#f4f5f8;color:#252832}.box{max-width:780px;margin:60px auto;padding:24px;background:#fff;border:1px solid #ddd;border-radius:16px}code{background:#f1f2f5;padding:2px 5px;border-radius:5px}.btn{display:inline-block;margin-top:12px;padding:10px 14px;border-radius:9px;background:#5a40d6;color:#fff;text-decoration:none;font-weight:700}</style></head><body><main class="box"><h1>Diagnóstico protegido</h1><p>Este panel técnico directo solo se abre localmente o con un token temporal.</p>
    <?php if($isWindows): ?>
      <p>En un Property Node Windows/XAMPP puedes generar el token con:</p><pre><code>tools\enable-diagnostics.bat</code></pre>
    <?php else: ?>
      <p><strong>Este servidor es Linux/cPanel:</strong> aquí no se ejecutan archivos <code>.bat</code>. Usa el diagnóstico integrado desde la sesión administrativa.</p>
      <a class="btn" href="index.php?route=system-check">Abrir diagnóstico del Central</a>
      <p>Para soporte avanzado, los logs están en <code>storage/logs/</code> y pueden descargarse con el Administrador de archivos de cPanel.</p>
    <?php endif; ?>
    </main></body></html><?php
    exit;
}

$status=[
    'app_version'=>(string)config('app.version',''),
    'instance_code'=>(string)config('app.instance_code',''),
    'instance_role'=>(string)config('app.instance_role',''),
    'php_version'=>PHP_VERSION,
    'installed_lock'=>is_file($root.'/storage/installed.lock'),
    'env_file'=>is_file($root.'/.env'),
    'log_dir'=>ErrorReporter::logDirectory(),
    'log_writable'=>is_dir(ErrorReporter::logDirectory())&&is_writable(ErrorReporter::logDirectory()),
    'pdo_mysql'=>extension_loaded('pdo_mysql'),
    'openssl'=>extension_loaded('openssl'),
    'curl'=>extension_loaded('curl'),
    'json'=>extension_loaded('json'),
];
$db=['ok'=>false,'message'=>'No probado'];
$schema=null;
try{
    require_once $root.'/app/bootstrap.php';
    $pdo=Connection::pdo();
    $db=['ok'=>true,'message'=>'Conexión correcta','server_version'=>(string)$pdo->getAttribute(PDO::ATTR_SERVER_VERSION)];
    try{
        $db['local_users']=(int)$pdo->query('SELECT COUNT(*) FROM bh_local_users')->fetchColumn();
        $db['active_users']=(int)$pdo->query("SELECT COUNT(*) FROM bh_local_users WHERE status='active'")->fetchColumn();
    }catch(Throwable $e){$db['user_query_error']=$e->getMessage();}
    try{$schema=(new SchemaManager($pdo))->status();}catch(Throwable $e){$schema=['ready'=>false,'error'=>$e->getMessage()];}
}catch(Throwable $e){
    $incident=ErrorReporter::report($e,'diagnostics');
    $db=['ok'=>false,'message'=>$e->getMessage(),'incident'=>$incident,'details'=>ErrorReporter::publicDetails($e)];
}
$logDir=ErrorReporter::logDirectory();
$today=date('Y-m-d');
$logs=[
    'Incidentes'=>diag_tail($logDir.'/property-hub-'.$today.'.log'),
    'Runtime PHP'=>diag_tail($logDir.'/php-runtime.log'),
    'Autenticación'=>diag_tail($logDir.'/auth-'.$today.'.jsonl'),
    'Runtime JSON'=>diag_tail($logDir.'/runtime-'.$today.'.jsonl'),
];
?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Diagnóstico · Property Hub</title><style>
:root{color-scheme:light dark}*{box-sizing:border-box}body{font-family:system-ui,-apple-system,"Segoe UI",sans-serif;margin:0;background:#f4f5f8;color:#252832}.wrap{max-width:1180px;margin:0 auto;padding:24px}.head{display:flex;justify-content:space-between;gap:16px;align-items:flex-start}.badge{display:inline-block;padding:5px 9px;border-radius:999px;background:#ece9ff;color:#4a35b8;font-weight:700}.grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px;margin:20px 0}.card{background:#fff;border:1px solid #dfe2ea;border-radius:14px;padding:16px}.card strong{display:block;margin-bottom:6px}.ok{color:#067647}.bad{color:#b42318}.logs{display:grid;gap:16px}.log{background:#11151d;color:#e7eaf1;border-radius:14px;padding:16px;overflow:auto;max-height:420px}.log h2{font-size:1rem;margin:0 0 10px;color:#fff}.log pre{margin:0;white-space:pre-wrap;overflow-wrap:anywhere;font:12px/1.5 ui-monospace,SFMono-Regular,Consolas,monospace}.actions{display:flex;gap:10px;flex-wrap:wrap}.btn{display:inline-block;padding:9px 13px;border-radius:9px;text-decoration:none;background:#5a40d6;color:#fff}.muted{color:#667085}@media(prefers-color-scheme:dark){body{background:#11131a;color:#eef0f7}.card{background:#1b1e28;border-color:#343847}.muted{color:#aab0c0}}
</style></head><body><main class="wrap"><div class="head"><div><span class="badge">Diagnóstico protegido</span><h1>Property Hub <?=diag_h($status['app_version'])?></h1><p class="muted"><?=diag_h($status['instance_code'])?> · Request ID <?=diag_h(ErrorReporter::requestId())?></p></div><div class="actions"><a class="btn" href="index.php?route=login">Volver a Property Hub</a></div></div>
<section class="grid">
<?php foreach($status as $k=>$v): ?><div class="card"><strong><?=diag_h($k)?></strong><span class="<?=($v===true||$v==='1')?'ok':(($v===false||$v==='')?'bad':'')?>"><?=diag_h(is_bool($v)?($v?'Sí':'No'):$v)?></span></div><?php endforeach; ?>
</section>
<section class="card"><h2>Base de datos</h2><pre><?=diag_h(json_encode($db,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))?></pre></section>
<section class="card"><h2>Migraciones</h2><pre><?=diag_h(json_encode($schema,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))?></pre></section>
<section class="logs"><h2>Últimos registros</h2><?php foreach($logs as $name=>$content): ?><div class="log"><h2><?=diag_h($name)?></h2><pre><?=diag_h($content!==''?$content:'Sin registros todavía.')?></pre></div><?php endforeach; ?></section>
</main></body></html>
