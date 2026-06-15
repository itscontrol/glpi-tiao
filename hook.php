<?php

function plugin_tiao_install() {
    global $DB;

    // Tabela de configuração do plugin
    if (!$DB->tableExists('glpi_plugin_tiao_configs')) {
        $query = "CREATE TABLE `glpi_plugin_tiao_configs` (
            `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `tiao_url`    VARCHAR(255) NOT NULL DEFAULT '',
            `api_key`     VARCHAR(255) NOT NULL DEFAULT '',
            `secret`      VARCHAR(255) NOT NULL DEFAULT '',
            `active`      TINYINT(1)   NOT NULL DEFAULT 1,
            `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        $DB->query($query) or die($DB->error());

        // Linha única de config
        $DB->insert('glpi_plugin_tiao_configs', [
            'tiao_url' => '',
            'api_key'  => '',
            'secret'   => bin2hex(random_bytes(16)),
        ]);
    }

    // Log de eventos enviados ao Tião
    if (!$DB->tableExists('glpi_plugin_tiao_events')) {
        $query = "CREATE TABLE `glpi_plugin_tiao_events` (
            `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `event`       VARCHAR(64)  NOT NULL,
            `ticket_id`   INT UNSIGNED NOT NULL,
            `payload`     TEXT         NOT NULL,
            `status`      TINYINT(1)   NOT NULL DEFAULT 0,
            `response`    TEXT         DEFAULT NULL,
            `sent_at`     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `ticket_id` (`ticket_id`),
            KEY `status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
        $DB->query($query) or die($DB->error());
    }

    return true;
}

function plugin_tiao_uninstall() {
    global $DB;
    $DB->query("DROP TABLE IF EXISTS `glpi_plugin_tiao_configs`");
    $DB->query("DROP TABLE IF EXISTS `glpi_plugin_tiao_events`");
    return true;
}
