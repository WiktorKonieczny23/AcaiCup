# Scenariusze testowe i wyniki (zadania 32, 41, 46 – DevOps / QA)

Stan na 24.11.2026. Wszystkie scenariusze przeszły. Testy są dwa rodzaje:

1. **Automatyczne testy API** – `tests/api_test.php`, 43 scenariusze: klient, pracownik,
   kierownik i ataki. Pełna tabela z ostatniego uruchomienia: [testy-wyniki.md](testy-wyniki.md).
2. **Testy w przeglądarce** – kliknięcie całej ścieżki w Chrome (konfigurator i panel),
   tabela poniżej.

## Jak uruchomić testy API

```bash
# XAMPP: Apache i MySQL włączone, baza zaimportowana
php tests/api_test.php http://localhost/ACAI/public

# z zapisem tabeli wyników
php tests/api_test.php http://localhost/ACAI/public --markdown=docs/testy-wyniki.md
```

Skrypt wypisuje `OK` / `BŁĄD` / `SKIP` dla każdego scenariusza i kończy się kodem 1, gdy coś nie przeszło.
Po sobie sprząta: przywraca ceny i dostępność, ukrywa dodany produkt testowy, anuluje zamówienia testowe.
**Nie uruchamiajcie go na serwerze produkcyjnym** – tylko lokalnie albo na kopii bazy.

Scenariusz S4 (nagłówki CSP z `.htaccess`) wymaga Apache. Na `php -S` dostaje `SKIP`, bo wbudowany serwer PHP nie czyta `.htaccess`.

## Gdzie to sprawdziliśmy

| Środowisko | Adres | Wynik |
| --- | --- | --- |
| Wbudowany serwer PHP 8.3 + MariaDB 10.11 | `php -S 127.0.0.1:8080 -t public` | 42 OK, 1 SKIP (S4) |
| Apache 2.4 + PHP 8.3, układ jak w XAMPP | `http://…/ACAI/public` | 43 OK |
| Apache 2.4 + PHP 8.3, układ jak na VPS (document root = `public/`) | `http://…:8082` | 43 OK |

W układzie XAMPP dodatkowo sprawdziliśmy, że pliki poza `public/` są zablokowane (403):
`logs/app.log`, `database/seed.sql`, `config/config.php`, `src/db.php`, `README.md`, a także `public/.htaccess`.

## Co obejmują testy API (skrót)

| Grupa | Scenariusze | Co sprawdzają |
| --- | --- | --- |
| Klient | K1–K13 | menu, zamówienie, status, walidacja składu (liczba warstw, kategoria, powtórzony topping, rozmiar), płatność online, zły JSON, zła metoda HTTP |
| Bezpieczeństwo | K3, S1–S4, X1–X2 | manipulacja ceną, SQL injection, nagłówki, XSS w nazwie produktu, złośliwy adres zdjęcia |
| Kontrola dostępu | A1–A2, A6–A8 | gość i pracownik nie wykonają operacji kierownika |
| Sesje i hasła | A3–A5, A9, H1 | ten sam komunikat dla złego loginu i hasła, nowa sesja po zalogowaniu, ciasteczko HttpOnly + SameSite, wylogowanie, blokada po 5 próbach |
| CSRF | C1–C2 | brak tokenu i fałszywy token → 403 |
| Pracownik | P1–P5 | lista zamówień, zmiana statusu, niedozwolone przejście, klient widzi status, wyłączenie produktu |
| Kierownik | M1–M7 | zmiana cen (stare zamówienia zachowują cenę), błędne ceny, produkt z duplikatem nazwy, ukrycie produktu, podsumowanie, błędna data |

## Testy w przeglądarce (konfigurator i panel)

Wykonane skryptem w Chrome (tryb bez okna) 24.11.2026 na Apache i wbudowanym serwerze PHP.

| # | Scenariusz | Oczekiwany wynik | Wynik | Stan |
| --- | --- | --- | --- | --- |
| B1 | Wejście na konfigurator, baza działa | menu z API, brak znacznika „dane testowe” | znacznik ukryty | OK |
| B2 | Rozmiar L, 4 warstwy | 4 grupy kart, Kiwi wyszarzone | Kiwi `disabled` | OK |
| B3 | Trzeci topping w rozmiarze L | komunikat, wybór nie zmieniony | „Możesz wybrać 2 toppingi. Odznacz jeden…” | OK |
| B4 | Podgląd na żywo po dodaniu białka | cena 29 + 4 zł, kcal, alergeny | 33,00 zł, 624 kcal, gluten / orzechy / mleko | OK |
| B5 | Płatność online → „Złóż zamówienie” | strona „płatność online wkrótce”, nic nie zapisane | przejście na `platnosc-wkrotce.html` | OK |
| B6 | „Wróć i zapłać przy kasie” | podsumowanie z tym samym kubkiem, płatność przy kasie | kubek odtworzony, zaznaczone „Przy kasie” | OK |
| B7 | „Złóż zamówienie” (kasa) | ekran z numerem, statusem i kwotą | np. `L-912`, „Przyjęte” | OK |
| B8 | „Złóż kolejne zamówienie” | konfigurator od kroku 1 | „Wybierz rozmiar” | OK |
| B9 | Panel: złe hasło | komunikat pod formularzem | „Nieprawidłowy login lub hasło.” | OK |
| B10 | Panel jako pracownik | zakładki Zamówienia i Produkty, brak „Dodaj produkt” | Ceny i Podsumowanie ukryte | OK |
| B11 | Pracownik: „Zacznij przygotowanie”, wyłączenie Mango | komunikat, nowy status, przełącznik zapisany | „Zamówienie Z-509: W przygotowaniu.”, „Mango: niedostępne.” | OK |
| B12 | Kierownik: zmiana ceny S na 20,50 zł | komunikat, nowa cena w formularzu | zapisano | OK |
| B13 | Kierownik: produkt o nazwie `<img src=x onerror=alert(1)> Kakao` | nazwa widoczna jako tekst, brak obrazka w tabeli | tekst, `<img>` nie powstał | OK |
| B14 | Kierownik: podsumowanie | kafelki z liczbą zamówień i przychodem | wyświetlone | OK |
| B15 | Wylogowanie | formularz logowania | widoczny | OK |
| B16 | Konsola przeglądarki | brak błędów JavaScript | brak `pageerror` | OK |

## Do sprawdzenia ręcznie przez zespół (zadanie 47)

- [ ] Konfigurator i panel na telefonie, tablecie i komputerze (DevTools → tryb urządzenia oraz prawdziwy telefon).
- [ ] Obsługa klawiaturą: Tab przechodzi po kartach, przyciskach i zakładkach, fokus jest widoczny.
- [ ] Czytnik ekranu (np. NVDA): nazwy przycisków i komunikaty statusu są czytane.
- [ ] Panel otwarty u pracownika na telefonie: nowe zamówienia pojawiają się same co 20 s.
