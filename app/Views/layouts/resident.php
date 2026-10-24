<?php
$user=current_user()??[];
$context=$residentContext??['installation_name'=>config('app.instance_name'),'property_name'=>'Sin unidad vinculada','role'=>''];
$memberships=$residentMemberships??[];
$settings=$residentSettings??['app_title'=>config('app.instance_name'),'theme_color'=>'#176b45','features'=>[]];
$flashes=pull_flashes();$currentRoute=route_name();$unread=(int)($unread??0);
$preview=!empty($_SESSION['resident_preview_person_id'])&&strtoupper((string)($user['role']??''))!=='RESIDENT';
?><!doctype html>
<html lang="es" data-bs-theme="light">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover,maximum-scale=1">
<meta name="theme-color" content="<?=e($settings['theme_color']??'#176b45')?>"><meta name="apple-mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-status-bar-style" content="default">
<title><?=e($title??'Property App')?> · <?=e($settings['app_title']??'Property Hub')?></title>
<link rel="manifest" href="manifest.webmanifest">
<link rel="stylesheet" href="<?=asset('assets/vendor/2cam-ui/assets/adminlte/adminlte.min.css')?>">
<link rel="stylesheet" href="<?=asset('assets/vendor/2cam-ui/assets/css/2cam-adminlte.css')?>">
<link rel="stylesheet" href="<?=asset('assets/vendor/2cam-ui/assets/css/2cam-modern-ui.css')?>">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="<?=asset('assets/css/building-resident.css')?>">
<style>:root{--r-primary:<?=e($settings['theme_color']??'#176b45')?>}</style>
</head>
<body class="resident-app">
<?php if($preview):?><div class="resident-preview-bar"><span><i class="bi bi-phone"></i> Vista móvil de prueba · datos reales de <?=e($context['property_name'])?></span><a href="<?=url('resident-preview-stop')?>">Volver a administración</a></div><?php endif;?>
<header class="resident-header">
  <button class="icon-button" type="button" data-bs-toggle="offcanvas" data-bs-target="#residentMenu" aria-label="Abrir menú"><i class="bi bi-list"></i></button>
  <button class="context-button" type="button" data-bs-toggle="modal" data-bs-target="#contextModal" <?=$memberships?'':'disabled'?>>
    <span class="context-building"><?=e($context['installation_name']??config('app.instance_name'))?></span>
    <span class="context-unit"><?=e($context['property_name']??'Sin unidad vinculada')?><?=!empty($context['role'])?' · '.e($context['role']):''?> <?php if($memberships):?><i class="bi bi-chevron-down"></i><?php endif;?></span>
  </button>
  <a class="icon-button position-relative" href="<?=url('resident-notifications')?>" aria-label="Notificaciones"><i class="bi bi-bell"></i><?php if($unread):?><span class="notify-dot"><?=e($unread>99?'99+':$unread)?></span><?php endif;?></a>
</header>
<main class="resident-main">
  <?php foreach($flashes as $flash):?><div class="resident-alert resident-alert-<?=e($flash['type'])?>"><?=e($flash['message'])?></div><?php endforeach;?>
  <?=$content?>
</main>
<nav class="resident-bottom-nav" aria-label="Navegación principal">
  <a href="<?=url('resident-home')?>" class="<?=$currentRoute==='resident-home'?'active':''?>"><i class="bi bi-house-fill"></i><span>Inicio</span></a>
  <a href="<?=url('resident-reservations')?>" class="<?=$currentRoute==='resident-reservations'?'active':''?>"><i class="bi bi-calendar2-check-fill"></i><span>Reservas</span></a>
  <a href="<?=url('resident-cameras')?>" class="<?=$currentRoute==='resident-cameras'?'active':''?>"><i class="bi bi-camera-video-fill"></i><span>Cámaras</span></a>
  <a href="<?=url('resident-bulletin')?>" class="<?=$currentRoute==='resident-bulletin'?'active':''?>"><i class="bi bi-people-fill"></i><span>Comunidad</span></a>
  <a href="<?=url('resident-profile')?>" class="<?=$currentRoute==='resident-profile'?'active':''?>"><i class="bi bi-person-fill"></i><span>Perfil</span></a>
