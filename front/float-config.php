<?php
/**
 * Emite configuração do widget flutuante como JavaScript.
 * Servido via add_javascript hook — Apache executa o PHP e retorna JS.
 */
ob_start();
define('GLPI_ROOT', realpath(__DIR__ . '/../../../'));
$SECURITY_STRATEGY = 'no_check';
include(GLPI_ROOT . '/inc/includes.php');
ob_clean();

header('Content-Type: application/javascript; charset=utf-8');
header('Cache-Control: private, max-age=300');

$cfg = PluginTiaoConfig::get();
$url = rtrim((string)($cfg['tiao_url'] ?? ''), '/');

echo 'window.__TIAO_URL__ = ' . json_encode($url) . ';' . "\n";
