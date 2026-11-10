<?php
/**
 * Połączenie z bazą przez PDO (zadanie 17, BE).
 *
 * Użycie w endpoincie:
 *     require __DIR__ . '/../../src/db.php';
 *     $pdo = db();
 *     $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
 *     $stmt->execute([$id]);
 *
 * ZASADA BEZPIECZEŃSTWA: dane od użytkownika trafiają do SQL wyłącznie
 * przez prepare() + execute(). Nigdy przez sklejanie stringów.
 */

declare(strict_types=1);

require_once __DIR__ . '/response.php';

/** Wczytuje konfigurację z config/config.php (lub szablonu, gdy brak). */
function app_config(): array
{
    static $config = null;

    if ($config === null) {
        $file = __DIR__ . '/../config/config.php';
        if (!is_file($file)) {
            // Lokalnie (np. Docker) wystarczą zmienne środowiskowe z szablonu.
            $file = __DIR__ . '/../config/config.example.php';
        }
        $config = require $file;
    }

    return $config;
}

/** Zwraca jedno, współdzielone połączenie PDO. */
function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $cfg = app_config()['db'];
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $cfg['host'],
        $cfg['port'],
        $cfg['name']
    );

    try {
        $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // błędy SQL jako wyjątki
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // wiersze jako tablice asocjacyjne
            PDO::ATTR_EMULATE_PREPARES   => false,                  // prawdziwe prepared statements
        ]);
    } catch (PDOException $e) {
        // Szczegóły tylko do logu serwera, użytkownik dostaje ogólny komunikat.
        error_log('[acai-cup] DB connection failed: ' . $e->getMessage());
        json_error('DB_UNAVAILABLE', 'Serwis jest chwilowo niedostępny. Spróbuj za chwilę.', 500);
    }

    return $pdo;
}
