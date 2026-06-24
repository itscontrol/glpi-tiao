<?php

/**
 * Envia eventos do GLPI para o Tião.
 * GLPI → Tião
 */
class PluginTiaoNotifier {

    static function send(string $event, Ticket $ticket): void {
        $config = PluginTiaoConfig::get();
        if (empty($config['tiao_url']) || empty($config['api_key']) || !$config['active']) return;

        $payload = self::buildPayload($event, $ticket);
        self::dispatch($config, $payload, $event, $ticket->getID());
    }

    private static function dispatch(array $config, array $payload, string $event, int $ticketId): void {
        $json = json_encode($payload);
        $sig  = hash_hmac('sha256', $json, $config['secret']);
        $url  = rtrim($config['tiao_url'], '/') . '/api/glpi/plugin';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'X-Tiao-Api-Key: ' . $config['api_key'],
                'X-Tiao-Signature: ' . $sig,
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        self::log($event, $ticketId, $json, ($httpCode >= 200 && $httpCode < 300), $response ?: $error);
    }

    static function sendProblem(string $event, Problem $problem): void {
        $config = PluginTiaoConfig::get();
        if (empty($config['tiao_url']) || empty($config['api_key']) || !$config['active']) return;

        $fields = $problem->fields;

        $payload = [
            'event'    => $event,
            'problem'  => [
                'id'          => (int) $fields['id'],
                'title'       => $fields['name'],
                'content'     => strip_tags($fields['content'] ?? ''),
                'status'      => (int) $fields['status'],
                'status_name' => Problem::getStatus($fields['status']),
                'priority'    => (int) $fields['priority'],
                'entity_id'   => (int) $fields['entities_id'],
                'created_at'  => $fields['date'],
                'updated_at'  => $fields['date_mod'],
                'solved_at'   => $fields['solvedate'] ?? null,
                'closed_at'   => $fields['closedate'] ?? null,
            ],
            'sent_at'  => date('c'),
            'glpi_url' => self::glpiUrl(),
        ];

        self::dispatch($config, $payload, $event, (int) $fields['id']);
    }

    static function sendChange(string $event, Change $change): void {
        $config = PluginTiaoConfig::get();
        if (empty($config['tiao_url']) || empty($config['api_key']) || !$config['active']) return;

        $fields = $change->fields;

        $payload = [
            'event'    => $event,
            'change'   => [
                'id'          => (int) $fields['id'],
                'title'       => $fields['name'],
                'content'     => strip_tags($fields['content'] ?? ''),
                'status'      => (int) $fields['status'],
                'status_name' => Change::getStatus($fields['status']),
                'priority'    => (int) $fields['priority'],
                'entity_id'   => (int) $fields['entities_id'],
                'created_at'  => $fields['date'],
                'updated_at'  => $fields['date_mod'],
                'solved_at'   => $fields['solvedate'] ?? null,
                'closed_at'   => $fields['closedate'] ?? null,
            ],
            'sent_at'  => date('c'),
            'glpi_url' => self::glpiUrl(),
        ];

        self::dispatch($config, $payload, $event, (int) $fields['id']);
    }

    static function sendFollowup(string $event, ITILFollowup $followup): void {
        $config = PluginTiaoConfig::get();
        if (empty($config['tiao_url']) || empty($config['api_key']) || !$config['active']) return;

        $fields   = $followup->fields;
        // ITILFollowup é polimórfico: pode pertencer a Ticket, Problem ou Change.
        $itemtype = (string) ($fields['itemtype'] ?? 'Ticket');
        $itemId   = (int) $fields['items_id'];

        $parent = self::loadItilParent($itemtype, $itemId);
        if (!$parent) return;

        $evt = self::eventFor($itemtype, $event);
        $payload = [
            'event'    => $evt,
            'followup' => [
                'id'         => (int) $fields['id'],
                'content'    => strip_tags($fields['content'] ?? ''),
                'is_private' => (bool) $fields['is_private'],
                'date'       => $fields['date'],
                'users_id'   => (int) $fields['users_id'],
            ],
            'sent_at'  => date('c'),
            'glpi_url' => self::glpiUrl(),
        ] + self::parentEnvelope($itemtype, $parent);

        self::dispatch($config, $payload, $evt, $itemId);
    }

