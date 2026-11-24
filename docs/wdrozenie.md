# Wdrożenie na serwer VPS (zadania 15, 16, 22, 44, 51 – DevOps)

Instrukcja krok po kroku dla serwera **Ubuntu 24.04** z Apache, PHP 8.3 i MySQL 8.
Zakłada, że macie domenę (albo darmową subdomenę) wskazującą rekordem A na adres IP serwera.
Bez domeny Let's Encrypt nie wystawi certyfikatu HTTPS.

W przykładach podmieńcie:

| Zapis w instrukcji | Co wpisać |
| --- | --- |
| `acai.example.pl` | Wasza domena |
| `HASLO_APLIKACJI`, `HASLO_BACKUPU` | dwa różne, wygenerowane hasła: `openssl rand -base64 24` |
| `WASZ_LOGIN` | konto lub organizacja na GitHubie z repozytorium `AcaiCup` |

Wszystkie polecenia wykonuje się jako zwykły użytkownik z `sudo` (nie logujcie się jako root).

## 1. Pakiety

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y apache2 mysql-server \
    php8.3 libapache2-mod-php8.3 php8.3-mysql php8.3-mbstring php8.3-curl \
    git unzip certbot python3-certbot-apache
sudo a2enmod rewrite headers ssl
```

`php8.3-mysql` daje PDO dla MySQL, `php8.3-curl` jest potrzebny tylko do testów (`tests/api_test.php`).

## 2. Baza i użytkownicy z minimalnymi uprawnieniami

Aplikacja łączy się użytkownikiem `acai_app`, który może tylko czytać i zmieniać **dane**.
Nie może usuwać tabel ani zmieniać ich struktury, więc nawet po włamaniu do aplikacji
nikt nie skasuje bazy jednym `DROP TABLE`. Kopie zapasowe robi osobny użytkownik `acai_backup`, który może tylko czytać.

```bash
sudo mysql_secure_installation      # usuń anonimowych użytkowników i bazę test
sudo mysql
```

```sql
CREATE DATABASE acai_cup CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER 'acai_app'@'localhost' IDENTIFIED BY 'HASLO_APLIKACJI';
GRANT SELECT, INSERT, UPDATE, DELETE ON acai_cup.* TO 'acai_app'@'localhost';

CREATE USER 'acai_backup'@'localhost' IDENTIFIED BY 'HASLO_BACKUPU';
GRANT SELECT, LOCK TABLES, SHOW VIEW, TRIGGER ON acai_cup.* TO 'acai_backup'@'localhost';

FLUSH PRIVILEGES;
EXIT;
```

MySQL nasłuchuje tylko na `localhost` (domyślnie w Ubuntu) – nie otwieramy portu 3306 na zewnątrz.

## 3. Kod aplikacji

Repozytorium jest prywatne, więc serwer dostaje własny klucz tylko do odczytu
(GitHub → repozytorium → Settings → **Deploy keys** → Add deploy key, bez „Allow write access”).

```bash
sudo mkdir -p /var/www/acai-cup && sudo chown $USER:www-data /var/www/acai-cup
ssh-keygen -t ed25519 -C "deploy acai-cup" -f ~/.ssh/acai_deploy -N ""
cat ~/.ssh/acai_deploy.pub          # ten tekst wklejcie jako Deploy key
GIT_SSH_COMMAND="ssh -i ~/.ssh/acai_deploy" git clone git@github.com:WASZ_LOGIN/AcaiCup.git /var/www/acai-cup

cd /var/www/acai-cup
sudo mysql acai_cup < database/schema.sql
sudo mysql acai_cup < database/seed.sql
```

Konfiguracja (plik poza repozytorium, czytelny tylko dla właściciela i Apache):

```bash
cp config/config.example.php config/config.php
nano config/config.php             # user 'acai_app', hasło HASLO_APLIKACJI; 'debug' zostaje false
sudo chown $USER:www-data config/config.php && chmod 640 config/config.php
```

Katalog na log aplikacji (`logs/app.log`) – Apache musi móc do niego pisać:

```bash
mkdir -p logs && sudo chown www-data:www-data logs && sudo chmod 750 logs
```

### Hasła kont personelu – zmieńcie od razu

Hasła `pracownik123` i `kierownik123` z `seed.sql` są w repozytorium, więc na serwerze nie mogą zostać.

```bash
php -r 'echo password_hash(readline("Nowe hasło: "), PASSWORD_DEFAULT), "\n";'
sudo mysql acai_cup -e "UPDATE users SET password_hash='TU_WKLEJ_WYNIK' WHERE login='kierownik';"
# to samo dla login='pracownik' z innym hasłem
```

## 4. Apache: z internetu widać tylko `public/`

`/etc/apache2/sites-available/acai-cup.conf`:

```apache
<VirtualHost *:80>
    ServerName acai.example.pl
    DocumentRoot /var/www/acai-cup/public

    # AllowOverride All – Apache czyta public/.htaccess (nagłówki bezpieczeństwa, CSP)
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

Dlaczego tak: `src/`, `config/`, `database/`, `logs/` i `scripts/` leżą obok `public/`,
więc żaden adres w przeglądarce do nich nie prowadzi. Sprawdzenie:
`http://acai.example.pl/api/menu.php` zwraca JSON z `"ok": true`.

## 5. HTTPS przez Let's Encrypt

```bash
sudo certbot --apache -d acai.example.pl --redirect -m WASZ_EMAIL --agree-tos
sudo certbot renew --dry-run       # test automatycznego odnawiania (certyfikat ważny 90 dni)
```

Certbot sam dopisze wersję `:443` strony i przekierowanie z `http://` na `https://`.
Odnawianie działa automatycznie (timer `certbot.timer`).

