<?php

class PluginTiaoConfig extends CommonDBTM {

    static $rightname = 'config';

    static function getTypeName($nb = 0) {
        return 'Tião';
    }

    static function get(): array {
        global $DB;

        $result = $DB->request([
            'FROM'  => 'glpi_plugin_tiao_configs',
            'LIMIT' => 1,
        ]);

        if ($row = $result->current()) {
            return $row;
        }

        return ['tiao_url' => '', 'api_key' => '', 'secret' => '', 'active' => 0];
    }

    static function save(array $data): void {
        global $DB;

        $result = $DB->request(['FROM' => 'glpi_plugin_tiao_configs', 'LIMIT' => 1]);
        $row    = $result->current();

        $fields = [
            'tiao_url' => trim($data['tiao_url'] ?? ''),
            'api_key'  => trim($data['api_key'] ?? ''),
            'active'   => isset($data['active']) ? 1 : 0,
        ];

        if ($row) {
            $DB->update('glpi_plugin_tiao_configs', $fields, ['id' => $row['id']]);
        } else {
            $DB->insert('glpi_plugin_tiao_configs', array_merge($fields, [
                'secret' => bin2hex(random_bytes(16)),
            ]));
        }
    }
}
