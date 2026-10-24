<?php
$flashes=pull_flashes(); $google=SocialAuth::configured('google'); $apple=SocialAuth::configured('apple'); $isCentral=InstallationMode::isCentral();
?><!doctype html>
<html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Acceder · 2CAM Property Hub</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"><link rel="stylesheet" href="<?=asset('assets/css/building-login.css')?>"></head>
<body class="login-page-building"><main class="login-shell"><section class="login-brand"><div class="brand-mark">PH</div>
<?php if($isCentral):?>
<h1>Control central de todas las instalaciones</h1><p>Supervise urbanizaciones, edificios, sedes y complejos, versiones, conectores, APIs, salud, telemetría agregada y distribución de actualizaciones.</p><div class="login-points"><span><i class="bi bi-buildings"></i> Portafolio</span><span><i class="bi bi-plug"></i> APIs y conectores</span><span><i class="bi bi-cloud-arrow-down"></i> Versiones</span></div>
<?php else:?>
<h1>Una sola aplicación para todas tus propiedades</h1><p>Visitantes, reservas, cámaras, accesos, comunicaciones y servicios de cada propiedad desde una experiencia sencilla.</p><div class="login-points"><span><i class="bi bi-buildings"></i> Multi-propiedad</span><span><i class="bi bi-shield-check"></i> Acceso controlado</span><span><i class="bi bi-phone"></i> PWA móvil</span></div>
<?php endif;?></section>
<section class="login-card"><div class="login-card-head"><span class="eyebrow">2CAM PROPERTY HUB</span><h2><?= $isCentral?'Acceder al Property Hub Central':'Iniciar sesión' ?></h2><p><?= $isCentral?'Esta consola no es el portal de usuarios ni la operación diaria de una instalación.':'Google o Apple identifican al usuario. El acceso a cada propiedad debe ser aprobado por su administración.' ?></p></div>
<?php foreach($flashes as $flash):?><div class="login-alert"><?=e($flash['message'])?></div><?php endforeach;?>
<form class="local-login-form" method="post" action="<?=url('local-login')?>"><?=csrf_field()?>
<label>Correo o usuario<input type="text" name="identifier" autocomplete="username" required></label>
<label>Contraseña<input type="password" name="password" autocomplete="current-password" required></label>
<button type="submit" class="local-login-button"><i class="bi bi-box-arrow-in-right"></i><span>Acceder al sistema</span></button>
</form>
<?php if(!$isCentral):?><div style="display:flex;justify-content:space-between;gap:1rem;margin-top:.75rem"><a href="<?=url('forgot-password')?>">Olvidé mi contraseña</a><a href="<?=url('register')?>">Crear cuenta</a></div>
<?php endif;?>
<?php if(!$isCentral):?>
<div class="divider"><span>O utilizar identidad social</span></div>
<a class="social-button google <?=$google?'':'disabled'?>" href="<?=$google?'oauth/google/start.php':'#'?>"><span class="social-letter">G</span><span>Continuar con Google</span><?php if(!$google):?><small>Configurar</small><?php endif;?></a>
<a class="social-button apple <?=$apple?'':'disabled'?>" href="<?=$apple?'oauth/apple/start.php':'#'?>"><i class="bi bi-apple"></i><span>Continuar con Apple</span><?php if(!$apple):?><small>Configurar</small><?php endif;?></a>
<?php endif;?>
<?php if(config('app.demo_mode',false)):?><div class="divider"><span>Acceso de demostración</span></div>
<?php if($isCentral):?>
<div class="demo-grid"><form method="post" action="<?=url('demo-login')?>"><?=csrf_field()?><input type="hidden" name="profile" value="central"><button><i class="bi bi-cloud-check"></i><strong>Property Hub Central</strong><small>Portafolio y repositorio</small></button></form></div>
<?php else:?><div class="demo-grid">
<form method="post" action="<?=url('demo-login')?>"><?=csrf_field()?><input type="hidden" name="profile" value="resident"><button><i class="bi bi-phone"></i><strong>Residente</strong><small>App móvil</small></button></form>
<form method="post" action="<?=url('demo-login')?>"><?=csrf_field()?><input type="hidden" name="profile" value="admin"><button><i class="bi bi-display"></i><strong>Administración</strong><small>Web completa</small></button></form>
<form method="post" action="<?=url('demo-login')?>"><?=csrf_field()?><input type="hidden" name="profile" value="security"><button><i class="bi bi-shield"></i><strong>Vigilancia</strong><small>Operación</small></button></form>
</div><?php endif;?><p class="demo-warning">El acceso directo existe porque <code>APP_DEMO_MODE=true</code>. Debe desactivarse en producción.</p><?php endif;?>
<footer>Versión <?=e(config('app.version'))?> · UI Standard <?=e(config('app.ui_standard_version'))?></footer></section></main></body></html>
