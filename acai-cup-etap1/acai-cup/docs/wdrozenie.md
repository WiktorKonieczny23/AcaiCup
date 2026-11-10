# Wdrożenie na serwer (zadania 15, 16, 22 – DevOps)

Instrukcja dla serwera VPS z **Ubuntu 24.04**. Zakłada, że macie już domenę
(albo darmową subdomenę) wskazującą na adres IP serwera. Bez domeny nie da się
wystawić certyfikatu HTTPS.

W przykładach podmieńcie:

- `acai.example.pl` – Wasza domena,
- `TU_SILNE_HASLO` – wygenerowane hasło do bazy (np. `openssl rand -base64 24`).

## 1. Pakiety (zadanie 15)

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y apache2 mysql-server php8.3 php8.3-mysql php8.3-mbstring libapache2-mod-php8.3 git unzip
sudo a2enmod rewrite headers ssl
```

## 2. Baza i użytkownik z minimalnymi uprawnieniami

Aplikacja łączy się osobnym użytkownikiem, który może tylko czytać i zmieniać
dane. Nie może kasować tabel ani zmieniać ich struktury.

```bash
sudo mysql_secure_installation
sudo mysql
```

```sql
CREATE DATABASE acai_cup CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'acai_app'@'localhost' IDENTIFIED BY 'TU_SILNE_HASLO';
GRANT SELECT, INSERT, UPDATE, DELETE ON acai_cup.* TO 'acai_app'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

## 3. Kod aplikacji

Repozytorium jest prywatne, więc serwer potrzebuje klucza do odczytu
(GitHub → Settings repozytorium → **Deploy keys**, tylko odczyt).

```bash
sudo mkdir -p /var/www/acai-cup && sudo chown $USER:www-data /var/www/acai-cup
ssh-keygen -t ed25519 -C "deploy acai-cup" -f ~/.ssh/acai_deploy   # klucz publiczny wklejcie jako Deploy key
GIT_SSH_COMMAND="ssh -i ~/.ssh/acai_deploy" git clone git@github.com:WASZ_LOGIN/acai-cup.git /var/www/acai-cup

cd /var/www/acai-cup
sudo mysql acai_cup < database/schema.sql
sudo mysql acai_cup < database/seed.sql

cp config/config.example.php config/config.php
nano config/config.php          # wpisz hasło bazy, 'debug' zostaw false
chmod 640 config/config.php && sudo chown $USER:www-data config/config.php
```

Od razu zmieńcie hasła kont `pracownik` i `kierownik` z pliku seed:

```bash
php -r 'echo password_hash("NOWE_HASLO", PASSWORD_DEFAULT), "\n";'
sudo mysql acai_cup -e "UPDATE users SET password_hash='WYNIK_Z_GÓRY' WHERE login='kierownik';"
```

## 4. Apache: tylko `public/` widoczne z internetu

`/etc/apache2/sites-available/acai-cup.conf`:

```apache
<VirtualHost *:80>
    ServerName acai.example.pl
    DocumentRoot /var/www/acai-cup/public

    <Directory /var/www/acai-cup/public>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/acai-cup-error.log
    CustomLog ${APACHE_LOG_DIR}/acai-cup-access.log combined
</VirtualHost>
```

```bash
sudo a2ensite acai-cup && sudo a2dissite 000-default
sudo apache2ctl configtest && sudo systemctl reload apache2
```

Sprawdzenie: `http://acai.example.pl/api/menu.php` zwraca JSON z `"ok": true`,
a `http://acai.example.pl/../config/config.php` **nie** jest dostępny.

## 5. HTTPS (zadanie 16)

```bash
sudo apt install -y certbot python3-certbot-apache
sudo certbot --apache -d acai.example.pl --redirect
sudo certbot renew --dry-run      # test automatycznego odnawiania
```

Opcja `--redirect` przekierowuje cały ruch HTTP na HTTPS.

## 6. Ustawienia PHP na produkcji

`/etc/php/8.3/apache2/conf.d/99-acai.ini`:

```ini
display_errors = Off
log_errors = On
error_log = /var/log/php/acai-cup.log
expose_php = Off
session.cookie_httponly = 1
session.cookie_secure = 1
session.cookie_samesite = Strict
session.use_strict_mode = 1
```

```bash
sudo mkdir -p /var/log/php && sudo chown www-data:www-data /var/log/php
sudo systemctl reload apache2
```

Logi są poza katalogiem `public/`, więc nie da się ich pobrać z przeglądarki.

## 7. Zapora

```bash
sudo ufw allow OpenSSH
sudo ufw allow "Apache Full"
sudo ufw enable
```

MySQL nasłuchuje tylko na `localhost` – nie otwieramy portu 3306.

## 8. Kolejne wdrożenia (koniec każdego etapu)

```bash
cd /var/www/acai-cup
GIT_SSH_COMMAND="ssh -i ~/.ssh/acai_deploy" git pull --ff-only
# jeśli zmienił się schemat bazy – osobny plik migracji, nie ponowny seed!
mysql acai_cup < database/migrations/001_zdjecia_skladnikow.sql   # 03.11.2026: zdjęcia składników
```

Migracje uruchamiamy po kolei i tylko raz. Na nowej instalacji niepotrzebne,
bo `schema.sql` i `seed.sql` już je zawierają.

## 9. Backup (zadanie 44, etap E4)

`/etc/cron.d/acai-backup` – codziennie o 3:15, kopie z 14 dni:

```cron
15 3 * * * root mysqldump --single-transaction acai_cup | gzip > /var/backups/acai-$(date +\%F).sql.gz && find /var/backups -name 'acai-*.sql.gz' -mtime +14 -delete
```

Test przywracania (zadanie 50): `gunzip -c plik.sql.gz | sudo mysql acai_cup_test`.

## Lista kontrolna „hello world” (kryterium E1)

- [ ] `https://acai.example.pl` otwiera konfigurator, kłódka w przeglądarce
- [ ] `http://…` przekierowuje na `https://…`
- [ ] `/api/menu.php` zwraca dane z bazy (znacznik „dane testowe” na stronie **nie** jest widoczny)
- [ ] `config.php` istnieje tylko na serwerze, `debug` = false
- [ ] hasła kont testowych zmienione
