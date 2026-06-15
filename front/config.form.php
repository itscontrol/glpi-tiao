<?php

include('../../../inc/includes.php');

Session::checkRight('config', UPDATE);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    if (method_exists('Session', 'checkCSRF')) {
        Session::checkCSRF($_POST);
    }
    PluginTiaoConfig::save($_POST);
    Session::addMessageAfterRedirect('Configuração salva com sucesso.', true, INFO);
    Html::redirect($_SERVER['PHP_SELF']);
}

$config = PluginTiaoConfig::get();

Html::header('Tião – Configuração', $_SERVER['PHP_SELF'], 'config', 'plugins');
?>

<div class="container-fluid">
  <div class="card mb-4">
    <div class="card-header">
      <h3 class="card-title">
        <i class="ti ti-robot me-2"></i>Tião — Configuração do plugin
      </h3>
    </div>
    <div class="card-body">
      <form method="post" action="">
        <?php echo Html::hidden('_glpi_csrf_token', ['value' => Session::getNewCSRFToken() ?? '']); ?>
        <input type="hidden" name="_no_csrf_check" value="0" />

        <div class="row mb-3">
          <label class="col-sm-3 col-form-label">URL da plataforma Tião</label>
          <div class="col-sm-9">
            <input type="url" name="tiao_url" class="form-control"
                   value="<?php echo htmlspecialchars($config['tiao_url']); ?>"
                   placeholder="https://tiao.ia.br" required />
            <div class="form-text">URL base da plataforma. Sem barra no final.</div>
          </div>
        </div>

        <div class="row mb-3">
          <label class="col-sm-3 col-form-label">API Key do Tião</label>
          <div class="col-sm-9">
            <input type="password" name="api_key" class="form-control"
                   value="<?php echo htmlspecialchars($config['api_key']); ?>"
                   placeholder="Gerada no dashboard do Tião" />
            <div class="form-text">
              Dashboard Tião → Conectores → GLPI → API Key.
            </div>
          </div>
        </div>

        <div class="row mb-3">
          <label class="col-sm-3 col-form-label">Secret de assinatura</label>
          <div class="col-sm-9">
            <div class="input-group">
              <input type="text" class="form-control font-monospace" readonly
                     value="<?php echo htmlspecialchars($config['secret']); ?>" />
              <button type="button" class="btn btn-outline-secondary"
                      onclick="navigator.clipboard.writeText('<?php echo addslashes($config['secret']); ?>')">
                Copiar
              </button>
            </div>
            <div class="form-text">
              Cole este valor no Dashboard Tião → Conectores → GLPI → Secret.
            </div>
          </div>
        </div>

        <div class="row mb-3">
          <div class="col-sm-9 offset-sm-3">
            <div class="form-check">
              <input type="checkbox" name="active" id="active" class="form-check-input" value="1"
                     <?php echo $config['active'] ? 'checked' : ''; ?> />
              <label for="active" class="form-check-label">Plugin ativo</label>
            </div>
          </div>
        </div>

        <div class="row mb-4">
          <label class="col-sm-3 col-form-label">Webhook URL (Tião → GLPI)</label>
          <div class="col-sm-9">
            <?php
              $proto   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
              $webhook = $proto . '://' . $_SERVER['HTTP_HOST']
                . Plugin::getWebDir('tiao', false) . '/front/api.php';
            ?>
            <div class="input-group">
              <input type="text" class="form-control font-monospace" readonly
                     value="<?php echo htmlspecialchars($webhook); ?>" />
              <button type="button" class="btn btn-outline-secondary"
                      onclick="navigator.clipboard.writeText('<?php echo addslashes($webhook); ?>')">
                Copiar
              </button>
            </div>
            <div class="form-text">Configure este URL no Dashboard Tião → Conectores → GLPI → Webhook URL.</div>
          </div>
        </div>

        <div class="row">
          <div class="col-sm-9 offset-sm-3">
            <button type="submit" name="save" class="btn btn-primary">
              <i class="ti ti-device-floppy me-1"></i>Salvar
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- Log dos últimos eventos -->
  <?php
  global $DB;
  $events = [];
  foreach ($DB->request([
      'FROM'  => 'glpi_plugin_tiao_events',
      'ORDER' => 'sent_at DESC',
      'LIMIT' => 20,
  ]) as $row) {
      $events[] = $row;
  }
  ?>
  <div class="card">
    <div class="card-header">
      <h3 class="card-title"><i class="ti ti-list me-2"></i>Últimos eventos enviados</h3>
    </div>
    <div class="card-body p-0">
      <table class="table table-sm mb-0">
        <thead>
          <tr><th>Data</th><th>Evento</th><th>Ticket</th><th>Status</th><th>Resposta</th></tr>
        </thead>
        <tbody>
          <?php if (empty($events)): ?>
            <tr><td colspan="5" class="text-center text-muted py-3">Nenhum evento registrado ainda.</td></tr>
          <?php else: ?>
            <?php foreach ($events as $row): ?>
            <tr>
              <td><?php echo htmlspecialchars($row['sent_at']); ?></td>
              <td><code><?php echo htmlspecialchars($row['event']); ?></code></td>
              <td>#<?php echo (int) $row['ticket_id']; ?></td>
              <td>
                <?php if ($row['status']): ?>
                  <span class="badge bg-success">OK</span>
                <?php else: ?>
                  <span class="badge bg-danger">Erro</span>
                <?php endif; ?>
              </td>
              <td><small class="text-muted"><?php echo htmlspecialchars(substr((string)($row['response'] ?? ''), 0, 80)); ?></small></td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php Html::footer(); ?>
