<?php
/**
 * POST /api/admin/login.php  (zadanie 33, BE) – logowanie personelu.
 *
 * Treść: {"login": "kierownik", "password": "…"}
 * Odpowiedź: {"user": {"login", "role"}, "csrf_token": "…"}
 * Token CSRF panel dołącza potem do każdego żądania POST (nagłówek X-CSRF-Token).
 */

declare(strict_types=1);

require __DIR__ . '/../../../src/bootstrap.php';
require_once __DIR__ . '/../../../src/auth.php';

require_method('POST');

$input = read_json_body();
$login = $input['login'] ?? null;
$password = $input['password'] ?? null;

if (!is_string($login) || !is_string($password) || $login === '' || $password === ''
    || strlen($login) > 40 || strlen($password) > 200) {
    throw new AppError('INVALID_CREDENTIALS', 'Podaj login i hasło.', 400);
}

$user = attempt_login(db(), trim($login), $password);

json_ok([
    'user'       => ['login' => $user['login'], 'role' => $user['role']],
    'csrf_token' => csrf_token(),
]);
