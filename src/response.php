<?php
/**
 * Wspólny format odpowiedzi API (zadanie 17, BE).
 *
 * Sukces: {"ok": true,  "data": {...}}
 * Błąd:   {"ok": false, "error": {"code": "KOD_BLEDU", "message": "Opis dla użytkownika"}}
 */

declare(strict_types=1);

/** Ustawia nagłówki wspólne dla wszystkich odpowiedzi JSON. */
function send_json_headers(int $status): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
}

/** Wysyła odpowiedź z danymi i kończy skrypt. */
function json_ok(array $data, int $status = 200): never
{
    send_json_headers($status);
    echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    exit;
}

/**
 * Wysyła błąd i kończy skrypt.
 * $code    – stały kod błędu dla frontendu, np. "PRODUCT_UNAVAILABLE"
 * $message – tekst, który można pokazać użytkownikowi
 * $status  – kod HTTP: 400, 401, 403, 404, 405, 409, 500
 */
function json_error(string $code, string $message, int $status = 400): never
{
    send_json_headers($status);
    echo json_encode(
        ['ok' => false, 'error' => ['code' => $code, 'message' => $message]],
        JSON_UNESCAPED_UNICODE
    );
    exit;
}

/** Przerywa, jeśli żądanie przyszło inną metodą HTTP niż dozwolona. */
function require_method(string $method): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== $method) {
        header('Allow: ' . $method);
        json_error('METHOD_NOT_ALLOWED', 'Niedozwolona metoda HTTP.', 405);
    }
}
