#!/usr/bin/env bash
# =====================================================================
# Test przywracania kopii zapasowej (zadanie 50, DevOps – etap E5).
#
# Kopia, której nikt nie przywrócił, to tylko nadzieja. Ten skrypt:
#   1. bierze NAJNOWSZY plik z katalogu kopii (albo plik podany jako argument),
#   2. wgrywa go do osobnej bazy acai_cup_restore_test (produkcja nietknięta),
#   3. porównuje liczbę wierszy w najważniejszych tabelach z bazą produkcyjną,
#   4. usuwa bazę testową.
#
# Uruchomienie:  sudo /var/www/acai-cup/scripts/restore-test.sh [plik.sql.gz]
# Wymaga konta z prawem CREATE/DROP (root przez gniazdo albo backup.cnf z takim kontem).
# =====================================================================
set -euo pipefail

DB_NAME="${DB_NAME:-acai_cup}"
TEST_DB="${DB_NAME}_restore_test"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/acai-cup}"
CREDENTIALS="${CREDENTIALS:-/etc/acai-cup/admin.cnf}"

OPTIONS=()
[[ -f "$CREDENTIALS" ]] && OPTIONS+=("--defaults-extra-file=$CREDENTIALS")
sql() { mysql "${OPTIONS[@]}" --batch --skip-column-names "$@"; }

FILE="${1:-$(ls -1t "$BACKUP_DIR"/"${DB_NAME}"-*.sql.gz | head -n 1)}"
echo "Przywracam: $FILE → $TEST_DB"

sql -e "DROP DATABASE IF EXISTS \`$TEST_DB\`; CREATE DATABASE \`$TEST_DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
gunzip -c "$FILE" | sql "$TEST_DB"

STATUS=0
for TABLE in sizes products users orders order_items order_status_log; do
    LIVE=$(sql -e "SELECT COUNT(*) FROM \`$DB_NAME\`.\`$TABLE\`")
    RESTORED=$(sql -e "SELECT COUNT(*) FROM \`$TEST_DB\`.\`$TABLE\`")
    # Produkcja mogła dostać nowe zamówienia po zrobieniu kopii – kopia nie może mieć WIĘCEJ.
    if (( RESTORED > LIVE )) || (( RESTORED == 0 && LIVE > 0 && TABLE != "order_status_log" )); then
        echo "BŁĄD  $TABLE: kopia $RESTORED, produkcja $LIVE"; STATUS=1
    else
        echo "OK    $TABLE: kopia $RESTORED, produkcja $LIVE"
    fi
done

sql -e "DROP DATABASE \`$TEST_DB\`;"
[[ $STATUS -eq 0 ]] && echo "Przywracanie działa." || echo "Przywracanie NIE działa – sprawdź kopię!"
exit $STATUS
