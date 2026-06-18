<?php

/**
 * Zabbix → GLPI Problema.
 * Recebe eventos de monitoramento do Zabbix e cria/correlaciona Problemas no GLPI.
 * Fase 1: criação + dedup por título/entidade + resolução de entidade por prefixo.
 * (Recovery/Acknowledge = Fase 2.)
 *
 * Ao criar o Problema nativamente, o hook item_add dispara o notifier
 * (plugin_tiao_problem_added → problem.created), então o problema aparece
 * automaticamente na aba "Problemas" do tiao-platform.
 */
class PluginTiaoZabbix {

    static function handle(array $payload): array {
        $title = self::normalizeTitle($payload['alert_subject'] ?? $payload['subject'] ?? $payload['title'] ?? '');
        if ($title === '') {
            throw new RuntimeException('alert_subject é obrigatório', 400);
        }

        $entityId = self::resolveEntity($payload);

        // Recovery / Acknowledge → Fase 2 (ainda não resolve/encerra aqui).
        if (self::isRecovery($payload) || self::isAcknowledge($payload)) {
            return ['processed' => true, 'action' => 'skipped_recovery_ack_phase2', 'title' => $title];
        }

        // Dedup: já existe Problema ABERTO com o mesmo título nessa entidade?
        // (O título do Zabbix é a chave de correlação.)
        $existingId = self::findOpenProblemByTitle($title, $entityId);
        if ($existingId) {
            return [
                'processed'  => true,
                'action'     => 'duplicate_open',
                'problem_id' => $existingId,
                'entity_id'  => $entityId,
                'title'      => $title,
            ];
        }

        $priority = self::severityToPriority($payload);
        $problem  = new Problem();
        $input = [
            'name'        => $title,
            'content'     => self::buildContent($payload, $title),
            'status'      => Problem::INCOMING,
            'urgency'     => $priority,
            'impact'      => 3,
            'priority'    => $priority,
            'entities_id' => $entityId,
        ];

        $id = $problem->add($input);
        if (!$id) {
            throw new RuntimeException('Falha ao criar problema', 500);
        }

        return [
            'processed'  => true,
            'action'     => 'created',
            'problem_id' => (int) $id,
            'entity_id'  => $entityId,
            'priority'   => $priority,
            'title'      => $title,
        ];
    }

    private static function normalizeTitle($value): string {
        $s = html_entity_decode((string) $value, ENT_QUOTES, 'UTF-8');
        return trim(preg_replace('/\s+/', ' ', $s));
    }

    private static function isRecovery(array $p): bool {
        return (string) ($p['event_source'] ?? '') === '0'
            && (string) ($p['event_value'] ?? '') === '0';
    }

    private static function isAcknowledge(array $p): bool {
        foreach (['acknowledge', 'acknowledged', 'ack', 'event_acknowledged'] as $k) {
            $v = strtolower(trim((string) ($p[$k] ?? '')));
            if (in_array($v, ['1', 'true', 'yes', 'sim'], true)) return true;
        }
        return false;
    }

    // Entidade: explícita (entity_id) → por prefixo (notification_subject_tag) → raiz (0).
    private static function resolveEntity(array $p): int {
        $explicit = $p['entity_id'] ?? $p['glpi_entity_id'] ?? null;
        if (is_numeric($explicit) && (int) $explicit >= 0) {
            return (int) $explicit;
        }
        $prefix = trim((string) ($p['prefix'] ?? $p['entity_prefix'] ?? $p['tag'] ?? ''));
        if ($prefix !== '') {
            $entity = new Entity();
            if ($entity->getFromDBByCrit(['notification_subject_tag' => $prefix])) {
                return (int) $entity->getID();
            }
        }
        return 0; // raiz
    }

    // Severidade do Zabbix (event_nseverity 0–5) → prioridade GLPI (1–5). 0/ausente = 3.
    private static function severityToPriority(array $p): int {
        $sev = $p['priority'] ?? $p['event_nseverity'] ?? $p['urgency'] ?? null;
        $n = is_numeric($sev) ? (int) $sev : 3;
        if ($n < 1) $n = 3;
        if ($n > 5) $n = 5;
        return $n;
    }

    private static function buildContent(array $p, string $title): string {
        $msg  = trim(strip_tags((string) ($p['alert_message'] ?? $p['message'] ?? $p['body'] ?? '')));
        $host = self::extractHost($p);
        $parts = [];
        if ($host !== '') $parts[] = "Host: $host";
        if ($msg !== '')  $parts[] = $msg;
        if (empty($parts)) $parts[] = $title;
        return implode("\n\n", $parts);
    }

    private static function extractHost(array $p): string {
        foreach (['host', 'host_name', 'hostname', 'zabbix_host'] as $k) {
            if (!empty($p[$k])) return trim((string) $p[$k]);
        }
        return '';
    }

    // Problema aberto (não Solucionado/Fechado) com o mesmo título na entidade.
    private static function findOpenProblemByTitle(string $title, int $entityId): ?int {
        global $DB;
        $rows = $DB->request([
            'SELECT' => ['id'],
            'FROM'   => 'glpi_problems',
            'WHERE'  => [
                'name'        => $title,
                'is_deleted'  => 0,
                'entities_id' => $entityId,
                'NOT'         => ['status' => [Problem::SOLVED, Problem::CLOSED]],
            ],
            'ORDER'  => ['id DESC'],
            'LIMIT'  => 1,
        ]);
        foreach ($rows as $row) {
            return (int) $row['id'];
        }
        return null;
    }
}
