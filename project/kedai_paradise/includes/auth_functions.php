<?php
/**
 * Authentication helpers: login rate limiting (SRS-F01) and the landing page per role.
 * Kedai Paradise POS
 */

const LOGIN_MAX_FAILURES = 5;
const LOGIN_WINDOW_MINUTES = 15;

/** Valid bcrypt hash of a random string. Unknown usernames are checked against it so the response time does not reveal whether a username exists. */
const LOGIN_DUMMY_HASH = '$2y$10$pTQPdVWJgrZFGzz4.fG/B.HNffZDty7lgV71nHR5xI6DId.0lmaMi';

/** Landing page after login: admin -> dashboard, cashier -> POS (dashboard until pos.php exists). */
function homePageFor(string $role): string
{
    if ($role === 'cashier' && is_file(__DIR__ . '/../pos.php')) {
        return 'pos.php';
    }
    return 'dashboard.php';
}

function clientIp(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

/** True when this username OR this IP has 5 or more failed attempts in the last 15 minutes. */
function isLoginRateLimited(PDO $pdo, string $username, string $ip): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM login_attempts
         WHERE attempted_at > (NOW() - INTERVAL ' . LOGIN_WINDOW_MINUTES . ' MINUTE)
           AND (username = :u OR ip_address = :ip)'
    );
    $stmt->execute([':u' => $username, ':ip' => $ip]);
    return (int)$stmt->fetchColumn() >= LOGIN_MAX_FAILURES;
}

function recordFailedLogin(PDO $pdo, string $username, string $ip): void
{
    $stmt = $pdo->prepare('INSERT INTO login_attempts (username, ip_address) VALUES (:u, :ip)');
    $stmt->execute([':u' => $username, ':ip' => $ip]);
}

function clearLoginAttempts(PDO $pdo, string $username): void
{
    $stmt = $pdo->prepare('DELETE FROM login_attempts WHERE username = :u');
    $stmt->execute([':u' => $username]);
}

/** Housekeeping: remove attempts older than one day. */
function purgeOldLoginAttempts(PDO $pdo): void
{
    $pdo->exec('DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)');
}