</nav>
<div class="offcanvas offcanvas-start resident-drawer" tabindex="-1" id="residentMenu">
  <div class="offcanvas-header"><div><strong><?=e($settings['app_title']??'Property App')?></strong><small><?=e($user['name']??'')?></small></div><button class="btn-close" data-bs-dismiss="offcanvas"></button></div>
  <div class="offcanvas-body">
    <a href="<?=url('resident-home')?>"><i class="bi bi-house"></i> Inicio</a>
    <a href="<?=url('resident-visitors')?>"><i class="bi bi-person-plus"></i> Invitaciones</a>
    <a href="<?=url('resident-reservations')?>"><i class="bi bi-calendar2-check"></i> Áreas y reservas</a>
    <a href="<?=url('resident-cameras')?>"><i class="bi bi-camera-video"></i> Cámaras</a>
    <a href="<?=url('resident-access')?>"><i class="bi bi-door-open"></i> Accesos</a>
    <?php if(!empty(($settings['features']??[])['vehicles'])):?><a href="<?=url('resident-vehicles')?>"><i class="bi bi-car-front-fill"></i> Vehículos</a><?php endif;?>
    <a href="<?=url('resident-bulletin')?>"><i class="bi bi-megaphone"></i> Cartelera</a>
    <a href="<?=url('resident-notifications')?>"><i class="bi bi-bell"></i> Notificaciones</a>
    <a href="<?=url('resident-emergency')?>"><i class="bi bi-telephone-forward"></i> Emergencias</a>
    <hr>
    <?php if($preview):?><a href="<?=url('resident-preview-stop')?>"><i class="bi bi-display"></i> Volver a administración</a><?php endif;?>
    <a href="<?=url('logout')?>" class="text-danger"><i class="bi bi-box-arrow-right"></i> Cerrar sesión</a>
  </div>
</div>
<div class="modal fade" id="contextModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-dialog-scrollable"><div class="modal-content resident-modal">
  <div class="modal-header"><div><h5 class="modal-title">Mis propiedades</h5><small>Cambia de unidad sin cerrar sesión</small></div><button class="btn-close" data-bs-dismiss="modal"></button></div>
  <div class="modal-body context-list">
    <?php foreach($memberships as $membership):?><form method="post" action="<?=url('switch-context')?>"><?=csrf_field()?><input type="hidden" name="property_id" value="<?=e($membership['property_id'])?>"><button class="context-card <?=((int)$membership['property_id']===(int)($context['property_id']??0))?'selected':''?>"><span class="context-icon"><i class="bi bi-building"></i></span><span><strong><?=e($membership['installation_name'])?></strong><small><?=e($membership['property_name'])?> · <?=e($membership['role'])?></small></span><?php if((int)$membership['property_id']===(int)($context['property_id']??0)):?><i class="bi bi-check-circle-fill ms-auto"></i><?php endif;?></button></form><?php endforeach;?>
    <?php if(!$memberships):?><div class="resident-empty">La administración todavía no ha vinculado una unidad a esta cuenta.</div><?php endif;?>
  </div>
</div></div></div>
<script src="<?=asset('assets/vendor/bootstrap/bootstrap.bundle.min.js')?>"></script>
<script src="<?=asset('assets/vendor/2cam-ui/assets/plugins/2cam-vendor-ui.js')?>"></script>
<script src="<?=asset('assets/js/building-resident.js')?>"></script>
<script>(()=>{const ping=()=>fetch('<?=url('presence-ping')?>',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/x-www-form-urlencoded'},body:'_csrf='+encodeURIComponent('<?=e(csrf_token())?>')}).catch(()=>{});ping();setInterval(ping,60000);document.addEventListener('visibilitychange',()=>{if(!document.hidden)ping();});})();</script>
</body></html>
