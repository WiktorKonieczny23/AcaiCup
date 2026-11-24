-- Migracja 001 (03.11.2026): zdjęcia składników.
-- Uruchom NA ISTNIEJĄCEJ bazie (np. na serwerze), zamiast ponownego importu seed.sql:
--   mysql acai_cup < database/migrations/001_zdjecia_skladnikow.sql
-- Nowa instalacja (schema.sql + seed.sql) ma już te kolumny i dane.

SET NAMES utf8mb4;

ALTER TABLE products
    ADD COLUMN image_url VARCHAR(255) NULL AFTER fat_g,
    ADD COLUMN image_author VARCHAR(80) NULL AFTER image_url,
    ADD COLUMN image_source VARCHAR(255) NULL AFTER image_author;

ALTER TABLE extras
    ADD COLUMN image_url VARCHAR(255) NULL AFTER fat_g,
    ADD COLUMN image_author VARCHAR(80) NULL AFTER image_url,
    ADD COLUMN image_source VARCHAR(255) NULL AFTER image_author;

-- Zdjęcia składników (Unsplash / Pexels, darmowe licencje). Podgląd: docs/zdjecia-produktow.html
-- Krem kokosowy (id 16) nie ma zdjęcia – konfigurator pokaże kolor, dopóki nie dodacie własnego.
-- Własne zdjęcie: wrzuć plik do public/assets/img/products/ i wpisz ścieżkę, np. 'assets/img/products/krem-kokosowy.jpg'.
UPDATE products SET image_url = 'https://images.unsplash.com/photo-1771371000586-33abd2ce9e23', image_author = 'Jakub Pabis', image_source = 'https://unsplash.com/photos/43VMTJ1WfsM' WHERE id = 1;
UPDATE products SET image_url = 'https://images.pexels.com/photos/1334129/pexels-photo-1334129.jpeg', image_author = 'Ella Olsson', image_source = 'https://www.pexels.com/photo/1334129/' WHERE id = 2;
UPDATE products SET image_url = 'https://images.unsplash.com/photo-1647545401835-c21e6b04d853', image_author = 'Mikey Frost', image_source = 'https://unsplash.com/photos/k2MfxJGSUbQ' WHERE id = 3;
UPDATE products SET image_url = 'https://images.unsplash.com/photo-1641579719214-534970165dc9', image_author = 'Alexandra Tran', image_source = 'https://unsplash.com/photos/W2YM9Dt_1Oo' WHERE id = 4;
UPDATE products SET image_url = 'https://images.unsplash.com/photo-1559951585-645e730d3cf0', image_author = 'Heather Barnes', image_source = 'https://unsplash.com/photos/hMGFMk1rT48' WHERE id = 5;
UPDATE products SET image_url = 'https://images.unsplash.com/photo-1622542086073-dcdc3350cc2b', image_author = 'Abhishek Hajare', image_source = 'https://unsplash.com/photos/GiB3TUjWIks' WHERE id = 6;
UPDATE products SET image_url = 'https://images.pexels.com/photos/6086854/pexels-photo-6086854.jpeg', image_author = 'Eva Bronzini', image_source = 'https://www.pexels.com/photo/6086854/' WHERE id = 7;
UPDATE products SET image_url = 'https://images.unsplash.com/photo-1722882270575-f437c338a4de', image_author = 'Xhiliana', image_source = 'https://unsplash.com/photos/ThMhV-RaQ24' WHERE id = 8;
UPDATE products SET image_url = 'https://images.unsplash.com/photo-1618258022300-ac769be6e840', image_author = 'Lina Osorio', image_source = 'https://unsplash.com/photos/sOfsOGSiaXI' WHERE id = 9;
UPDATE products SET image_url = 'https://images.pexels.com/photos/36852876/pexels-photo-36852876.jpeg', image_author = 'Juliane Chatillon', image_source = 'https://www.pexels.com/photo/36852876/' WHERE id = 10;
UPDATE products SET image_url = 'https://images.unsplash.com/photo-1594282241894-4da286138f44', image_author = 'Natasha Skov', image_source = 'https://unsplash.com/photos/VyRmCBnDj5g' WHERE id = 11;
UPDATE products SET image_url = 'https://images.unsplash.com/photo-1626433281588-ae724357378d', image_author = 'Hanna Balan', image_source = 'https://unsplash.com/photos/N0nXYmCW4JA' WHERE id = 12;
UPDATE products SET image_url = 'https://images.unsplash.com/photo-1582655299221-2b6bff351df0', image_author = 'Fedor', image_source = 'https://unsplash.com/photos/BRiT_s3tN6Y' WHERE id = 13;
UPDATE products SET image_url = 'https://images.unsplash.com/photo-1650261062904-b51482026eac', image_author = 'Tom Paolini', image_source = 'https://unsplash.com/photos/VCa_6OdVV2w' WHERE id = 14;
UPDATE products SET image_url = 'https://images.unsplash.com/photo-1729673520849-263dbc8e0fc3', image_author = 'Olivie Strauss', image_source = 'https://unsplash.com/photos/a_4gTRTG_Ws' WHERE id = 15;
UPDATE products SET image_url = 'https://images.unsplash.com/photo-1728994062543-74a1dc2c9392', image_author = '360floralflaves', image_source = 'https://unsplash.com/photos/j9gn__IqJN4' WHERE id = 17;
UPDATE products SET image_url = 'https://images.unsplash.com/photo-1653750660402-d009795394b2', image_author = 'NIKHIL', image_source = 'https://unsplash.com/photos/eaN_2b0-o88' WHERE id = 18;
UPDATE products SET image_url = 'https://images.unsplash.com/photo-1676817167465-38a0fe938c53', image_author = 'bader photographer', image_source = 'https://unsplash.com/photos/OLwCpJmzJOA' WHERE id = 19;
UPDATE products SET image_url = 'https://images.pexels.com/photos/7717792/pexels-photo-7717792.jpeg', image_author = 'Marina Leonova', image_source = 'https://www.pexels.com/photo/7717792/' WHERE id = 20;
UPDATE products SET image_url = 'https://images.unsplash.com/photo-1623018697148-8350cf18e64e', image_author = 'Jonas Hensel', image_source = 'https://unsplash.com/photos/3Cpws7ibtfo' WHERE id = 21;
UPDATE products SET image_url = 'https://images.unsplash.com/photo-1502825926876-e8819fbb2fd0', image_author = 'Joanna Kosinska', image_source = 'https://unsplash.com/photos/6MlhT-THmk4' WHERE id = 22;
UPDATE products SET image_url = 'https://images.unsplash.com/photo-1585226906833-c76e175379f7', image_author = 'Rita Patrese', image_source = 'https://unsplash.com/photos/njXuDXO5Qd8' WHERE id = 23;
UPDATE products SET image_url = 'https://images.pexels.com/photos/6086888/pexels-photo-6086888.jpeg', image_author = 'Eva Bronzini', image_source = 'https://www.pexels.com/photo/6086888/' WHERE id = 24;
UPDATE products SET image_url = 'https://images.unsplash.com/photo-1609622511068-70c39f3c2253', image_author = 'Ulyana Grishina', image_source = 'https://unsplash.com/photos/aAKjE83oKqs' WHERE id = 25;
UPDATE extras SET image_url = 'https://images.unsplash.com/photo-1593095948071-474c5cc2989d', image_author = 'HowToGym', image_source = 'https://unsplash.com/photos/S9NchuPb79I' WHERE code = 'whey_protein';
