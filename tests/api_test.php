<?php
/**
 * Automatyczne testy całej ścieżki przez API (zadania 32, 41, 46 – DevOps / QA).
 *
 * Testuje to, co robi przeglądarka: klienta (menu → zamówienie → status),
 * pracownika i kierownika w panelu oraz próby ataków (SQL injection, XSS,
 * CSRF, manipulacja ceną, kontrola dostępu, sesje, zgadywanie haseł).
 *
 * Uruchomienie (XAMPP, Apache i MySQL włączone):
 *     php tests/api_test.php http://localhost/ACAI/public
 * Wyniki jako tabela Markdown do pliku:
 *     php tests/api_test.php http://localhost/ACAI/public --markdown=docs/testy-wyniki.md
 *
 * Wymaga kont z seed.sql (pracownik / pracownik123, kierownik / kierownik123).
 * Testy po sobie sprzątają: przywracają ceny i dostępność, ukrywają dodany produkt,
 * a złożone zamówienia testowe anulują (anulowane nie liczą się do sprzedaży).
 * NIE uruchamiajcie ich na serwerze produkcyjnym – tylko lokalnie albo na kopii.
 */

declare(strict_types=1);

date_default_timezone_set('Europe/Warsaw');

if (PHP_SAPI !== 'cli') {
    exit('Uruchom z wiersza poleceń: php tests/api_test.php <adres>');
}

$base = rtrim($argv[1] ?? 'http://localhost/ACAI/public', '/');
$markdownFile = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--markdown=')) {
        $markdownFile = substr($arg, 11);
    }
}

/* ------------------------------------------------------------------ */
/* Mini-klient HTTP: osobne ciasteczka dla każdej „osoby”               */
/* ------------------------------------------------------------------ */

final class Client
{
    public ?string $csrf = null;
    private string $jar;

    public function __construct(private string $base)
    {
        $this->jar = tempnam(sys_get_temp_dir(), 'acai');
    }

    /** Wysyła żądanie; zwraca [kod HTTP, JSON jako tablica|null, nagłówki jako tekst]. */
    public function request(string $method, string $path, mixed $body = null, array $headers = [], bool $withCsrf = true): array
    {
        $ch = curl_init($this->base . $path);
        $sendHeaders = ['Accept: application/json'];
        if ($body !== null) {
            $sendHeaders[] = 'Content-Type: application/json';
        }
        if ($withCsrf && $this->csrf !== null && $method === 'POST') {
            $sendHeaders[] = 'X-CSRF-Token: ' . $this->csrf;
        }
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_COOKIEJAR      => $this->jar,
            CURLOPT_COOKIEFILE     => $this->jar,
            CURLOPT_HTTPHEADER     => array_merge($sendHeaders, $headers),
            CURLOPT_TIMEOUT        => 20,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($body) ? $body : json_encode($body));
        }
        $response = curl_exec($ch);
        if ($response === false) {
            throw new RuntimeException('Brak połączenia z ' . $this->base . $path . ': ' . curl_error($ch));
        }
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        return [$status, json_decode(substr($response, $headerSize), true), substr($response, 0, $headerSize)];
    }

    public function get(string $path): array { return $this->request('GET', $path); }
    public function post(string $path, mixed $body = null, bool $withCsrf = true): array { return $this->request('POST', $path, $body ?? (object) [], [], $withCsrf); }

    /** Wartość ciasteczka sesji z pliku curl (do sprawdzenia session_regenerate_id). */
    public function sessionId(): ?string
    {
        foreach (file($this->jar) ?: [] as $line) {
            $parts = explode("\t", trim($line));
            if (count($parts) === 7 && $parts[5] === 'ACAI_PANEL') {
                return $parts[6];
            }
        }
        return null;
    }

    public function login(string $login, string $password): array
    {
        [$status, $json] = $this->post('/api/admin/login.php', ['login' => $login, 'password' => $password], false);
        $this->csrf = $json['data']['csrf_token'] ?? null;
        return [$status, $json];
    }
}

/* ------------------------------------------------------------------ */
/* Rejestr wyników                                                     */
/* ------------------------------------------------------------------ */

