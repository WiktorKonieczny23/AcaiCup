# Wyniki testów automatycznych

Uruchomione 2026-11-24 12:42 na Apache 2.4 + PHP 8.3 + MariaDB (document root = `public/`, jak na VPS) poleceniem `php tests/api_test.php`.

Razem: 43 · OK: 43 · BŁĄD: 0 · SKIP: 0

| # | Obszar | Scenariusz | Oczekiwany wynik | Wynik | Stan |
| --- | --- | --- | --- | --- | --- |
| K1 | klient | Menu: rozmiary, 4 kategorie warstw, toppingi, białko | 200, S/M/L = 2+1, 3+1, 4+2 | 200, S=2+1, M=3+1, L=4+2 | OK |
| K2 | klient | Zamówienie M z białkiem, płatność przy kasie | 201, numer A-123, cena = M + białko | 201 ok, H-214, 28 zł | OK |
| K3 | bezpieczeństwo | Manipulacja ceną: klient wysyła "total_price": 0.01 | serwer ignoruje cenę z przeglądarki | 201 ok, zapisana cena 19 zł | OK |
| K4 | klient | Status zamówienia po numerze | 200, status new („Przyjęte”) | 200 ok, Przyjęte | OK |
| K5 | walidacja | Rozmiar S z 3 warstwami | 400 INVALID_LAYERS | 400 INVALID_LAYERS | OK |
| K6 | walidacja | Owoc jako warstwa 1 (zła kategoria) | 400 PRODUCT_WRONG_CATEGORY | 400 PRODUCT_WRONG_CATEGORY | OK |
| K7 | walidacja | Rozmiar L z dwoma takimi samymi toppingami | 400 DUPLICATE_PRODUCT | 400 DUPLICATE_PRODUCT | OK |
| K8 | walidacja | Za mało toppingów w L (1 zamiast 2) | 400 INVALID_TOPPINGS | 400 INVALID_TOPPINGS | OK |
| K9 | walidacja | Nieistniejący rozmiar XL | 400 INVALID_SIZE | 400 INVALID_SIZE | OK |
| K10 | klient | Płatność online (Przelewy24 jeszcze nie działa) | 409 PAYMENT_UNAVAILABLE, nic nie zapisano | 409 PAYMENT_UNAVAILABLE | OK |
| K11 | walidacja | Uszkodzony JSON w treści żądania | 400 INVALID_JSON | 400 INVALID_JSON | OK |
| K12 | walidacja | GET zamiast POST na /api/orders.php | 405 METHOD_NOT_ALLOWED | 405 METHOD_NOT_ALLOWED | OK |
| K13 | klient | Status nieistniejącego zamówienia | 404 ORDER_NOT_FOUND | 404 ORDER_NOT_FOUND | OK |
| S1 | bezpieczeństwo | SQL injection w numerze: ?code=A-001' OR '1'='1 | 400 INVALID_CODE (zapytanie nie poszło do bazy) | 400 INVALID_CODE | OK |
| S2 | bezpieczeństwo | SQL injection w id warstwy: "1 OR 1=1" | 400 INVALID_LAYERS | 400 INVALID_LAYERS | OK |
| S3 | bezpieczeństwo | Nagłówki odpowiedzi API | JSON, nosniff, no-store | wszystkie obecne | OK |
| S4 | bezpieczeństwo | Nagłówek CSP i X-Frame-Options na stronie (Apache, .htaccess) | Content-Security-Policy, X-Frame-Options: DENY | obecne | OK |
| A1 | dostęp | Gość: lista zamówień panelu | 401 NOT_LOGGED_IN | 401 NOT_LOGGED_IN | OK |
| A2 | dostęp | Gość: zmiana ceny | 401 NOT_LOGGED_IN | 401 NOT_LOGGED_IN | OK |
| A3 | hasła | Złe hasło i nieistniejący login | 401, ten sam komunikat dla obu | 401 INVALID_CREDENTIALS / 401 INVALID_CREDENTIALS | OK |
| A4 | sesje | Logowanie pracownika zmienia identyfikator sesji | nowy ACAI_PANEL po zalogowaniu, token CSRF 64 znaki | 200, sesja zmieniona, token 64 znaków | OK |
| A5 | sesje | Ciasteczko sesji: HttpOnly i SameSite=Strict | oba atrybuty w Set-Cookie | HttpOnly; SameSite=Strict | OK |
| P1 | pracownik | Lista zamówień w toku zawiera zamówienie z K2 | 200, numer z K2 na liście | 200 ok, 2 zamówień | OK |
| C1 | CSRF | Zmiana statusu BEZ tokenu CSRF | 403 CSRF_INVALID | 403 CSRF_INVALID | OK |
| C2 | CSRF | Zmiana statusu z FAŁSZYWYM tokenem | 403 CSRF_INVALID | 403 CSRF_INVALID | OK |
| P2 | pracownik | Zmiana statusu new → preparing (z tokenem) | 200, status preparing | 200 ok | OK |
| P3 | pracownik | Niedozwolone przejście preparing → new | 409 STATUS_NOT_ALLOWED | 409 STATUS_NOT_ALLOWED | OK |
| P4 | pracownik | Klient widzi nowy status | 200, „W przygotowaniu” | 200 ok, W przygotowaniu | OK |
| P5 | pracownik | Oznaczenie produktu „Banan” jako niedostępny | 200; w menu "available": false; zamówienie z nim → 409 | 200 ok, menu: false, zamówienie: 409 PRODUCT_UNAVAILABLE | OK |
| A6 | dostęp | Pracownik zmienia cenę rozmiaru | 403 FORBIDDEN, cena bez zmian | 403 FORBIDDEN, cena S = 19 zł | OK |
| A7 | dostęp | Pracownik dodaje produkt | 403 FORBIDDEN | 403 FORBIDDEN | OK |
| A8 | dostęp | Pracownik otwiera podsumowanie sprzedaży | 403 FORBIDDEN | 403 FORBIDDEN | OK |
| M1 | kierownik | Logowanie kierownika | 200, rola manager | 200, manager | OK |
| M2 | kierownik | Zmiana ceny M i białka; stare zamówienie zachowuje cenę | nowe ceny w menu i w nowym zamówieniu, K2 bez zmian | nowe zamówienie 30.5 zł, stare nadal 28 zł | OK |
| M3 | walidacja | Ujemna cena | 400 INVALID_PRICE | 400 INVALID_PRICE | OK |
| X1 | bezpieczeństwo | XSS: produkt o nazwie z kodem HTML | 201; API zwraca nazwę jako zwykły tekst (frontend wstawia przez textContent) | 201 ok, nazwa w menu bez zmian (tekst) | OK |
| X2 | bezpieczeństwo | Złośliwy adres zdjęcia: javascript:… i obca domena | 400 INVALID_PRODUCT | 400 INVALID_PRODUCT / 400 INVALID_PRODUCT | OK |
| M4 | kierownik | Ten sam produkt drugi raz w tej samej kategorii | 409 PRODUCT_EXISTS | 409 PRODUCT_EXISTS | OK |
| M5 | kierownik | Ukrycie produktu (is_active = false) zamiast usuwania | 200; produkt znika z menu klienta | 200 ok, w menu: nie | OK |
| M6 | kierownik | Podsumowanie sprzedaży za dziś | 200, liczba zamówień ≥ 1, przychód > 0 | 200 ok, 3 zamówień, 77.5 zł | OK |
| M7 | walidacja | Podsumowanie z błędną datą | 400 INVALID_DATE | 400 INVALID_DATE | OK |
| A9 | sesje | Wylogowanie kierownika; stara sesja nie działa | 200, potem 401 | 200 ok, potem 401 NOT_LOGGED_IN | OK |
| H1 | hasła | 6. nieudana próba logowania w 15 minut | 429 TOO_MANY_ATTEMPTS (nawet z dobrym hasłem) | 429 TOO_MANY_ATTEMPTS | OK |
