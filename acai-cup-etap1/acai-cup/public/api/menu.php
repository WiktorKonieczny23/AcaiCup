<?php
/**
 * GET /api/menu.php  (zadanie 18, BE)
 *
 * Zwraca wszystko, czego konfigurator potrzebuje do zbudowania ekranów:
 * rozmiary z cenami, kategorie warstw z produktami, toppingi i extra białko.
 * Produkty niedostępne (is_available = 0) są w odpowiedzi z "available": false,
 * żeby frontend mógł je wyszarzyć. Produkty ukryte (is_active = 0) są pomijane.
 *
 * Każdy produkt ma pole "image" ({url, author, source}) albo null.
 *
 * Format odpowiedzi opisuje kontrakt API w planie projektu oraz
 * plik public/assets/js/mock-menu.json (ten sam kształt danych).
 */

declare(strict_types=1);

require __DIR__ . '/../../src/db.php';

require_method('GET');

/**
 * Zdjęcie produktu dla frontendu albo null, gdy produkt nie ma zdjęcia
 * (wtedy konfigurator użyje samego koloru).
 * url    – adres zdjęcia (Unsplash / Pexels) lub ścieżka do pliku w public/
 * author – autor do podpisu, source – strona zdjęcia
 */
function photo_info(array $row): ?array
{
    if (empty($row['image_url'])) {
        return null;
    }
    return [
        'url'    => $row['image_url'],
        'author' => $row['image_author'],
        'source' => $row['image_source'],
    ];
}

try {
    $pdo = db();

    // 1. Rozmiary ----------------------------------------------------------
    $sizes = [];
    $rows = $pdo->query(
        'SELECT code, name, layers_count, toppings_count, price
           FROM sizes
          WHERE is_active = 1
          ORDER BY layers_count'
    )->fetchAll();

    foreach ($rows as $row) {
        $sizes[] = [
            'code'     => $row['code'],
            'name'     => $row['name'],
            'layers'   => (int) $row['layers_count'],
            'toppings' => (int) $row['toppings_count'],
            'price'    => (float) $row['price'],
        ];
    }

    // 2. Alergeny wszystkich produktów jednym zapytaniem -------------------
    $allergensByProduct = [];
    $rows = $pdo->query(
        'SELECT pa.product_id, a.name
           FROM product_allergens pa
           JOIN allergens a ON a.id = pa.allergen_id
          ORDER BY a.id'
    )->fetchAll();

    foreach ($rows as $row) {
        $allergensByProduct[(int) $row['product_id']][] = $row['name'];
    }

    // 3. Kategorie i produkty ---------------------------------------------
    $rows = $pdo->query(
        'SELECT c.id AS category_id, c.name AS category_name, c.type, c.position,
                p.id, p.name, p.color_hex, p.portion_g, p.kcal,
                p.protein_g, p.carbs_g, p.fat_g, p.image_url, p.image_author, p.image_source,
                p.is_available
           FROM categories c
           LEFT JOIN products p ON p.category_id = c.id AND p.is_active = 1
          ORDER BY c.type, c.position, p.name'
    )->fetchAll();

    $layers   = [];   // klucz: numer warstwy
    $toppings = null;

    foreach ($rows as $row) {
        $isLayer = $row['type'] === 'layer';

        // Utwórz grupę kategorii przy pierwszym wierszu danej kategorii.
        if ($isLayer && !isset($layers[$row['position']])) {
            $layers[$row['position']] = [
                'position'    => (int) $row['position'],
                'category_id' => (int) $row['category_id'],
                'name'        => $row['category_name'],
                'products'    => [],
            ];
        }
        if (!$isLayer && $toppings === null) {
            $toppings = [
                'category_id' => (int) $row['category_id'],
                'name'        => $row['category_name'],
                'products'    => [],
            ];
        }

        if ($row['id'] === null) {
            continue; // kategoria bez produktów
        }

        $product = [
            'id'        => (int) $row['id'],
            'name'      => $row['name'],
            'color'     => $row['color_hex'],
            'portion_g' => (int) $row['portion_g'],
            'kcal'      => (float) $row['kcal'],
            'protein_g' => (float) $row['protein_g'],
            'carbs_g'   => (float) $row['carbs_g'],
            'fat_g'     => (float) $row['fat_g'],
            'allergens' => $allergensByProduct[(int) $row['id']] ?? [],
            'image'     => photo_info($row),
            'available' => (bool) $row['is_available'],
        ];

        if ($isLayer) {
            $layers[$row['position']]['products'][] = $product;
        } else {
            $toppings['products'][] = $product;
        }
    }

    // 4. Extra białko ------------------------------------------------------
    $stmt = $pdo->prepare(
        'SELECT e.id, e.name, e.price, e.portion_g, e.kcal, e.protein_g, e.carbs_g,
                e.fat_g, e.image_url, e.image_author, e.image_source, e.is_available,
                GROUP_CONCAT(a.name ORDER BY a.id SEPARATOR "|") AS allergens
           FROM extras e
           LEFT JOIN extra_allergens ea ON ea.extra_id = e.id
           LEFT JOIN allergens a ON a.id = ea.allergen_id
          WHERE e.code = ?
          GROUP BY e.id'
    );
    $stmt->execute(['whey_protein']);
    $extra = $stmt->fetch();

    $extraProtein = $extra ? [
        'id'        => (int) $extra['id'],
        'name'      => $extra['name'],
        'price'     => (float) $extra['price'],
        'portion_g' => (int) $extra['portion_g'],
        'kcal'      => (float) $extra['kcal'],
        'protein_g' => (float) $extra['protein_g'],
        'carbs_g'   => (float) $extra['carbs_g'],
        'fat_g'     => (float) $extra['fat_g'],
        'allergens' => $extra['allergens'] ? explode('|', $extra['allergens']) : [],
        'image'     => photo_info($extra),
        'available' => (bool) $extra['is_available'],
    ] : null;

    json_ok([
        'currency'      => 'PLN',
        'sizes'         => $sizes,
        'layers'        => array_values($layers),
        'toppings'      => $toppings,
        'extra_protein' => $extraProtein,
    ]);
} catch (PDOException $e) {
    error_log('[acai-cup] menu.php: ' . $e->getMessage());
    json_error('SERVER_ERROR', 'Nie udało się wczytać menu. Spróbuj za chwilę.', 500);
}