$results = [];

/** Jeden scenariusz: $check zwraca [czy_ok, opis_wyniku] albo 'SKIP: powód'. */
function scenario(string $id, string $area, string $name, string $expected, callable $check): void
{
    global $results;
    try {
        $outcome = $check();
        if (is_string($outcome) && str_starts_with($outcome, 'SKIP')) {
            $results[] = [$id, $area, $name, $expected, $outcome, 'SKIP'];
        } else {
            [$ok, $actual] = $outcome;
            $results[] = [$id, $area, $name, $expected, $actual, $ok ? 'OK' : 'BŁĄD'];
        }
    } catch (Throwable $e) {
        $results[] = [$id, $area, $name, $expected, 'wyjątek: ' . $e->getMessage(), 'BŁĄD'];
    }
    $last = end($results);
    printf("%-5s %-4s %s\n", $last[0], $last[5], $last[2]);
}

/** Krótki opis odpowiedzi do tabeli: "409 PRODUCT_UNAVAILABLE". */
function described(array $response): string
{
    [$status, $json] = $response;
    return $status . ' ' . ($json['ok'] ?? false ? 'ok' : ($json['error']['code'] ?? 'brak JSON'));
}

function is_error(array $response, int $status, string $code): array
{
    return [$response[0] === $status && ($response[1]['error']['code'] ?? '') === $code, described($response)];
}

/* ------------------------------------------------------------------ */
/* Dane z menu potrzebne do zamówień                                   */
/* ------------------------------------------------------------------ */

$guest = new Client($base);
$employee = new Client($base);
$manager = new Client($base);

echo "Acai Cup – testy API: {$base}\n\n";

$menuResponse = $guest->get('/api/menu.php');
$menu = $menuResponse[1]['data'] ?? null;
if (!$menu) {
    exit("Nie udało się pobrać menu ({$menuResponse[0]}). Czy Apache i MySQL działają, a adres jest poprawny?\n");
}

$sizes = array_column($menu['sizes'], null, 'code');
$available = fn (array $products) => array_values(array_filter($products, fn ($p) => $p['available']));
$layerChoice = [];
foreach ($menu['layers'] as $category) {
    $layerChoice[$category['position']] = $available($category['products']);
}
$toppingChoice = $available($menu['toppings']['products']);

/** Poprawne zamówienie dla rozmiaru (pierwsze dostępne produkty). */
$validOrder = function (string $size, bool $protein = false) use ($sizes, $layerChoice, $toppingChoice): array {
    $layers = [];
    for ($p = 1; $p <= $sizes[$size]['layers']; $p++) {
        $layers[(string) $p] = $layerChoice[$p][0]['id'];
    }
    return [
        'size'           => $size,
        'layers'         => $layers,
        'toppings'       => array_map(fn ($t) => $t['id'], array_slice($toppingChoice, 0, $sizes[$size]['toppings'])),
        'extra_protein'  => $protein,
        'payment_method' => 'cash',
    ];
};

$createdOrders = [];   // id/kody zamówień testowych – na końcu je anulujemy

/* ------------------------------------------------------------------ */
/* KLIENT                                                              */
/* ------------------------------------------------------------------ */

scenario('K1', 'klient', 'Menu: rozmiary, 4 kategorie warstw, toppingi, białko', '200, S/M/L = 2+1, 3+1, 4+2', function () use ($menu, $sizes) {
    $ok = count($menu['layers']) === 4 && $sizes['S']['layers'] === 2 && $sizes['M']['layers'] === 3
        && $sizes['L']['layers'] === 4 && $sizes['L']['toppings'] === 2 && $menu['extra_protein'] !== null;
    return [$ok, '200, ' . implode(', ', array_map(fn ($s) => "{$s['code']}={$s['layers']}+{$s['toppings']}", $menu['sizes']))];
});

