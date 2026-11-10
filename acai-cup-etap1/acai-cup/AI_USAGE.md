# Wykorzystanie narzędzi AI

Każde użycie ChatGPT, Codexa lub innego asystenta AI dopisujemy tutaj na bieżąco:
kto, kiedy, do czego i co zmieniliśmy w odpowiedzi. Nie przepisujemy rozmów –
wystarczy krótki opis.

| Data | Osoba / rola | Narzędzie | Do czego | Co zmieniliśmy / zweryfikowaliśmy |
| --- | --- | --- | --- | --- |
| 2026-10-27 | cały zespół | Claude (Anthropic) | plan projektu, podział na etapy, kontrakt API, lista 54 zadań | doprecyzowaliśmy kategorie warstw, cenę białka, sposób płatności i konta personelu |
| 2026-10-27 | cały zespół | Claude (Anthropic) | szkielet etapu E1: `schema.sql`, `seed.sql`, `src/db.php`, `src/response.php`, `api/menu.php`, konfigurator (HTML/CSS/JS, kubek SVG), Docker, dokumenty w `docs/` | _do uzupełnienia: kto przejrzał który plik, co poprawiliście, które wartości odżywcze zamieniliście na dane z etykiet_ |
| 2026-11-03 | cały zespół | Claude (Anthropic) | wybór stylu: 3 propozycje ze styles.refero.design nałożone na konfigurator; po wyborze „Wise” przepisany `style.css` i `docs/styl.md` | zespół wybrał styl Wise spośród Reflect Notes, Wise i Brex |
| 2026-11-03 | cały zespół | Claude (Anthropic) | wyszukanie darmowych zdjęć składników z góry (Unsplash, Pexels) do zastąpienia kolorów w kubku; galeria `docs/zdjecia-produktow.html` | _do uzupełnienia: które zdjęcia zaakceptowaliście, które zastąpiliście własnymi_ |
| 2026-11-03 | cały zespół | Claude (Anthropic) | zdjęcia w konfiguratorze: kolumny `image_*` + migracja 001, `menu.php`, tekstury SVG w `cup.js`, miniatury i autorzy zdjęć w `app.js`, CSP | _do uzupełnienia: kto przejrzał zmiany_ |
| 2026-11-10 | cały zespół | Claude (Anthropic) | podmiana zdjęć: 4 bazy acai na zdjęcia zespołu, wycinki samej tekstury (bez misek, talerzy, miarki) dla granoli, musli, banana, mango, czekolady i białka; migracja 002 | _do uzupełnienia: kto sprawdził_ |

## Zasady

1. Kod od AI traktujemy jak kod od kolegi: przechodzi review w Pull Requeście.
2. Każdy musi umieć wytłumaczyć kod ze swojego obszaru, także ten wygenerowany.
3. Nie wklejamy do AI haseł, kluczy ani pliku `config.php`.
4. Code review z pomocą AI (zakres roli 3) – wynik i wprowadzone poprawki zapisujemy w tabeli.
