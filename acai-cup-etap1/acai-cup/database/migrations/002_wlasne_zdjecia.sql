-- Migracja 002 (10.11.2026): zdjęcia wybrane przez zespół zamiast zdjęć z Unsplash / Pexels.
-- Pliki leżą w public/assets/img/products/. Uruchom na istniejącej bazie (phpMyAdmin → SQL)
-- po migracji 001. Nowa instalacja (schema.sql + seed.sql) ma już te zmiany.

SET NAMES utf8mb4;

-- Acai z guaraną
UPDATE products SET image_url = 'assets/img/products/acai-z-guarana.jpg', image_author = NULL, image_source = NULL WHERE id = 2;

-- Acai bez cukru
UPDATE products SET image_url = 'assets/img/products/acai-bez-cukru.jpg', image_author = NULL, image_source = NULL WHERE id = 3;

-- Acai klasyczne z bananem
UPDATE products SET image_url = 'assets/img/products/acai-z-bananem.jpg', image_author = NULL, image_source = NULL WHERE id = 1;

-- Acai kokosowe
UPDATE products SET image_url = 'assets/img/products/acai-kokosowe.jpg', image_author = NULL, image_source = NULL WHERE id = 4;

-- Granola orzechowa: wycinek samej tekstury (bez miski) ze zdjęcia z Unsplash, autor bez zmian
UPDATE products SET image_url = 'assets/img/products/granola-orzechowa.jpg', image_author = 'Abhishek Hajare', image_source = 'https://unsplash.com/photos/GiB3TUjWIks' WHERE id = 6;

-- Wycinki samej tekstury (bez miski, talerza, miarki i tła) ze zdjęć z Unsplash / Pexels. Autorzy bez zmian.
UPDATE products SET image_url = 'assets/img/products/granola-klasyczna.jpg', image_author = 'Heather Barnes', image_source = 'https://unsplash.com/photos/hMGFMk1rT48' WHERE id = 5;
UPDATE products SET image_url = 'assets/img/products/musli-czekoladowe.jpg', image_author = 'Lina Osorio', image_source = 'https://unsplash.com/photos/sOfsOGSiaXI' WHERE id = 9;
UPDATE products SET image_url = 'assets/img/products/banan.jpg', image_author = 'Juliane Chatillon', image_source = 'https://www.pexels.com/photo/36852876/' WHERE id = 10;
UPDATE products SET image_url = 'assets/img/products/mango.jpg', image_author = 'Fedor', image_source = 'https://unsplash.com/photos/BRiT_s3tN6Y' WHERE id = 13;
UPDATE products SET image_url = 'assets/img/products/gorzka-czekolada.jpg', image_author = 'Rita Patrese', image_source = 'https://unsplash.com/photos/njXuDXO5Qd8' WHERE id = 23;
UPDATE extras SET image_url = 'assets/img/products/bialko-serwatkowe.jpg', image_author = 'HowToGym', image_source = 'https://unsplash.com/photos/S9NchuPb79I' WHERE code = 'whey_protein';

-- Krem kokosowy: tekstura gęstego kremowego deseru z góry (Pexels)
UPDATE products SET image_url = 'assets/img/products/krem-kokosowy.jpg', image_author = 'DS stories', image_source = 'https://www.pexels.com/photo/9227965/' WHERE id = 16;
