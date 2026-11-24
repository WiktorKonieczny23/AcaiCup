<?php
/**
 * POST /api/admin/logout.php  (zadanie 33, BE) – wylogowanie.
 * Wymaga tokenu CSRF, żeby obca strona nie mogła wylogować pracownika.
 */

declare(strict_types=1);

require __DIR__ . '/../../../src/bootstrap.php';
require_once __DIR__ . '/../../../src/auth.php';

require_method('POST');
require_role('employee', 'manager');
require_csrf();

logout();

json_ok(['logged_out' => true]);
