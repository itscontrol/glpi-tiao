<?php

/**
 * Endpoint Zabbix → GLPI Problema.
 * POST /plugins/tiao/front/zabbix.php
 *
 * Público (no_check); autenticado pela api_key do plugin, aceita via header
 * X-Tiao-Api-Key OU campo no corpo (api_key/token/secret) — flexível com o
 * media type "Webhook" do Zabbix. Não precisa de campo "action".
 */

define('GLPI_ROOT', realpath(__DIR__ . '/../../../'));
$SECURITY_STRATEGY = 'no_check';
include(GLPI_ROOT . '/inc/includes.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method Not Allowed']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!is_array($body)) $body = [];

$config = PluginTiaoConfig::get();
if (empty($config['active'])) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Plugin desativado']);
    exit;
}

// O script do Zabbix manda o segredo no header X-Zabbix-Webhook-Secret; aceitamos
// também X-Tiao-Api-Key ou campo no corpo. Casa com a api_key OU o secret do plugin.
$sentKey = $_SERVER['HTTP_X_ZABBIX_WEBHOOK_SECRET']
    ?? $_SERVER['HTTP_X_TIAO_API_KEY']
    ?? $body['api_key']
    ?? $body['token']
    ?? $body['secret']
    ?? '';
$validKeys = array_values(array_filter([
    (string) ($config['api_key'] ?? ''),
    (string) ($config['secret'] ?? ''),
]));
$authOk = false;
foreach ($validKeys as $vk) {
    if ($vk !== '' && hash_equals($vk, (string) $sentKey)) { $authOk = true; break; }
}
if (!$authOk) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'API key inválida']);
    exit;
}

try {
    $data = PluginTiaoZabbix::handle($body);
    echo json_encode(['ok' => true, 'data' => $data]);
} catch (RuntimeException $e) {
    http_response_code($e->getCode() ?: 400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Erro interno']);
    Toolbox::logError($e->getMessage());
}
