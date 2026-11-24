<?php
/**
 * GET /api/admin/me.php  (etap E3) – kto jest zalogowany.
 *
 * Panel wywołuje to przy starcie: 200 = pokaż panel (z rolą i tokenem CSRF),
 * 401 = pokaż formularz logowania.
 */

declare(strict_types=1);

require __DIR__ . '/../../../src/bootstrap.php';
require_once __DIR__ . '/../../../src/auth.php';

require_method('GET');

$user = require_role('employee', 'manager');

json_ok([
    'user'       => ['login' => $user['login'], 'role' => $user['role']],
    'csrf_token' => csrf_token(),
]);
