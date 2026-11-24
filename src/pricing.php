<?php
/**
 * Walidacja konfiguracji kubka i wyliczenie ceny PO STRONIE SERWERA (zadanie 25, BE).
 *
 * Najważniejsza zasada: serwer nie wierzy przeglądarce. Klient wysyła tylko
 * CO wybrał (kod rozmiaru, id produktów, czy chce białko). Ceny, nazwy,
 * kcal i alergeny serwer bierze z bazy. Nawet jeśli ktoś dopisze do żądania
 * "price": 0.01, to pole zostanie zignorowane.
 *
 * Zasady biznesowe:
 *  - S = 2 warstwy + 1 topping, M = 3 + 1, L = 4 + 2 (dane z tabeli sizes),
 *  - warstwa N musi być produktem z kategorii o position = N
 *    (1 baza acai, 2 zboża/granola, 3 owoce, 4 krem),
 *  - składnik nie może się powtórzyć (w L: dwa RÓŻNE toppingi),
 *  - produkt musi być aktywny (is_active) i dostępny (is_available),
 *  - cena = cena rozmiaru + ewentualna dopłata za białko.
 *
 * Wywołuje: public/api/orders.php
 */

declare(strict_types=1);

require_once __DIR__ . '/errors.php';
require_once __DIR__ . '/request.php';

/**
 * Sprawdza konfigurację i liczy cenę.
 *
 * $input – dane od klienta, np.:
 *   ["size" => "M", "layers" => [1 => 3, 2 => 11, 3 => 17], "toppings" => [24], "extra_protein" => true]
 *
 * Zwraca tablicę z rozmiarem, listą pozycji (ze slotami layer_1…topping_2),
 * cenami, wartościami odżywczymi i alergenami.
 * Przy błędzie rzuca AppError z kodem dla frontendu.
 */
function price_order(PDO $pdo, array $input): array
{
    $size = load_size($pdo, $input['size'] ?? null);

    // 1. Zbieramy, jaki produkt ma trafić do którego miejsca w kubku -------
    $wanted = [];   // slot => id produktu, np. 'layer_1' => 3

    $layers = $input['layers'] ?? null;
    if (!is_array($layers) || count($layers) !== $size['layers_count']) {
        throw new AppError(
            'INVALID_LAYERS',
            sprintf('Rozmiar %s ma %d warstwy – wybierz składnik dla każdej z nich.', $size['code'], $size['layers_count'])
        );
    }
    for ($position = 1; $position <= $size['layers_count']; $position++) {
        $id = positive_int($layers[$position] ?? null);
        if ($id === null) {
            throw new AppError('INVALID_LAYERS', "Wybierz składnik dla warstwy {$position}.");
        }
        $wanted["layer_{$position}"] = $id;
    }

    $toppings = $input['toppings'] ?? null;
    if (!is_array($toppings) || !array_is_list($toppings) || count($toppings) !== $size['toppings_count']) {
        throw new AppError(
            'INVALID_TOPPINGS',
            $size['toppings_count'] === 1 ? 'Wybierz jeden topping.' : "Wybierz {$size['toppings_count']} różne toppingi."
        );
    }
    foreach ($toppings as $index => $value) {
        $id = positive_int($value);
        if ($id === null) {
            throw new AppError('INVALID_TOPPINGS', 'Niepoprawny topping.');
        }
        $wanted['topping_' . ($index + 1)] = $id;
    }

    // Składnik nie może się powtórzyć (to samo pilnuje UNIQUE w order_items).
    if (count(array_unique($wanted)) !== count($wanted)) {
        throw new AppError('DUPLICATE_PRODUCT', 'Każdy składnik może wystąpić w kubku tylko raz. Toppingi muszą być różne.');
    }

    // 2. Pobieramy wszystkie te produkty z bazy jednym zapytaniem ----------
    $products = load_products($pdo, array_values($wanted));

    $items = [];
    foreach ($wanted as $slot => $id) {
        $product = $products[$id] ?? null;
        if ($product === null) {
            throw new AppError('PRODUCT_NOT_FOUND', 'Wybrany składnik nie istnieje. Odśwież stronę.', 404);
        }

        // Czy produkt pasuje do miejsca? layer_3 → kategoria warstwy nr 3, topping_N → toppingi.
        $isLayerSlot = str_starts_with($slot, 'layer_');
        $fits = $isLayerSlot
            ? ($product['type'] === 'layer' && (int) $product['position'] === (int) substr($slot, 6))
            : $product['type'] === 'topping';
        if (!$fits) {
            throw new AppError('PRODUCT_WRONG_CATEGORY', "Składnik „{$product['name']}” nie pasuje do tego miejsca w kubku.");
        }

        if (!$product['is_available']) {
            throw new AppError('PRODUCT_UNAVAILABLE', "„{$product['name']}” jest chwilowo niedostępne. Wybierz inny składnik.", 409);
        }

        $items[] = [
            'slot'       => $slot,
            'product_id' => $id,
            'name'       => $product['name'],
            'kcal'       => (float) $product['kcal'],
            'protein_g'  => (float) $product['protein_g'],
            'carbs_g'    => (float) $product['carbs_g'],
            'fat_g'      => (float) $product['fat_g'],
        ];
    }

    // 3. Extra białko --------------------------------------------------------
    $wantsProtein = $input['extra_protein'] ?? false;
    if (!is_bool($wantsProtein)) {
        throw new AppError('INVALID_EXTRA', 'Niepoprawny wybór dodatkowego białka.');
    }
    $extra = $wantsProtein ? load_protein($pdo) : null;

    // 4. Cena: liczymy w groszach (liczby całkowite), żeby uniknąć błędów zaokrągleń
    $sizeGrosze    = (int) round((float) $size['price'] * 100);
    $proteinGrosze = $extra ? (int) round((float) $extra['price'] * 100) : 0;

    // 5. Wartości odżywcze i alergeny (tak jak w podglądzie na stronie) ------
    $nutritionRows = $items;
    if ($extra) {
        $nutritionRows[] = $extra;
    }
    $nutrition = [];
    foreach (['kcal', 'protein_g', 'carbs_g', 'fat_g'] as $key) {
        $nutrition[$key] = round(array_sum(array_map(fn ($row) => (float) $row[$key], $nutritionRows)), 1);
    }

    return [
        'size' => [
            'id'   => (int) $size['id'],
            'code' => $size['code'],
            'name' => $size['name'],
        ],
        'items'         => $items,
        'extra'         => $extra ? ['id' => (int) $extra['id'], 'name' => $extra['name'], 'portion_g' => (int) $extra['portion_g']] : null,
        'size_price'    => $sizeGrosze / 100.0,
        'protein_price' => $proteinGrosze / 100.0,
        'total_price'   => ($sizeGrosze + $proteinGrosze) / 100.0, // 100.0, żeby zawsze była liczba z groszami (28.0, nie 28)
        'nutrition'     => $nutrition,
        'allergens'     => load_allergens($pdo, array_column($items, 'product_id'), $extra ? (int) $extra['id'] : null),
    ];
}

