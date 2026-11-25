# Acai Cup – konfigurator kubków acai

Aplikacja internetowa, w której klient w kilku krokach komponuje swój acai cup:
wybiera rozmiar, składnik każdej warstwy, toppingi i opcjonalne białko. Przez cały
czas widzi wizualizację kubka, cenę, kalorie, makroskładniki i alergeny.
Zamawia anonimowo i dostaje numer do odbioru. Personel (pracownik i kierownik)
obsługuje zamówienia, produkty i ceny w panelu.

Projekt zaliczeniowy z przedmiotu *Programowanie serwisów internetowych*,
zespół 3-osobowy (Frontend / Backend / Security-QA-DevOps). Oddanie: 20.01.2027.

**Adres na serwerze:** _uzupełnić po wdrożeniu_

## Stan prac

| Etap | Zakres | Stan |
| --- | --- | --- |
| E1 Fundamenty | baza, `GET /api/menu.php`, konfigurator z wizualizacją, środowisko, dokumenty DevOps | gotowe lokalnie; brak wdrożenia na serwer |
| E2 Konfigurator i zamówienia | `src/pricing.php`, `POST /api/orders.php`, `GET /api/order-status.php`, potwierdzenie z numerem, strona „płatność online wkrótce” | gotowe lokalnie |
| E3 Panel personelu | logowanie, role, CSRF, zamówienia, produkty, ceny, podsumowanie, `src/payments/` | gotowe lokalnie |
| E4 Domknięcie | obsługa błędów w jednym miejscu, log poza `public/`, backup bazy | gotowe lokalnie |
| E5 Testy | 43 testy API, testy w przeglądarce, raport bezpieczeństwa | gotowe; zostaje ręczny test na telefonie |
| E6 Wdrożenie | instrukcja VPS, README, AI_USAGE | instrukcja gotowa; wdrożenie robi zespół |

## Technologie

