<?php
/**
 * POST /api/admin/order-status.php  (zadanie 35, BE) – zmiana statusu zamówienia.
 *
 * Treść: {"order_id": 12, "status": "preparing"}
 * Każda zmiana trafia do order_status_log (kto, z jakiego statusu, na jaki, kiedy).
 * Dostęp: pracownik i kierownik + token CSRF.
 */

declare(strict_types=1);

require __DIR__ . '/../../../src/bootstrap.php';
require_once __DIR__ . '/../../../src/auth.php';
require_once __DIR__ . '/../../../src/orders.php';

require_method('POST');
$user = require_role('employee', 'manager');
require_csrf();

$input = read_json_body();
$orderId = positive_int($input['order_id'] ?? null);
$status = $input['status'] ?? null;

if ($orderId === null || !is_string($status)) {
    throw new AppError('INVALID_INPUT', 'Brak numeru zamówienia lub statusu.');
}

$result = change_order_status(db(), $orderId, $status, $user['id']);
$result['status_label'] = ORDER_STATUSES[$result['status']];
$result['next_statuses'] = STATUS_FLOW[$result['status']];

json_ok($result);
