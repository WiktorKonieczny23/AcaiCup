-- =====================================================================
-- Acai Cup – schemat bazy danych (MySQL 8 / MariaDB 10.6+)
-- Zadanie 11 (BE). Uruchom najpierw ten plik, potem seed.sql.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS order_status_log;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS extra_allergens;
DROP TABLE IF EXISTS extras;
DROP TABLE IF EXISTS product_allergens;
DROP TABLE IF EXISTS allergens;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS sizes;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- Rozmiary kubka: S (2 warstwy + 1 topping), M (3 + 1), L (4 + 2).
-- Cena zależy tylko od rozmiaru; zmienia ją kierownik.
-- ---------------------------------------------------------------------
CREATE TABLE sizes (
    id              TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code            CHAR(1)          NOT NULL,
    name            VARCHAR(30)      NOT NULL,
    layers_count    TINYINT UNSIGNED NOT NULL,
    toppings_count  TINYINT UNSIGNED NOT NULL,
    price           DECIMAL(6,2)     NOT NULL,
    is_active       BOOLEAN          NOT NULL DEFAULT TRUE,
    CONSTRAINT uq_sizes_code UNIQUE (code),
    CONSTRAINT chk_sizes_price    CHECK (price >= 0),
    CONSTRAINT chk_sizes_layers   CHECK (layers_count BETWEEN 1 AND 4),
    CONSTRAINT chk_sizes_toppings CHECK (toppings_count BETWEEN 0 AND 2)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Kategorie są stałe (ustalenie zespołu): 4 kategorie warstw + toppingi.
-- position = numer warstwy liczony od dołu kubka.
-- S używa warstw 1–2, M 1–3, L 1–4.
-- ---------------------------------------------------------------------
CREATE TABLE categories (
    id        TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name      VARCHAR(50)               NOT NULL,
    type      ENUM('layer', 'topping')  NOT NULL,
    position  TINYINT UNSIGNED          NOT NULL,
    CONSTRAINT uq_categories_type_position UNIQUE (type, position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Produkty (składniki). Wartości odżywcze na jedną porcję (portion_g).
-- is_available – przełącza pracownik, gdy produktu zabraknie.
-- is_active    – kierownik może ukryć produkt na stałe (zamiast DELETE).
-- ---------------------------------------------------------------------
CREATE TABLE products (
    id            SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id   TINYINT UNSIGNED NOT NULL,
    name          VARCHAR(60)      NOT NULL,
    color_hex     CHAR(7)          NOT NULL DEFAULT '#999999',
    portion_g     SMALLINT UNSIGNED NOT NULL,
    kcal          DECIMAL(6,1)     NOT NULL,
    protein_g     DECIMAL(5,1)     NOT NULL,
    carbs_g       DECIMAL(5,1)     NOT NULL,
    fat_g         DECIMAL(5,1)     NOT NULL,
    image_url     VARCHAR(255)     NULL,      -- zdjęcie z góry (tekstura warstwy); NULL = sam kolor
    image_author  VARCHAR(80)      NULL,      -- autor zdjęcia (podpis w stopce)
    image_source  VARCHAR(255)     NULL,      -- strona zdjęcia (Unsplash / Pexels / własne)
    is_available  BOOLEAN          NOT NULL DEFAULT TRUE,
    is_active     BOOLEAN          NOT NULL DEFAULT TRUE,
    created_at    TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_products_category FOREIGN KEY (category_id)
        REFERENCES categories (id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT uq_products_category_name UNIQUE (category_id, name),
    CONSTRAINT chk_products_values CHECK (
        portion_g > 0 AND kcal >= 0 AND protein_g >= 0 AND carbs_g >= 0 AND fat_g >= 0
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 14 alergenów, które w UE trzeba oznaczać.
-- ---------------------------------------------------------------------
CREATE TABLE allergens (
    id    TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name  VARCHAR(60) NOT NULL,
    CONSTRAINT uq_allergens_name UNIQUE (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_allergens (
    product_id   SMALLINT UNSIGNED NOT NULL,
    allergen_id  TINYINT UNSIGNED  NOT NULL,
    PRIMARY KEY (product_id, allergen_id),
    CONSTRAINT fk_pa_product  FOREIGN KEY (product_id)  REFERENCES products (id)  ON DELETE CASCADE,
    CONSTRAINT fk_pa_allergen FOREIGN KEY (allergen_id) REFERENCES allergens (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Dodatki płatne osobno. Na start jeden: białko serwatkowe 20 g za 4 zł.
-- ---------------------------------------------------------------------
CREATE TABLE extras (
    id            TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code          VARCHAR(30)      NOT NULL,
    name          VARCHAR(60)      NOT NULL,
    price         DECIMAL(6,2)     NOT NULL,
    portion_g     SMALLINT UNSIGNED NOT NULL,
    kcal          DECIMAL(6,1)     NOT NULL,
    protein_g     DECIMAL(5,1)     NOT NULL,
    carbs_g       DECIMAL(5,1)     NOT NULL,
    fat_g         DECIMAL(5,1)     NOT NULL,
    image_url     VARCHAR(255)     NULL,
    image_author  VARCHAR(80)      NULL,
    image_source  VARCHAR(255)     NULL,
    is_available  BOOLEAN          NOT NULL DEFAULT TRUE,
    CONSTRAINT uq_extras_code UNIQUE (code),
    CONSTRAINT chk_extras_price CHECK (price >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE extra_allergens (
    extra_id     TINYINT UNSIGNED NOT NULL,
    allergen_id  TINYINT UNSIGNED NOT NULL,
    PRIMARY KEY (extra_id, allergen_id),
    CONSTRAINT fk_ea_extra    FOREIGN KEY (extra_id)    REFERENCES extras (id)    ON DELETE CASCADE,
    CONSTRAINT fk_ea_allergen FOREIGN KEY (allergen_id) REFERENCES allergens (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Konta personelu: jedno pracownika, jedno kierownika.
-- Hasła wyłącznie jako wynik password_hash() w PHP.
-- ---------------------------------------------------------------------
CREATE TABLE users (
    id              TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    login           VARCHAR(40)                   NOT NULL,
    password_hash   VARCHAR(255)                  NOT NULL,
    role            ENUM('employee', 'manager')   NOT NULL,
    is_active       BOOLEAN                       NOT NULL DEFAULT TRUE,
    last_login_at   DATETIME                      NULL,
    CONSTRAINT uq_users_login UNIQUE (login)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- Zamówienia (używane od etapu E2). Ceny zapisywane jako kopia z chwili
-- zakupu, żeby późniejsza zmiana cennika nie psuła historii.
-- ---------------------------------------------------------------------
CREATE TABLE orders (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_code       VARCHAR(10)      NOT NULL,
    size_id          TINYINT UNSIGNED NOT NULL,
    size_price       DECIMAL(6,2)     NOT NULL,
    extra_protein    BOOLEAN          NOT NULL DEFAULT FALSE,
    protein_price    DECIMAL(6,2)     NOT NULL DEFAULT 0,
    total_price      DECIMAL(7,2)     NOT NULL,
    payment_method   ENUM('cash', 'online')                              NOT NULL DEFAULT 'cash',
    payment_status   ENUM('pending', 'paid', 'failed')                   NOT NULL DEFAULT 'pending',
    status           ENUM('new', 'preparing', 'ready', 'collected', 'cancelled') NOT NULL DEFAULT 'new',
    created_at       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_orders_code UNIQUE (order_code),
    CONSTRAINT fk_orders_size FOREIGN KEY (size_id) REFERENCES sizes (id) ON DELETE RESTRICT,
    CONSTRAINT chk_orders_prices CHECK (size_price >= 0 AND protein_price >= 0 AND total_price >= 0),
    INDEX idx_orders_status_created (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Jeden wiersz = jeden składnik w konkretnym miejscu kubka.
-- UNIQUE(order_id, product_id) pilnuje, że składnik się nie powtarza
-- (w tym dwa różne toppingi w rozmiarze L).
CREATE TABLE order_items (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id      INT UNSIGNED      NOT NULL,
    product_id    SMALLINT UNSIGNED NOT NULL,
    product_name  VARCHAR(60)       NOT NULL,
    slot          ENUM('layer_1', 'layer_2', 'layer_3', 'layer_4', 'topping_1', 'topping_2') NOT NULL,
    CONSTRAINT fk_items_order   FOREIGN KEY (order_id)   REFERENCES orders (id)   ON DELETE CASCADE,
    CONSTRAINT fk_items_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE RESTRICT,
    CONSTRAINT uq_items_slot    UNIQUE (order_id, slot),
    CONSTRAINT uq_items_product UNIQUE (order_id, product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kto i kiedy zmienił status zamówienia (od etapu E3).
CREATE TABLE order_status_log (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id    INT UNSIGNED     NOT NULL,
    user_id     TINYINT UNSIGNED NOT NULL,
    old_status  ENUM('new', 'preparing', 'ready', 'collected', 'cancelled') NOT NULL,
    new_status  ENUM('new', 'preparing', 'ready', 'collected', 'cancelled') NOT NULL,
    changed_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_log_order FOREIGN KEY (order_id) REFERENCES orders (id) ON DELETE CASCADE,
    CONSTRAINT fk_log_user  FOREIGN KEY (user_id)  REFERENCES users (id)  ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
