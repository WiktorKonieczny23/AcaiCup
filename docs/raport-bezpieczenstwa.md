# Raport bezpieczeństwa (zadanie 48 – DevOps)

Stan na 24.11.2026, po etapach E2–E4. Raport opiera się na analizie zagrożeń z
[bezpieczenstwo.md](bezpieczenstwo.md) i na testach automatycznych z [testy.md](testy.md).

**Wynik:** wszystkie 14 zagrożeń z analizy ma zabezpieczenie w kodzie, a 43 testy API przechodzą.
Podczas prac znaleźliśmy i poprawiliśmy 3 realne problemy (sekcja 1). Sekcja 2 pokazuje
zabezpieczenia w formie „przed / po”: **przed** to typowy błędny kod, który łatwo napisać,
**po** to fragment z naszej aplikacji.

## 1. Znalezione problemy i poprawki

| # | Problem | Gdzie | Ryzyko | Poprawka | Test |
| --- | --- | --- | --- | --- | --- |
| 1 | W XAMPP cały projekt leży w `htdocs`, więc dało się pobrać `logs/app.log` i `database/seed.sql` (hashe haseł) | XAMPP | wyciek logów i hashy | `.htaccess` w katalogu projektu blokuje wszystko, `public/.htaccess` wpuszcza tylko `public/` | ręcznie: 403 dla 6 plików |
| 2 | `?code[]=x` (tablica zamiast tekstu) wywoływał ostrzeżenie PHP i błąd 500 | `api/order-status.php`, `api/admin/orders.php` | ujawnienie, że coś się psuje; śmieci w logu | sprawdzenie `is_string()` przed użyciem parametru | ręcznie: 400 `INVALID_CODE` |
| 3 | Brak limitu prób logowania – hasło dało się zgadywać bez końca | `api/admin/login.php` | przejęcie konta kierownika | tabela `login_attempts`: 5 prób na login i 20 na IP w 15 minut | H1 |

## 2. Zabezpieczenia „przed / po”

### SQL injection (Z1)

Złośliwy tekst w parametrze zmienia treść zapytania. Przed: `A-001' OR '1'='1` zwraca wszystkie zamówienia.

**Przed (błąd):**
```php
$code = $_GET['code'];
$result = $pdo->query("SELECT * FROM orders WHERE order_code = '$code'");
```

**Po** (`public/api/order-status.php`):
```php
if (!preg_match('/^[A-Z]-[0-9]{3}$/', $code)) {
    throw new AppError('INVALID_CODE', 'Podaj numer zamówienia w formacie A-123.');
}
$stmt = db()->prepare('SELECT order_code, status, … FROM orders WHERE order_code = ?');
$stmt->execute([$code]);
```

Dane idą do bazy osobno od treści zapytania (prepared statement), a `PDO::ATTR_EMULATE_PREPARES = false`
w `src/db.php` wymusza prawdziwe przygotowanie po stronie MySQL. Listy (`IN (?, ?, ?)`) też budujemy
z samych znaków zapytania. Testy: S1, S2.

### XSS (Z2)

Kod JavaScript zapisany w danych wykonuje się w przeglądarce innej osoby.

**Przed (błąd):**
```js
row.innerHTML = `<td>${product.name}</td>`;   // nazwa "<img src=x onerror=…>" uruchomi skrypt
```

**Po** (`public/assets/js/dom.js`, używane w całym frontendzie):
```js
node.append(child instanceof Node ? child : document.createTextNode(String(child)));
```

Nigdzie w projekcie nie ma `innerHTML`. Dodatkowo nagłówek CSP w `public/.htaccess` pozwala na skrypty
tylko z własnej domeny i CDN Bootstrapa, więc nawet wstrzyknięty `<script>` by się nie uruchomił.
Testy: X1 (API zwraca nazwę jako tekst), B13 (w przeglądarce nie powstaje `<img>`), S4 (nagłówek CSP).

### Manipulacja ceną (Z3)

