<?php
/**
 * /api/admin/products.php  (zadania 35 i 38, BE)
 *
 * GET  – lista WSZYSTKICH produktów (także ukrytych) z alergenami, kategorie,
 *        alergeny i białko. Dostęp: pracownik i kierownik (pracownik potrzebuje
 *        listy, żeby przełączać dostępność).
 * POST – dodanie (bez "id") albo edycja (z "id") produktu w istniejącej kategorii.
 *        Dostęp: tylko kierownik + token CSRF.
 *
 * Produktów nie usuwamy – kierownik ukrywa je polem is_active = false.
 */

declare(strict_types=1);

require __DIR__ . '/../../../src/bootstrap.php';
require_once __DIR__ . '/../../../src/auth.php';
require_once __DIR__ . '/../../../src/products.php';

$pdo = db();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    require_role('employee', 'manager');

    $allergensByProduct = [];
    foreach ($pdo->query('SELECT product_id, allergen_id FROM product_allergens') as $row) {
        $allergensByProduct[(int) $row['product_id']][] = (int) $row['allergen_id'];
    }

    $products = $pdo->query(
        'SELECT p.id, p.category_id, p.name, p.color_hex, p.portion_g, p.kcal, p.protein_g, p.carbs_g, p.fat_g,
                p.image_url, p.image_author, p.image_source, p.is_available, p.is_active
           FROM products p
           JOIN categories c ON c.id = p.category_id
          ORDER BY c.type, c.position, p.name'
    )->fetchAll();

    $extras = $pdo->query('SELECT id, name, price, portion_g, is_available FROM extras ORDER BY id')->fetchAll();

    json_ok([
        'categories' => array_map(fn ($c) => [
            'id' => (int) $c['id'], 'name' => $c['name'], 'type' => $c['type'], 'position' => (int) $c['position'],
        ], $pdo->query('SELECT id, name, type, position FROM categories ORDER BY type, position')->fetchAll()),
        'allergens' => array_map(fn ($a) => ['id' => (int) $a['id'], 'name' => $a['name']],
            $pdo->query('SELECT id, name FROM allergens ORDER BY id')->fetchAll()),
        'products' => array_map(fn ($p) => [
            'id'           => (int) $p['id'],
            'category_id'  => (int) $p['category_id'],
            'name'         => $p['name'],
            'color_hex'    => $p['color_hex'],
            'portion_g'    => (int) $p['portion_g'],
            'kcal'         => (float) $p['kcal'],
            'protein_g'    => (float) $p['protein_g'],
            'carbs_g'      => (float) $p['carbs_g'],
            'fat_g'        => (float) $p['fat_g'],
            'image_url'    => $p['image_url'],
            'image_author' => $p['image_author'],
            'image_source' => $p['image_source'],
            'is_available' => (bool) $p['is_available'],
            'is_active'    => (bool) $p['is_active'],
            'allergen_ids' => $allergensByProduct[(int) $p['id']] ?? [],
        ], $products),
        'extras' => array_map(fn ($e) => [
            'id' => (int) $e['id'], 'name' => $e['name'], 'price' => (float) $e['price'],
            'portion_g' => (int) $e['portion_g'], 'is_available' => (bool) $e['is_available'],
        ], $extras),
    ]);
}

require_method('POST');
require_role('manager');
require_csrf();

$input = read_json_body();
$id = isset($input['id']) ? positive_int($input['id']) : null;
if (isset($input['id']) && $id === null) {
    throw new AppError('INVALID_PRODUCT', 'Niepoprawny produkt.');
}
$data = validate_product_input($pdo, $input);

$columns = ['category_id', 'name', 'color_hex', 'portion_g', 'kcal', 'protein_g', 'carbs_g', 'fat_g',
            'image_url', 'image_author', 'image_source', 'is_available', 'is_active'];
$values = array_map(fn ($column) => is_bool($data[$column]) ? (int) $data[$column] : $data[$column], $columns);

$pdo->beginTransaction();
try {
    if ($id === null) {
        // Nazwy kolumn pochodzą z listy $columns w kodzie, wartości idą jako parametry.
        $sql = 'INSERT INTO products (' . implode(', ', $columns) . ') VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $pdo->prepare($sql)->execute($values);
        $id = (int) $pdo->lastInsertId();
    } else {
        $exists = $pdo->prepare('SELECT id FROM products WHERE id = ? FOR UPDATE');
        $exists->execute([$id]);
        if (!$exists->fetch()) {
            throw new AppError('PRODUCT_NOT_FOUND', 'Nie ma takiego produktu.', 404);
        }
        $sql = 'UPDATE products SET ' . implode(', ', array_map(fn ($c) => "$c = ?", $columns)) . ' WHERE id = ?';
        $pdo->prepare($sql)->execute([...$values, $id]);
    }

    // Alergeny: usuń stare powiązania i wpisz aktualne.
    $pdo->prepare('DELETE FROM product_allergens WHERE product_id = ?')->execute([$id]);
    $insert = $pdo->prepare('INSERT INTO product_allergens (product_id, allergen_id) VALUES (?, ?)');
    foreach ($data['allergen_ids'] as $allergenId) {
        $insert->execute([$id, $allergenId]);
    }

    $pdo->commit();
} catch (PDOException $e) {
    $pdo->rollBack();
    // 1062 = naruszenie UNIQUE(category_id, name): taki produkt już jest w tej kategorii.
    if (($e->errorInfo[1] ?? 0) === 1062) {
        throw new AppError('PRODUCT_EXISTS', 'Produkt o tej nazwie już jest w tej kategorii.', 409);
    }
    throw $e;
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}

json_ok(['id' => $id, 'saved' => true], isset($input['id']) ? 200 : 201);
