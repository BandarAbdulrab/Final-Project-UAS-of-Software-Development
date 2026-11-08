<?php
/**
 * Singleton PDO Database Wrapper
 * Kedai Paradise POS
 */

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
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

        try {
            $dsn = "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], $options);
            
            // Set timezone WIB (Asia/Jakarta)
            $pdo->exec("SET time_zone = '+07:00'");
            date_default_timezone_set('Asia/Jakarta');

        } catch (PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            http_response_code(500);
            exit("Terjadi kesalahan sistem.");
        }
    }
    return $pdo;
}