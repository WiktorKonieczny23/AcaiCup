-- =====================================================================
-- Acai Cup – dane startowe (zadanie 12, BE). Uruchom po schema.sql.
--
-- UWAGA: wartości odżywcze są PRZYBLIŻONE (typowe wartości z tabel
-- żywieniowych). Przed oddaniem projektu zastąpcie je danymi z etykiet
-- produktów, których naprawdę używacie (zadanie 5).
-- Ceny rozmiarów to wartości przykładowe – ustala je kierownik.
-- =====================================================================

SET NAMES utf8mb4;

-- Rozmiary --------------------------------------------------------------
INSERT INTO sizes (id, code, name, layers_count, toppings_count, price) VALUES
    (1, 'S', 'Mały',    2, 1, 19.00),
    (2, 'M', 'Średni',  3, 1, 24.00),
    (3, 'L', 'Duży',    4, 2, 29.00);

-- Kategorie (stałe) -----------------------------------------------------
INSERT INTO categories (id, name, type, position) VALUES
    (1, 'Baza acai',       'layer',   1),
    (2, 'Zboża i granola', 'layer',   2),
    (3, 'Owoce',           'layer',   3),
    (4, 'Krem',            'layer',   4),
    (5, 'Toppingi',        'topping', 1);

-- 14 alergenów wymaganych w UE -----------------------------------------
INSERT INTO allergens (id, name) VALUES
    (1,  'Zboża zawierające gluten'),
    (2,  'Skorupiaki'),
    (3,  'Jaja'),
    (4,  'Ryby'),
    (5,  'Orzeszki ziemne'),
    (6,  'Soja'),
    (7,  'Mleko'),
    (8,  'Orzechy'),
    (9,  'Seler'),
    (10, 'Gorczyca'),
    (11, 'Sezam'),
    (12, 'Dwutlenek siarki i siarczyny'),
    (13, 'Łubin'),
    (14, 'Mięczaki');

-- Produkty ---------------------------------------------------------------
-- kolumny: id, kategoria, nazwa, kolor warstwy, porcja [g], kcal, B, W, T
INSERT INTO products (id, category_id, name, color_hex, portion_g, kcal, protein_g, carbs_g, fat_g, is_available) VALUES
    -- 1. Baza acai (porcja 150 g)
    (1,  1, 'Acai klasyczne z bananem', '#4B1A55', 150, 135.0, 2.0, 22.0, 5.0, TRUE),
    (2,  1, 'Acai z guaraną',           '#5E2266', 150, 150.0, 1.8, 26.0, 4.8, TRUE),
    (3,  1, 'Acai bez cukru',           '#3E1447', 150, 105.0, 2.0,  9.0, 7.5, TRUE),
    (4,  1, 'Acai kokosowe',            '#6A2C6E', 150, 170.0, 1.8, 20.0, 9.5, TRUE),
    -- 2. Zboża i granola (porcja 40 g)
    (5,  2, 'Granola klasyczna',        '#C9974B',  40, 180.0, 4.0, 26.0, 6.5, TRUE),
    (6,  2, 'Granola orzechowa',        '#B07A3A',  40, 195.0, 4.8, 22.0, 9.5, TRUE),
    (7,  2, 'Granola bezglutenowa',     '#D2A862',  40, 175.0, 3.6, 27.0, 6.0, TRUE),
    (8,  2, 'Płatki owsiane',           '#E0C58F',  40, 150.0, 5.2, 24.0, 2.8, TRUE),
    (9,  2, 'Musli czekoladowe',        '#8A5A3B',  40, 170.0, 3.6, 27.0, 5.2, TRUE),
    -- 3. Owoce (porcja 80 g)
    (10, 3, 'Banan',                    '#F2D857',  80,  71.0, 0.9, 18.3, 0.2, TRUE),
    (11, 3, 'Truskawki',                '#E2384D',  80,  26.0, 0.5,  6.1, 0.2, TRUE),
    (12, 3, 'Borówki',                  '#3E4E9C',  80,  46.0, 0.6, 11.6, 0.3, TRUE),
    (13, 3, 'Mango',                    '#F4A300',  80,  48.0, 0.7, 12.0, 0.3, TRUE),
    (14, 3, 'Kiwi',                     '#8DBB3C',  80,  49.0, 0.9, 11.7, 0.4, FALSE), -- niedostępne: test wyszarzania
    -- 4. Krem
    (15, 4, 'Masło orzechowe',          '#A86B32',  20, 118.0, 5.0,  4.0, 10.0, TRUE),
    (16, 4, 'Krem kokosowy',            '#EFE7D6',  30,  99.0, 1.0,  2.0, 10.4, TRUE),
    (17, 4, 'Jogurt grecki',            '#F4F1EA',  50,  60.0, 3.0,  2.0, 5.0, TRUE),
    (18, 4, 'Krem pistacjowy',          '#93B04A',  20, 115.0, 2.6,  7.0, 8.6, TRUE),
    (19, 4, 'Krem kakaowo-orzechowy',   '#5A3420',  20, 108.0, 1.3, 11.5, 6.2, TRUE),
    -- 5. Toppingi
    (20, 5, 'Wiórki kokosowe',          '#F5F1E6',   5,  33.0, 0.3,  0.3, 3.2, TRUE),
    (21, 5, 'Miód',                     '#E8A93A',  10,  30.0, 0.0,  8.2, 0.0, TRUE),
    (22, 5, 'Nasiona chia',             '#4A4A48',   5,  24.0, 0.8,  2.1, 1.5, TRUE),
    (23, 5, 'Gorzka czekolada',         '#3B2418',  10,  58.0, 0.8,  4.6, 4.3, TRUE),
    (24, 5, 'Płatki migdałów',          '#E4C9A0',   8,  46.0, 1.7,  1.7, 4.0, TRUE),
    (25, 5, 'Liofilizowane maliny',     '#D23F62',   3,  11.0, 0.3,  2.2, 0.1, TRUE);

