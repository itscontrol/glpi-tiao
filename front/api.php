<?php

/**
 * Endpoint REST — Tião → GLPI
 * POST /plugins/tiao/front/api.php
 */

// Bootstrap do GLPI SEM exigir sessão/CSRF (endpoint público para webhooks).
// A autenticação real é por X-Tiao-Api-Key no PluginTiaoApi::dispatch().
define('GLPI_ROOT', realpath(__DIR__ . '/../../../'));
$SECURITY_STRATEGY = 'no_check';
include(GLPI_ROOT . '/inc/includes.php');

// Apenas POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method Not Allowed']);
    exit;
}

PluginTiaoApi::dispatch();
