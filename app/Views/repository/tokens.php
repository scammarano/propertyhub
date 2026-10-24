<?php
$newToken=$newToken??null;
$purposeLabel=static fn(string $purpose): string => $purpose==='relink'?'Reconexión / rotación':'Registro inicial';
$statusClass=static fn(string $status): string => match($status){'active'=>'success','expired'=>'secondary','revoked'=>'danger',default=>'secondary'};
?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
  <div>
    <h5 class="mb-1">Tokens de instalación</h5>
    <p class="text-body-secondary mb-0">Los tokens se guardan como hash y no pueden recuperarse en texto plano. Siempre puede revocarlos y generar un reemplazo sin borrar el expediente del Property Node. Los Edge Agents se emparejan con PHEDGE desde su Node.</p>
  </div>
</div>

<?php if(is_string($newToken) && $newToken!==''):?>
<div class="alert alert-warning">
  <strong>Copie este token ahora:</strong>
  <code class="d-block mt-2 p-3 bg-dark text-white rounded text-break fs-6"><?=e($newToken)?></code>
  <small>Solo se mostrará una vez. Si se pierde, genere otro; no elimine la instalación.</small>
</div>
<?php endif;?>

<div class="alert alert-light border d-flex gap-3 align-items-start">
  <i class="bi bi-shield-lock fs-4 text-primary"></i>
  <div>
    <strong>Dos usos diferentes</strong>
    <div class="row g-2 mt-1 small">
      <div class="col-md-6"><b>Registro inicial:</b> vincula por primera vez un Property Node con Property Hub Central.</div>
      <div class="col-md-6"><b>Reconexión:</b> renueva las credenciales de una instalación ya registrada, conservando su ficha, métricas, historial y configuración central.</div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-xl-8">
    <div class="card">
      <div class="card-header"><strong>Historial de tokens</strong></div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Token</th><th>Destino</th><th>Uso</th><th>Vence</th><th>Estado</th><th></th></tr></thead>
            <tbody>
            <?php foreach(($tokens??[]) as $item):?>
              <tr>
                <td><strong><?=e($item['token_name'])?></strong><small class="d-block text-body-secondary"><?=e($purposeLabel((string)($item['token_purpose']??'initial_registration')))?> · creado <?=e(format_datetime_es((string)$item['created_at']))?></small></td>
                <td><code><?=e($item['allowed_installation_code']?:'CUALQUIERA')?></code><small class="d-block text-body-secondary"><?=e($item['installation_name']??$item['plan_name']??'Sin expediente vinculado')?> · <?=e($item['allowed_channel']?:'canal de la instalación')?></small></td>
                <td><?=e($item['used_count'].' / '.$item['max_uses'])?><small class="d-block text-body-secondary"><?=e($item['last_used_at']?'Último uso '.format_datetime_es((string)$item['last_used_at']):'Nunca utilizado')?></small></td>
                <td><?=e($item['expires_at']?format_datetime_es((string)$item['expires_at']):'Sin vencimiento')?></td>
                <td><span class="badge text-bg-<?=e($statusClass((string)$item['status']))?>"><?=e($item['status'])?></span><?php if(!empty($item['revocation_reason'])):?><small class="d-block text-body-secondary"><?=e($item['revocation_reason'])?></small><?php endif;?></td>
                <td class="text-end">
                  <?php if(($item['status']??'')==='active'):?>
                  <form method="post" action="<?=url('repository-revoke-token')?>" onsubmit="return confirm('¿Revocar este token? La instalación no será eliminada.');">
                    <?=csrf_field()?><input type="hidden" name="token_id" value="<?=e($item['id'])?>"><input type="hidden" name="reason" value="Revocado manualmente desde el Central">
                    <button class="btn btn-sm btn-outline-danger">Revocar</button>
                  </form>
                  <?php endif;?>
                </td>
              </tr>
            <?php endforeach;?>
            <?php if(empty($tokens)):?><tr><td colspan="6" class="text-center py-5 text-body-secondary">No hay tokens emitidos.</td></tr><?php endif;?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
  <div class="col-xl-4">
    <div class="card">
      <div class="card-header"><strong>Token técnico manual</strong></div>
      <div class="card-body">
        <p class="small text-body-secondary">Para el flujo normal use <strong>Nueva instalación</strong> o <strong>Generar token de reconexión</strong> desde la ficha del Property Node. Este formulario BHR queda para Nodes de laboratorio o casos controlados. <strong>No se usa para Edge Agents</strong>; ellos reciben un PHEDGE desde su Property Node.</p>
        <form method="post" action="<?=url('repository-create-token')?>" class="row g-3">
          <?=csrf_field()?>
          <div class="col-12"><label class="form-label">Nombre</label><input class="form-control" name="token_name" required></div>
          <div class="col-12"><label class="form-label">Código limitado</label><input class="form-control text-uppercase" name="installation_code" placeholder="PREMIER-LM"></div>
          <div class="col-6"><label class="form-label">Canal</label><select class="form-select" name="channel"><option value="stable">stable</option><option value="beta">beta</option><option value="development">development</option><option value="pinned">pinned</option></select></div>
          <div class="col-6"><label class="form-label">Usos máximos</label><input class="form-control" type="number" min="1" name="max_uses" value="1"></div>
          <div class="col-12"><label class="form-label">Vence</label><div class="border rounded px-3 py-2"><input class="form-control border-0 p-0" type="datetime-local" name="expires_at"></div></div>
          <div class="col-12"><button class="btn btn-primary w-100">Generar token</button></div>
        </form>
      </div>
    </div>
  </div>
</div>