-- Alergeny produktów -----------------------------------------------------
INSERT INTO product_allergens (product_id, allergen_id) VALUES
    (5, 1),                       -- granola klasyczna: gluten
    (6, 1), (6, 8),               -- granola orzechowa: gluten, orzechy
    (8, 1),                       -- płatki owsiane: gluten
    (9, 1), (9, 6), (9, 7),       -- musli czekoladowe: gluten, soja, mleko
    (15, 5),                      -- masło orzechowe: orzeszki ziemne
    (17, 7),                      -- jogurt grecki: mleko
    (18, 7), (18, 8),             -- krem pistacjowy: mleko, orzechy
    (19, 6), (19, 7), (19, 8),    -- krem kakaowo-orzechowy: soja, mleko, orzechy
    (23, 6), (23, 7),             -- gorzka czekolada: soja, mleko (śladowo)
    (24, 8);                      -- płatki migdałów: orzechy

-- Extra białko: 20 g białka serwatkowego za 4 zł (ustalenie zespołu) ----
INSERT INTO extras (id, code, name, price, portion_g, kcal, protein_g, carbs_g, fat_g) VALUES
    (1, 'whey_protein', 'Białko serwatkowe', 4.00, 20, 78.0, 15.0, 1.5, 1.2);

INSERT INTO extra_allergens (extra_id, allergen_id) VALUES
    (1, 7);                       -- białko serwatkowe: mleko

-- Konta personelu (jedno na rolę) ----------------------------------------
-- Hasła testowe: pracownik / pracownik123, kierownik / kierownik123.
-- NA SERWERZE PRODUKCYJNYM ZMIEŃCIE JE od razu po wdrożeniu.
INSERT INTO users (id, login, password_hash, role) VALUES
    (1, 'pracownik', '$2y$10$uZ6oMIiTvwWpoESozzGgt.ydXoxmsCtMG2EpxSMxpsUgzJ87ybBPS', 'employee'),
    (2, 'kierownik', '$2y$10$2ca0z4VnBZjrKmEFbW/rF.7vJGGm/VuszFF4eihrziyMAmOlmh626', 'manager');

