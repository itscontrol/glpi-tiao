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
    PluginTiaoNotifier::sendProblem('problem.created', $problem);
}

function plugin_tiao_problem_updated(Problem $problem) {
    $relevant = ['status', 'name', 'content', 'priority', 'users_id_assign', 'groups_id_assign'];
    $updates  = array_keys($problem->updates ?? []);
    if (empty(array_intersect($relevant, $updates))) {
        return;
    }
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
