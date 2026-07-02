<?php

/**
 * Endpoint REST para o Tião chamar o GLPI.
 * Tião → GLPI
 */
class PluginTiaoApi {

    static function dispatch(): void {
        header('Content-Type: application/json');

        try {
            self::authenticate();

            $body   = json_decode(file_get_contents('php://input'), true);
            $action = $body['action'] ?? '';

            $result = match ($action) {
                'ticket.create'     => self::createTicket($body),
                'ticket.update'     => self::updateTicket($body),
                'ticket.status'     => self::updateTicketStatus($body),
                'ticket.close'      => self::closeTicket($body),
                'ticket.followup'   => self::addFollowup($body),
                'ticket.get'        => self::getTicket($body),
                'report.billing'    => self::reportBilling($body),
                'zabbix.event'      => PluginTiaoZabbix::handle($body),
                default             => throw new RuntimeException("Ação desconhecida: $action", 400),
            };

            echo json_encode(['ok' => true, 'data' => $result]);

        } catch (RuntimeException $e) {
            http_response_code($e->getCode() ?: 400);
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'Erro interno']);
            Toolbox::logError($e->getMessage());
        }
    }

    // ─── Autenticação ────────────────────────────────────────────────────────

    private static function authenticate(): void {
        $config = PluginTiaoConfig::get();

        if (!$config['active']) {
            throw new RuntimeException('Plugin desativado', 403);
        }

        $apiKey = $_SERVER['HTTP_X_TIAO_API_KEY'] ?? '';
        if (empty($config['api_key']) || !hash_equals($config['api_key'], $apiKey)) {
            throw new RuntimeException('API key inválida', 401);
        }

        $signature = $_SERVER['HTTP_X_TIAO_SIGNATURE'] ?? '';
        if ($signature) {
            $body     = file_get_contents('php://input');
            $expected = hash_hmac('sha256', $body, $config['secret']);
            if (!hash_equals($expected, $signature)) {
                throw new RuntimeException('Assinatura inválida', 401);
            }
        }
    }

    // ─── Ações ───────────────────────────────────────────────────────────────

    private static function createTicket(array $body): array {
        $ticket = new Ticket();

        $input = [
            'name'              => $body['title']       ?? 'Chamado via Tião',
            'content'           => $body['content']     ?? '',
            'priority'          => $body['priority']    ?? 3,
            'entities_id'       => $body['entity_id']  ?? 0,
            'itilcategories_id' => $body['category_id'] ?? 0,
            'status'            => Ticket::INCOMING,
            '_actors'           => [],
        ];

        if (!empty($body['requester_id'])) {
            $input['_actors']['requester'][] = [
                'itemtype'  => 'User',
                'items_id'  => (int) $body['requester_id'],
                'use_notification' => 1,
            ];
        }

        if (!empty($body['assignee_id'])) {
            $input['_actors']['assign'][] = [
                'itemtype'  => 'User',
                'items_id'  => (int) $body['assignee_id'],
                'use_notification' => 1,
            ];
        }

        $id = $ticket->add($input);
        if (!$id) {
            throw new RuntimeException('Falha ao criar ticket', 500);
        }

        return ['ticket_id' => $id];
    }

    private static function updateTicket(array $body): array {
        self::requireField($body, 'ticket_id');

        $ticket = new Ticket();
        if (!$ticket->getFromDB((int) $body['ticket_id'])) {
            throw new RuntimeException('Ticket não encontrado', 404);
        }

        $input = ['id' => (int) $body['ticket_id']];
        if (isset($body['title']))       $input['name']     = $body['title'];
        if (isset($body['content']))     $input['content']  = $body['content'];
        if (isset($body['priority']))    $input['priority'] = (int) $body['priority'];
        if (isset($body['status']))      $input['status']   = (int) $body['status'];
        if (isset($body['category_id'])) $input['itilcategories_id'] = (int) $body['category_id'];

        $ticket->update($input);
        return ['ticket_id' => (int) $body['ticket_id']];
    }

    private static function updateTicketStatus(array $body): array {
        global $DB;

        self::requireField($body, 'ticket_id');
        self::requireField($body, 'status');

        $ticketId = (int) $body['ticket_id'];
        $status = (int) $body['status'];
        $ticket = new Ticket();
        if (!$ticket->getFromDB($ticketId)) {
            throw new RuntimeException('Ticket não encontrado', 404);
        }

        $previousStatus = (int) $ticket->fields['status'];
        if ($status === Ticket::WAITING) {
            $reason = trim((string) ($body['pending_reason'] ?? ''));
            $pendingUntil = trim((string) ($body['pending_until'] ?? ''));
            if ($reason === '' || $pendingUntil === '' || strtotime($pendingUntil) <= time()) {
                throw new RuntimeException('Motivo e data futura são obrigatórios para Pendente', 400);
            }
            if (!Plugin::isPluginActive('moreticket')
                || !$DB->tableExists('glpi_plugin_moreticket_waitingtickets')) {
                throw new RuntimeException('Plugin MoreTicket não está ativo', 409);
            }
        }

        if (!$ticket->update(['id' => $ticketId, 'status' => $status])) {
            throw new RuntimeException('Falha ao alterar status do ticket', 500);
        }

        if ($status === Ticket::WAITING) {
            $active = null;
            foreach ($DB->request([
                'FROM' => 'glpi_plugin_moreticket_waitingtickets',
                'WHERE' => [
                    'tickets_id' => $ticketId,
                    'date_end_suspension' => null,
                ],
                'ORDERBY' => ['date_suspension DESC'],
                'LIMIT' => 1,
            ]) as $row) {
                $active = $row;
                break;
            }

            $waitingData = [
                'reason' => trim((string) $body['pending_reason']),
                'date_report' => (string) $body['pending_until'],
            ];
            if ($active) {
                $saved = $DB->update(
                    'glpi_plugin_moreticket_waitingtickets',
                    $waitingData,
                    ['id' => (int) $active['id']]
                );
            } else {
                $saved = $DB->insert('glpi_plugin_moreticket_waitingtickets', $waitingData + [
                    'tickets_id' => $ticketId,
                    'date_suspension' => date('Y-m-d H:i:s'),
                    'date_end_suspension' => null,
                    'plugin_moreticket_waitingtypes_id' => 0,
                    'status' => $previousStatus === Ticket::WAITING ? Ticket::ASSIGNED : $previousStatus,
                ]);
            }
            if (!$saved) {
                $ticket->update(['id' => $ticketId, 'status' => $previousStatus]);
                throw new RuntimeException('Falha ao salvar motivo e data no MoreTicket', 500);
            }
        } elseif ($previousStatus === Ticket::WAITING
                  && $DB->tableExists('glpi_plugin_moreticket_waitingtickets')) {
            $DB->update(
                'glpi_plugin_moreticket_waitingtickets',
                ['date_end_suspension' => date('Y-m-d H:i:s')],
                ['tickets_id' => $ticketId, 'date_end_suspension' => null]
            );
        }

        return [
            'ticket_id' => $ticketId,
            'status' => $status,
            'pending_saved' => true,
        ];
    }

    private static function closeTicket(array $body): array {
        self::requireField($body, 'ticket_id');

        $ticket = new Ticket();
        if (!$ticket->getFromDB((int) $body['ticket_id'])) {
            throw new RuntimeException('Ticket não encontrado', 404);
        }

        $input = [
            'id'       => (int) $body['ticket_id'],
            'status'   => Ticket::CLOSED,
            'solution' => $body['solution'] ?? 'Resolvido via Tião',
        ];

        if (!empty($body['solution'])) {
            $solution = new ITILSolution();
            $solution->add([
                'itemtype' => 'Ticket',
                'items_id' => (int) $body['ticket_id'],
                'content'  => $body['solution'],
                'status'   => CommonITILValidation::ACCEPTED,
            ]);
        }

        $ticket->update($input);
        return ['ticket_id' => (int) $body['ticket_id']];
    }

    private static function addFollowup(array $body): array {
        self::requireField($body, 'ticket_id');
        self::requireField($body, 'content');

        $followup = new ITILFollowup();
        $id = $followup->add([
            'itemtype'        => 'Ticket',
            'items_id'        => (int) $body['ticket_id'],
            'content'         => $body['content'],
            'is_private'      => $body['private'] ?? 0,
            'requesttypes_id' => 0,
        ]);

        if (!$id) {
            throw new RuntimeException('Falha ao adicionar acompanhamento', 500);
        }

        return ['followup_id' => $id];
    }

    private static function getTicket(array $body): array {
        self::requireField($body, 'ticket_id');

        $ticket = new Ticket();
        if (!$ticket->getFromDB((int) $body['ticket_id'])) {
            throw new RuntimeException('Ticket não encontrado', 404);
        }

        $f = $ticket->fields;
        return [
            'id'          => (int) $f['id'],
            'title'       => $f['name'],
            'content'     => strip_tags($f['content'] ?? ''),
            'status'      => (int) $f['status'],
            'status_name' => Ticket::getStatus($f['status']),
            'priority'    => (int) $f['priority'],
            'created_at'  => $f['date'],
            'updated_at'  => $f['date_mod'],
            'solved_at'   => $f['solvedate'],
            'closed_at'   => $f['closedate'],
        ];
    }

    /**
     * Relatório de faturamento por entidade + período.
     * Migra as queries do Apps Script (AdicionaTabela / Problema / Tarefa) para
     * dentro do plugin, onde há acesso direto ao $DB. Devolve dados crus (sem
     * formatar URL/data/duração) — a formatação fica no Tião.
     *
     * Body: { entity_id:int, from:'Y-m-d H:i:s', to:'Y-m-d H:i:s',
     *         recursive?:bool (default true — inclui sub-entidades) }
     */
    private static function reportBilling(array $body): array {
        global $DB;

        $entityId = isset($body['entity_id']) ? (int) $body['entity_id'] : -1;
        if ($entityId < 0) {
            throw new RuntimeException('Campo obrigatório: entity_id', 400);
        }

        $from = self::sanitizeDate($body['from'] ?? '');
        $to   = self::sanitizeDate($body['to'] ?? '');
        if (!$from || !$to) {
            throw new RuntimeException("Campos 'from' e 'to' devem ser datas 'Y-m-d H:i:s'", 400);
        }

        // Escopo de entidades: a própria + sub-entidades (espelha a árvore GLPI),
        // a menos que recursive=false. getSonsOf devolve os ids da subárvore.
        $recursive = ($body['recursive'] ?? true) !== false;
        if ($recursive) {
            $ids = getSonsOf('glpi_entities', $entityId);
            $ids = array_map('intval', array_values($ids));
        } else {
            $ids = [$entityId];
        }
        if (empty($ids)) {
            $ids = [$entityId];
        }
        $entityIn = implode(',', $ids);

        return [
            'entity_id' => $entityId,
            'from'      => $from,
            'to'        => $to,
            'tickets'   => self::queryTickets($entityIn, $from, $to),
            'problems'  => self::queryProblems($entityIn, $from, $to),
            'tasks'     => self::queryTicketTasks($entityIn, $from, $to),
        ];
    }

    /** Valida e normaliza 'Y-m-d' ou 'Y-m-d H:i:s'. Retorna string segura ou null. */
    private static function sanitizeDate(string $value): ?string {
        $value = trim($value);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value . ' 00:00:00';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value)) {
            return $value;
        }
        return null;
    }

    /** Executa SQL cru e devolve todas as linhas como array associativo. */
    private static function fetchAll(string $sql): array {
        global $DB;
        $rows = [];
        $result = $DB->doQuery($sql);
        if ($result) {
            while ($row = $DB->fetchAssoc($result)) {
                $rows[] = $row;
            }
        }
        return $rows;
    }

    private static function queryTickets(string $entityIn, string $from, string $to): array {
        $sql = "
            SELECT gt.id,
                   ge.notification_subject_tag,
                   ge.name AS entidade,
                   gt.name,
                   gt.date_creation,
                   gt.status,
                   CASE WHEN gt.type = 1 THEN 'Incidente' ELSE 'Requisição' END AS tipo,
                   CONCAT(COALESCE(gu.firstname, ''), ' ', COALESCE(gu.realname, '')) AS requerente,
                   gu2.email,
                   gc.completename AS categoria
            FROM glpi_tickets gt
            INNER JOIN glpi_entities ge ON ge.id = gt.entities_id
            LEFT JOIN glpi_tickets_users gtu ON gtu.tickets_id = gt.id AND gtu.`type` = 1
            LEFT JOIN glpi_users gu ON gu.id = gtu.users_id
            LEFT JOIN glpi_useremails gu2 ON gu2.users_id = gu.id AND gu2.is_default = 1
            LEFT JOIN glpi_itilcategories gc ON gc.id = gt.itilcategories_id
            WHERE gt.is_deleted = 0
              AND gt.entities_id IN ($entityIn)
              AND gt.date_creation >= '$from' AND gt.date_creation <= '$to'
            ORDER BY gt.date_creation
        ";
        $rows = self::fetchAll($sql);
        foreach ($rows as &$r) {
            $r['id']          = (int) $r['id'];
            $r['status']      = (int) $r['status'];
            $r['status_name'] = Ticket::getStatus((int) $r['status']);
            $r['requerente']  = trim((string) $r['requerente']);
        }
        return $rows;
    }

    private static function queryProblems(string $entityIn, string $from, string $to): array {
        $sql = "
            SELECT gp.id,
                   ge.notification_subject_tag,
                   ge.name AS entidade,
                   gp.name,
                   gp.date_creation,
                   gp.status,
                   gc.completename AS categoria,
                   TRIM(CONCAT(COALESCE(gc2.name, ''), ' ', COALESCE(gc2.serial, ''))) AS item
            FROM glpi_problems gp
            INNER JOIN glpi_entities ge ON ge.id = gp.entities_id
            LEFT JOIN glpi_items_problems gip ON gip.problems_id = gp.id
            LEFT JOIN glpi_computers gc2 ON gc2.id = gip.items_id
            LEFT JOIN glpi_itilcategories gc ON gc.id = gp.itilcategories_id
            WHERE gp.is_deleted = 0
              AND gp.entities_id IN ($entityIn)
              AND gp.date_creation >= '$from' AND gp.date_creation <= '$to'
            ORDER BY gp.date_creation
        ";
        $rows = self::fetchAll($sql);
        foreach ($rows as &$r) {
            $r['id']          = (int) $r['id'];
            $r['status']      = (int) $r['status'];
            $r['status_name'] = Problem::getStatus((int) $r['status']);
        }
        return $rows;
    }

    private static function queryTicketTasks(string $entityIn, string $from, string $to): array {
        global $DB;
        // A tabela de apontamentos só existe se o plugin ActualTime estiver instalado.
        $hasActualTime = $DB->tableExists('glpi_plugin_actualtime_tasks');
        $atSelect = $hasActualTime
            ? "gpat.actual_begin, gpat.actual_end, gpat.actual_actiontime"
            : "NULL AS actual_begin, NULL AS actual_end, NULL AS actual_actiontime";
        $atJoin = $hasActualTime
            ? "LEFT JOIN glpi_plugin_actualtime_tasks gpat
                 ON gpat.items_id = gtt.id AND gpat.itemtype = 'TicketTask'"
            : "";
        $atRange = $hasActualTime
            ? "OR (gpat.actual_begin >= '$from' AND gpat.actual_end <= '$to')"
            : "";

        // Parênteses corrigidos: no Apps Script o AND is_deleted vinha antes de um
        // bloco de OR sem parênteses, o que trazia linhas apagadas. Aqui o filtro de
        // período fica todo dentro de um único grupo OR.
        $sql = "
            SELECT gt.id AS ticket_id,
                   gtt.id AS task_id,
                   ge.notification_subject_tag,
                   ge.name AS entidade,
                   CONCAT(COALESCE(gu.firstname, ''), ' ', COALESCE(gu.realname, '')) AS requerente,
                   gu2.email,
                   gtt.content,
                   gtt.`date`,
                   gtt.date_creation,
                   $atSelect
            FROM glpi_tickets gt
            INNER JOIN glpi_entities ge ON ge.id = gt.entities_id
            INNER JOIN glpi_tickettasks gtt ON gtt.tickets_id = gt.id
            LEFT JOIN glpi_tickets_users gtu ON gtu.tickets_id = gt.id AND gtu.`type` = 1
            LEFT JOIN glpi_users gu ON gu.id = gtu.users_id
            LEFT JOIN glpi_useremails gu2 ON gu2.users_id = gu.id AND gu2.is_default = 1
            $atJoin
            WHERE gt.is_deleted = 0
              AND gt.entities_id IN ($entityIn)
              AND (
                    (gtt.date_creation >= '$from' AND gtt.date_creation <= '$to')
                 OR (gtt.date_mod >= '$from' AND gtt.date_mod <= '$to')
                 $atRange
              )
            ORDER BY gt.id, gtt.id
        ";
        $rows = self::fetchAll($sql);
        foreach ($rows as &$r) {
            $r['ticket_id']         = (int) $r['ticket_id'];
            $r['task_id']           = (int) $r['task_id'];
            $r['requerente']        = trim((string) $r['requerente']);
            $r['actual_actiontime'] = isset($r['actual_actiontime']) ? (int) $r['actual_actiontime'] : 0;
        }
        return $rows;
    }

    private static function requireField(array $body, string $field): void {
        if (empty($body[$field])) {
            throw new RuntimeException("Campo obrigatório: $field", 400);
        }
    }
}
