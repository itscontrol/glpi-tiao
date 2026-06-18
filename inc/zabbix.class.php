<?php

/**
 * Zabbix → GLPI Problema.
 * Recebe eventos de monitoramento do Zabbix e cria/correlaciona Problemas no GLPI.
 * - Problema (alerta): cria (com dedup por título+entidade) ou ignora se já aberto.
 * - Recovery (Zabbix recuperou): coloca o Problema em Observação.
 * - Acknowledge (técnico reconheceu): registra solução e Soluciona o Problema.
 *
 * Tudo nativo (sem token/REST). Ao criar/atualizar/solucionar, os hooks do GLPI
 * disparam o notifier (problem.created/updated/solution_added) → o tiao-platform
 * reflete na aba "Problemas".
 */
class PluginTiaoZabbix {

    // Status ITIL (GLPI). Locais para não depender de constantes do core.
    const ST_NEW      = 1;
    const ST_SOLVED   = 5;
    const ST_CLOSED   = 6;
    const ST_OBSERVED = 8;

    static function handle(array $payload): array {
        $title = self::normalizeTitle($payload['alert_subject'] ?? $payload['subject'] ?? $payload['title'] ?? '');
        if ($title === '') {
            throw new RuntimeException('alert_subject é obrigatório', 400);
        }

        $entityId = self::resolveEntity($payload);
        $recovery = self::isRecovery($payload);
        $ack      = self::isAcknowledge($payload);

        // Recovery / Acknowledge agem sobre um Problema já existente.
        if ($recovery || $ack) {
            $problemId = self::resolveExistingProblemId($payload, $title, $entityId);
            if (!$problemId) {
                return ['processed' => true, 'action' => 'no_open_problem', 'title' => $title];
            }
            return $ack
                ? self::acknowledgeSolve($problemId, $payload, $title)
                : self::recoveryObserve($problemId, $payload);
        }

        // Alerta novo: dedup por título aberto na entidade (título = chave de correlação).
        $existingId = self::findOpenProblemByTitle($title, $entityId);
        if ($existingId) {
            return ['processed' => true, 'action' => 'duplicate_open', 'problem_id' => $existingId, 'entity_id' => $entityId, 'title' => $title];
        }

        $priority = self::severityToPriority($payload);
        $problem  = new Problem();
        $id = $problem->add([
            'name'        => $title,
            'content'     => self::buildContent($payload, $title),
            'status'      => self::ST_NEW,
            'urgency'     => $priority,
            'impact'      => 3,
            'priority'    => $priority,
            'entities_id' => $entityId,
        ]);
        if (!$id) {
            throw new RuntimeException('Falha ao criar problema', 500);
        }

        return ['processed' => true, 'action' => 'created', 'problem_id' => (int) $id, 'entity_id' => $entityId, 'priority' => $priority, 'title' => $title];
    }

    private static function recoveryObserve(int $problemId, array $payload): array {
        $problem = new Problem();
        if (!$problem->getFromDB($problemId)) {
            return ['processed' => true, 'action' => 'recovery_problem_not_found', 'problem_id' => $problemId];
        }
        if (in_array((int) $problem->fields['status'], [self::ST_SOLVED, self::ST_CLOSED], true)) {
            return ['processed' => true, 'action' => 'recovery_skip_closed', 'problem_id' => $problemId];
        }
        $note = trim(strip_tags((string) ($payload['recovery_message'] ?? $payload['alert_message'] ?? $payload['message'] ?? '')));
        $fup  = new ITILFollowup();
        $fup->add([
            'itemtype'   => 'Problem',
            'items_id'   => $problemId,
            'content'    => 'Recuperação detectada no monitoramento (Zabbix).' . ($note !== '' ? "\n\n$note" : ''),
            'is_private' => 0,
        ]);
        $problem->update(['id' => $problemId, 'status' => self::ST_OBSERVED]);
        return ['processed' => true, 'action' => 'recovery_observed', 'problem_id' => $problemId];
    }

    private static function acknowledgeSolve(int $problemId, array $payload, string $title): array {
        $problem = new Problem();
        if (!$problem->getFromDB($problemId)) {
            return ['processed' => true, 'action' => 'ack_problem_not_found', 'problem_id' => $problemId];
        }
        if (in_array((int) $problem->fields['status'], [self::ST_SOLVED, self::ST_CLOSED], true)) {
            return ['processed' => true, 'action' => 'ack_skip_closed', 'problem_id' => $problemId];
        }
        $solution = new ITILSolution();
        $sid = $solution->add([
            'itemtype' => 'Problem',
            'items_id' => $problemId,
            'content'  => self::buildAckSolution($payload, $title),
        ]);
        // Adicionar solução já move para Solucionado; garante o status.
        $fresh = new Problem();
        if ($fresh->getFromDB($problemId) && (int) $fresh->fields['status'] !== self::ST_SOLVED) {
            $fresh->update(['id' => $problemId, 'status' => self::ST_SOLVED]);
        }
        return ['processed' => true, 'action' => 'acknowledge_solved', 'problem_id' => $problemId, 'solution_id' => (int) $sid];
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

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

    private static function buildAckSolution(array $p, string $title): string {
        $obs = trim(strip_tags((string) ($p['acknowledge_message'] ?? $p['ack_message'] ?? $p['message'] ?? $p['observation'] ?? '')));
        $who = trim((string) ($p['ack_user'] ?? $p['user'] ?? ''));
        $parts = [];
        if ($obs !== '') $parts[] = $obs;
        if ($who !== '') $parts[] = "Reconhecido por: $who";
        if (empty($parts)) $parts[] = "Reconhecido no monitoramento (Zabbix): $title";
        return implode("\n\n", $parts);
    }

    private static function extractHost(array $p): string {
        foreach (['host', 'host_name', 'hostname', 'zabbix_host'] as $k) {
            if (!empty($p[$k])) return trim((string) $p[$k]);
        }
        return '';
    }

    private static function resolveExistingProblemId(array $p, string $title, int $entityId): ?int {
        $explicit = $p['problem_id'] ?? $p['glpi_problem_id'] ?? null;
        if (is_numeric($explicit) && (int) $explicit > 0) {
            $pr = new Problem();
            if ($pr->getFromDB((int) $explicit)) return (int) $explicit;
        }
        return self::findOpenProblemByTitle($title, $entityId);
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
                'NOT'         => ['status' => [self::ST_SOLVED, self::ST_CLOSED]],
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
