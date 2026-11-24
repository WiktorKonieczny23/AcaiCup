<?php
/**
 * Płatność online – miejsce na Przelewy24.
 *
 * Na razie bramka jest wyłączona: isAvailable() zwraca false, więc
 * orders.php NIE zapisuje zamówienia, a frontend pokazuje stronę
 * „płatność online wkrótce”.
 *
 * Gdy dojdzie Przelewy24:
 *  1. isAvailable() zwróci true, gdy w config.php są klucze P24,
 *  2. start() zarejestruje transakcję w P24 i zwróci redirect_url do bramki,
 *  3. nowy endpoint api/payments/p24-notify.php sprawdzi podpis i ustawi payment_status = 'paid'.
 */

declare(strict_types=1);

require_once __DIR__ . '/PaymentGateway.php';

final class OnlinePayment implements PaymentGateway
{
    public function code(): string
    {
        return 'online';
    }

    public function isAvailable(): bool
    {
        return false;
    }

    public function start(array $order): array
    {
        throw new LogicException('Płatność online nie jest jeszcze podłączona.');
    }
}