    static function sendSolution(string $event, ITILSolution $solution): void {
        $config = PluginTiaoConfig::get();
        if (empty($config['tiao_url']) || empty($config['api_key']) || !$config['active']) return;

        $fields   = $solution->fields;
        $itemtype = (string) ($fields['itemtype'] ?? 'Ticket');
        $itemId   = (int) $fields['items_id'];

        $parent = self::loadItilParent($itemtype, $itemId);
        if (!$parent) return;

        $evt = self::eventFor($itemtype, $event);
        $payload = [
            'event'    => $evt,
            'solution' => [
                'id'      => (int) $fields['id'],
                'content' => strip_tags($fields['content'] ?? ''),
                'status'  => (int) $fields['status'],
                'date'    => $fields['date_creation'],
            ],
            'sent_at'  => date('c'),
            'glpi_url' => self::glpiUrl(),
        ] + self::parentEnvelope($itemtype, $parent);

        self::dispatch($config, $payload, $evt, $itemId);
    }

    static function sendTask(string $event, TicketTask $task): void {
        $config = PluginTiaoConfig::get();
        if (empty($config['tiao_url']) || empty($config['api_key']) || !$config['active']) return;

        $fields   = $task->fields;
        $ticketId = (int) ($fields['tickets_id'] ?? 0);
        if ($ticketId <= 0) return;

        $ticket = new Ticket();
        if (!$ticket->getFromDB($ticketId)) return;

        $payload = [
            'event'   => $event,
            'ticket'  => self::buildTicketData($ticket),
            'task'    => [
                'id'         => (int) $fields['id'],
                'begin'      => $fields['begin'] ?? null,
                'end'        => $fields['end'] ?? null,
                'actiontime' => (int) ($fields['actiontime'] ?? 0),
                'users_id'   => (int) ($fields['users_id_tech'] ?? 0),
                'content'    => strip_tags($fields['content'] ?? ''),
                'tickets_id' => $ticketId,
            ],
            'sent_at'  => date('c'),
            'glpi_url' => self::glpiUrl(),
        ];

        self::dispatch($config, $payload, $event, $ticketId);
    }

    // Carrega o item ITIL pai (Ticket/Problem/Change) de um followup/solução.
    private static function loadItilParent(string $itemtype, int $id): ?CommonITILObject {
        if (!in_array($itemtype, ['Ticket', 'Problem', 'Change'], true) || $id <= 0) return null;
        $obj = new $itemtype();
        if (!$obj->getFromDB($id)) return null;
        return $obj;
    }

    // Reescreve o prefixo do evento conforme o itemtype do pai
    // (ex.: "ticket.followup_added" + Problem → "problem.followup_added").
    private static function eventFor(string $itemtype, string $baseEvent): string {
        $pos    = strpos($baseEvent, '.');
        $action = $pos !== false ? substr($baseEvent, $pos + 1) : $baseEvent;
        return strtolower($itemtype) . '.' . $action;
    }

    // Envelope do item pai no payload: Ticket vai com dados ricos (requerente,
    // observadores, origem); Problem/Change vão com o shape leve (thread interna).
    private static function parentEnvelope(string $itemtype, CommonITILObject $parent): array {
        if ($itemtype === 'Ticket') {
            return ['ticket' => self::buildTicketData($parent)];
        }
        return [strtolower($itemtype) => self::buildItilData($parent)];
    }

    // Shape leve para Problem/Change (sem atores externos nem requesttypes_id).
    private static function buildItilData(CommonITILObject $item): array {
        $f = $item->fields;
        return [
            'id'         => (int) $f['id'],
            'title'      => $f['name'] ?? null,
            'content'    => strip_tags($f['content'] ?? ''),
            'status'     => (int) ($f['status'] ?? 0),
            'priority'   => (int) ($f['priority'] ?? 0),
            'entity_id'  => (int) ($f['entities_id'] ?? 0),
            'created_at' => $f['date'] ?? null,
            'updated_at' => $f['date_mod'] ?? null,
            'solved_at'  => $f['solvedate'] ?? null,
            'closed_at'  => $f['closedate'] ?? null,
        ];
    }

    private static function buildPayload(string $event, Ticket $ticket): array {
        return [
            'event'    => $event,
            'ticket'   => self::buildTicketData($ticket),
            'sent_at'  => date('c'),
            'glpi_url' => self::glpiUrl(),
        ];
    }

