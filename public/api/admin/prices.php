<?php
/**
 * /api/admin/prices.php  (zadanie 38, BE) – ceny rozmiarów i dopłata za białko.
 *
 * GET  – aktualne ceny.
 * POST – {"sizes": {"S": 19.00, "M": 24.00, "L": 29.00}, "protein": 4.00, "extra_topping": 5.00}
 *        Można wysłać tylko część, np. samo "protein".
 * Dostęp: tylko kierownik (+ token CSRF przy zmianie).
 *
 * Złożone zamówienia mają własną kopię ceny (orders.size_price),
 * więc zmiana cennika nie zmienia historii ani podsumowania sprzedaży.
 */

declare(strict_types=1);

require __DIR__ . '/../../../src/bootstrap.php';
require_once __DIR__ . '/../../../src/auth.php';

require_role('manager');
$pdo = db();

/** Aktualny cennik w formacie odpowiedzi. */
function current_prices(PDO $pdo): array
{
    $sizes = $pdo->query('SELECT code, name, layers_count, toppings_count, price FROM sizes ORDER BY layers_count')->fetchAll();
    $protein = $pdo->query("SELECT name, portion_g, price FROM extras WHERE code = 'whey_protein'")->fetch();
    $topping = $pdo->query("SELECT price FROM extras WHERE code = 'extra_topping'")->fetch();
    return [
        'sizes' => array_map(fn ($s) => [
            'code' => $s['code'], 'name' => $s['name'], 'layers' => (int) $s['layers_count'],
            'toppings' => (int) $s['toppings_count'], 'price' => (float) $s['price'],
        ], $sizes),
        'protein' => $protein ? ['name' => $protein['name'], 'portion_g' => (int) $protein['portion_g'], 'price' => (float) $protein['price']] : null,
        'extra_topping' => $topping ? ['price' => (float) $topping['price']] : null,
    ];
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    json_ok(current_prices($pdo));
}

require_method('POST');
require_csrf();

$input = read_json_body();
$sizes = $input['sizes'] ?? [];
if (!is_array($sizes)) {
    throw new AppError('INVALID_PRICE', 'Niepoprawne ceny rozmiarów.');
}

// Najpierw sprawdzamy WSZYSTKO, potem zapisujemy – żeby nie zapisać połowy zmian.
$sizeUpdates = [];
foreach ($sizes as $code => $value) {
    $price = money_value($value);
    if (!in_array($code, ['S', 'M', 'L'], true) || $price === null) {
        throw new AppError('INVALID_PRICE', 'Cena rozmiaru musi być kwotą od 0,01 do 999,99 zł.');
    }
    $sizeUpdates[$code] = $price;
}
$proteinPrice = null;
if (array_key_exists('protein', $input)) {
    $proteinPrice = money_value($input['protein']);
    if ($proteinPrice === null) {
        throw new AppError('INVALID_PRICE', 'Dopłata za białko musi być kwotą od 0,01 do 999,99 zł.');
    }
}
$toppingPrice = null;
if (array_key_exists('extra_topping', $input)) {
    $toppingPrice = money_value($input['extra_topping']);
    if ($toppingPrice === null) {
        throw new AppError('INVALID_PRICE', 'Cena dodatkowego toppingu musi być kwotą od 0,01 do 999,99 zł.');
    }
}
if (!$sizeUpdates && $proteinPrice === null && $toppingPrice === null) {
    throw new AppError('INVALID_PRICE', 'Nie podano żadnej ceny.');
}

$pdo->beginTransaction();
try {
    $update = $pdo->prepare('UPDATE sizes SET price = ? WHERE code = ?');
    foreach ($sizeUpdates as $code => $price) {
        $update->execute([$price, $code]);
    }
    if ($proteinPrice !== null) {
        $pdo->prepare("UPDATE extras SET price = ? WHERE code = 'whey_protein'")->execute([$proteinPrice]);
    }
    if ($toppingPrice !== null) {
        $pdo->prepare("UPDATE extras SET price = ? WHERE code = 'extra_topping'")->execute([$toppingPrice]);
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}

json_ok(current_prices($pdo));
