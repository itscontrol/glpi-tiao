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
        $url  = rtrim($config['tiao_url'], '/') . '/api/glpi/webhook';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $json,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
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

    static function sendFollowup(string $event, ITILFollowup $followup): void {
        $config = PluginTiaoConfig::get();
        if (empty($config['tiao_url']) || empty($config['api_key']) || !$config['active']) return;

        $fields    = $followup->fields;
        $ticketId  = (int) $fields['items_id'];

        $ticket = new Ticket();
        if (!$ticket->getFromDB($ticketId)) return;

        $payload = [
            'event'     => $event,
            'followup'  => [
                'id'         => (int) $fields['id'],
                'content'    => strip_tags($fields['content'] ?? ''),
                'is_private' => (bool) $fields['is_private'],
                'date'       => $fields['date'],
                'users_id'   => (int) $fields['users_id'],
            ],
            'ticket'    => self::buildTicketData($ticket),
            'sent_at'   => date('c'),
            'glpi_url'  => rtrim(GLPI_URL, '/'),
        ];

        self::dispatch($config, $payload, $event, $ticketId);
    }

    static function sendSolution(string $event, ITILSolution $solution): void {
        $config = PluginTiaoConfig::get();
        if (empty($config['tiao_url']) || empty($config['api_key']) || !$config['active']) return;

        $fields   = $solution->fields;
        $ticketId = (int) $fields['items_id'];

        $ticket = new Ticket();
        if (!$ticket->getFromDB($ticketId)) return;

        $payload = [
            'event'    => $event,
            'solution' => [
                'id'      => (int) $fields['id'],
                'content' => strip_tags($fields['content'] ?? ''),
                'status'  => (int) $fields['status'],
                'date'    => $fields['date_creation'],
            ],
            'ticket'   => self::buildTicketData($ticket),
            'sent_at'  => date('c'),
            'glpi_url' => rtrim(GLPI_URL, '/'),
        ];

        self::dispatch($config, $payload, $event, $ticketId);
    }

    private static function buildPayload(string $event, Ticket $ticket): array {
        return [
            'event'    => $event,
            'ticket'   => self::buildTicketData($ticket),
            'sent_at'  => date('c'),
            'glpi_url' => rtrim(GLPI_URL, '/'),
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
        foreach ($ticket->getActorsForType(CommonITILActor::REQUESTER) as $req) {
            if ($req['itemtype'] === 'User') {
                $user = new User();
                $user->getFromDB($req['items_id']);
                $requester = ['id' => $req['items_id'], 'name' => $user->getFriendlyName()];
                break;
            }
        }

        return [
            'id'          => (int) $fields['id'],
            'title'       => $fields['name'],
            'content'     => strip_tags($fields['content'] ?? ''),
            'status'      => (int) $fields['status'],
            'status_name' => Ticket::getStatus($fields['status']),
            'priority'    => (int) $fields['priority'],
            'category_id' => (int) ($fields['itilcategories_id'] ?? 0),
            'entity_id'   => (int) $fields['entities_id'],
            'assignee'    => $assignee,
            'requester'   => $requester,
            'created_at'  => $fields['date'],
            'updated_at'  => $fields['date_mod'],
            'solved_at'   => $fields['solvedate'],
            'closed_at'   => $fields['closedate'],
        ];
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