$order = null;
scenario('K2', 'klient', 'Zamówienie M z białkiem, płatność przy kasie', '201, numer A-123, cena = M + białko', function () use ($guest, $validOrder, $sizes, $menu, &$order, &$createdOrders) {
    $response = $guest->post('/api/orders.php', $validOrder('M', true));
    $order = $response[1]['data'] ?? null;
    $expected = $sizes['M']['price'] + $menu['extra_protein']['price'];
    $ok = $response[0] === 201 && preg_match('/^[A-Z]-\d{3}$/', $order['order_code'] ?? '') && abs($order['total_price'] - $expected) < 0.001;
    if ($order) $createdOrders[] = $order['order_code'];
    return [(bool) $ok, described($response) . ', ' . ($order['order_code'] ?? '?') . ', ' . ($order['total_price'] ?? '?') . ' zł'];
});

scenario('K3', 'bezpieczeństwo', 'Manipulacja ceną: klient wysyła "total_price": 0.01', 'serwer ignoruje cenę z przeglądarki', function () use ($guest, $validOrder, $sizes, &$createdOrders) {
    $body = $validOrder('S') + ['total_price' => 0.01, 'price' => 0.01, 'size_price' => 0.01];
    $response = $guest->post('/api/orders.php', $body);
    $total = $response[1]['data']['total_price'] ?? null;
    if ($total !== null) $createdOrders[] = $response[1]['data']['order_code'];
    return [$response[0] === 201 && abs($total - $sizes['S']['price']) < 0.001, described($response) . ", zapisana cena {$total} zł"];
});

scenario('K4', 'klient', 'Status zamówienia po numerze', '200, status new („Przyjęte”)', function () use ($guest, &$order) {
    $response = $guest->get('/api/order-status.php?code=' . urlencode($order['order_code']));
    return [$response[0] === 200 && ($response[1]['data']['status'] ?? '') === 'new', described($response) . ', ' . ($response[1]['data']['status_label'] ?? '')];
});

scenario('K5', 'walidacja', 'Rozmiar S z 3 warstwami', '400 INVALID_LAYERS', function () use ($guest, $validOrder) {
    $body = $validOrder('S');
    $body['layers']['3'] = $body['layers']['1'];
    return is_error($guest->post('/api/orders.php', $body), 400, 'INVALID_LAYERS');
});

scenario('K6', 'walidacja', 'Owoc jako warstwa 1 (zła kategoria)', '400 PRODUCT_WRONG_CATEGORY', function () use ($guest, $validOrder, $layerChoice) {
    $body = $validOrder('S');
    $body['layers']['1'] = $layerChoice[3][0]['id'];
    return is_error($guest->post('/api/orders.php', $body), 400, 'PRODUCT_WRONG_CATEGORY');
});

scenario('K7', 'walidacja', 'Rozmiar L z dwoma takimi samymi toppingami', '400 DUPLICATE_PRODUCT', function () use ($guest, $validOrder) {
    $body = $validOrder('L');
    $body['toppings'] = [$body['toppings'][0], $body['toppings'][0]];
    return is_error($guest->post('/api/orders.php', $body), 400, 'DUPLICATE_PRODUCT');
});

scenario('K8', 'walidacja', 'Za mało toppingów w L (1 zamiast 2)', '400 INVALID_TOPPINGS', function () use ($guest, $validOrder) {
    $body = $validOrder('L');
    $body['toppings'] = [$body['toppings'][0]];
    return is_error($guest->post('/api/orders.php', $body), 400, 'INVALID_TOPPINGS');
});

scenario('K9', 'walidacja', 'Nieistniejący rozmiar XL', '400 INVALID_SIZE', function () use ($guest, $validOrder) {
    $body = $validOrder('S');
    $body['size'] = 'XL';
    return is_error($guest->post('/api/orders.php', $body), 400, 'INVALID_SIZE');
});

scenario('K10', 'klient', 'Płatność online (Przelewy24 jeszcze nie działa)', '409 PAYMENT_UNAVAILABLE, nic nie zapisano', function () use ($guest, $validOrder) {
    $body = $validOrder('S');
    $body['payment_method'] = 'online';
    return is_error($guest->post('/api/orders.php', $body), 409, 'PAYMENT_UNAVAILABLE');
});

