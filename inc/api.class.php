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

    private static function requireField(array $body, string $field): void {
        if (empty($body[$field])) {
            throw new RuntimeException("Campo obrigatório: $field", 400);
        }
    }
}
