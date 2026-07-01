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
        'Change'        => 'plugin_tiao_change_added',
        'ITILFollowup'  => 'plugin_tiao_followup_added',
        'ITILSolution'  => 'plugin_tiao_solution_added',
        'TicketTask'    => 'plugin_tiao_task_added',
    ];
    $PLUGIN_HOOKS['item_update']['tiao'] = [
        'Ticket'        => 'plugin_tiao_ticket_updated',
        'Problem'       => 'plugin_tiao_problem_updated',
        'Change'        => 'plugin_tiao_change_updated',
        'ITILSolution'  => 'plugin_tiao_solution_updated',
        'TicketTask'    => 'plugin_tiao_task_updated',
    ];
    $PLUGIN_HOOKS['item_purge']['tiao'] = [
        'Ticket'  => 'plugin_tiao_ticket_purged',
        'Problem' => 'plugin_tiao_problem_purged',
        'Change'  => 'plugin_tiao_change_purged',
    ];

    $PLUGIN_HOOKS['config_page']['tiao'] = 'front/config.form.php';

    // Link direto no formulário do chamado
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
    $svg = plugin_tiao_logo_svg(20);
    echo '<div style="margin:8px 0;">'
       . '<div style="text-align:center;">'
       . '<a href="' . $u . '" target="_blank" rel="noopener" '
       . 'style="display:inline-flex;align-items:center;gap:8px;background:#D81F2A;color:#fff;'
       . 'padding:6px 14px;border-radius:8px;text-decoration:none;font-weight:600;">'
       . $svg . 'Abrir no Tião</a></div>'
       . '</div>';
}

// Logo do Tião (mascote) como SVG inline — evita depender de caminho de asset
// servido pelo GLPI. `$size` em px.
function plugin_tiao_logo_svg(int $size = 20): string {
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" '
       . 'width="' . $size . '" height="' . $size . '" '
       . 'style="display:block;border-radius:5px;flex:0 0 auto;" aria-hidden="true">'
       . '<rect width="64" height="64" rx="14" fill="#06162B"/>'
       . '<circle cx="32" cy="34" r="24" fill="#003A8C"/>'
       . '<path d="M17 33c0-12 7-21 15-21s15 9 15 21v9c0 8-7 15-15 15s-15-7-15-15z" fill="#FFD2A6"/>'
       . '<path d="M17 32c1-13 8-22 15-22s14 8 15 22c-8-7-22-7-30 0z" fill="#0046AD"/>'
       . '<circle cx="24" cy="36" r="4" fill="#111"/>'
       . '<circle cx="40" cy="36" r="4" fill="#111"/>'
       . '<path d="M25 45c4 4 10 4 14 0" fill="none" stroke="#111" stroke-width="3" stroke-linecap="round"/>'
       . '<circle cx="13" cy="34" r="7" fill="#fff"/>'
       . '<circle cx="51" cy="34" r="7" fill="#fff"/>'
       . '<circle cx="13" cy="34" r="4" fill="#E30613"/>'
       . '<circle cx="51" cy="34" r="4" fill="#E30613"/>'
       . '<rect x="30" y="4" width="4" height="10" rx="2" fill="#E30613"/>'
       . '<circle cx="32" cy="4" r="4" fill="#E30613"/>'
       . '</svg>';
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
    // Envia sempre — a plataforma detecta o que mudou (status, priority, SLA…).
    // O filtro por $ticket->updates foi removido porque no GLPI 11 a propriedade
    // não está populada no momento do hook item_update.
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

function plugin_tiao_change_added(Change $change) {
    Toolbox::logDebug('[Tião] change_added chamado: #' . $change->getID());
    PluginTiaoNotifier::sendChange('change.created', $change);
}

function plugin_tiao_change_updated(Change $change) {
    Toolbox::logDebug('[Tião] change_updated chamado: #' . $change->getID());
    PluginTiaoNotifier::sendChange('change.updated', $change);
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

function plugin_tiao_task_added(TicketTask $task) {
    PluginTiaoNotifier::sendTask('ticket.task_added', $task);
}

function plugin_tiao_task_updated(TicketTask $task) {
    PluginTiaoNotifier::sendTask('ticket.task_updated', $task);
}

function plugin_tiao_ticket_purged(Ticket $ticket) {
    PluginTiaoNotifier::sendDeleted('ticket.deleted', 'Ticket', (int) $ticket->fields['id']);
}

function plugin_tiao_problem_purged(Problem $problem) {
    PluginTiaoNotifier::sendDeleted('problem.deleted', 'Problem', (int) $problem->fields['id']);
}

function plugin_tiao_change_purged(Change $change) {
    PluginTiaoNotifier::sendDeleted('change.deleted', 'Change', (int) $change->fields['id']);
}