scenario('K11', 'walidacja', 'Uszkodzony JSON w treści żądania', '400 INVALID_JSON', function () use ($guest) {
    return is_error($guest->request('POST', '/api/orders.php', '{"size": "M",'), 400, 'INVALID_JSON');
});

scenario('K12', 'walidacja', 'GET zamiast POST na /api/orders.php', '405 METHOD_NOT_ALLOWED', function () use ($guest) {
    return is_error($guest->get('/api/orders.php'), 405, 'METHOD_NOT_ALLOWED');
});

scenario('K13', 'klient', 'Status nieistniejącego zamówienia', '404 ORDER_NOT_FOUND', function () use ($guest) {
    // Szukamy numeru, którego nie ma (losowanie może trafić w istniejący).
    foreach (range(0, 30) as $i) {
        $response = $guest->get('/api/order-status.php?code=Z-' . sprintf('%03d', 999 - $i));
        if ($response[0] === 404) return is_error($response, 404, 'ORDER_NOT_FOUND');
    }
    return [false, 'nie znaleziono wolnego numeru'];
});

scenario('S1', 'bezpieczeństwo', "SQL injection w numerze: ?code=A-001' OR '1'='1", '400 INVALID_CODE (zapytanie nie poszło do bazy)', function () use ($guest) {
    return is_error($guest->get('/api/order-status.php?code=' . urlencode("A-001' OR '1'='1")), 400, 'INVALID_CODE');
});

scenario('S2', 'bezpieczeństwo', 'SQL injection w id warstwy: "1 OR 1=1"', '400 INVALID_LAYERS', function () use ($guest, $validOrder) {
    $body = $validOrder('S');
    $body['layers']['1'] = '1 OR 1=1';
    return is_error($guest->post('/api/orders.php', $body), 400, 'INVALID_LAYERS');
});

scenario('S3', 'bezpieczeństwo', 'Nagłówki odpowiedzi API', 'JSON, nosniff, no-store', function () use ($guest) {
    [, , $headers] = $guest->get('/api/menu.php');
    $ok = stripos($headers, 'content-type: application/json') !== false
        && stripos($headers, 'x-content-type-options: nosniff') !== false
        && stripos($headers, 'cache-control: no-store') !== false;
    return [$ok, $ok ? 'wszystkie obecne' : 'brak nagłówka'];
});

scenario('S4', 'bezpieczeństwo', 'Nagłówek CSP i X-Frame-Options na stronie (Apache, .htaccess)', 'Content-Security-Policy, X-Frame-Options: DENY', function () use ($guest) {
    [, , $headers] = $guest->request('GET', '/index.html');
    if (stripos($headers, 'server: apache') === false) {
        return 'SKIP: serwer to nie Apache (np. php -S nie czyta .htaccess)';
    }
    $ok = stripos($headers, 'content-security-policy:') !== false && stripos($headers, 'x-frame-options: deny') !== false;
    return [$ok, $ok ? 'obecne' : 'brak'];
});

/* ------------------------------------------------------------------ */
/* KONTROLA DOSTĘPU I SESJE                                            */
/* ------------------------------------------------------------------ */

scenario('A1', 'dostęp', 'Gość: lista zamówień panelu', '401 NOT_LOGGED_IN', function () use ($guest) {
    return is_error($guest->get('/api/admin/orders.php'), 401, 'NOT_LOGGED_IN');
});

scenario('A2', 'dostęp', 'Gość: zmiana ceny', '401 NOT_LOGGED_IN', function () use ($guest) {
    return is_error($guest->post('/api/admin/prices.php', ['sizes' => ['S' => 1]]), 401, 'NOT_LOGGED_IN');
});

scenario('A3', 'hasła', 'Złe hasło i nieistniejący login', '401, ten sam komunikat dla obu', function () use ($guest) {
    $a = $guest->post('/api/admin/login.php', ['login' => 'pracownik', 'password' => 'zle-haslo'], false);
    $b = $guest->post('/api/admin/login.php', ['login' => 'nie_ma_takiego', 'password' => 'zle-haslo'], false);
    $ok = $a[0] === 401 && $b[0] === 401 && $a[1]['error']['message'] === $b[1]['error']['message'];
    return [$ok, described($a) . ' / ' . described($b)];
});