**Przed (błąd):**
```php
$total = $input['total_price'];    // cena z przeglądarki – klient wpisze 0,01 zł
```

**Po** (`src/pricing.php`):
```php
$size = load_size($pdo, $input['size'] ?? null);              // cena rozmiaru z tabeli sizes
$extra = $wantsProtein ? load_protein($pdo) : null;           // dopłata z tabeli extras
$sizeGrosze    = (int) round((float) $size['price'] * 100);
$proteinGrosze = $extra ? (int) round((float) $extra['price'] * 100) : 0;
```

Klient wysyła tylko kod rozmiaru i id produktów. Pola z ceną są ignorowane. Test: K3.

### Kontrola dostępu (Z5)

**Przed (błąd):** przycisk „Ceny” ukryty w panelu, ale endpoint nie sprawdza roli – pracownik wyśle żądanie z konsoli.
```php
// api/admin/prices.php
$pdo->prepare('UPDATE sizes SET price = ? WHERE code = ?')->execute([$price, $code]);
```

**Po** (`public/api/admin/prices.php` i `src/auth.php`):
```php
require_role('manager');          // 401 dla gościa, 403 dla pracownika
…
require_csrf();
```

Każdy plik w `public/api/admin/` (oprócz `login.php`) zaczyna się od `require_role(...)`.
Testy: A1, A2, A6, A7, A8.

### CSRF (Z6)

Obca strona wysyła formularz do naszego panelu, a przeglądarka dołącza ciasteczko zalogowanego kierownika.

**Przed (błąd):** endpoint zmienia dane, gdy tylko jest sesja.

**Po** (`src/auth.php`):
```php
function require_csrf(): void
{
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($sent) || $sent === '' || !hash_equals(csrf_token(), $sent)) {
        throw new AppError('CSRF_INVALID', '…', 403);
    }
}
```

Token to 64 losowe znaki z `random_bytes(32)`, zapisany w sesji i wysyłany przez panel w nagłówku
`X-CSRF-Token`. Obca strona go nie zna. Drugą warstwą jest ciasteczko `SameSite=Strict`. Testy: C1, C2.

### Sesje (Z8)

**Przed (błąd):**
```php
session_start();
$_SESSION['user_id'] = $user['id'];   // ten sam identyfikator sesji co przed logowaniem
```

**Po** (`src/auth.php`):
```php
ini_set('session.use_strict_mode', '1');
session_set_cookie_params(['httponly' => true, 'secure' => $https, 'samesite' => 'Strict', …]);
…
session_regenerate_id(true);          // po zalogowaniu NOWY identyfikator, stary przestaje działać
```

Do tego wylogowanie po 30 minutach bezczynności i usuwanie ciasteczka przy wylogowaniu. Testy: A4, A5, A9.

### Hasła (Z7)

**Przed (błąd):**
```php
if (md5($password) === $user['password']) { … }   // md5 łamie się w sekundy
```

**Po** (`src/auth.php`):
```php
$valid = $user && $user['is_active'] && password_verify($password, $user['password_hash']);
if (!$valid) {
    $pdo->prepare('INSERT INTO login_attempts (login, ip) VALUES (?, ?)')->execute([…]);
    throw new AppError('INVALID_CREDENTIALS', 'Nieprawidłowy login lub hasło.', 401);
}
```

Hasła są w bazie jako bcrypt z `password_hash()`. Zły login i złe hasło dają ten sam komunikat,
więc nie da się sprawdzić, które konta istnieją. Po 5 nieudanych próbach logowanie jest blokowane na 15 minut.
**Po wdrożeniu trzeba zmienić hasła kont z `seed.sql`** (instrukcja w [wdrozenie.md](wdrozenie.md)). Testy: A3, H1.

### Ujawnianie szczegółów błędów (Z10)

**Przed (błąd):**
```php
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);   // nazwa bazy, użytkownika, treść SQL
}
```

