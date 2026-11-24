# Analiza zagrożeń i checklista bezpieczeństwa (zadanie 21 – DevOps)

Dokument żyje razem z projektem: po każdym etapie odhaczamy punkty
i dopisujemy wyniki do kolumny „Stan”. Stan na 24.11.2026 (po E2–E4).
Raport z fragmentami kodu „przed / po”: [raport-bezpieczenstwa.md](raport-bezpieczenstwa.md).

## Co chronimy

| Zasób | Dlaczego ważny |
| --- | --- |
| Ceny i dostępność produktów | zmiana przez niepowołaną osobę = straty lub chaos przy ladzie |
| Konta pracownika i kierownika | dają dostęp do panelu |
| Zamówienia | personel musi im ufać przy wydawaniu |
| Dane dostępowe do bazy | pełny dostęp do wszystkiego powyżej |
| Dostępność serwisu | klient nie złoży zamówienia |

Klient zamawia anonimowo, więc nie przechowujemy danych osobowych klientów.

## Zagrożenia i zabezpieczenia

| # | Zagrożenie | Gdzie | Zabezpieczenie | Etap | Stan |
| --- | --- | --- | --- | --- | --- |
| Z1 | **SQL Injection** – złośliwy tekst w parametrze zmienia zapytanie | każdy endpoint | tylko `prepare()` + `execute()`, `ATTR_EMULATE_PREPARES = false`; zakaz sklejania SQL z danymi | E1→ | gotowe – testy S1, S2 |
| Z2 | **XSS** – kod JS w nazwie produktu wykonuje się u innych | konfigurator, panel | dane w DOM wyłącznie przez `textContent`; w PHP `htmlspecialchars()`; nagłówek CSP w `.htaccess` | E1→ | gotowe – testy X1, B13, S4 |
| Z3 | **Manipulacja ceną** – klient zmienia cenę w żądaniu | `POST /api/orders.php` | serwer ignoruje cenę z przeglądarki i liczy ją z bazy | E2 | gotowe – `src/pricing.php`, test K3 |
| Z4 | **Nieprawidłowy skład** – 5 warstw, powtórzony topping, produkt niedostępny | `POST /api/orders.php` | walidacja w `src/pricing.php`; w bazie `UNIQUE(order_id, product_id)` i `UNIQUE(order_id, slot)` | E2 | gotowe – testy K5–K9 |
| Z5 | **Złamana kontrola dostępu** – pracownik zmienia ceny, gość wchodzi do panelu | `api/admin/*` | `require_role()` na początku KAŻDEGO endpointu panelu; brak zaufania do ukrytych przycisków w UI | E3 | gotowe – testy A1, A2, A6–A8 |
| Z6 | **CSRF** – obca strona wysyła żądanie w imieniu zalogowanego kierownika | `api/admin/*` | token CSRF w sesji + nagłówek `X-CSRF-Token`; ciasteczko `SameSite=Strict` | E3 | gotowe – testy C1, C2 |
| Z7 | **Słabe hasła / zgadywanie** | logowanie | `password_hash()` / `password_verify()`; limit 5 nieudanych prób na 15 min; zmiana haseł z seeda po wdrożeniu | E3 | gotowe – limit 5 prób/login, 20/IP; testy A3, H1 |
| Z8 | **Przejęcie sesji** | panel | `session_regenerate_id(true)` po zalogowaniu; ciasteczka `HttpOnly`, `Secure`; tylko HTTPS | E3 | gotowe – testy A4, A5, A9 |
| Z9 | **Wyciek konfiguracji** – hasło do bazy w repozytorium lub dostępne z przeglądarki | repo, serwer | `config.php` w `.gitignore`; document root = `public/`; repo prywatne | E1 | gotowe – także `.htaccess` w katalogu projektu (XAMPP) |
| Z10 | **Ujawnienie szczegółów błędów** | API | `display_errors = Off`; użytkownik dostaje ogólny komunikat, szczegóły w logu poza `public/` | E1→ | gotowe – `src/errors.php`, `logs/app.log` |
| Z11 | **Clickjacking, sniffing typu** | cała strona | `X-Frame-Options: DENY`, `frame-ancestors 'none'`, `X-Content-Type-Options: nosniff` | E1 | gotowe – testy S3, S4 |
| Z12 | **Utrata danych** | baza | codzienny `mysqldump` + test przywracania | E4–E5 | gotowe – `scripts/backup.sh`, `scripts/restore-test.sh` |
| Z13 | **Nadmierne uprawnienia do bazy** | serwer | użytkownik `acai_app` ma tylko SELECT/INSERT/UPDATE/DELETE | E1 | instrukcja w `docs/wdrozenie.md` |
| Z14 | **Złośliwy adres zdjęcia** – kierownik (albo ktoś z jego kontem) wpisuje adres, który wstrzykuje kod do CSS/SVG albo ładuje obraz z obcego serwera | `api/admin/products.php` (E3), `cup.js`, `app.js` | serwer przyjmuje tylko `https://images.unsplash.com/…`, `https://images.pexels.com/…` albo ścieżkę `assets/img/products/*.jpg / .png / .webp` (`filter_var` + wyrażenie regularne); CSP `img-src` wpuszcza tylko te dwa serwisy | E3 | gotowe – `src/products.php`, test X2 |

## Checklista do code review (każdy Pull Request)

- [x] Żadne dane od użytkownika nie trafiają do SQL inaczej niż przez `execute([...])`
- [x] Żadne dane nie trafiają do HTML przez `innerHTML` ani bez `htmlspecialchars()`
- [x] Endpoint panelu zaczyna się od `require_role(...)`, a zmieniający dane – od sprawdzenia tokenu CSRF
- [x] Endpoint sprawdza metodę HTTP (`require_method`)
- [x] Błędy zwracane przez `json_error()` z właściwym kodem HTTP, bez treści wyjątku
- [x] Brak haseł, kluczy i adresów serwera w kodzie
- [x] Wynik review (także z ChatGPT/Codex) dopisany do `AI_USAGE.md`