scenario('A4', 'sesje', 'Logowanie pracownika zmienia identyfikator sesji', 'nowy ACAI_PANEL po zalogowaniu, token CSRF 64 znaki', function () use ($employee) {
    $employee->get('/api/admin/me.php');               // sesja „przed logowaniem”
    $before = $employee->sessionId();
    [$status, $json] = $employee->login('pracownik', 'pracownik123');
    $after = $employee->sessionId();
    $ok = $status === 200 && $json['data']['user']['role'] === 'employee' && $before !== null && $after !== null
        && $before !== $after && strlen((string) $employee->csrf) === 64;
    return [$ok, "{$status}, sesja " . ($before !== $after ? 'zmieniona' : 'TA SAMA') . ', token ' . strlen((string) $employee->csrf) . ' znaków'];
});

scenario('A5', 'sesje', 'Ciasteczko sesji: HttpOnly i SameSite=Strict', 'oba atrybuty w Set-Cookie', function () use ($base) {
    $c = new Client($base);
    [, , $headers] = $c->request('POST', '/api/admin/login.php', ['login' => 'pracownik', 'password' => 'pracownik123']);
    preg_match('/^set-cookie: ACAI_PANEL=.*$/im', $headers, $m);
    $cookie = $m[0] ?? '';
    $ok = stripos($cookie, 'httponly') !== false && stripos($cookie, 'samesite=strict') !== false;
    return [$ok, $ok ? 'HttpOnly; SameSite=Strict' : ('brak: ' . $cookie)];
});

scenario('P1', 'pracownik', 'Lista zamówień w toku zawiera zamówienie z K2', '200, numer z K2 na liście', function () use ($employee, &$order) {
    $response = $employee->get('/api/admin/orders.php?status=active');
    $codes = array_column($response[1]['data']['orders'] ?? [], 'order_code');
    return [$response[0] === 200 && in_array($order['order_code'], $codes, true), described($response) . ', ' . count($codes) . ' zamówień'];
});

$orderId = null;
scenario('C1', 'CSRF', 'Zmiana statusu BEZ tokenu CSRF', '403 CSRF_INVALID', function () use ($employee, &$order, &$orderId) {
    $list = $employee->get('/api/admin/orders.php?status=all')[1]['data']['orders'];
    $orderId = array_values(array_filter($list, fn ($o) => $o['order_code'] === $order['order_code']))[0]['id'];
    return is_error($employee->post('/api/admin/order-status.php', ['order_id' => $orderId, 'status' => 'preparing'], false), 403, 'CSRF_INVALID');
});

scenario('C2', 'CSRF', 'Zmiana statusu z FAŁSZYWYM tokenem', '403 CSRF_INVALID', function () use ($employee, &$orderId) {
    $response = $employee->request('POST', '/api/admin/order-status.php', ['order_id' => $orderId, 'status' => 'preparing'],
        ['X-CSRF-Token: ' . str_repeat('a', 64)], false);
    return is_error($response, 403, 'CSRF_INVALID');
});

scenario('P2', 'pracownik', 'Zmiana statusu new → preparing (z tokenem)', '200, status preparing', function () use ($employee, &$orderId) {
    $response = $employee->post('/api/admin/order-status.php', ['order_id' => $orderId, 'status' => 'preparing']);
    return [$response[0] === 200 && $response[1]['data']['status'] === 'preparing', described($response)];
});

scenario('P3', 'pracownik', 'Niedozwolone przejście preparing → new', '409 STATUS_NOT_ALLOWED', function () use ($employee, &$orderId) {
    return is_error($employee->post('/api/admin/order-status.php', ['order_id' => $orderId, 'status' => 'new']), 409, 'STATUS_NOT_ALLOWED');
});

scenario('P4', 'pracownik', 'Klient widzi nowy status', '200, „W przygotowaniu”', function () use ($guest, &$order) {
    $response = $guest->get('/api/order-status.php?code=' . urlencode($order['order_code']));
    return [($response[1]['data']['status'] ?? '') === 'preparing', described($response) . ', ' . ($response[1]['data']['status_label'] ?? '')];
});

