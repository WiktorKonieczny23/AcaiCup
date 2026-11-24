#!/usr/bin/env bash
# =====================================================================
# Kopia zapasowa bazy Acai Cup (zadanie 44, DevOps – etap E4).
#
# Co robi:
#   1. mysqldump całej bazy w jednej transakcji (--single-transaction:
#      spójna kopia bez blokowania tabel, aplikacja działa dalej),
#   2. pakuje kopię gzipem do pliku acai_cup-RRRR-MM-DD_GGMM.sql.gz,
#   3. sprawdza, czy archiwum nie jest uszkodzone (gzip -t),
#   4. usuwa kopie starsze niż KEEP_DAYS dni (domyślnie 14).
#
# Uruchomienie ręczne:   sudo /var/www/acai-cup/scripts/backup.sh
# Automatycznie (cron):  scripts/acai-backup.cron → /etc/cron.d/acai-backup
#
# Dane logowania do bazy NIE są w tym pliku. Skrypt używa:
#   - pliku /etc/acai-cup/backup.cnf (jeśli istnieje, chmod 600), albo
#   - logowania root przez gniazdo (Ubuntu: sudo mysql bez hasła).
# =====================================================================
set -euo pipefail   # przerwij przy pierwszym błędzie, także w środku potoku |
umask 077           # nowe pliki kopii czytelne tylko dla właściciela

DB_NAME="${DB_NAME:-acai_cup}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/acai-cup}"
KEEP_DAYS="${KEEP_DAYS:-14}"
CREDENTIALS="${CREDENTIALS:-/etc/acai-cup/backup.cnf}"

mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"          # kopie zawierają hashe haseł – tylko dla właściciela

FILE="$BACKUP_DIR/${DB_NAME}-$(date +%F_%H%M).sql.gz"

OPTIONS=()
if [[ -f "$CREDENTIALS" ]]; then
    OPTIONS+=("--defaults-extra-file=$CREDENTIALS")   # musi być PIERWSZĄ opcją
fi

# Zapis do pliku .tmp i zmiana nazwy dopiero po sukcesie: nie zostanie połowa kopii.
mysqldump "${OPTIONS[@]}" --single-transaction --no-tablespaces --default-character-set=utf8mb4 "$DB_NAME" \
    | gzip > "$FILE.tmp"
mv "$FILE.tmp" "$FILE"
gzip -t "$FILE"                  # test integralności archiwum

find "$BACKUP_DIR" -name "${DB_NAME}-*.sql.gz" -mtime +"$KEEP_DAYS" -delete

echo "$(date '+%F %T') OK $FILE ($(du -h "$FILE" | cut -f1))"
