<?php

function plugin_tiao_install() {
    global $DB;

    // GLPI 11 usa Migration para criar tabelas
    $migration = new Migration(PLUGIN_TIAO_VERSION);

    // Tabela de configuração
    if (!$DB->tableExists('glpi_plugin_tiao_configs')) {
        $migration->addField('glpi_plugin_tiao_configs', 'id',         'autoincrement');
        $migration->addField('glpi_plugin_tiao_configs', 'tiao_url',   'string',  ['value' => '']);
        $migration->addField('glpi_plugin_tiao_configs', 'api_key',    'string',  ['value' => '']);
        $migration->addField('glpi_plugin_tiao_configs', 'secret',     'string',  ['value' => '']);
        $migration->addField('glpi_plugin_tiao_configs', 'active',     'bool',    ['value' => 1]);
        $migration->addField('glpi_plugin_tiao_configs', 'created_at', 'datetime');
        $migration->addField('glpi_plugin_tiao_configs', 'updated_at', 'datetime');
        $migration->migrationOneTable('glpi_plugin_tiao_configs');

        $secret = bin2hex(random_bytes(16));
        $DB->insert('glpi_plugin_tiao_configs', [
            'tiao_url'   => '',
            'api_key'    => '',
            'secret'     => $secret,
            'active'     => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    // Tabela de log de eventos
    if (!$DB->tableExists('glpi_plugin_tiao_events')) {
        $migration->addField('glpi_plugin_tiao_events', 'id',        'autoincrement');
        $migration->addField('glpi_plugin_tiao_events', 'event',     'string');
        $migration->addField('glpi_plugin_tiao_events', 'ticket_id', 'integer', ['value' => 0]);
        $migration->addField('glpi_plugin_tiao_events', 'payload',   'text');
        $migration->addField('glpi_plugin_tiao_events', 'status',    'bool',    ['value' => 0]);
        $migration->addField('glpi_plugin_tiao_events', 'response',  'text');
        $migration->addField('glpi_plugin_tiao_events', 'sent_at',   'datetime');
        $migration->addKey('glpi_plugin_tiao_events',   ['ticket_id']);
        $migration->addKey('glpi_plugin_tiao_events',   ['status']);
        $migration->migrationOneTable('glpi_plugin_tiao_events');
    }

    $migration->executeMigration();

    return true;
}

function plugin_tiao_uninstall() {
    global $DB;

    $tables = [
        'glpi_plugin_tiao_configs',
        'glpi_plugin_tiao_events',
    ];

    foreach ($tables as $table) {
        if ($DB->tableExists($table)) {
            $DB->doQuery("DROP TABLE IF EXISTS `$table`");
        }
    }

    return true;
}
