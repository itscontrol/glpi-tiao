<?php

/**
 * Endpoint REST — Tião → GLPI
 * POST /plugins/tiao/front/api.php
 */

// Bootstrap mínimo do GLPI sem sessão de usuário
define('GLPI_ROOT', realpath(__DIR__ . '/../../../'));
include(GLPI_ROOT . '/inc/includes.php');

// Apenas POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method Not Allowed']);
    exit;
}

PluginTiaoApi::dispatch();
