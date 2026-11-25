<?php
/**
 * GET /api/admin/orders.php?status=active  (zadanie 35, BE) – lista zamówień dla personelu.
 *
 * status: active (domyślnie: new + preparing + ready), new, preparing, ready,
 *         collected, cancelled albo all.
 * Zamówienia w toku są posortowane od najstarszego (kto pierwszy, ten lepszy),
 * zakończone – od najnowszego. Maksymalnie 100 zamówień.
 * Dostęp: pracownik i kierownik.
 */

declare(strict_types=1);

require __DIR__ . '/../../../src/bootstrap.php';
require_once __DIR__ . '/../../../src/auth.php';
require_once __DIR__ . '/../../../src/orders.php';

require_method('GET');
require_role('employee', 'manager');

$filter = $_GET['status'] ?? 'active';
$filter = is_string($filter) ? $filter : '';
$active = ['new', 'preparing', 'ready'];

if ($filter === 'active') {
    $statuses = $active;
} elseif ($filter === 'all') {
    $statuses = array_keys(ORDER_STATUSES);
} elseif (array_key_exists($filter, ORDER_STATUSES)) {
    $statuses = [$filter];
} else {
    throw new AppError('INVALID_STATUS', 'Nieznany filtr statusu.');
}

$pdo = db();

// Statusy idą do zapytania jako parametry (?, ?, ?), nigdy wklejone w tekst SQL.
$placeholders = implode(', ', array_fill(0, count($statuses), '?'));
$order = array_diff($statuses, $active) === [] ? 'ASC' : 'DESC'; // stała z kodu, nie od klienta
$stmt = $pdo->prepare(
    "SELECT o.id, o.order_code, s.code AS size, o.extra_protein, o.extra_toppings, o.total_price,
            o.payment_method, o.payment_status, o.status, o.created_at, o.updated_at
       FROM orders o
       JOIN sizes s ON s.id = o.size_id
      WHERE o.status IN ($placeholders)
      ORDER BY o.created_at $order, o.id $order
      LIMIT 100"
);
$stmt->execute($statuses);
$orders = $stmt->fetchAll();

// Składniki wszystkich pobranych zamówień jednym zapytaniem.
$itemsByOrder = [];
if ($orders) {
    $ids = array_column($orders, 'id');
    $placeholders = implode(', ', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare(
        "SELECT order_id, slot, product_name FROM order_items WHERE order_id IN ($placeholders) ORDER BY order_id, id"
    );
    $stmt->execute($ids);
    foreach ($stmt->fetchAll() as $row) {
        $itemsByOrder[(int) $row['order_id']][] = ['slot' => $row['slot'], 'name' => $row['product_name']];
    }
}

// Liczba zamówień w każdym statusie (do przycisków filtra).
$counts = array_fill_keys(array_keys(ORDER_STATUSES), 0);
foreach ($pdo->query('SELECT status, COUNT(*) AS n FROM orders GROUP BY status') as $row) {
    $counts[$row['status']] = (int) $row['n'];
}

json_ok([
    'orders' => array_map(fn ($o) => [
        'id'             => (int) $o['id'],
        'order_code'     => $o['order_code'],
        'size'           => $o['size'],
        'extra_protein'  => (bool) $o['extra_protein'],
        'extra_toppings' => (int) $o['extra_toppings'],
        'total_price'    => (float) $o['total_price'],
        'payment_method' => $o['payment_method'],
        'payment_status' => $o['payment_status'],
        'status'         => $o['status'],
        'status_label'   => ORDER_STATUSES[$o['status']],
        'next_statuses'  => STATUS_FLOW[$o['status']],
        'created_at'     => $o['created_at'],
        'items'          => $itemsByOrder[(int) $o['id']] ?? [],
    ], $orders),
    'counts'   => $counts,
    'statuses' => ORDER_STATUSES,
]);
