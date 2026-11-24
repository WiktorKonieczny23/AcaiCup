<?php
/**
 * Płatność przy kasie: klient płaci przy odbiorze, podając numer zamówienia.
 * Zamówienie od razu trafia do panelu jako „do zapłaty” (payment_status = pending).
 */

declare(strict_types=1);

require_once __DIR__ . '/PaymentGateway.php';

final class CashPayment implements PaymentGateway
{
    public function code(): string
    {
        return 'cash';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function start(array $order): array
    {
        // Nic nie wysyłamy na zewnątrz – pieniądze przyjmie pracownik przy ladzie.
        return ['payment_status' => 'pending', 'redirect_url' => null];
    }
}
