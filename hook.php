<?php

function plugin_tiao_install() {
    global $DB;

    PluginTiaoConfig::ensureStorage();

    if (!$DB->tableExists('glpi_plugin_tiao_events')) {
        $DB->doQuery("
            CREATE TABLE `glpi_plugin_tiao_events` (
                `id`        INT UNSIGNED  NOT NULL AUTO_INCREMENT,
                `event`     VARCHAR(64)   NOT NULL DEFAULT '',
                `ticket_id` INT UNSIGNED  NOT NULL DEFAULT 0,
                `payload`   LONGTEXT      NOT NULL,
                `status`    TINYINT(1)    NOT NULL DEFAULT 0,
                `response`  TEXT          DEFAULT NULL,
                `sent_at`   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `ticket_id` (`ticket_id`),
                KEY `status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    return true;
}

function plugin_tiao_uninstall() {
    global $DB;

    foreach (['glpi_plugin_tiao_configs', 'glpi_plugin_tiao_events'] as $table) {
        if ($DB->tableExists($table)) {
            $DB->doQuery("DROP TABLE IF EXISTS `$table`");
        }
    }

    return true;
}
