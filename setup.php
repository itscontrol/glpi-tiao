<?php

/**
 * Plugin Tião para GLPI
 * Integra o GLPI com a plataforma Tião (tiao.ia.br)
 */

define('PLUGIN_TIAO_VERSION', '1.0.0');
define('PLUGIN_TIAO_MIN_GLPI', '10.0.0');
define('PLUGIN_TIAO_MAX_GLPI', '11.0.99');

function plugin_init_tiao() {
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['tiao'] = true;

    // Hooks de eventos de ticket
    $PLUGIN_HOOKS[Hooks::ITEM_ADD]['tiao']    = ['Ticket' => 'plugin_tiao_ticket_added'];
    $PLUGIN_HOOKS[Hooks::ITEM_UPDATE]['tiao'] = ['Ticket' => 'plugin_tiao_ticket_updated'];

    // Página de configuração no menu Admin
    if (Session::haveRight('config', UPDATE)) {
        $PLUGIN_HOOKS['config_page']['tiao'] = 'front/config.form.php';
    }
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

// ─── Callbacks de evento ─────────────────────────────────────────────────────

function plugin_tiao_ticket_added(Ticket $ticket) {
    PluginTiaoNotifier::send('ticket.created', $ticket);
}

function plugin_tiao_ticket_updated(Ticket $ticket) {
    // Evita notificar quando só muda campo interno sem relevância
    $relevant = ['status', 'name', 'content', 'priority', 'users_id_assign', 'groups_id_assign'];
    $updates  = array_keys($ticket->updates ?? []);
    if (empty(array_intersect($relevant, $updates))) {
        return;
    }
    PluginTiaoNotifier::send('ticket.updated', $ticket);
}
