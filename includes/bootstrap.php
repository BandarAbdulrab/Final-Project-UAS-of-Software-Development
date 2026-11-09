<?php
/**
 * System Bootstrap & Error Reporting Handler
 */

date_default_timezone_set('Asia/Jakarta');

$privateConfig = dirname(__DIR__, 2) . '/private/config.local.php';
if (!file_exists($privateConfig)) {
    $privateConfig = __DIR__ . '/../private/config.local.php';
}

$env = 'production';
if (file_exists($privateConfig)) {
    $cfg = require $privateConfig;
    $env = $cfg['env'] ?? 'production';
}

if ($env === 'development') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(E_ALL);
    ini_set('log_errors', 1);
    
    $logDir = dirname(__DIR__, 2) . '/private/logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    ini_set('error_log', $logDir . '/php-error.log');
}

require_once __DIR__ . '/../config/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers.php';
