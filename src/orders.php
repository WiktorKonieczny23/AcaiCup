<?php
/**
 * Zapis zamówień i zmiany statusów (zadania 26 i 35, BE).
 *
 * Wywołują:
 *  - public/api/orders.php              → create_order()
 *  - public/api/admin/order-status.php  → change_order_status()
 *  - public/api/order-status.php i panel → ORDER_STATUSES (nazwy statusów)
 */

declare(strict_types=1);

require_once __DIR__ . '/errors.php';

/** Statusy zamówienia i ich nazwy po polsku (dla klienta i panelu). */
const ORDER_STATUSES = [
    'new'       => 'Przyjęte',
    'preparing' => 'W przygotowaniu',
    'ready'     => 'Gotowe do odbioru',
    'collected' => 'Odebrane',
    'cancelled' => 'Anulowane',
];

/**
 * Dozwolone przejścia między statusami: z którego statusu na które można przejść.
 * Odebranego ani anulowanego zamówienia nie da się już zmienić.
 */
const STATUS_FLOW = [
    'new'       => ['preparing', 'cancelled'],
    'preparing' => ['ready', 'cancelled'],
    'ready'     => ['collected', 'cancelled'],
    'collected' => [],
    'cancelled' => [],
];

/**
 * Losuje numer do odbioru w formacie litera-trzy cyfry, np. "K-042".
 * Numer jest losowy (a nie kolejny), żeby nie dało się łatwo zgadywać cudzych zamówień.
 */
function generate_order_code(): string
{
    $letters = 'ABCDEFGHJKLMNPRSTUWXYZ'; // bez I, O, Q, V – łatwo je pomylić z cyframi
    return $letters[random_int(0, strlen($letters) - 1)] . '-' . sprintf('%03d', random_int(0, 999));
}

/**
 * Zapisuje zamówienie w JEDNEJ transakcji: orders + order_items + order_status_log.
 * Albo zapisze się wszystko, albo nic (np. przy awarii w połowie nie zostanie
 * zamówienie bez składników).
 *
 * $priced        – wynik price_order() z src/pricing.php (ceny już policzone przez serwer),
 * $paymentMethod – 'cash' albo 'online'.
 * Zwraca ['id', 'order_code', 'total_price'].
 */
function create_order(PDO $pdo, array $priced, string $paymentMethod): array
{
    $pdo->beginTransaction();
    try {
        // 1. Nagłówek zamówienia z KOPIĄ cen z chwili zakupu.
        $insertOrder = $pdo->prepare(
            'INSERT INTO orders (order_code, size_id, size_price, extra_protein, protein_price, total_price, payment_method)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );

        // Wylosowany numer może już istnieć (UNIQUE w bazie) – wtedy losujemy jeszcze raz.
        for ($attempt = 1; ; $attempt++) {
            $code = generate_order_code();
            try {
                $insertOrder->execute([
                    $code,
                    $priced['size']['id'],
                    $priced['size_price'],
                    $priced['extra'] ? 1 : 0,
                    $priced['protein_price'],
                    $priced['total_price'],
                    $paymentMethod,
                ]);
                break;
            } catch (PDOException $e) {
                $isDuplicateCode = ($e->errorInfo[1] ?? 0) === 1062 && str_contains($e->getMessage(), 'uq_orders_code');
                if (!$isDuplicateCode || $attempt >= 20) {
                    throw $e;
                }
            }
        }
        $orderId = (int) $pdo->lastInsertId();

        // 2. Składniki z KOPIĄ nazw (zmiana nazwy produktu nie zmieni historii).
        $insertItem = $pdo->prepare(
            'INSERT INTO order_items (order_id, product_id, product_name, slot) VALUES (?, ?, ?, ?)'
        );
        foreach ($priced['items'] as $item) {
            $insertItem->execute([$orderId, $item['product_id'], $item['name'], $item['slot']]);
        }

        // 3. Pierwszy wpis w historii: klient (user_id = NULL) złożył zamówienie.
        $pdo->prepare('INSERT INTO order_status_log (order_id, user_id, old_status, new_status) VALUES (?, NULL, NULL, ?)')
            ->execute([$orderId, 'new']);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack(); // cofamy wszystko, co zdążyło się zapisać
        throw $e;
    }

    return ['id' => $orderId, 'order_code' => $code, 'total_price' => $priced['total_price']];
}

/**
 * Zmienia status zamówienia i zapisuje to w order_status_log (kto, z czego, na co).
 *
 * Sprawdza, czy przejście jest dozwolone (STATUS_FLOW). Wiersz zamówienia
 * blokujemy (SELECT … FOR UPDATE), żeby dwie osoby jednocześnie nie zmieniły
 * statusu z tego samego stanu.
 * Przy odbiorze zamówienia płatnego przy kasie ustawia payment_status = 'paid'.
 *
 * Zwraca ['id', 'order_code', 'status', 'payment_status'].
 */
function change_order_status(PDO $pdo, int $orderId, string $newStatus, int $userId): array
{
    if (!array_key_exists($newStatus, ORDER_STATUSES)) {
        throw new AppError('INVALID_STATUS', 'Nieznany status zamówienia.');
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT id, order_code, status, payment_method, payment_status FROM orders WHERE id = ? FOR UPDATE');
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        if (!$order) {
            throw new AppError('ORDER_NOT_FOUND', 'Nie ma takiego zamówienia.', 404);
        }

        $oldStatus = $order['status'];
        if (!in_array($newStatus, STATUS_FLOW[$oldStatus], true)) {
            throw new AppError(
                'STATUS_NOT_ALLOWED',
                sprintf('Nie można zmienić statusu z „%s” na „%s”.', ORDER_STATUSES[$oldStatus], ORDER_STATUSES[$newStatus]),
                409
            );
        }

        $paymentStatus = $order['payment_status'];
        if ($newStatus === 'collected' && $order['payment_method'] === 'cash') {
            $paymentStatus = 'paid'; // klient zapłacił przy ladzie, odbierając kubek
        }

        $pdo->prepare('UPDATE orders SET status = ?, payment_status = ? WHERE id = ?')
            ->execute([$newStatus, $paymentStatus, $orderId]);
        $pdo->prepare('INSERT INTO order_status_log (order_id, user_id, old_status, new_status) VALUES (?, ?, ?, ?)')
            ->execute([$orderId, $userId, $oldStatus, $newStatus]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    return ['id' => $orderId, 'order_code' => $order['order_code'], 'status' => $newStatus, 'payment_status' => $paymentStatus];
}
