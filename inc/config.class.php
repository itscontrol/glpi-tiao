<?php

class PluginTiaoConfig extends CommonDBTM {

    static $rightname = 'config';
    private const TABLE = 'glpi_plugin_tiao_configs';

    static function getTypeName($nb = 0) {
        return 'Tião';
    }

    static function get(): array {
        global $DB;

        self::ensureStorage();

        $rows = [];
        foreach ($DB->request(['FROM' => self::TABLE]) as $row) {
            $rows[$row['name']] = $row['value'];
        }

        return [
            'tiao_url' => $rows['tiao_url'] ?? '',
            'api_key'  => $rows['api_key']  ?? '',
            'secret'   => $rows['secret']   ?? '',
            'active'   => (int) ($rows['active'] ?? 0),
        ];
    }

    static function save(array $data): void {
        global $DB;

        self::ensureStorage();

        $fields = [
            'tiao_url' => trim($data['tiao_url'] ?? ''),
            'api_key'  => trim($data['api_key']  ?? ''),
            'secret'   => trim($data['secret']   ?? ''),
            'active'   => isset($data['active']) ? '1' : '0',
        ];

        foreach ($fields as $name => $value) {
            $exists = countElementsInTable(self::TABLE, ['name' => $name]);
            if ($exists > 0) {
                $DB->update(self::TABLE, ['value' => $value], ['name' => $name]);
            } else {
                $DB->insert(self::TABLE, ['name' => $name, 'value' => $value]);
            }
        }
    }

    static function ensureStorage(): void {
        global $DB;

        if (!$DB->tableExists(self::TABLE)) {
            self::createStorage();
            self::insertMissingDefaults(self::defaults());
            return;
        }

        if (!self::hasColumn('name') || !self::hasColumn('value')) {
            $legacy = self::readLegacyValues();
            $DB->doQuery('DROP TABLE IF EXISTS `' . self::TABLE . '`');
            self::createStorage();
            self::insertMissingDefaults($legacy);
            return;
        }

        self::insertMissingDefaults(self::defaults());
    }

    private static function createStorage(): void {
        global $DB;

        $DB->doQuery("
            CREATE TABLE `" . self::TABLE . "` (
                `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name`       VARCHAR(64)  NOT NULL DEFAULT '',
                `value`      TEXT         DEFAULT NULL,
                `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `name` (`name`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    private static function insertMissingDefaults(array $values): void {
        global $DB;

        foreach ($values as $name => $value) {
            $exists = countElementsInTable(self::TABLE, ['name' => $name]);
            if ($exists === 0) {
                $DB->insert(self::TABLE, ['name' => $name, 'value' => (string) $value]);
            }
        }
    }

    private static function readLegacyValues(): array {
        global $DB;

        $values = self::defaults();
        foreach ($DB->request(['FROM' => self::TABLE, 'LIMIT' => 1]) as $row) {
            foreach (array_keys($values) as $key) {
                if (array_key_exists($key, $row)) {
                    $values[$key] = (string) $row[$key];
                }
            }
            break;
        }

        return $values;
    }

    private static function defaults(): array {
        return [
            'tiao_url' => '',
            'api_key'  => '',
            'secret'   => bin2hex(random_bytes(16)),
            'active'   => '1',
        ];
    }

    private static function hasColumn(string $field): bool {
        global $DB;

        return method_exists($DB, 'fieldExists')
            ? $DB->fieldExists(self::TABLE, $field, false)
            : false;
    }
}
