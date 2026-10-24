<?php
declare(strict_types=1);
require_once __DIR__.'/UiTheme.php';
$theme=UiTheme::defaults();
$presets=[
 'violet'=>['name'=>'Violeta','primary'=>'#5a40d6','sidebar'=>'#15182b','topbar'=>'#ffffff','background'=>'#f4f6f9','palette'=>'casamia'],
 'ocean'=>['name'=>'Océano','primary'=>'#087ea4','sidebar'=>'#0b2030','topbar'=>'#ffffff','background'=>'#f2f7f9','palette'=>'ocean'],
 'emerald'=>['name'=>'Esmeralda','primary'=>'#07865c','sidebar'=>'#10251f','topbar'=>'#ffffff','background'=>'#f2f8f5','palette'=>'emerald'],
 'blue'=>['name'=>'Azul','primary'=>'#2563eb','sidebar'=>'#111827','topbar'=>'#ffffff','background'=>'#f3f6fb','palette'=>'corporate'],
 'sunset'=>['name'=>'Atardecer','primary'=>'#ea580c','sidebar'=>'#2b1b14','topbar'=>'#ffffff','background'=>'#fbf5f1','palette'=>'sunset'],
 'graphite'=>['name'=>'Grafito','primary'=>'#475467','sidebar'=>'#171a21','topbar'=>'#ffffff','background'=>'#f4f5f7','palette'=>'graphite'],
];
$palettes=[
 'casamia'=>['#5a40d6','#1aa374','#e2a20f','#dc4b55','#087ea4','#9b59b6'],
 'ocean'=>['#087ea4','#26a5cc','#6bc4df','#1aa374','#e2a20f','#475467'],
 'emerald'=>['#07865c','#1aa374','#54c69e','#087ea4','#e2a20f','#64748b'],
 'corporate'=>['#2563eb','#60a5fa','#1aa374','#e2a20f','#dc4b55','#64748b'],
 'sunset'=>['#ea580c','#f59e0b','#fbbf24','#dc4b55','#9b59b6','#475467'],
 'graphite'=>['#475467','#667085','#98a2b3','#087ea4','#1aa374','#e2a20f'],
];
?>
<!-- Requiere body.cam-adminlte y la carga de 2cam-adminlte.css + 2cam-modern-ui.css. -->
<div class="appearance-layout cam-appearance-layout">
  <section class="card cam-appearance-card">
    <div class="card-title cam-appearance-card-title">
      <div><h2>Tema de la aplicación</h2><p>Personaliza colores, modo visual, densidad y proporciones.</p></div>
      <span class="badge text-bg-primary">Configuración global</span>
    </div>
    <form method="post" data-cam-appearance-form class="cam-appearance-form">
      <div class="field full cam-appearance-presets-field">
        <label>Temas prediseñados</label>
        <div class="theme-presets cam-theme-presets">
          <?php foreach($presets as $key=>$preset): ?>
          <button type="button" class="theme-preset<?=$theme['preset']===$key?' selected':''?>"
            data-cam-preset="<?=$key?>" data-cam-primary="<?=$preset['primary']?>"
            data-cam-sidebar="<?=$preset['sidebar']?>" data-cam-topbar="<?=$preset['topbar']?>"
            data-cam-background="<?=$preset['background']?>" data-cam-chart-palette="<?=$preset['palette']?>">
            <span style="background:<?=$preset['sidebar']?>"></span><i style="background:<?=$preset['primary']?>"></i><strong><?=$preset['name']?></strong>
          </button>
          <?php endforeach; ?>
        </div>
        <input type="hidden" name="preset" value="<?=$theme['preset']?>">
      </div>
      <div class="form-grid three cam-appearance-controls">
        <div class="field"><label>Color principal</label><input type="color" name="primary" value="<?=$theme['primary']?>"></div>
        <div class="field"><label>Barra lateral</label><input type="color" name="sidebar" value="<?=$theme['sidebar']?>"></div>
        <div class="field"><label>Barra superior</label><input type="color" name="topbar" value="<?=$theme['topbar']?>"></div>
        <div class="field"><label>Fondo general</label><input type="color" name="background" value="<?=$theme['background']?>"></div>
        <div class="field"><label>Modo visual</label><select name="appearance"><option value="light">Claro</option><option value="dark">Oscuro</option><option value="system">Seguir sistema</option></select></div>
        <div class="field"><label>Densidad general</label><select name="density"><option value="compact">Compacta</option><option value="comfortable" selected>Cómoda</option><option value="spacious">Amplia</option></select></div>
        <div class="field cam-range-field"><label>Redondeo: <span data-cam-radius-label>12</span> px</label><input type="range" min="4" max="24" name="radius" value="12"></div>
        <div class="field cam-range-field"><label>Tamaño de texto: <span data-cam-font-label>100</span>%</label><input type="range" min="85" max="120" name="font_scale" value="100"></div>
        <div class="field"><label>Densidad de tablas</label><select name="table_density"><option>Compacta</option><option selected>Normal</option><option>Amplia</option></select></div>
        <div class="field"><label>Estilo de indicadores</label><select name="kpi_style"><option value="soft">Suave</option><option value="solid">Color sólido</option><option value="minimal">Minimalista</option></select></div>
        <div class="field"><label>Paleta de gráficos</label><select name="chart_palette"><?php foreach($palettes as $key=>$colors): ?><option value="<?=$key?>"><?=$key?></option><?php endforeach; ?></select></div>
        <div class="field"><label>Menú al iniciar</label><select name="sidebar_default"><option value="expanded">Expandido</option><option value="collapsed">Contraído</option></select></div>
        <label class="checkbox-line cam-appearance-check"><input type="checkbox" name="chart_labels" value="1" checked><span>Mostrar valores visibles en los gráficos cuando el espacio lo permita.</span></label>
      </div>
      <div class="form-actions cam-appearance-actions"><button class="btn btn-primary" type="submit">Guardar apariencia</button><button class="btn btn-outline-secondary" type="button">Restaurar predeterminado</button></div>
    </form>
  </section>
  <section class="card cam-preview-card cam-appearance-preview-card">
    <div class="card-title"><div><h2>Vista previa</h2><p>Navegación, indicadores, tabla y gráfico.</p></div></div>
    <div class="appearance-preview" data-cam-appearance-preview data-cam-palettes='<?=htmlspecialchars(json_encode($palettes),ENT_QUOTES,'UTF-8')?>'>
      <aside><strong>2C</strong><span>▦</span><span>◎</span><span>▤</span></aside>
      <main><header>Dashboard gerencial</header><article><div class="cam-preview-kpis"><div><small>INGRESOS</small><b>12.450,00 €</b></div><div><small>GASTOS</small><b>3.280,00 €</b></div></div><div class="cam-preview-chart"><i data-cam-preview-bar></i><i data-cam-preview-bar></i><i data-cam-preview-bar></i><i data-cam-preview-bar></i><i data-cam-preview-bar></i><i data-cam-preview-bar></i></div><button class="btn btn-primary" type="button">Nueva operación</button><div class="preview-table"><span>FACTURA · PROVEEDOR</span><strong>250,00 €</strong></div></article></main>
    </div>
  </section>
</div>
<script src="assets/js/2cam-appearance.js"></script>
