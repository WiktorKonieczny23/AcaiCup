<?php
/**
 * Walidacja produktu dodawanego lub edytowanego przez kierownika (zadanie 38, BE).
 * Wywołuje: public/api/admin/products.php
 */

declare(strict_types=1);

require_once __DIR__ . '/errors.php';
require_once __DIR__ . '/request.php';

/**
 * Sprawdza dane produktu z formularza i zwraca je oczyszczone.
 * Kategoria musi już istnieć (kierownik nie tworzy nowych kategorii).
 */
function validate_product_input(PDO $pdo, array $input): array
{
    $fail = fn (string $message) => throw new AppError('INVALID_PRODUCT', $message);

    $categoryId = positive_int($input['category_id'] ?? null) ?? $fail('Wybierz kategorię.');
    $stmt = $pdo->prepare('SELECT id FROM categories WHERE id = ?');
    $stmt->execute([$categoryId]);
    if (!$stmt->fetch()) {
        $fail('Taka kategoria nie istnieje.');
    }

    $name = trim((string) ($input['name'] ?? ''));
    if (mb_strlen($name) < 2 || mb_strlen($name) > 60) {
        $fail('Nazwa musi mieć od 2 do 60 znaków.');
    }

    $color = (string) ($input['color_hex'] ?? '');
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
        $fail('Kolor podaj w formacie #RRGGBB, np. #4B1A55.');
    }

    $portion = positive_int($input['portion_g'] ?? null);
    if ($portion === null || $portion > 1000) {
        $fail('Porcja musi być liczbą gramów od 1 do 1000.');
    }

    $numbers = [];
    foreach (['kcal' => 2000, 'protein_g' => 200, 'carbs_g' => 200, 'fat_g' => 200] as $key => $max) {
        $value = $input[$key] ?? null;
        if (!is_numeric($value) || (float) $value < 0 || (float) $value > $max) {
            $fail("Pole {$key} musi być liczbą od 0 do {$max}.");
        }
        $numbers[$key] = round((float) $value, 1);
    }

    $allergenIds = $input['allergen_ids'] ?? [];
    if (!is_array($allergenIds)) {
        $fail('Niepoprawna lista alergenów.');
    }
    $allergenIds = array_values(array_unique(array_map(fn ($v) => positive_int($v) ?? $fail('Niepoprawny alergen.'), $allergenIds)));
    if ($allergenIds) {
        $placeholders = implode(', ', array_fill(0, count($allergenIds), '?'));
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM allergens WHERE id IN ($placeholders)");
        $stmt->execute($allergenIds);
        if ((int) $stmt->fetchColumn() !== count($allergenIds)) {
            $fail('Niepoprawny alergen.');
        }
    }

    $imageUrl = trim((string) ($input['image_url'] ?? ''));
    if ($imageUrl !== '' && !is_allowed_image_url($imageUrl)) {
        $fail('Zdjęcie: dozwolony jest adres https://images.unsplash.com/…, https://images.pexels.com/… albo plik assets/img/products/nazwa.jpg (.png, .webp).');
    }

    $imageAuthor = trim((string) ($input['image_author'] ?? ''));
    if (mb_strlen($imageAuthor) > 80) {
        $fail('Autor zdjęcia może mieć najwyżej 80 znaków.');
    }

    $imageSource = trim((string) ($input['image_source'] ?? ''));
    if ($imageSource !== '' && (strlen($imageSource) > 255
        || !filter_var($imageSource, FILTER_VALIDATE_URL) || !str_starts_with($imageSource, 'https://'))) {
        $fail('Źródło zdjęcia musi być adresem https://.');
    }

    foreach (['is_available', 'is_active'] as $flag) {
        if (isset($input[$flag]) && !is_bool($input[$flag])) {
            $fail('Niepoprawna wartość pola ' . $flag . '.');
        }
    }

    return [
        'category_id'  => $categoryId,
        'name'         => $name,
        'color_hex'    => strtoupper($color),
        'portion_g'    => $portion,
        'kcal'         => $numbers['kcal'],
        'protein_g'    => $numbers['protein_g'],
        'carbs_g'      => $numbers['carbs_g'],
        'fat_g'        => $numbers['fat_g'],
        'image_url'    => $imageUrl === '' ? null : $imageUrl,
        'image_author' => $imageAuthor === '' ? null : $imageAuthor,
        'image_source' => $imageSource === '' ? null : $imageSource,
        'is_available' => $input['is_available'] ?? true,
        'is_active'    => $input['is_active'] ?? true,
        'allergen_ids' => $allergenIds,
    ];
}

/**
 * Zagrożenie Z14 z docs/bezpieczenstwo.md: adres zdjęcia trafia do CSS i SVG,
 * więc przyjmujemy tylko dwa zaufane serwisy albo własny plik w public/assets/img/products/.
 * Wyrażenie regularne dopuszcza tylko litery, cyfry, myślnik, podkreślnik, kropkę i ukośnik
 * – bez cudzysłowów, nawiasów i spacji, którymi dałoby się „wyjść” z url("…").
 */
function is_allowed_image_url(string $url): bool
{
    if (strlen($url) > 255) {
        return false;
    }
    if (preg_match('#^assets/img/products/[a-z0-9_-]+\.(jpe?g|png|webp)$#i', $url)) {
        return true;
    }
    return (bool) preg_match('#^https://images\.(unsplash|pexels)\.com/[A-Za-z0-9/_.-]+$#', $url);
}