/** Rozmiar po kodzie (S/M/L); tylko aktywne rozmiary. */
function load_size(PDO $pdo, mixed $code): array
{
    if (!is_string($code) || $code === '') {
        throw new AppError('INVALID_SIZE', 'Wybierz rozmiar kubka.');
    }
    $stmt = $pdo->prepare(
        'SELECT id, code, name, layers_count, toppings_count, price
           FROM sizes
          WHERE code = ? AND is_active = 1'
    );
    $stmt->execute([$code]);
    $size = $stmt->fetch();
    if (!$size) {
        throw new AppError('INVALID_SIZE', 'Taki rozmiar nie istnieje.');
    }
    $size['layers_count']   = (int) $size['layers_count'];
    $size['toppings_count'] = (int) $size['toppings_count'];
    return $size;
}

/**
 * Produkty o podanych id razem z typem i numerem kategorii.
 * Zwraca tablicę id => produkt. Ukryte produkty (is_active = 0) pomija,
 * więc dla klienta wyglądają tak, jakby nie istniały.
 */
function load_products(PDO $pdo, array $ids): array
{
    // Tyle znaków zapytania, ile id: "?, ?, ?". Same id idą osobno do execute().
    $placeholders = implode(', ', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare(
        "SELECT p.id, p.name, p.kcal, p.protein_g, p.carbs_g, p.fat_g, p.is_available,
                c.type, c.position
           FROM products p
           JOIN categories c ON c.id = p.category_id
          WHERE p.id IN ($placeholders) AND p.is_active = 1"
    );
    $stmt->execute($ids);

    $byId = [];
    foreach ($stmt->fetchAll() as $row) {
        $row['is_available'] = (bool) $row['is_available'];
        $byId[(int) $row['id']] = $row;
    }
    return $byId;
}

/** Białko serwatkowe; przerywa, gdy jest niedostępne. */
function load_protein(PDO $pdo): array
{
    $stmt = $pdo->prepare(
        'SELECT id, name, price, portion_g, kcal, protein_g, carbs_g, fat_g, is_available
           FROM extras
          WHERE code = ?'
    );
    $stmt->execute(['whey_protein']);
    $extra = $stmt->fetch();
    if (!$extra || !$extra['is_available']) {
        throw new AppError('EXTRA_UNAVAILABLE', 'Dodatkowe białko jest chwilowo niedostępne.', 409);
    }
    return $extra;
}

/** Nazwy alergenów wybranych produktów i białka, bez powtórzeń. */
function load_allergens(PDO $pdo, array $productIds, ?int $extraId): array
{
    $placeholders = implode(', ', array_fill(0, count($productIds), '?'));
    $sql = "SELECT DISTINCT a.id, a.name FROM product_allergens pa
              JOIN allergens a ON a.id = pa.allergen_id
             WHERE pa.product_id IN ($placeholders)";
    $params = $productIds;

    if ($extraId !== null) {
        $sql .= ' UNION SELECT a.id, a.name FROM extra_allergens ea
                    JOIN allergens a ON a.id = ea.allergen_id
                   WHERE ea.extra_id = ?';
        $params[] = $extraId;
    }

    // DISTINCT i UNION usuwają powtórzenia; sortujemy po id, czyli w kolejności z listy UE.
    $stmt = $pdo->prepare("SELECT id, name FROM ($sql) AS x ORDER BY id");
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_COLUMN, 1); // tylko kolumna name
}
