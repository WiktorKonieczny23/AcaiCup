-- Migracja 004 (25.11.2026): dodatkowe toppingi i podwójna porcja toppingu w L.
-- Uruchom RAZ na istniejącej bazie (phpMyAdmin → baza → SQL) po migracjach 001–003.
-- Nowa instalacja (schema.sql + seed.sql) ma już te zmiany.

SET NAMES utf8mb4;

-- 1. Zamówienie pamięta, ile było płatnych toppingów i ile za nie zapłacono.
ALTER TABLE orders
    ADD COLUMN extra_toppings       TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER protein_price,
    ADD COLUMN extra_toppings_price DECIMAL(6,2)     NOT NULL DEFAULT 0 AFTER extra_toppings;

-- 2. Do 5 toppingów w kubku (2 w cenie L + 3 dodatkowe) i ten sam topping
--    dwa razy (podwójna porcja), więc znika UNIQUE(order_id, product_id).
ALTER TABLE order_items
    MODIFY slot ENUM('layer_1', 'layer_2', 'layer_3', 'layer_4',
                     'topping_1', 'topping_2', 'topping_3', 'topping_4', 'topping_5') NOT NULL,
    DROP INDEX uq_items_product;

-- 3. Cena dodatkowego toppingu (kierownik zmienia ją w panelu, zakładka Ceny).
INSERT INTO extras (code, name, price, portion_g, kcal, protein_g, carbs_g, fat_g)
VALUES ('extra_topping', 'Dodatkowy topping', 5.00, 0, 0, 0, 0, 0);
