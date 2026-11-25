<?php
/**
 * POST /api/orders.php  (zadanie 26, BE) – złożenie zamówienia przez klienta.
 *
 * Treść żądania (JSON):
 *   {"size": "M", "layers": {"1": 3, "2": 11, "3": 17}, "toppings": [24],
 *    "extra_protein": true, "payment_method": "cash"}
 *
 * "toppings" może mieć więcej pozycji niż rozmiar ma w cenie (każda kolejna jest płatna),
 * a w L jeden topping może wystąpić dwa razy (podwójna porcja), np. [24, 24].
 *
 * Kolejność:
 *  1. price_order()      – walidacja składu i cena liczona z bazy (src/pricing.php),
 *  2. payment_gateway()  – czy wybrana płatność jest dostępna (src/payments/),
 *  3. create_order()     – zapis w jednej transakcji z numerem do odbioru (src/orders.php),
 *  4. $gateway->start()  – rozpoczęcie płatności (przy kasie: nic do zrobienia).
 *
 * Odpowiedź 201: {"ok": true, "data": {"order_code": "K-042", "total_price": 28.0, ...}}
 * Płatność online (na razie wyłączona): 409 PAYMENT_UNAVAILABLE i zamówienie NIE jest zapisywane.
 */

declare(strict_types=1);

require __DIR__ . '/../../src/bootstrap.php';
require_once __DIR__ . '/../../src/pricing.php';
require_once __DIR__ . '/../../src/orders.php';
require_once __DIR__ . '/../../src/payments/payments.php';

require_method('POST');

$input = read_json_body();
$pdo = db();

// 1. Skład i cena – wszystko z bazy, nic z przeglądarki.
$priced = price_order($pdo, $input);

// 2. Płatność.
$gateway = payment_gateway($input['payment_method'] ?? null);
if (!$gateway->isAvailable()) {
    throw new AppError('PAYMENT_UNAVAILABLE', 'Płatność online będzie dostępna wkrótce. Wybierz płatność przy kasie.', 409);
}

// 3. Zapis.
$order = create_order($pdo, $priced, $gateway->code());

// 4. Start płatności (dla Przelewy24 tu pojawi się adres bramki w redirect_url).
$payment = $gateway->start($order);

json_ok([
    'order_code'     => $order['order_code'],
    'status'         => 'new',
    'status_label'   => ORDER_STATUSES['new'],
    'total_price'    => $order['total_price'],
    'payment_method' => $gateway->code(),
    'payment_status' => $payment['payment_status'],
    'redirect_url'   => $payment['redirect_url'],
    'size'           => $priced['size']['code'],
    'items'          => array_map(fn ($item) => ['slot' => $item['slot'], 'name' => $item['name']], $priced['items']),
    'extra_protein'  => $priced['extra'] !== null,
    'extra_toppings' => $priced['extra_toppings'],
    'nutrition'      => $priced['nutrition'],
    'allergens'      => $priced['allergens'],
], 201);
