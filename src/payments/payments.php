<?php
/**
 * Wybór sposobu płatności po kodzie z żądania klienta.
 * Wywołuje: public/api/orders.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../errors.php';
require_once __DIR__ . '/PaymentGateway.php';
require_once __DIR__ . '/CashPayment.php';
require_once __DIR__ . '/OnlinePayment.php';

/** Zwraca obiekt płatności dla 'cash' albo 'online'; inny kod to błąd 400. */
function payment_gateway(mixed $method): PaymentGateway
{
    return match ($method) {
        'cash'   => new CashPayment(),
        'online' => new OnlinePayment(),
        default  => throw new AppError('INVALID_PAYMENT', 'Wybierz sposób płatności.'),
    };
}
