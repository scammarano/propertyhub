<?php
$user=current_user()??[];$context=$residentContext??[];$data=$homeData??[];$settings=$residentSettings??[];
$features=$settings['features']??[];$profile=$residentProfile??[];
$firstName=trim((string)($profile['first_name']??''))?:explode(' ',(string)($user['name']??'Residente'))[0];
$hero=trim((string)($settings['hero_image_url']??''));
if((int)($context['property_id']??0)<1):?>
<section class="resident-welcome"><div><span>Hola, <?=e($firstName)?></span><h1>Tu cuenta está activa</h1></div><div class="property-avatar"><i class="bi bi-building"></i></div></section>
<?=empty_state('Sin propiedad vinculada','La administración debe vincular tu persona con un apartamento, casa, oficina o local y asignar los permisos de la Property App.','bi bi-house-lock')?>
<?php return;endif;
$bulletins=$data['bulletins']??[];$reservations=$data['reservations']??[];$invitations=$data['invitations']??[];$notifications=$data['notifications']??[];$cameras=$data['cameras']??[];
?>
<section class="resident-hero <?=$hero?'has-image':''?>" <?=$hero?'style="background-image:linear-gradient(180deg,rgba(5,42,30,.18),rgba(5,42,30,.86)),url(\''.e($hero).'\')"':''?>>
  <div class="resident-hero-copy"><small><?=e($settings['welcome_message']??'Bienvenido')?></small><h1><?=e($context['installation_name']??config('app.instance_name'))?></h1><p><?=e($context['property_name'])?> · <?=e($context['role'])?></p><span class="connection-chip"><i></i> Conectado</span></div>
  <div class="resident-hero-bell"><i class="bi bi-buildings"></i></div>
</section>
<section class="resident-action-grid">
<?php if(!empty($features['visitors'])&&!empty($context['can_invite'])):?><a href="<?=url('resident-visitors')?>"><i class="bi bi-person-plus-fill action-green"></i><span>Invitar</span></a><?php endif;?>
<?php if(!empty($features['reservations'])&&!empty($context['can_reserve'])):?><a href="<?=url('resident-reservations')?>"><i class="bi bi-calendar2-check-fill action-blue"></i><span>Reservas</span></a><?php endif;?>
<?php if(!empty($features['cameras'])&&!empty($context['can_view_cameras'])):?><a href="<?=url('resident-cameras')?>"><i class="bi bi-camera-video-fill action-purple"></i><span>Cámaras</span></a><?php endif;?>
<?php if(!empty($features['access'])&&!empty($context['can_operate_access'])):?><a href="<?=url('resident-access')?>"><i class="bi bi-lock-fill action-teal"></i><span>Accesos</span></a><?php endif;?>
<?php if(!empty($features['notifications'])):?><a href="<?=url('resident-notifications')?>"><i class="bi bi-megaphone-fill action-orange"></i><span>Avisos</span></a><?php endif;?>
<?php if(!empty($features['reservations'])):?><a href="<?=url('resident-reservations')?>"><i class="bi bi-tree-fill action-lime"></i><span>Áreas</span></a><?php endif;?>
<?php if(!empty($features['vehicles'])):?><a href="<?=url('resident-vehicles')?>"><i class="bi bi-car-front-fill action-blue"></i><span>Vehículos</span></a><?php endif;?>
<a href="<?=url('resident-profile')?>"><i class="bi bi-person-fill action-gray"></i><span>Perfil</span></a>
</section>
<section class="resident-dashboard-cards">
<?php if($invitations):$inv=$invitations[0];?><a class="resident-summary-card summary-green" href="<?=url('resident-visitors')?>"><span class="summary-icon"><i class="bi bi-people-fill"></i></span><div><small>Próxima visita</small><strong><?=e($inv['visitor_name'])?></strong><span><?=e(date('d/m/Y · H:i',strtotime((string)$inv['starts_at'])))?></span></div><i class="bi bi-chevron-right"></i></a><?php endif;?>
<?php if($reservations):$res=$reservations[0];?><a class="resident-summary-card summary-blue" href="<?=url('resident-reservations')?>"><span class="summary-icon"><i class="bi bi-calendar2-check-fill"></i></span><div><small>Reserva activa</small><strong><?=e($res['area'])?></strong><span><?=e(date('d/m/Y · H:i',strtotime((string)$res['starts_at'])))?></span></div><i class="bi bi-chevron-right"></i></a><?php endif;?>
<?php if($notifications):$notice=$notifications[0];?><a class="resident-summary-card summary-orange" href="<?=url('resident-notifications')?>"><span class="summary-icon"><i class="bi bi-exclamation-triangle-fill"></i></span><div><small>Aviso importante</small><strong><?=e($notice['title'])?></strong><span><?=e(mb_strimwidth((string)$notice['message'],0,80,'…'))?></span></div><i class="bi bi-chevron-right"></i></a><?php elseif($bulletins):$notice=$bulletins[0];?><a class="resident-summary-card summary-orange" href="<?=url('resident-bulletin')?>"><span class="summary-icon"><i class="bi bi-megaphone-fill"></i></span><div><small>Cartelera</small><strong><?=e($notice['title'])?></strong><span><?=e(mb_strimwidth((string)$notice['body'],0,80,'…'))?></span></div><i class="bi bi-chevron-right"></i></a><?php endif;?>
<?php if(!$invitations&&!$reservations&&!$notifications&&!$bulletins):?><div class="resident-empty">No hay actividad pendiente. La aplicación ya está conectada con los datos reales de esta instalación.</div><?php endif;?>
</section>
<?php if($cameras):?><section class="resident-section"><div class="section-heading"><h2>Cámaras autorizadas</h2><a href="<?=url('resident-cameras')?>">Ver todas</a></div><div class="home-camera-row"><?php foreach(array_slice($cameras,0,3) as $camera):?><a href="<?=url('resident-cameras')?>"><span class="home-camera-visual"><i class="bi bi-camera-video-fill"></i><b class="<?=e($camera['status']==='online'?'online':'offline')?>"></b></span><small><?=e($camera['name'])?></small></a><?php endforeach;?></div></section><?php endif;?>