## 6. Ustawienia PHP na produkcji

`/etc/php/8.3/apache2/conf.d/99-acai.ini`:

```ini
display_errors = Off               ; błędy nigdy nie trafiają do przeglądarki
log_errors = On
error_log = /var/log/php/acai-cup.log
expose_php = Off                   ; nie zdradzaj wersji PHP w nagłówkach
session.cookie_httponly = 1
session.cookie_secure = 1
session.cookie_samesite = Strict
session.use_strict_mode = 1
```

```bash
sudo mkdir -p /var/log/php && sudo chown www-data:www-data /var/log/php
sudo systemctl reload apache2
```

Aplikacja i tak ustawia te opcje sesji w `src/auth.php` (flagę `Secure` włącza sama, gdy strona działa po HTTPS).

## 7. Logi

| Log | Co w nim jest | Gdzie |
| --- | --- | --- |
| aplikacji | nieprzewidziane błędy API, błędy połączenia z bazą (`src/errors.php`) | `/var/www/acai-cup/logs/app.log` |
| PHP | błędy, których aplikacja nie złapała | `/var/log/php/acai-cup.log` |
| Apache | każde żądanie i błędy serwera | `/var/log/apache2/acai-cup-*.log` |
| kopii zapasowych | wynik każdego uruchomienia `backup.sh` | `/var/log/acai-backup.log` |

Żaden z nich nie leży w `public/`, więc nie da się go pobrać z przeglądarki.
Żeby logi nie rosły bez końca, `/etc/logrotate.d/acai-cup`:

```
/var/www/acai-cup/logs/app.log /var/log/php/acai-cup.log /var/log/acai-backup.log {
    weekly
    rotate 8
    compress
    missingok
    notifempty
    create 640 www-data www-data
}
```

Podgląd na żywo: `sudo tail -f /var/www/acai-cup/logs/app.log`.

## 8. Kopia zapasowa bazy (codziennie, cron)

Dane logowania dla `mysqldump` w pliku tylko dla roota (nie w skrypcie i nie w cronie):

```bash
sudo mkdir -p /etc/acai-cup
sudo tee /etc/acai-cup/backup.cnf > /dev/null <<'EOF'
[client]
user=acai_backup
password=HASLO_BACKUPU
EOF
sudo chmod 600 /etc/acai-cup/backup.cnf

chmod +x scripts/backup.sh scripts/restore-test.sh
sudo scripts/backup.sh                                # pierwsza kopia ręcznie
sudo cp scripts/acai-backup.cron /etc/cron.d/acai-backup
sudo chmod 644 /etc/cron.d/acai-backup
```

Kopie trafiają do `/var/backups/acai-cup/` (codziennie o 3:15, trzymane 14 dni).
Raz w tygodniu (i przed oddaniem projektu) sprawdźcie, że kopia się przywraca:

```bash
sudo scripts/restore-test.sh        # wgrywa najnowszą kopię do osobnej bazy i porównuje tabele
```

Przywrócenie produkcji z kopii (awaria): `gunzip -c /var/backups/acai-cup/PLIK.sql.gz | sudo mysql acai_cup`.

Kopia na tym samym serwerze nie chroni przed awarią dysku. Raz na jakiś czas ściągnijcie
najnowszy plik na swój komputer: `scp serwer:/var/backups/acai-cup/PLIK.sql.gz .`

## 9. Zapora

```bash
sudo ufw allow OpenSSH
sudo ufw allow "Apache Full"        # porty 80 i 443
sudo ufw enable
```

## 10. Kolejne wdrożenia (koniec każdego etapu)

```bash
cd /var/www/acai-cup
sudo scripts/backup.sh                                        # zawsze kopia przed zmianą
GIT_SSH_COMMAND="ssh -i ~/.ssh/acai_deploy" git pull --ff-only
```

Jeśli w `database/migrations/` pojawił się nowy plik, uruchamiacie go **raz**, po kolei:

```bash
sudo mysql acai_cup < database/migrations/001_zdjecia_skladnikow.sql   # 03.11.2026: zdjęcia składników
sudo mysql acai_cup < database/migrations/002_wlasne_zdjecia.sql       # 10.11.2026: własne zdjęcia
sudo mysql acai_cup < database/migrations/003_panel_personelu.sql      # 24.11.2026: zamówienia i panel
```

Na nowej instalacji migracje są niepotrzebne – `schema.sql` i `seed.sql` już je zawierają.
**Nigdy nie uruchamiajcie ponownie `schema.sql` na działającej bazie** – kasuje wszystkie tabele.

## 11. Lista kontrolna po wdrożeniu

- [ ] `https://acai.example.pl` otwiera konfigurator, kłódka w przeglądarce
- [ ] `http://…` przekierowuje na `https://…`
- [ ] znacznik „dane testowe” na stronie **nie** jest widoczny (menu idzie z bazy)
- [ ] zamówienie z płatnością przy kasie dostaje numer; płatność online pokazuje stronę „wkrótce”
- [ ] `https://acai.example.pl/panel/` – logowanie działa, hasła kont z seeda są zmienione
- [ ] `config.php` istnieje tylko na serwerze, `debug` = false, uprawnienia 640
- [ ] w nagłówkach strony są `Content-Security-Policy` i `X-Frame-Options: DENY` (DevTools → Network)
- [ ] `sudo scripts/backup.sh` tworzy plik, `sudo scripts/restore-test.sh` kończy się „Przywracanie działa.”
- [ ] `/etc/cron.d/acai-backup` istnieje; następnego dnia w `/var/log/acai-backup.log` jest wpis `OK`

Testy całej ścieżki można puścić na serwerze testowym (nie na produkcji, bo składają zamówienia):
`php tests/api_test.php https://test.acai.example.pl`.
