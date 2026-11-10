# Acai Cup – konfigurator kubków acai

Aplikacja internetowa, w której klient w kilku krokach komponuje swój acai cup:
wybiera rozmiar, składnik każdej warstwy, toppingi i opcjonalne białko. Przez cały
czas widzi wizualizację kubka, cenę, kalorie, makroskładniki i alergeny.
Personel (pracownik i kierownik) obsługuje zamówienia w panelu.

Projekt zaliczeniowy z przedmiotu *Programowanie serwisów internetowych*,
zespół 3-osobowy (Frontend / Backend / Security-QA-DevOps). Oddanie: 20.01.2027.

**Adres na serwerze:** _uzupełnić po wdrożeniu (zadanie 22)_

## Stan prac

| Etap | Zakres | Stan |
| --- | --- | --- |
| E1 Fundamenty | baza, `GET /api/menu.php`, konfigurator z wizualizacją, środowisko, dokumenty DevOps | gotowe lokalnie; brak wdrożenia na serwer i makiet panelu |
| E2 Konfigurator i zamówienia | `POST /api/orders.php`, numer zamówienia, strona „płatność online wkrótce” | – |
| E3 Panel personelu | logowanie, zamówienia, produkty, ceny, podsumowanie | – |
| E4–E6 | domknięcie, testy, raport bezpieczeństwa, wdrożenie końcowe | – |

## Technologie

- **Frontend:** HTML, CSS, Bootstrap 5.3, czysty JavaScript (moduły ES), wizualizacja w SVG
- **Backend:** czysty PHP 8.3 (bez frameworka), PDO
- **Baza:** MySQL 8 (lokalnie działa też MariaDB 10.6+)
- **Serwer:** Apache + HTTPS (Let's Encrypt)

## Struktura

```
acai-cup/
├── public/                 # jedyny katalog widoczny z internetu
│   ├── index.html          # konfigurator
│   ├── .htaccess           # nagłówki bezpieczeństwa
│   ├── api/menu.php        # GET – menu dla konfiguratora
│   └── assets/
│       ├── css/style.css
│       └── js/             # app.js (logika), cup.js (kubek SVG), mock-menu.json
├── src/                    # wspólny kod PHP (db.php, response.php)
├── config/                 # config.example.php (szablon), config.php (lokalny, poza Git)
├── database/               # schema.sql, seed.sql
├── docker/ + docker-compose.yml
├── docs/                   # baza (ERD), styl, wdrożenie, bezpieczeństwo
├── README.md
└── AI_USAGE.md
```

## Uruchomienie lokalne

### Opcja A – Docker (zalecana, wszyscy mają to samo środowisko)

```bash
docker compose up -d
```

- konfigurator: <http://localhost:8080>
- API: <http://localhost:8080/api/menu.php>
- phpMyAdmin: <http://localhost:8081> (login `acai_app`, hasło `acai_local`)

Baza importuje się sama z `database/` przy pierwszym starcie.
Reset bazy: `docker compose down -v && docker compose up -d`.

### Opcja B – XAMPP

1. Skopiuj folder projektu do `C:\xampp\htdocs\acai-cup`.
2. W phpMyAdmin utwórz bazę `acai_cup` (kodowanie `utf8mb4_unicode_ci`) i zaimportuj
   kolejno `database/schema.sql` oraz `database/seed.sql`.
3. Skopiuj `config/config.example.php` jako `config/config.php` i wpisz
   użytkownika `root` z pustym hasłem (domyślne w XAMPP).
4. Otwórz <http://localhost/acai-cup/public/>.

### Opcja C – sam PHP (szybki podgląd)

```bash
DB_USER=root DB_PASS=haslo php -S localhost:8080 -t public
```

### Bez bazy

Jeśli API nie odpowiada, konfigurator sam przełącza się na plik
`public/assets/js/mock-menu.json` i pokazuje w nagłówku znacznik **„dane testowe”**.
Dzięki temu frontend da się rozwijać, zanim backend będzie gotowy.
Mock ma dokładnie ten sam kształt co odpowiedź `GET /api/menu.php`.

## Konta testowe

| Login | Hasło | Rola |
| --- | --- | --- |
| `pracownik` | `pracownik123` | pracownik |
| `kierownik` | `kierownik123` | kierownik |

Panel powstanie w etapie E3. **Na serwerze hasła trzeba zmienić** (`docs/wdrozenie.md`).

## API

Wszystkie odpowiedzi mają format `{"ok": true, "data": …}` albo
`{"ok": false, "error": {"code": …, "message": …}}`.

| Metoda | Endpoint | Opis | Stan |
| --- | --- | --- | --- |
| GET | `/api/menu.php` | rozmiary, kategorie warstw z produktami, toppingi, białko; niedostępne produkty z `"available": false` | gotowe |
| POST | `/api/orders.php` | złożenie zamówienia | E2 |

Pełny kontrakt API jest w dokumencie z planem projektu.

## Dane w bazie

- Rozmiary: S (2 warstwy + 1 topping), M (3 + 1), L (4 + 2). Ceny 19 / 24 / 29 zł są **przykładowe**.
- Kategorie warstw od dna: baza acai → zboża i granola → owoce → krem.
- Extra białko: 20 g białka serwatkowego za 4 zł.
- Kiwi jest celowo oznaczone jako niedostępne, żeby było widać wyszarzanie.
- **Wartości odżywcze są przybliżone** – przed oddaniem zastąpcie je danymi z etykiet.

## Zdjęcia składników

Warstwy kubka, toppingi i karty wyboru pokazują zdjęcia składników z góry (Unsplash, Pexels –
darmowe licencje). Adresy są w kolumnie `image_url`, autorzy w stopce strony. Serwisy same
przycinają zdjęcia do potrzebnego rozmiaru (parametry w adresie, funkcja `photoUrl()` w `cup.js`).

- Podgląd wszystkich zdjęć i uwagi: [docs/zdjecia-produktow.html](docs/zdjecia-produktow.html)
- Bez internetu zdjęcia się nie wczytają, a konfigurator pokaże kolory (działa dalej normalnie).
- Istniejąca baza: uruchom `database/migrations/001_zdjecia_skladnikow.sql`.

## Dokumentacja

- [Baza danych – ERD](docs/baza.md)
- [Przewodnik stylu](docs/styl.md)
- [Wdrożenie na serwer](docs/wdrozenie.md)
- [Bezpieczeństwo – zagrożenia i checklista](docs/bezpieczenstwo.md)
- [Użycie narzędzi AI](AI_USAGE.md)
