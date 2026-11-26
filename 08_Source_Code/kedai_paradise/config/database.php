<?php
/**
 * Singleton PDO Database Wrapper with Automatic Live / Testing DB Routing
 * Kedai Paradise POS
 */

function isTestUsername(string $username): bool {
    $configFile = dirname(__DIR__, 2) . '/private/config.local.php';
    if (!file_exists($configFile)) {
        $configFile = __DIR__ . '/../private/config.local.php';
    }
    $config = file_exists($configFile) ? (require $configFile) : [];
    $testUsers = $config['test_users'] ?? ['admin_test', 'kasir_test'];
    return in_array(strtolower(trim($username)), array_map('strtolower', $testUsers), true);
}

function setActiveDbMode(string $mode): void {
    $GLOBALS['__KP_DB_MODE_OVERRIDE'] = ($mode === 'test') ? 'test' : 'live';
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['db_mode'] = $GLOBALS['__KP_DB_MODE_OVERRIDE'];
    }
}

function getActiveDbMode(): string {
    if (!empty($GLOBALS['__KP_DB_MODE_OVERRIDE'])) {
        return $GLOBALS['__KP_DB_MODE_OVERRIDE'];
    }
    if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['db_mode'])) {
        return $_SESSION['db_mode'] === 'test' ? 'test' : 'live';
    }
    if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['username'])) {
        return isTestUsername((string)$_SESSION['username']) ? 'test' : 'live';
    }
    return 'live';
}

function db(?string $forceMode = null): PDO {
    static $connections = [];

    $mode = $forceMode ?? getActiveDbMode();
    if ($mode !== 'test') {
        $mode = 'live';
    }

    if (!isset($connections[$mode])) {
        $configFile = dirname(__DIR__, 2) . '/private/config.local.php';
        if (!file_exists($configFile)) {
            $configFile = __DIR__ . '/../private/config.local.php';
        }

        if (!file_exists($configFile)) {
            error_log("Database configuration file not found.");
            http_response_code(500);
            exit("Terjadi kesalahan sistem.");
        }

        $config = require $configFile;
        $dbName = ($mode === 'test' && !empty($config['db_name_test']))
            ? $config['db_name_test']
            : $config['db_name'];

        try {
            $port = $config['db_port'] ?? 3306;
            $dsn = "mysql:host={$config['db_host']};port={$port};dbname={$dbName};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], $options);
            $pdo->exec("SET time_zone = '+07:00'");
            date_default_timezone_set('Asia/Jakarta');

            $connections[$mode] = $pdo;
        } catch (PDOException $e) {
            error_log("Database Connection Error ($mode): " . $e->getMessage());
            http_response_code(500);
            exit("Terjadi kesalahan sistem.");
        }
    }

    return $connections[$mode];
}