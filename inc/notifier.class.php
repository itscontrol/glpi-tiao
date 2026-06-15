<?php

/**
 * Envia eventos do GLPI para o Tião.
 * GLPI → Tião
 */
class PluginTiaoNotifier {

    static function send(string $event, Ticket $ticket): void {
        $config = PluginTiaoConfig::get();

        if (empty($config['tiao_url']) || empty($config['api_key']) || !$config['active']) {
            return;
        }

        $payload = self::buildPayload($event, $ticket);
        $json    = json_encode($payload);
        $sig     = hash_hmac('sha256', $json, $config['secret']);
        $url     = rtrim($config['tiao_url'], '/') . '/api/glpi/webhook';

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

        self::log($event, $ticket->getID(), $json, ($httpCode >= 200 && $httpCode < 300), $response ?: $error);
    }

    private static function buildPayload(string $event, Ticket $ticket): array {
        $fields = $ticket->fields;

        // Técnico atribuído
        $assignee = null;
        $actors   = $ticket->getActorsForType(CommonITILActor::ASSIGN);
        foreach ($actors as $actor) {
            if ($actor['itemtype'] === 'User') {
                $user     = new User();
                $user->getFromDB($actor['items_id']);
                $assignee = ['id' => $actor['items_id'], 'name' => $user->getFriendlyName()];
                break;
            }
        }

        // Solicitante
        $requester = null;
        $reqs      = $ticket->getActorsForType(CommonITILActor::REQUESTER);
        foreach ($reqs as $req) {
            if ($req['itemtype'] === 'User') {
                $user      = new User();
                $user->getFromDB($req['items_id']);
                $requester = ['id' => $req['items_id'], 'name' => $user->getFriendlyName()];
                break;
            }
        }

        return [
            'event'     => $event,
            'ticket'    => [
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
            ],
            'sent_at'   => date('c'),
            'glpi_url'  => rtrim(GLPI_URL, '/'),
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
