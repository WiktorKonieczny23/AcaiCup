<?php
/**
 * Odczyt danych z żądania (etap E2).
 *
 * Frontend wysyła dane jako JSON w treści żądania POST
 * (nagłówek Content-Type: application/json). PHP nie wkłada takich danych
 * do $_POST, więc czytamy je sami z php://input.
 */

declare(strict_types=1);

require_once __DIR__ . '/errors.php';

/** Zwraca treść żądania jako tablicę; przy złym JSON-ie przerywa błędem 400. */
function read_json_body(): array
{
    $raw = file_get_contents('php://input') ?: '';
    if (strlen($raw) > 20000) {
        throw new AppError('BODY_TOO_LARGE', 'Za dużo danych w żądaniu.', 413);
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        throw new AppError('INVALID_JSON', 'Niepoprawny format danych.', 400);
    }
    return $data;
}

/**
 * Liczba całkowita dodatnia z danych od klienta (np. id produktu).
 * Przyjmuje 7 albo "7"; wszystko inne (7.5, "7abc", -1, true) to null.
 */
function positive_int(mixed $value): ?int
{
    if (is_int($value)) {
        return $value > 0 ? $value : null;
    }
    if (is_string($value) && preg_match('/^[1-9][0-9]{0,8}$/', $value)) {
        return (int) $value;
    }
    return null;
}

/** Kwota w złotych z dwoma miejscami po przecinku w zakresie 0,01–999,99, albo null. */
function money_value(mixed $value): ?float
{
    if (!is_int($value) && !is_float($value) && !(is_string($value) && is_numeric($value))) {
        return null;
    }
    $amount = round((float) $value, 2);
    return ($amount > 0 && $amount < 1000) ? $amount : null;
}
