<?php

define('PLUGIN_TIAO_VERSION', '1.0.0');
define('PLUGIN_TIAO_MIN_GLPI', '11.0.0');
define('PLUGIN_TIAO_MAX_GLPI', '12.0.99');

function plugin_init_tiao() {
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['tiao'] = true;

    $PLUGIN_HOOKS['item_add']['tiao']    = ['Ticket' => 'plugin_tiao_ticket_added'];
    $PLUGIN_HOOKS['item_update']['tiao'] = ['Ticket' => 'plugin_tiao_ticket_updated'];

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
