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
            'theme_enabled'       => (int) ($rows['theme_enabled'] ?? 1),
            'theme_body_bg'       => $rows['theme_body_bg'] ?? '#111827',
            'theme_header_bg'     => $rows['theme_header_bg'] ?? '#0B1220',
            'theme_sidebar_bg'    => $rows['theme_sidebar_bg'] ?? '#0B1220',
            'theme_sidebar_fg'    => $rows['theme_sidebar_fg'] ?? '#E6E8EC',
            'theme_card_bg'       => $rows['theme_card_bg'] ?? '#182333',
            'theme_card_hover_bg' => $rows['theme_card_hover_bg'] ?? '#22314A',
            'theme_border'        => $rows['theme_border'] ?? '#31425C',
            'theme_primary'       => $rows['theme_primary'] ?? '#4FA3FF',
            'theme_primary_dark'  => $rows['theme_primary_dark'] ?? '#0047A1',
            'theme_accent'        => $rows['theme_accent'] ?? '#E5242A',
            'theme_text'          => $rows['theme_text'] ?? '#F4F6F9',
            'theme_text_muted'    => $rows['theme_text_muted'] ?? '#C3D0E4',
            'theme_text_disabled' => $rows['theme_text_disabled'] ?? '#8796AF',
            'theme_input_bg'      => $rows['theme_input_bg'] ?? '#182333',
            'theme_input_border'  => $rows['theme_input_border'] ?? '#34445C',
            'theme_table_bg'      => $rows['theme_table_bg'] ?? '#182333',
            'theme_table_alt_bg'  => $rows['theme_table_alt_bg'] ?? '#141F2D',
            'theme_table_hover'   => $rows['theme_table_hover'] ?? '#22314A',
            'theme_radius'        => $rows['theme_radius'] ?? '10',
            'theme_card_radius'   => $rows['theme_card_radius'] ?? '12',
            'theme_shadow'        => $rows['theme_shadow'] ?? '1',
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
            'theme_enabled'       => isset($data['theme_enabled']) ? '1' : '0',
            'theme_body_bg'       => self::normalizeColor($data['theme_body_bg'] ?? '#111827', '#111827'),
            'theme_header_bg'     => self::normalizeColor($data['theme_header_bg'] ?? '#0B1220', '#0B1220'),
            'theme_sidebar_bg'    => self::normalizeColor($data['theme_sidebar_bg'] ?? '#0B1220', '#0B1220'),
            'theme_sidebar_fg'    => self::normalizeColor($data['theme_sidebar_fg'] ?? '#E6E8EC', '#E6E8EC'),
            'theme_card_bg'       => self::normalizeColor($data['theme_card_bg'] ?? '#182333', '#182333'),
            'theme_card_hover_bg' => self::normalizeColor($data['theme_card_hover_bg'] ?? '#22314A', '#22314A'),
            'theme_border'        => self::normalizeColor($data['theme_border'] ?? '#31425C', '#31425C'),
            'theme_primary'       => self::normalizeColor($data['theme_primary'] ?? '#4FA3FF', '#4FA3FF'),
            'theme_primary_dark'  => self::normalizeColor($data['theme_primary_dark'] ?? '#0047A1', '#0047A1'),
            'theme_accent'        => self::normalizeColor($data['theme_accent'] ?? '#E5242A', '#E5242A'),
            'theme_text'          => self::normalizeColor($data['theme_text'] ?? '#F4F6F9', '#F4F6F9'),
            'theme_text_muted'    => self::normalizeColor($data['theme_text_muted'] ?? '#C3D0E4', '#C3D0E4'),
            'theme_text_disabled' => self::normalizeColor($data['theme_text_disabled'] ?? '#8796AF', '#8796AF'),
            'theme_input_bg'      => self::normalizeColor($data['theme_input_bg'] ?? '#182333', '#182333'),
            'theme_input_border'  => self::normalizeColor($data['theme_input_border'] ?? '#34445C', '#34445C'),
            'theme_table_bg'      => self::normalizeColor($data['theme_table_bg'] ?? '#182333', '#182333'),
            'theme_table_alt_bg'  => self::normalizeColor($data['theme_table_alt_bg'] ?? '#141F2D', '#141F2D'),
            'theme_table_hover'   => self::normalizeColor($data['theme_table_hover'] ?? '#22314A', '#22314A'),
            'theme_radius'        => self::normalizePixels($data['theme_radius'] ?? '10', '10'),
            'theme_card_radius'   => self::normalizePixels($data['theme_card_radius'] ?? '12', '12'),
            'theme_shadow'        => isset($data['theme_shadow']) ? '1' : '0',
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
            'theme_enabled'       => '1',
            'theme_body_bg'       => '#111827',
            'theme_header_bg'     => '#0B1220',
            'theme_sidebar_bg'    => '#0B1220',
            'theme_sidebar_fg'    => '#E6E8EC',
            'theme_card_bg'       => '#182333',
            'theme_card_hover_bg' => '#22314A',
            'theme_border'        => '#31425C',
            'theme_primary'       => '#4FA3FF',
            'theme_primary_dark'  => '#0047A1',
            'theme_accent'        => '#E5242A',
            'theme_text'          => '#F4F6F9',
            'theme_text_muted'    => '#C3D0E4',
            'theme_text_disabled' => '#8796AF',
            'theme_input_bg'      => '#182333',
            'theme_input_border'  => '#34445C',
            'theme_table_bg'      => '#182333',
            'theme_table_alt_bg'  => '#141F2D',
            'theme_table_hover'   => '#22314A',
            'theme_radius'        => '10',
            'theme_card_radius'   => '12',
            'theme_shadow'        => '1',
        ];
    }

    private static function normalizeColor(string $value, string $fallback): string {
        $value = trim($value);
        if (preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
            return strtoupper($value);
        }

        return $fallback;
    }

    private static function normalizePixels(string $value, string $fallback): string {
        $value = trim($value);
        if (preg_match('/^\d{1,2}$/', $value)) {
            return (string) min(24, max(0, (int) $value));
        }

        return $fallback;
    }

    private static function hasColumn(string $field): bool {
        global $DB;

        if (method_exists($DB, 'fieldExists')) {
            return (bool) $DB->fieldExists(self::TABLE, $field, false);
        }

        // fieldExists não existe nesta versão do GLPI — consulta information_schema.
        // Fallback seguro: assume que a coluna existe para não disparar migração
        // destrutiva (DROP + recreate) que apagaria toda a config.
        try {
            $result = $DB->doQuery(
                "SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = '" . self::TABLE . "'
                   AND COLUMN_NAME = '" . $field . "'"
            );
            if ($result) {
                $row = $DB->fetchAssoc($result);
                return isset($row['cnt']) && (int) $row['cnt'] > 0;
            }
        } catch (\Throwable $e) {
            // Ignore — assume exists
        }

        return true;
    }
}
