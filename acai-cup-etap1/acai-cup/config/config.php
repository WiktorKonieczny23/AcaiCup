<?php
/**
 * Konfiguracja lokalna (XAMPP): root bez hasła. NIE wrzucać do repozytorium.
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
        'user' => getenv('DB_USER') ?: 'root',
        'pass' => getenv('DB_PASS') ?: '',
    ],

    // true tylko lokalnie: pokazuje szczegóły błędów w odpowiedzi API.
    // Na serwerze ZAWSZE false.
    'debug' => filter_var(getenv('APP_DEBUG') ?: 'true', FILTER_VALIDATE_BOOLEAN),
];