    private static function buildTicketData(Ticket $ticket): array {
        $fields = $ticket->fields;

        $assignee = null;
        foreach ($ticket->getActorsForType(CommonITILActor::ASSIGN) as $actor) {
            if ($actor['itemtype'] === 'User') {
                $user = new User();
                $user->getFromDB($actor['items_id']);
                $assignee = ['id' => $actor['items_id'], 'name' => $user->getFriendlyName()];
                break;
            }
        }

        $requester = null;
        $requesterEmail = null; // fallback: requerente só por e-mail (chamado por e-mail anônimo)
        foreach ($ticket->getActorsForType(CommonITILActor::REQUESTER) as $req) {
            if ($req['itemtype'] === 'User' && (int) $req['items_id'] > 0) {
                $user = new User();
                $user->getFromDB($req['items_id']);
                $requester = [
                    'id'    => (int) $req['items_id'],
                    'name'  => $user->getFriendlyName(),
                    'email' => method_exists($user, 'getDefaultEmail') ? ($user->getDefaultEmail() ?: null) : null,
                    'phone' => $user->fields['phone'] ?? ($user->fields['mobile'] ?? null),
                ];
                break;
            }
            // requerente sem usuário (e-mail): guarda o alternative_email
            if (empty($requesterEmail) && !empty($req['alternative_email'])) {
                $requesterEmail = $req['alternative_email'];
            }
        }
        // Sem usuário, mas com e-mail do remetente → cria requerente por e-mail
        if ($requester === null && !empty($requesterEmail)) {
            $requester = ['id' => 0, 'name' => $requesterEmail, 'email' => $requesterEmail, 'phone' => null];
        }

        // Observadores (watchers) — usuários acompanhando o chamado
        $observers = [];
        foreach ($ticket->getActorsForType(CommonITILActor::OBSERVER) as $obs) {
            if ($obs['itemtype'] === 'User' && (int) $obs['items_id'] > 0) {
                $u = new User();
                $u->getFromDB($obs['items_id']);
                $observers[] = [
                    'id'    => (int) $obs['items_id'],
                    'name'  => $u->getFriendlyName(),
                    'email' => method_exists($u, 'getDefaultEmail') ? ($u->getDefaultEmail() ?: null) : null,
                    'phone' => $u->fields['phone'] ?? ($u->fields['mobile'] ?? null),
                ];
            } elseif (!empty($obs['alternative_email'])) {
                $observers[] = ['id' => 0, 'name' => $obs['alternative_email'], 'email' => $obs['alternative_email'], 'phone' => null];
            }
        }

        $requestTypeId = (int) ($fields['requesttypes_id'] ?? 0);

        return [
            'id'              => (int) $fields['id'],
            'title'           => $fields['name'],
            'content'         => strip_tags($fields['content'] ?? ''),
            'status'          => (int) $fields['status'],
            'status_name'     => Ticket::getStatus($fields['status']),
            'priority'        => (int) $fields['priority'],
            'urgency'         => (int) ($fields['urgency'] ?? 0),
            'category_id'     => (int) ($fields['itilcategories_id'] ?? 0),
            'entity_id'       => (int) $fields['entities_id'],
            // SLA/OLA do GLPI — prazos reais calculados pelo motor de SLA, no
            // calendário do cliente. A plataforma usa como due da task do Reclaim
            // (prefere o interno/OLA). null quando o chamado não tem SLA atribuído.
            'time_to_resolve'          => self::toIso($fields['time_to_resolve'] ?? null),
            'internal_time_to_resolve' => self::toIso($fields['internal_time_to_resolve'] ?? null),
            'time_to_own'              => self::toIso($fields['time_to_own'] ?? null),
            'internal_time_to_own'     => self::toIso($fields['internal_time_to_own'] ?? null),
            // Origem da requisição (Helpdesk, E-Mail, WhatsApp...) — usada pela
            // plataforma para decidir quais tickets viram conversa.
            'requesttypes_id' => $requestTypeId,
            'request_source'  => $requestTypeId ? Dropdown::getDropdownName('glpi_requesttypes', $requestTypeId) : null,
            'assignee'        => $assignee,
            'requester'       => $requester,
            'observers'       => $observers,
            'created_at'      => $fields['date'],
            'updated_at'      => $fields['date_mod'],
            'solved_at'       => $fields['solvedate'],
            'closed_at'       => $fields['closedate'],
        ];
    }

    private static function glpiUrl(): string {
        // GLPI_URL foi removido no GLPI 11 — reconstrói a URL base
        $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host  = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $proto . '://' . $host;
    }

    // Converte um datetime naive do GLPI (ex.: "2026-06-24 14:25:00", no fuso
    // configurado no GLPI) para ISO8601 com offset — evita erro de fuso quando a
    // plataforma interpreta a data do SLA. Retorna null para datas vazias/zeradas.
    private static function toIso(?string $dt): ?string {
        if (empty($dt) || strpos($dt, '0000-00-00') === 0) return null;
        try {
            $tz = new DateTimeZone(date_default_timezone_get());
            return (new DateTime($dt, $tz))->format('c');
        } catch (\Exception $e) {
            return null;
        }
    }

    private static function log(string $event, int $ticketId, string $payload, bool $ok, ?string $response): void {
        global $DB;
        $DB->insert('glpi_plugin_tiao_events', [
            'event'     => $event,
            'ticket_id' => $ticketId,
            'payload'   => $payload,
            'status'    => $ok ? 1 : 0,
            'response'  => $response ?? '',
        ]);
    }
}
