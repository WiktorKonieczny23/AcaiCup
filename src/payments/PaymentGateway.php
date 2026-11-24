<?php
/**
 * Wspólny interfejs płatności (zadanie 40, BE).
 *
 * Każdy sposób płatności (przy kasie, później Przelewy24) to osobna klasa
 * z tymi samymi trzema metodami. Endpoint zamówienia nie wie, jak działa
 * konkretna płatność – pyta tylko, czy jest dostępna, i ją rozpoczyna.
 * Dzięki temu dodanie Przelewy24 to nowa klasa, bez przepisywania orders.php.
 */

declare(strict_types=1);

interface PaymentGateway
{
    /** Kod zapisywany w orders.payment_method: 'cash' albo 'online'. */
    public function code(): string;

    /** Czy klient może teraz wybrać tę płatność. */
    public function isAvailable(): bool;

    /**
     * Rozpoczyna płatność dla zapisanego zamówienia.
     * $order – ['id', 'order_code', 'total_price'].
     * Zwraca ['payment_status' => 'pending'|'paid', 'redirect_url' => adres bramki albo null].
     */
    public function start(array $order): array;
}
