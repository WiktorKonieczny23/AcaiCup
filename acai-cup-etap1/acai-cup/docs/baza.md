# Baza danych – diagram ERD (zadanie 11, BE)

GitHub sam rysuje poniższy diagram. Pełna definicja: `database/schema.sql`.

```mermaid
erDiagram
    sizes ||--o{ orders : "rozmiar"
    categories ||--o{ products : "zawiera"
    products ||--o{ product_allergens : ""
    allergens ||--o{ product_allergens : ""
    extras ||--o{ extra_allergens : ""
    allergens ||--o{ extra_allergens : ""
    orders ||--|{ order_items : "składa się z"
    products ||--o{ order_items : "wybrany jako"
    orders ||--o{ order_status_log : "historia"
    users ||--o{ order_status_log : "zmienił"

    sizes {
        tinyint id PK
        char code UK "S, M, L"
        tinyint layers_count "2, 3, 4"
        tinyint toppings_count "1, 1, 2"
        decimal price
    }
    categories {
        tinyint id PK
        enum type "layer / topping"
        tinyint position "numer warstwy od dna"
    }
    products {
        smallint id PK
        tinyint category_id FK
        varchar name
        char color_hex "kolor warstwy w SVG"
        decimal kcal
        decimal protein_g
        decimal carbs_g
        decimal fat_g
        bool is_available "przełącza pracownik"
        bool is_active "ukrycie zamiast DELETE"
        varchar image_url "zdjęcie z góry, NULL = kolor"
        varchar image_author
        varchar image_source
    }
    allergens {
        tinyint id PK
        varchar name UK
    }
    product_allergens {
        smallint product_id PK,FK
        tinyint allergen_id PK,FK
    }
    extras {
        tinyint id PK
        varchar code UK "whey_protein"
        decimal price "4.00"
        smallint portion_g "20"
    }
    extra_allergens {
        tinyint extra_id PK,FK
        tinyint allergen_id PK,FK
    }
    users {
        tinyint id PK
        varchar login UK
        varchar password_hash
        enum role "employee / manager"
    }
    orders {
        int id PK
        varchar order_code UK "numer do odbioru"
        tinyint size_id FK
        decimal size_price "kopia ceny"
        decimal total_price
        enum payment_method "cash / online"
        enum status "new … collected / cancelled"
    }
    order_items {
        int id PK
        int order_id FK
        smallint product_id FK
        varchar product_name "kopia nazwy"
        enum slot "layer_1 … topping_2"
    }
    order_status_log {
        int id PK
        int order_id FK
        tinyint user_id FK
        enum old_status
        enum new_status
    }
```

## Najważniejsze decyzje

- **Kopie cen i nazw w zamówieniu** (`size_price`, `product_name`) – zmiana cennika
  lub nazwy produktu nie zmienia historii ani podsumowania sprzedaży.
- **Brak fizycznego usuwania produktów** – `is_available` (pracownik, chwilowy brak)
  i `is_active` (kierownik, wycofanie na stałe). `ON DELETE RESTRICT` na produktach
  użytych w zamówieniach dodatkowo blokuje przypadkowe `DELETE`.
- **`UNIQUE(order_id, product_id)`** – składnik nie może się powtórzyć w jednym kubku,
  co przy okazji wymusza dwa *różne* toppingi w rozmiarze L.
- **Zdjęcia składników** (`image_url`, `image_author`, `image_source` w `products` i `extras`)
  – adres zdjęcia z Unsplash / Pexels albo ścieżka do własnego pliku w `public/`. Konfigurator
  wypełnia nim warstwę kubka; gdy `image_url` jest puste, zostaje `color_hex`. Dodane migracją
  `database/migrations/001_zdjecia_skladnikow.sql`.
- **`extra_allergens`** – tabela dodana względem planu, bo białko serwatkowe zawiera
  mleko i ten alergen musi trafić do podsumowania.
