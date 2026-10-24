<?php
$calendar = $calendar ?? month_calendar(date('Y-m'), DemoRepository::reservations());
$monthDate = new DateTimeImmutable($calendar['month'].'-01');
$prev = $monthDate->modify('-1 month')->format('Y-m');
$next = $monthDate->modify('+1 month')->format('Y-m');
$returnRoute = $returnRoute ?? route_name();
?>
<div class="calendar-shell">
  <div class="calendar-toolbar"><a class="btn btn-sm btn-outline-secondary" href="<?=url($returnRoute,['month'=>$prev])?>"><i class="bi bi-chevron-left"></i></a><strong><?=e($calendar['label'])?></strong><a class="btn btn-sm btn-outline-secondary" href="<?=url($returnRoute,['month'=>$next])?>"><i class="bi bi-chevron-right"></i></a></div>
  <div class="calendar-grid calendar-weekdays"><?php foreach(['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'] as $day):?><div><?=e($day)?></div><?php endforeach;?></div>
  <div class="calendar-grid calendar-days"><?php foreach($calendar['cells'] as $cell):?>
    <?php if($cell===null):?><div class="calendar-cell empty"></div><?php else:?><div class="calendar-cell <?=$cell['date']===date('Y-m-d')?'today':''?>"><span class="calendar-day-number"><?=e($cell['day'])?></span><?php foreach(array_slice($cell['items'],0,3) as $item):?><div class="calendar-event event-<?=e(status_badge($item['status']))?>" title="<?=e($item['area'].' '.$item['start'].'–'.$item['end'])?>"><strong><?=e($item['start'])?></strong> <?=e($item['area'])?></div><?php endforeach;?><?php if(count($cell['items'])>3):?><small>+<?=count($cell['items'])-3?> más</small><?php endif;?></div><?php endif;?>
  <?php endforeach;?></div>
</div>
