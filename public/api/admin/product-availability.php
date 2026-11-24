<?php
/**
 * POST /api/admin/product-availability.php  (zadanie 35, BE)
 * Oznaczenie składnika (albo białka) jako dostępny / chwilowo niedostępny.
 *
 * Treść: {"kind": "product", "id": 14, "available": false}
 *        {"kind": "extra",   "id": 1,  "available": true}   ← białko serwatkowe
 * Niedostępny produkt konfigurator pokazuje wyszarzony, a pricing.php go odrzuca.
 * Dostęp: pracownik i kierownik + token CSRF.
 */

declare(strict_types=1);

require __DIR__ . '/../../../src/bootstrap.php';
require_once __DIR__ . '/../../../src/auth.php';

require_method('POST');
require_role('employee', 'manager');
require_csrf();

$input = read_json_body();
$kind = $input['kind'] ?? 'product';
$id = positive_int($input['id'] ?? null);
$available = $input['available'] ?? null;

if (!in_array($kind, ['product', 'extra'], true) || $id === null || !is_bool($available)) {
    throw new AppError('INVALID_INPUT', 'Niepoprawne dane produktu.');
}

// Nazwa tabeli pochodzi z NASZEJ listy (product/extra), nie z tekstu od klienta.
$table = $kind === 'product' ? 'products' : 'extras';
$stmt = db()->prepare("UPDATE $table SET is_available = ? WHERE id = ?");
$stmt->execute([$available ? 1 : 0, $id]);

// rowCount() = 0 także wtedy, gdy wartość się nie zmieniła, więc sprawdzamy istnienie osobno.
$check = db()->prepare("SELECT id, name, is_available FROM $table WHERE id = ?");
$check->execute([$id]);
$row = $check->fetch();
if (!$row) {
    throw new AppError('PRODUCT_NOT_FOUND', 'Nie ma takiego produktu.', 404);
}

json_ok(['kind' => $kind, 'id' => (int) $row['id'], 'name' => $row['name'], 'available' => (bool) $row['is_available']]);