-- Zdjęcia składników (Unsplash / Pexels, darmowe licencje). Podgląd: docs/zdjecia-produktow.html
-- Własne zdjęcie: wrzuć plik do public/assets/img/products/ i wpisz ścieżkę, np. 'assets/img/products/krem-kokosowy.jpg'.
UPDATE products SET image_url = 'assets/img/products/acai-z-bananem.jpg', image_author = NULL, image_source = NULL WHERE id = 1; -- zdjęcie zespołu
UPDATE products SET image_url = 'assets/img/products/acai-z-guarana.jpg', image_author = NULL, image_source = NULL WHERE id = 2; -- zdjęcie zespołu
UPDATE products SET image_url = 'assets/img/products/acai-bez-cukru.jpg', image_author = NULL, image_source = NULL WHERE id = 3; -- zdjęcie zespołu
UPDATE products SET image_url = 'assets/img/products/acai-kokosowe.jpg', image_author = NULL, image_source = NULL WHERE id = 4; -- zdjęcie zespołu
UPDATE products SET image_url = 'assets/img/products/granola-klasyczna.jpg', image_author = 'Heather Barnes', image_source = 'https://unsplash.com/photos/hMGFMk1rT48' WHERE id = 5; -- wycinek tekstury
UPDATE products SET image_url = 'assets/img/products/granola-orzechowa.jpg', image_author = 'Abhishek Hajare', image_source = 'https://unsplash.com/photos/GiB3TUjWIks' WHERE id = 6; -- wycinek bez miski
UPDATE products SET image_url = 'https://images.pexels.com/photos/6086854/pexels-photo-6086854.jpeg', image_author = 'Eva Bronzini', image_source = 'https://www.pexels.com/photo/6086854/' WHERE id = 7;
UPDATE products SET image_url = 'https://images.unsplash.com/photo-1722882270575-f437c338a4de', image_author = 'Xhiliana', image_source = 'https://unsplash.com/photos/ThMhV-RaQ24' WHERE id = 8;
UPDATE products SET image_url = 'assets/img/products/musli-czekoladowe.jpg', image_author = 'Lina Osorio', image_source = 'https://unsplash.com/photos/sOfsOGSiaXI' WHERE id = 9; -- wycinek tekstury
UPDATE products SET image_url = 'assets/img/products/banan.jpg', image_author = 'Juliane Chatillon', image_source = 'https://www.pexels.com/photo/36852876/' WHERE id = 10; -- wycinek tekstury
UPDATE products SET image_url = 'https://images.unsplash.com/photo-1594282241894-4da286138f44', image_author = 'Natasha Skov', image_source = 'https://unsplash.com/photos/VyRmCBnDj5g' WHERE id = 11;
UPDATE products SET image_url = 'https://images.unsplash.com/photo-1626433281588-ae724357378d', image_author = 'Hanna Balan', image_source = 'https://unsplash.com/photos/N0nXYmCW4JA' WHERE id = 12;
UPDATE products SET image_url = 'assets/img/products/mango.jpg', image_author = 'Fedor', image_source = 'https://unsplash.com/photos/BRiT_s3tN6Y' WHERE id = 13; -- wycinek tekstury
UPDATE products SET image_url = 'https://images.unsplash.com/photo-1650261062904-b51482026eac', image_author = 'Tom Paolini', image_source = 'https://unsplash.com/photos/VCa_6OdVV2w' WHERE id = 14;
UPDATE products SET image_url = 'https://images.unsplash.com/photo-1729673520849-263dbc8e0fc3', image_author = 'Olivie Strauss', image_source = 'https://unsplash.com/photos/a_4gTRTG_Ws' WHERE id = 15;
UPDATE products SET image_url = 'assets/img/products/krem-kokosowy.jpg', image_author = 'DS stories', image_source = 'https://www.pexels.com/photo/9227965/' WHERE id = 16; -- tekstura kremowego deseru (Pexels)
UPDATE products SET image_url = 'https://images.unsplash.com/photo-1728994062543-74a1dc2c9392', image_author = '360floralflaves', image_source = 'https://unsplash.com/photos/j9gn__IqJN4' WHERE id = 17;
UPDATE products SET image_url = 'https://images.unsplash.com/photo-1653750660402-d009795394b2', image_author = 'NIKHIL', image_source = 'https://unsplash.com/photos/eaN_2b0-o88' WHERE id = 18;
UPDATE products SET image_url = 'https://images.unsplash.com/photo-1676817167465-38a0fe938c53', image_author = 'bader photographer', image_source = 'https://unsplash.com/photos/OLwCpJmzJOA' WHERE id = 19;
UPDATE products SET image_url = 'https://images.pexels.com/photos/7717792/pexels-photo-7717792.jpeg', image_author = 'Marina Leonova', image_source = 'https://www.pexels.com/photo/7717792/' WHERE id = 20;
UPDATE products SET image_url = 'https://images.unsplash.com/photo-1623018697148-8350cf18e64e', image_author = 'Jonas Hensel', image_source = 'https://unsplash.com/photos/3Cpws7ibtfo' WHERE id = 21;
UPDATE products SET image_url = 'https://images.unsplash.com/photo-1502825926876-e8819fbb2fd0', image_author = 'Joanna Kosinska', image_source = 'https://unsplash.com/photos/6MlhT-THmk4' WHERE id = 22;
UPDATE products SET image_url = 'assets/img/products/gorzka-czekolada.jpg', image_author = 'Rita Patrese', image_source = 'https://unsplash.com/photos/njXuDXO5Qd8' WHERE id = 23; -- wycinek tekstury
UPDATE products SET image_url = 'https://images.pexels.com/photos/6086888/pexels-photo-6086888.jpeg', image_author = 'Eva Bronzini', image_source = 'https://www.pexels.com/photo/6086888/' WHERE id = 24;
UPDATE products SET image_url = 'https://images.unsplash.com/photo-1609622511068-70c39f3c2253', image_author = 'Ulyana Grishina', image_source = 'https://unsplash.com/photos/aAKjE83oKqs' WHERE id = 25;
UPDATE extras SET image_url = 'assets/img/products/bialko-serwatkowe.jpg', image_author = 'HowToGym', image_source = 'https://unsplash.com/photos/S9NchuPb79I' WHERE code = 'whey_protein'; -- wycinek tekstury
