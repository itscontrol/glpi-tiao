<?php

class PluginTiaoConfig extends CommonDBTM {

    static $rightname = 'config';

    static function getTypeName($nb = 0) {
        return 'Tião';
    }

    static function get(): array {
        global $DB;

        $rows = [];
        foreach ($DB->request(['FROM' => 'glpi_plugin_tiao_configs']) as $row) {
            $rows[$row['name']] = $row['value'];
        }

        return [
            'tiao_url' => $rows['tiao_url'] ?? '',
            'api_key'  => $rows['api_key']  ?? '',
            'secret'   => $rows['secret']   ?? '',
            'active'   => $rows['active']   ?? 0,
        ];
    }

    static function save(array $data): void {
        global $DB;

        $fields = [
            'tiao_url' => trim($data['tiao_url'] ?? ''),
            'api_key'  => trim($data['api_key']  ?? ''),
            'active'   => isset($data['active']) ? '1' : '0',
        ];

        foreach ($fields as $name => $value) {
            $exists = countElementsInTable('glpi_plugin_tiao_configs', ['name' => $name]);
            if ($exists > 0) {
                $DB->update('glpi_plugin_tiao_configs', ['value' => $value], ['name' => $name]);
            } else {
                $DB->insert('glpi_plugin_tiao_configs', ['name' => $name, 'value' => $value]);
            }
        }
    }
}