$availabilityTarget = $layerChoice[3][0];
scenario('P5', 'pracownik', "Oznaczenie produktu „{$availabilityTarget['name']}” jako niedostępny", '200; w menu "available": false; zamówienie z nim → 409', function () use ($employee, $guest, $availabilityTarget, $validOrder) {
    $response = $employee->post('/api/admin/product-availability.php', ['kind' => 'product', 'id' => $availabilityTarget['id'], 'available' => false]);
    $menu = $guest->get('/api/menu.php')[1]['data'];
    $inMenu = array_values(array_filter($menu['layers'][2]['products'], fn ($p) => $p['id'] === $availabilityTarget['id']))[0];
    $order = $guest->post('/api/orders.php', $validOrder('M'));
    $ok = $response[0] === 200 && $inMenu['available'] === false && ($order[1]['error']['code'] ?? '') === 'PRODUCT_UNAVAILABLE';
    $employee->post('/api/admin/product-availability.php', ['kind' => 'product', 'id' => $availabilityTarget['id'], 'available' => true]); // przywróć
    return [$ok, described($response) . ', menu: ' . var_export($inMenu['available'], true) . ', zamówienie: ' . described($order)];
});

scenario('A6', 'dostęp', 'Pracownik zmienia cenę rozmiaru', '403 FORBIDDEN, cena bez zmian', function () use ($employee, $guest, $sizes) {
    $response = $employee->post('/api/admin/prices.php', ['sizes' => ['S' => 0.5]]);
    $price = array_column($guest->get('/api/menu.php')[1]['data']['sizes'], 'price', 'code')['S'];
    $result = is_error($response, 403, 'FORBIDDEN');
    return [$result[0] && abs($price - $sizes['S']['price']) < 0.001, $result[1] . ", cena S = {$price} zł"];
});

scenario('A7', 'dostęp', 'Pracownik dodaje produkt', '403 FORBIDDEN', function () use ($employee) {
    return is_error($employee->post('/api/admin/products.php', ['category_id' => 3, 'name' => 'Test']), 403, 'FORBIDDEN');
});

scenario('A8', 'dostęp', 'Pracownik otwiera podsumowanie sprzedaży', '403 FORBIDDEN', function () use ($employee) {
    return is_error($employee->get('/api/admin/summary.php'), 403, 'FORBIDDEN');
});

/* ------------------------------------------------------------------ */
/* KIEROWNIK                                                           */
/* ------------------------------------------------------------------ */

scenario('M1', 'kierownik', 'Logowanie kierownika', '200, rola manager', function () use ($manager) {
    [$status, $json] = $manager->login('kierownik', 'kierownik123');
    return [$status === 200 && ($json['data']['user']['role'] ?? '') === 'manager', "{$status}, " . ($json['data']['user']['role'] ?? '?')];
});

scenario('M2', 'kierownik', 'Zmiana ceny M i białka; stare zamówienie zachowuje cenę', 'nowe ceny w menu i w nowym zamówieniu, K2 bez zmian', function () use ($manager, $guest, $employee, $validOrder, $sizes, $menu, &$order, &$createdOrders) {
    $oldM = $sizes['M']['price'];
    $oldProtein = $menu['extra_protein']['price'];
    $set = $manager->post('/api/admin/prices.php', ['sizes' => ['M' => $oldM + 1.5], 'protein' => $oldProtein + 1]);
    $newMenu = $guest->get('/api/menu.php')[1]['data'];
    $newOrder = $guest->post('/api/orders.php', $validOrder('M', true))[1]['data'];
    $createdOrders[] = $newOrder['order_code'];
    $all = $employee->get('/api/admin/orders.php?status=all')[1]['data']['orders'];
    $oldOrder = array_values(array_filter($all, fn ($o) => $o['order_code'] === $order['order_code']))[0];
    $manager->post('/api/admin/prices.php', ['sizes' => ['M' => $oldM], 'protein' => $oldProtein]); // przywróć
    $expectedNew = $oldM + 1.5 + $oldProtein + 1;
    $ok = $set[0] === 200 && abs(array_column($newMenu['sizes'], 'price', 'code')['M'] - ($oldM + 1.5)) < 0.001
        && abs($newOrder['total_price'] - $expectedNew) < 0.001 && abs($oldOrder['total_price'] - $order['total_price']) < 0.001;
    return [$ok, "nowe zamówienie {$newOrder['total_price']} zł, stare nadal {$oldOrder['total_price']} zł"];
});

