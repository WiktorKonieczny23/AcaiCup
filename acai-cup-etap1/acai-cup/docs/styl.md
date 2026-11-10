# Przewodnik stylu (zadanie 14, FE)

Styl bazowy: **Wise** z [styles.refero.design](https://styles.refero.design/),
wybrany przez zespół 03.11.2026 spośród trzech propozycji (Reflect Notes, Wise, Brex).
Głęboka zieleń liści, jeden limonkowy akcent, ciężkie nagłówki i przyciski w kształcie pigułek.
Zielone tło podglądu mocno odcina się od fioletowego kubka acai.

Wszystkie wartości są zmiennymi CSS w `public/assets/css/style.css` (`:root`).
Zmieniamy je tylko tam, nigdy na sztywno w innych miejscach.

## Kolory

| Zmienna | Wartość | Użycie |
| --- | --- | --- |
| `--forest` | `#163300` | tekst marki, ciemna sekcja podglądu, obramowanie zaznaczonej karty, pasek na telefonie |
| `--spruce` | `#054d28` | tło kubka w podglądzie |
| `--obsidian` | `#0e0f0c` | nagłówki |
| `--lime` | `#9fe870` | **tylko** przycisk główny, aktywny krok, znaczek białka, cena w pasku telefonu |
| `--mist` | `#e2f6d5` | tło zaznaczonej karty, plakietki alergenów |
| `--text` | `#454745` | tekst główny |
| `--muted` | `#6a6c6a` | tekst pomocniczy |
| `--fog` | `#e8ebe6` | ramki, nieaktywne kroki |
| `--paper` | `#ffffff` | tło strony i kart |

Kolory warstw w kubku pochodzą z bazy (`products.color_hex`). Napis na warstwie
sam dobiera kolor (jasny lub ciemny) do jasności tła.

## Typografia

- **Inter 900** z ciasnym odstępem liter (`-0.04em`) – nagłówki kroków, nazwa marki, cena.
- **Inter 400–700** – cała reszta: 700 nazwy produktów, 600 przyciski i kroki, 400 tekst.
- Font z Google Fonts; gdy się nie załaduje, przeglądarka użyje kroju systemowego.

## Kształty

| Element | Zaokrąglenie |
| --- | --- |
| przyciski, kroki, plakietki | pigułka (`--radius-pill`) |
| karty wyboru, lista podsumowania, pola | 10 px (`--radius`) |
| ciemna karta podglądu | 28 px (`--radius-lg`), 20 px na telefonie |

Bez cieni i gradientów. Głębię daje tylko kontrast białej strony i ciemnozielonej sekcji.

## Elementy

- **Karta wyboru** (`.option-card`) – jedyny sposób wybierania czegokolwiek
  w konfiguratorze. Stany: zwykła (ramka Fog), najechana (ramka Forest),
  zaznaczona (tło Mist + ramka Forest, `aria-pressed="true"`), niedostępna (wyszarzona, `disabled`).
- **Przycisk główny** (`.btn-acai`) – limonkowa pigułka z ciemnozielonym tekstem,
  jeden na ekran („Dalej”, „Złóż zamówienie”). Pomocniczy: obrysowana pigułka („Wstecz”).
- **Podgląd kubka** – ciemnozielona karta z kubkiem, ceną, wartościami odżywczymi i alergenami.

## Zasady

- Limonka maksymalnie w jednym, dwóch miejscach na ekranie. Nigdy jako kolor tekstu na białym tle.
- Na telefonie wszystko w jednej kolumnie, cena i kcal w przyklejonym dolnym pasku.
- Każdy element klikalny ma widoczny fokus z klawiatury (obwódka Forest).
- Tekst pomocniczy nie jaśniejszy niż `--muted` (kontrast min. 4.5:1).
