<?php
/**
 * GET /api/admin/summary.php?from=2026-11-01&to=2026-11-30  (zadanie 38, BE)
 * Podsumowanie sprzedaży dla kierownika.
 *
 * Liczymy tylko zamówienia nieanulowane. Przychód to suma total_price, czyli
 * cen z chwili zakupu (kopii), więc późniejsza zmiana cennika go nie zmienia.
 * Domyślnie: ostatnie 7 dni. Dostęp: tylko kierownik.
 */

declare(strict_types=1);

require __DIR__ . '/../../../src/bootstrap.php';
require_once __DIR__ . '/../../../src/auth.php';

require_method('GET');
require_role('manager');

/** Data z adresu w formacie RRRR-MM-DD albo wartość domyślna. */
function date_param(string $name, string $default): DateTimeImmutable
{
    $value = $_GET[$name] ?? $default;
    $date = is_string($value) ? DateTimeImmutable::createFromFormat('!Y-m-d', $value) : false;
    if (!$date || $date->format('Y-m-d') !== $value) {
        throw new AppError('INVALID_DATE', 'Podaj datę w formacie RRRR-MM-DD.');
    }
    return $date;
}

$from = date_param('from', date('Y-m-d', strtotime('-6 days')));
$to = date_param('to', date('Y-m-d'));
if ($from > $to) {
    throw new AppError('INVALID_DATE', 'Data „od” musi być wcześniejsza niż „do”.');
}
if ($from->diff($to)->days > 366) {
    throw new AppError('INVALID_DATE', 'Maksymalny zakres to rok.');
}

$pdo = db();
// Zakres: od północy dnia "from" do północy dnia po "to".
$range = [$from->format('Y-m-d 00:00:00'), $to->modify('+1 day')->format('Y-m-d 00:00:00')];
$where = "o.created_at >= ? AND o.created_at < ? AND o.status <> 'cancelled'";

$stmt = $pdo->prepare(
    "SELECT COUNT(*) AS orders, COALESCE(SUM(o.total_price), 0) AS revenue,
            COALESCE(SUM(o.extra_protein), 0) AS with_protein
       FROM orders o WHERE $where"
);
$stmt->execute($range);
$totals = $stmt->fetch();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM orders o WHERE o.created_at >= ? AND o.created_at < ? AND o.status = 'cancelled'");
$stmt->execute($range);
$cancelled = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare(
    "SELECT s.code, s.name, COUNT(o.id) AS orders, COALESCE(SUM(o.total_price), 0) AS revenue
       FROM sizes s
       LEFT JOIN orders o ON o.size_id = s.id AND $where
      GROUP BY s.id, s.code, s.name
      ORDER BY s.layers_count"
);
$stmt->execute($range);
$bySize = $stmt->fetchAll();

$stmt = $pdo->prepare(
    "SELECT oi.product_id, MAX(oi.product_name) AS name, COUNT(*) AS times
       FROM order_items oi
       JOIN orders o ON o.id = oi.order_id
      WHERE $where
      GROUP BY oi.product_id
      ORDER BY times DESC, name
      LIMIT 10"
);
$stmt->execute($range);
$topProducts = $stmt->fetchAll();

$stmt = $pdo->prepare(
    "SELECT DATE(o.created_at) AS day, COUNT(*) AS orders, SUM(o.total_price) AS revenue
       FROM orders o
      WHERE $where
      GROUP BY DATE(o.created_at)
      ORDER BY day"
);
$stmt->execute($range);
$byDay = $stmt->fetchAll();

$orders = (int) $totals['orders'];
$revenue = (float) $totals['revenue'];

json_ok([
    'from'          => $from->format('Y-m-d'),
    'to'            => $to->format('Y-m-d'),
    'orders'        => $orders,
    'revenue'       => round($revenue, 2),
    'average'       => $orders ? round($revenue / $orders, 2) : 0.0,
    'with_protein'  => (int) $totals['with_protein'],
    'cancelled'     => $cancelled,
    'by_size'       => array_map(fn ($r) => ['code' => $r['code'], 'name' => $r['name'], 'orders' => (int) $r['orders'], 'revenue' => (float) $r['revenue']], $bySize),
    'top_products'  => array_map(fn ($r) => ['name' => $r['name'], 'times' => (int) $r['times']], $topProducts),
    'by_day'        => array_map(fn ($r) => ['day' => $r['day'], 'orders' => (int) $r['orders'], 'revenue' => (float) $r['revenue']], $byDay),
]);