- **Frontend:** HTML, CSS, Bootstrap 5.3, czysty JavaScript (moduły ES), wizualizacja w SVG ze zdjęciami składników
- **Backend:** czysty PHP 8 (bez frameworka), PDO z prepared statements
- **Baza:** MySQL 8 (lokalnie działa też MariaDB 10.4+)
- **Serwer:** Apache + HTTPS (Let's Encrypt)

## Struktura

```
ACAI/
├── public/                     # jedyny katalog widoczny z internetu (document root)
│   ├── index.html              # konfigurator dla klienta
│   ├── platnosc-wkrotce.html   # „płatność online wkrótce”
│   ├── panel/index.html        # panel personelu (logowanie + zakładki)
│   ├── .htaccess               # nagłówki bezpieczeństwa, CSP
│   ├── api/                    # menu.php, orders.php, order-status.php
│   │   └── admin/              # endpointy panelu: login, logout, me, orders, order-status,
│   │                           #   product-availability, products, prices, summary
│   └── assets/
│       ├── css/style.css       # styl Wise
│       ├── img/products/       # zdjęcia składników
│       └── js/                 # app.js, cup.js, panel.js, api.js, dom.js, mock-menu.json
├── src/                        # wspólny kod PHP (niewidoczny z przeglądarki)
│   ├── bootstrap.php           # początek każdego endpointu
│   ├── db.php, response.php, request.php, errors.php
│   ├── pricing.php             # walidacja kubka i cena po stronie serwera
│   ├── orders.php              # zapis zamówienia, zmiana statusu
│   ├── auth.php                # logowanie, role, CSRF
│   ├── products.php            # walidacja produktu
│   └── payments/               # wspólny interfejs płatności (kasa, później Przelewy24)
├── config/                     # config.example.php (szablon), config.php (lokalny, poza Git)
├── database/                   # schema.sql, seed.sql, migrations/001–003
├── scripts/                    # backup.sh, restore-test.sh, acai-backup.cron
├── tests/api_test.php          # automatyczne testy całej ścieżki
├── logs/                       # log aplikacji (tworzy się sam, poza Git)
├── docs/                       # baza, styl, wdrożenie, bezpieczeństwo, testy, raport
├── .htaccess                   # XAMPP: blokuje wszystko poza public/
├── docker/ + docker-compose.yml
├── README.md
└── AI_USAGE.md
```

## Uruchomienie lokalne

### XAMPP (tak pracujemy)

1. Folder projektu leży w `C:\xampp2\htdocs\ACAI`. W panelu XAMPP włącz **Apache** i **MySQL**.
2. **Nowa baza:** w phpMyAdmin utwórz bazę `acai_cup` (kodowanie `utf8mb4_unicode_ci`)
   i zaimportuj kolejno `database/schema.sql` oraz `database/seed.sql`.
   **Baza już istnieje (z etapu E1):** uruchom tylko brakujące migracje z `database/migrations/`
   po kolei (001, 002, 003, 004) – w phpMyAdmin: baza `acai_cup` → zakładka SQL / Import.
3. `config/config.php` z użytkownikiem `root` i pustym hasłem (szablon: `config/config.example.php`).
4. Otwórz:
   - konfigurator: <http://localhost/ACAI/public/>
   - panel personelu: <http://localhost/ACAI/public/panel/>

### Docker

```bash
docker compose up -d
```

Konfigurator: <http://localhost:8080>, phpMyAdmin: <http://localhost:8081> (login `acai_app`, hasło `acai_local`).
Baza importuje się sama przy pierwszym starcie. Reset: `docker compose down -v && docker compose up -d`.

### Bez bazy

Jeśli API nie odpowiada, konfigurator sam przełącza się na `public/assets/js/mock-menu.json`
i pokazuje w nagłówku znacznik **„dane testowe”**. Zamówienia wtedy nie da się złożyć.

## Konta testowe

| Login | Hasło | Rola | Co może |
| --- | --- | --- | --- |
| `pracownik` | `pracownik123` | pracownik | zamówienia i zmiana statusów, dostępność produktów |
| `kierownik` | `kierownik123` | kierownik | to co pracownik + ceny, dodawanie i edycja produktów, podsumowanie sprzedaży |

**Na serwerze hasła trzeba zmienić od razu po wdrożeniu** ([docs/wdrozenie.md](docs/wdrozenie.md), sekcja 3).
Po 5 nieudanych próbach logowania konto jest blokowane na 15 minut.

## API

Wszystkie odpowiedzi mają format `{"ok": true, "data": …}` albo
`{"ok": false, "error": {"code": "KOD", "message": "komunikat dla użytkownika"}}`.
Dane do POST wysyłamy jako JSON. Endpointy panelu, które zmieniają dane, wymagają
nagłówka `X-CSRF-Token` z tokenem z `login.php` / `me.php`.

| Metoda | Endpoint | Dostęp | Co robi |
| --- | --- | --- | --- |
| GET | `/api/menu.php` | publiczny | rozmiary z cenami, kategorie warstw z produktami, toppingi, białko; niedostępne z `"available": false` |
| POST | `/api/orders.php` | publiczny | waliduje kubek, liczy cenę na serwerze, zapisuje zamówienie; zwraca `order_code` (201). Online → 409 `PAYMENT_UNAVAILABLE` |
| GET | `/api/order-status.php?code=K-042` | publiczny | status zamówienia i płatności |
| POST | `/api/admin/login.php` | – | logowanie; zwraca rolę i token CSRF |
| POST | `/api/admin/logout.php` | personel + CSRF | wylogowanie |
| GET | `/api/admin/me.php` | personel | kto jest zalogowany (401, gdy nikt) |
| GET | `/api/admin/orders.php?status=active` | pracownik, kierownik | lista zamówień z filtrem (`active`, `new`, `preparing`, `ready`, `collected`, `cancelled`, `all`) |
| POST | `/api/admin/order-status.php` | pracownik, kierownik + CSRF | zmiana statusu `{"order_id", "status"}`, zapis w `order_status_log` |
| POST | `/api/admin/product-availability.php` | pracownik, kierownik + CSRF | `{"kind": "product"\|"extra", "id", "available"}` |
| GET | `/api/admin/products.php` | pracownik, kierownik | wszystkie produkty (także ukryte), kategorie, alergeny |
| POST | `/api/admin/products.php` | kierownik + CSRF | dodanie (bez `id`) lub edycja (z `id`) produktu |
| GET/POST | `/api/admin/prices.php` | kierownik (+ CSRF przy POST) | ceny rozmiarów i dopłata za białko |
| GET | `/api/admin/summary.php?from=…&to=…` | kierownik | liczba zamówień, przychód, rozmiary, top składniki, dni |

Przykład zamówienia:

```json
{"size": "M", "layers": {"1": 3, "2": 11, "3": 17}, "toppings": [24], "extra_protein": true, "payment_method": "cash"}
```

Statusy zamówienia: `new` → `preparing` → `ready` → `collected`; z trzech pierwszych można przejść na `cancelled`.

## Testy

```bash
php tests/api_test.php http://localhost/ACAI/public
```

43 scenariusze (klient, pracownik, kierownik, ataki). Wyniki i opis: [docs/testy.md](docs/testy.md).

## Kopie zapasowe

`scripts/backup.sh` (mysqldump + gzip, 14 dni), uruchamiany codziennie przez cron (`scripts/acai-backup.cron`),
oraz `scripts/restore-test.sh`, który sprawdza, że kopia się przywraca. Instrukcja: [docs/wdrozenie.md](docs/wdrozenie.md), sekcja 8.

## Dane w bazie

- Rozmiary: S (2 warstwy + 1 topping), M (3 + 1), L (4 + 2). Ceny 19 / 24 / 29 zł są **przykładowe** (zmienia je kierownik).
- Kategorie warstw od dna: baza acai → zboża i granola → owoce → krem.
- Extra białko: 20 g białka serwatkowego za 4 zł.
- Dodatkowe toppingi: w każdym rozmiarze można dobrać do 3 toppingów ponad te w cenie, po 5 zł za każdy
  (cenę zmienia kierownik, `extras.code = 'extra_topping'`).
- Podwójna porcja: w L jeden topping można wybrać dwa razy (np. `"toppings": [24, 24]`).
- Kiwi jest celowo oznaczone jako niedostępne, żeby było widać wyszarzanie.
- Produktów nie usuwamy: `is_available` (chwilowy brak) i `is_active` (ukrycie na stałe).
- **Wartości odżywcze są przybliżone** – przed oddaniem zastąpcie je danymi z etykiet.

## Zdjęcia składników

Warstwy kubka, toppingi i karty wyboru pokazują zdjęcia składników z góry (własne pliki w
`public/assets/img/products/` oraz Unsplash i Pexels). Adresy są w kolumnie `image_url`, autorzy w stopce strony.
Bez internetu zdjęcia z Unsplash i Pexels się nie wczytają, a konfigurator pokaże kolory.
Galeria wyboru: [docs/zdjecia-produktow.html](docs/zdjecia-produktow.html).

## Dokumentacja

- [Baza danych – ERD](docs/baza.md)
- [Przewodnik stylu](docs/styl.md)
- [Wdrożenie na serwer](docs/wdrozenie.md)
- [Bezpieczeństwo – zagrożenia i checklista](docs/bezpieczenstwo.md)
- [Raport bezpieczeństwa](docs/raport-bezpieczenstwa.md)
- [Scenariusze testowe](docs/testy.md) i [wyniki testów automatycznych](docs/testy-wyniki.md)
- [Użycie narzędzi AI](AI_USAGE.md)
