<?php

define('PLUGIN_TIAO_VERSION', '1.0.0');
define('PLUGIN_TIAO_MIN_GLPI', '11.0.0');
define('PLUGIN_TIAO_MAX_GLPI', '12.0.99');

function plugin_init_tiao() {
    global $PLUGIN_HOOKS;

    Toolbox::logDebug('[Tião] plugin_init_tiao() carregado');

    $PLUGIN_HOOKS['csrf_compliant']['tiao'] = true;

    $PLUGIN_HOOKS['item_add']['tiao'] = [
        'Ticket'        => 'plugin_tiao_ticket_added',
        'Problem'       => 'plugin_tiao_problem_added',
        'ITILFollowup'  => 'plugin_tiao_followup_added',
        'ITILSolution'  => 'plugin_tiao_solution_added',
    ];
    $PLUGIN_HOOKS['item_update']['tiao'] = [
        'Ticket'        => 'plugin_tiao_ticket_updated',
        'Problem'       => 'plugin_tiao_problem_updated',
        'ITILSolution'  => 'plugin_tiao_solution_updated',
    ];

    $PLUGIN_HOOKS['config_page']['tiao'] = 'front/config.form.php';

    // Botão no formulário do chamado: abre o atendimento no Tião
    $PLUGIN_HOOKS['post_item_form']['tiao'] = 'plugin_tiao_post_item_form';
}

// Renderiza um botão "Abrir no Tião" no formulário do Ticket, com link para a
// conversa correspondente na plataforma (resolvida pelo id do chamado).
function plugin_tiao_post_item_form($params) {
    $item = $params['item'] ?? null;
    if (!($item instanceof Ticket) || $item->isNewItem()) return;

    $cfg = PluginTiaoConfig::get();
    $base = rtrim((string)($cfg['tiao_url'] ?? ''), '/');
    if ($base === '') return;

    $id  = (int) $item->getID();
    $url = $base . '/dashboard/atendimentos?ticket=' . $id;
    $u   = htmlspecialchars($url, ENT_QUOTES);
    echo '<div style="text-align:center;margin:8px 0;">'
       . '<a href="' . $u . '" target="_blank" rel="noopener" '
       . 'style="display:inline-flex;align-items:center;gap:6px;background:#D81F2A;color:#fff;'
       . 'padding:6px 14px;border-radius:8px;text-decoration:none;font-weight:600;">'
       . '🤖 Abrir no Tião</a></div>';
}

function plugin_version_tiao() {
    return [
        'name'         => 'Tião',
        'version'      => PLUGIN_TIAO_VERSION,
        'author'       => 'ITS Control',
        'homepage'     => 'https://tiao.ia.br',
        'license'      => 'MIT',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_TIAO_MIN_GLPI,
                'max' => PLUGIN_TIAO_MAX_GLPI,
            ],
            'php'  => [
                'min' => '8.1',
            ],
        ],
    ];
}

function plugin_tiao_check_prerequisites() {
    if (version_compare(GLPI_VERSION, PLUGIN_TIAO_MIN_GLPI, 'lt')) {
        echo 'Este plugin requer GLPI ' . PLUGIN_TIAO_MIN_GLPI . ' ou superior.';
        return false;
    }
    return true;
}

function plugin_tiao_check_config() {
    return true;
}

function plugin_tiao_ticket_added(Ticket $ticket) {
    Toolbox::logDebug('[Tião] ticket criado: #' . $ticket->getID());
    PluginTiaoNotifier::send('ticket.created', $ticket);
}

function plugin_tiao_ticket_updated(Ticket $ticket) {
    $relevant = ['status', 'name', 'content', 'priority', 'users_id_assign', 'groups_id_assign'];
    $updates  = array_keys($ticket->updates ?? []);
    if (empty(array_intersect($relevant, $updates))) {
        return;
    }
    PluginTiaoNotifier::send('ticket.updated', $ticket);
}

function plugin_tiao_problem_added(Problem $problem) {
    Toolbox::logDebug('[Tião] problem_added chamado: #' . $problem->getID());
    PluginTiaoNotifier::sendProblem('problem.created', $problem);
}

function plugin_tiao_problem_updated(Problem $problem) {
    Toolbox::logDebug('[Tião] problem_updated chamado: #' . $problem->getID());
    PluginTiaoNotifier::sendProblem('problem.updated', $problem);
}

function plugin_tiao_followup_added(ITILFollowup $followup) {
    PluginTiaoNotifier::sendFollowup('ticket.followup_added', $followup);
}

function plugin_tiao_solution_added(ITILSolution $solution) {
    PluginTiaoNotifier::sendSolution('ticket.solution_added', $solution);
}

function plugin_tiao_solution_updated(ITILSolution $solution) {
    PluginTiaoNotifier::sendSolution('ticket.solution_updated', $solution);
}
