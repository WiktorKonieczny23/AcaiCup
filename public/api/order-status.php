<?php
/**
 * GET /api/order-status.php?code=K-042  (zadanie 27, BE)
 *
 * Publiczny podgląd statusu zamówienia po numerze do odbioru.
 * Ekran potwierdzenia odpytuje go co kilkanaście sekund, żeby klient
 * zobaczył „Gotowe do odbioru”. Nie zwraca składu ani danych personelu.
 */

declare(strict_types=1);

require __DIR__ . '/../../src/bootstrap.php';
require_once __DIR__ . '/../../src/orders.php';

require_method('GET');

$code = $_GET['code'] ?? '';
$code = is_string($code) ? strtoupper(trim($code)) : ''; // ?code[]=… to tablica, nie tekst
// Format numeru: litera, myślnik, trzy cyfry. Inne wartości odrzucamy od razu.
if (!preg_match('/^[A-Z]-[0-9]{3}$/', $code)) {
    throw new AppError('INVALID_CODE', 'Podaj numer zamówienia w formacie A-123.');
}

$stmt = db()->prepare(
    'SELECT order_code, status, payment_method, payment_status, total_price, created_at, updated_at
       FROM orders
      WHERE order_code = ?'
);
$stmt->execute([$code]);
$order = $stmt->fetch();

if (!$order) {
    throw new AppError('ORDER_NOT_FOUND', 'Nie znaleziono zamówienia o tym numerze.', 404);
}

json_ok([
    'order_code'     => $order['order_code'],
    'status'         => $order['status'],
    'status_label'   => ORDER_STATUSES[$order['status']],
    'payment_method' => $order['payment_method'],
    'payment_status' => $order['payment_status'],
    'total_price'    => (float) $order['total_price'],
    'created_at'     => $order['created_at'],
    'updated_at'     => $order['updated_at'],
]);
