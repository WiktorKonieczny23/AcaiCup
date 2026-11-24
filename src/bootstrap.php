<?php
/**
 * Wspólny początek każdego endpointu API.
 *
 * Użycie (pierwsza linia po declare):
 *     require __DIR__ . '/../../src/bootstrap.php';
 *
 * Ładuje funkcje odpowiedzi JSON, bazę, odczyt żądania i włącza
 * obsługę błędów w jednym miejscu (src/errors.php).
 */

declare(strict_types=1);

require_once __DIR__ . '/response.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/errors.php';
require_once __DIR__ . '/request.php';

// Strefa czasowa dla dat zamówień i podsumowań.
date_default_timezone_set('Europe/Warsaw');

register_error_handlers();
