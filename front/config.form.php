<?php

include('../../../inc/includes.php');

Session::checkRight('config', UPDATE);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    PluginTiaoConfig::save($_POST);
    Session::addMessageAfterRedirect('Configuração salva com sucesso.', true, INFO);
    Html::redirect($_SERVER['PHP_SELF']);
}

$config = PluginTiaoConfig::get();

Html::header('Tião – Configuração', $_SERVER['PHP_SELF'], 'config', 'plugins');

$colorFields = [
    'Base' => [
        'theme_body_bg'       => 'Fundo principal',
        'theme_header_bg'     => 'Topo',
        'theme_sidebar_bg'    => 'Menu lateral',
        'theme_sidebar_fg'    => 'Texto do menu',
        'theme_card_bg'       => 'Cards',
        'theme_card_hover_bg' => 'Hover / destaque',
        'theme_border'        => 'Bordas',
    ],
    'Marca e texto' => [
        'theme_primary'       => 'Links e foco',
        'theme_primary_dark'  => 'Botões primários',
        'theme_accent'        => 'Acento / perigo',
        'theme_text'          => 'Texto principal',
        'theme_text_muted'    => 'Texto secundário',
        'theme_text_disabled' => 'Texto desabilitado',
    ],
    'Formulários e tabelas' => [
        'theme_input_bg'      => 'Fundo dos campos',
        'theme_input_border'  => 'Borda dos campos',
        'theme_table_bg'      => 'Linha da tabela',
        'theme_table_alt_bg'  => 'Linha alternada',
        'theme_table_hover'   => 'Hover da tabela',
    ],
];
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
        <input type="hidden" name="_glpi_csrf_token" value="<?php echo Session::getNewCSRFToken(); ?>" />

        <div class="row mb-3">
          <label class="col-sm-3 col-form-label">URL da plataforma Tião</label>
          <div class="col-sm-9">
            <input type="url" name="tiao_url" class="form-control"
                   value="<?php echo htmlspecialchars($config['tiao_url']); ?>"
                   placeholder="https://tiao.ia.br" />
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
            <input type="text" name="secret" class="form-control font-monospace"
                   value="<?php echo htmlspecialchars($config['secret']); ?>"
                   placeholder="Cole o Secret gerado no Dashboard Tião" />
            <div class="form-text">
              Dashboard Tião → Conectores → GLPI → Secret. Cole aqui o mesmo valor.
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

        <hr class="my-4" />

        <h4 class="mb-3">
          <i class="ti ti-palette me-2"></i>Tema Tião para o GLPI
        </h4>

        <div class="row mb-3">
          <div class="col-sm-9 offset-sm-3">
            <div class="form-check">
              <input type="checkbox" name="theme_enabled" id="theme_enabled" class="form-check-input" value="1"
                     <?php echo $config['theme_enabled'] ? 'checked' : ''; ?> />
              <label for="theme_enabled" class="form-check-label">Aplicar identidade visual do Tião no GLPI</label>
            </div>
            <div class="form-text">Ative para injetar o tema escuro, tabelas, cards, botões e campos com a identidade IT'S Control/Tião.</div>
          </div>
        </div>

        <?php foreach ($colorFields as $section => $fields): ?>
          <div class="mb-3">
            <h5 class="text-muted mb-2"><?php echo htmlspecialchars($section); ?></h5>
            <div class="row g-3">
              <?php foreach ($fields as $name => $label): ?>
                <div class="col-md-4">
                  <label class="form-label" for="<?php echo htmlspecialchars($name); ?>"><?php echo htmlspecialchars($label); ?></label>
                  <div class="input-group">
                    <input type="color" class="form-control form-control-color"
                           value="<?php echo htmlspecialchars($config[$name]); ?>"
                           onchange="document.getElementById('<?php echo htmlspecialchars($name); ?>').value = this.value.toUpperCase();" />
                    <input type="text" class="form-control font-monospace" id="<?php echo htmlspecialchars($name); ?>"
                           name="<?php echo htmlspecialchars($name); ?>"
                           value="<?php echo htmlspecialchars($config[$name]); ?>"
                           pattern="#[0-9A-Fa-f]{6}" />
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>

        <div class="row mb-4">
          <div class="col-md-4">
            <label class="form-label" for="theme_radius">Raio geral (px)</label>
            <input type="number" min="0" max="24" name="theme_radius" id="theme_radius"
                   class="form-control" value="<?php echo htmlspecialchars($config['theme_radius']); ?>" />
          </div>
          <div class="col-md-4">
            <label class="form-label" for="theme_card_radius">Raio dos cards (px)</label>
            <input type="number" min="0" max="24" name="theme_card_radius" id="theme_card_radius"
                   class="form-control" value="<?php echo htmlspecialchars($config['theme_card_radius']); ?>" />
          </div>
          <div class="col-md-4 d-flex align-items-end">
            <div class="form-check mb-2">
              <input type="checkbox" name="theme_shadow" id="theme_shadow" class="form-check-input" value="1"
                     <?php echo $config['theme_shadow'] ? 'checked' : ''; ?> />
              <label for="theme_shadow" class="form-check-label">Usar sombra nos cards</label>
            </div>
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
              <td><?php echo $row['status'] ? '<span class="badge bg-success">OK</span>' : '<span class="badge bg-danger">Erro</span>'; ?></td>
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