**Po** (`src/errors.php`): jeden wspólny „łapacz” wyjątków dla całego API.
```php
app_log(sprintf('%s: %s in %s:%d | %s', get_class($e), $e->getMessage(), $e->getFile(), $e->getLine(), $where));
json_error('SERVER_ERROR', 'Coś poszło nie tak. Spróbuj ponownie za chwilę.', 500);
```

Szczegóły trafiają do `logs/app.log` poza `public/`. Sprawdzone ręcznie: przy złym haśle do bazy
klient dostaje `503 DB_UNAVAILABLE` z ogólnym komunikatem, a dokładny błąd MySQL jest tylko w logu.

### Złośliwy adres zdjęcia (Z14)

**Po** (`src/products.php`): adres zdjęcia musi pasować do jednego z dwóch wzorców.
```php
preg_match('#^assets/img/products/[a-z0-9_-]+\.(jpe?g|png|webp)$#i', $url)
preg_match('#^https://images\.(unsplash|pexels)\.com/[A-Za-z0-9/_.-]+$#', $url)
```

Wzorce nie przepuszczają cudzysłowów, nawiasów ani `javascript:`, więc adresu nie da się użyć,
żeby „wyjść” z `url("…")` w CSS. Test: X2.

## 3. Stan wszystkich zagrożeń z analizy

| # | Zagrożenie | Zabezpieczenie | Test |
| --- | --- | --- | --- |
| Z1 | SQL injection | prepared statements, walidacja formatu | S1, S2 |
| Z2 | XSS | `textContent` / `createTextNode`, CSP | X1, B13, S4 |
| Z3 | Manipulacja ceną | `src/pricing.php` liczy cenę z bazy | K3 |
| Z4 | Nieprawidłowy skład | `src/pricing.php` + `UNIQUE` w `order_items` | K5–K9 |
| Z5 | Kontrola dostępu | `require_role()` w każdym endpoincie panelu | A1, A2, A6–A8 |
| Z6 | CSRF | token w sesji + nagłówek, `SameSite=Strict` | C1, C2 |
| Z7 | Słabe hasła, zgadywanie | `password_hash` / `password_verify`, limit prób | A3, H1 |
| Z8 | Przejęcie sesji | `session_regenerate_id(true)`, HttpOnly, Secure (HTTPS), timeout | A4, A5, A9 |
| Z9 | Wyciek konfiguracji | `config.php` w `.gitignore`, document root = `public/`, `.htaccess` w XAMPP | ręcznie (403) |
| Z10 | Szczegóły błędów | `src/errors.php`, log poza `public/` | ręcznie (503) |
| Z11 | Clickjacking, sniffing | `X-Frame-Options: DENY`, `frame-ancestors`, `nosniff` | S3, S4 |
| Z12 | Utrata danych | `scripts/backup.sh` + cron, `scripts/restore-test.sh` | przywrócenie kopii: 6 tabel zgodnych |
| Z13 | Nadmierne uprawnienia do bazy | użytkownik `acai_app` tylko SELECT/INSERT/UPDATE/DELETE | instrukcja w wdrozenie.md |
| Z14 | Złośliwy adres zdjęcia | walidacja w `src/products.php`, CSP `img-src` | X2 |

## 4. Ryzyka, które zostają

- **Hasła testowe z `seed.sql` są w repozytorium.** Na serwerze trzeba je zmienić od razu po wdrożeniu.
- **Numer zamówienia ma tylko 22 × 1000 kombinacji.** Ktoś może zgadywać numery i podglądać statusy,
  ale endpoint nie zwraca składu ani danych osobowych (klient jest anonimowy).
- **Logowanie nie ma własnego tokenu CSRF.** Chroni je `SameSite=Strict` i to, że po zalogowaniu wszystkie
  zmiany wymagają tokenu.
- **Bootstrap i fonty z CDN.** Gdyby CDN został przejęty, mógłby podmienić styl. Można dodać atrybut `integrity`
  albo trzymać pliki lokalnie.