scenario('M3', 'walidacja', 'Ujemna cena', '400 INVALID_PRICE', function () use ($manager) {
    return is_error($manager->post('/api/admin/prices.php', ['sizes' => ['S' => -5]]), 400, 'INVALID_PRICE');
});

$xssName = '<img src=x onerror=alert(1)> T' . random_int(1000, 9999);
$newProductId = null;
scenario('X1', 'bezpieczeństwo', 'XSS: produkt o nazwie z kodem HTML', '201; API zwraca nazwę jako zwykły tekst (frontend wstawia przez textContent)', function () use ($manager, $guest, $xssName, &$newProductId) {
    $response = $manager->post('/api/admin/products.php', [
        'category_id' => 5, 'name' => $xssName, 'color_hex' => '#123456', 'portion_g' => 5,
        'kcal' => 10, 'protein_g' => 0, 'carbs_g' => 1, 'fat_g' => 0, 'allergen_ids' => [7],
    ]);
    $newProductId = $response[1]['data']['id'] ?? null;
    $names = array_column($guest->get('/api/menu.php')[1]['data']['toppings']['products'], 'name');
    return [$response[0] === 201 && in_array($xssName, $names, true), described($response) . ', nazwa w menu bez zmian (tekst)'];
});

scenario('X2', 'bezpieczeństwo', 'Złośliwy adres zdjęcia: javascript:… i obca domena', '400 INVALID_PRODUCT', function () use ($manager) {
    $base = ['category_id' => 5, 'name' => 'Zdjęcie test', 'color_hex' => '#123456', 'portion_g' => 5, 'kcal' => 1, 'protein_g' => 0, 'carbs_g' => 0, 'fat_g' => 0];
    $a = $manager->post('/api/admin/products.php', $base + ['image_url' => 'javascript:alert(1)']);
    $b = $manager->post('/api/admin/products.php', $base + ['image_url' => 'https://evil.example/x.jpg")']);
    return [$a[0] === 400 && $b[0] === 400, described($a) . ' / ' . described($b)];
});

scenario('M4', 'kierownik', 'Ten sam produkt drugi raz w tej samej kategorii', '409 PRODUCT_EXISTS', function () use ($manager, $xssName) {
    return is_error($manager->post('/api/admin/products.php', [
        'category_id' => 5, 'name' => $xssName, 'color_hex' => '#123456', 'portion_g' => 5,
        'kcal' => 10, 'protein_g' => 0, 'carbs_g' => 1, 'fat_g' => 0,
    ]), 409, 'PRODUCT_EXISTS');
});

scenario('M5', 'kierownik', 'Ukrycie produktu (is_active = false) zamiast usuwania', '200; produkt znika z menu klienta', function () use ($manager, $guest, &$newProductId, $xssName) {
    $response = $manager->post('/api/admin/products.php', [
        'id' => $newProductId, 'category_id' => 5, 'name' => $xssName, 'color_hex' => '#123456', 'portion_g' => 5,
        'kcal' => 10, 'protein_g' => 0, 'carbs_g' => 1, 'fat_g' => 0, 'is_active' => false,
    ]);
    $ids = array_column($guest->get('/api/menu.php')[1]['data']['toppings']['products'], 'id');
    return [$response[0] === 200 && !in_array($newProductId, $ids, true), described($response) . ', w menu: ' . (in_array($newProductId, $ids, true) ? 'TAK' : 'nie')];
});

