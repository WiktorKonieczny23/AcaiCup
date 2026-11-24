<?php
/**
 * Konfiguracja aplikacji – SZABLON.
 *
 * 1. Skopiuj ten plik jako config/config.php
 * 2. Wpisz swoje dane dostępowe.
 *
 * Plik config.php jest w .gitignore i NIGDY nie trafia do repozytorium.
 * Zmienne środowiskowe (np. z docker-compose.yml) mają pierwszeństwo.
 */

return [
    'db' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => getenv('DB_PORT') ?: '3306',
        'name' => getenv('DB_NAME') ?: 'acai_cup',
        'user' => getenv('DB_USER') ?: 'acai_app',
        'pass' => getenv('DB_PASS') ?: 'zmien_to_haslo',
    ],

    // true tylko lokalnie: dopisuje treść błędu do odpowiedzi API.
    // Na serwerze ZAWSZE false.
    'debug' => filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOLEAN),

    // Log aplikacji – poza public/, żeby nie dało się go pobrać z przeglądarki.
    'log_file' => getenv('APP_LOG_FILE') ?: __DIR__ . '/../logs/app.log',
];