scenario('M6', 'kierownik', 'Podsumowanie sprzedaży za dziś', '200, liczba zamówień ≥ 1, przychód > 0', function () use ($manager) {
    $today = date('Y-m-d');
    $response = $manager->get("/api/admin/summary.php?from={$today}&to={$today}");
    $data = $response[1]['data'] ?? [];
    return [$response[0] === 200 && ($data['orders'] ?? 0) >= 1 && ($data['revenue'] ?? 0) > 0,
        described($response) . ", {$data['orders']} zamówień, {$data['revenue']} zł"];
});

scenario('M7', 'walidacja', 'Podsumowanie z błędną datą', '400 INVALID_DATE', function () use ($manager) {
    return is_error($manager->get('/api/admin/summary.php?from=2026-13-45'), 400, 'INVALID_DATE');
});

/* ------------------------------------------------------------------ */
/* WYLOGOWANIE I LIMIT PRÓB                                            */
/* ------------------------------------------------------------------ */

scenario('A9', 'sesje', 'Wylogowanie kierownika; stara sesja nie działa', '200, potem 401', function () use ($manager) {
    $out = $manager->post('/api/admin/logout.php');
    $after = $manager->post('/api/admin/prices.php', ['sizes' => ['S' => 1]]);
    return [$out[0] === 200 && $after[0] === 401, described($out) . ', potem ' . described($after)];
});

scenario('H1', 'hasła', '6. nieudana próba logowania w 15 minut', '429 TOO_MANY_ATTEMPTS (nawet z dobrym hasłem)', function () use ($base) {
    $attacker = new Client($base);
    $login = 'blokada_' . random_int(1000, 9999);
    for ($i = 0; $i < 5; $i++) {
        $attacker->post('/api/admin/login.php', ['login' => $login, 'password' => 'zgaduje' . $i], false);
    }
    return is_error($attacker->post('/api/admin/login.php', ['login' => $login, 'password' => 'kolejna'], false), 429, 'TOO_MANY_ATTEMPTS');
});

// Sprzątanie: anulujemy zamówienia testowe (udane logowanie czyści też licznik prób z tego IP).
$cleaner = new Client($base);
$cleaner->login('pracownik', 'pracownik123');
$all = $cleaner->get('/api/admin/orders.php?status=all')[1]['data']['orders'] ?? [];
foreach ($all as $o) {
    if (in_array($o['order_code'], $createdOrders, true) && in_array('cancelled', $o['next_statuses'], true)) {
        $cleaner->post('/api/admin/order-status.php', ['order_id' => $o['id'], 'status' => 'cancelled']);
    }
}
$cleaner->post('/api/admin/logout.php');

/* ------------------------------------------------------------------ */
/* Podsumowanie                                                        */
/* ------------------------------------------------------------------ */

$counts = array_count_values(array_column($results, 5));
printf("\nRazem: %d, OK: %d, BŁĄD: %d, SKIP: %d\n", count($results), $counts['OK'] ?? 0, $counts['BŁĄD'] ?? 0, $counts['SKIP'] ?? 0);

if ($markdownFile !== null) {
    $escape = fn (string $text) => str_replace(['|', "\n"], ['\\|', ' '], $text);
    $md = "# Wyniki testów automatycznych\n\n"
        . 'Uruchomione ' . date('Y-m-d H:i') . " na `{$base}` poleceniem `php tests/api_test.php`.\n\n"
        . sprintf("Razem: %d · OK: %d · BŁĄD: %d · SKIP: %d\n\n", count($results), $counts['OK'] ?? 0, $counts['BŁĄD'] ?? 0, $counts['SKIP'] ?? 0)
        . "| # | Obszar | Scenariusz | Oczekiwany wynik | Wynik | Stan |\n| --- | --- | --- | --- | --- | --- |\n";
    foreach ($results as $row) {
        $md .= '| ' . implode(' | ', array_map(fn ($cell) => $escape((string) $cell), $row)) . " |\n";
    }
    file_put_contents($markdownFile, $md);
    echo "Zapisano: {$markdownFile}\n";
}

exit(($counts['BŁĄD'] ?? 0) > 0 ? 1 : 0);
