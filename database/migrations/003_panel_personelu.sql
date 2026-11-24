-- Migracja 003 (24.11.2026): etap E2–E3 – zamówienia i panel personelu.
-- Uruchom na istniejącej bazie (phpMyAdmin → baza acai_cup → SQL) po migracjach 001 i 002.
-- Nowa instalacja (schema.sql + seed.sql) ma już te zmiany.

SET NAMES utf8mb4;

-- 1. Historia statusów zapisuje też złożenie zamówienia przez klienta:
--    old_status = NULL i user_id = NULL (klient jest anonimowy).
ALTER TABLE order_status_log
    MODIFY user_id    TINYINT UNSIGNED NULL,
    MODIFY old_status ENUM('new', 'preparing', 'ready', 'collected', 'cancelled') NULL,
    ADD INDEX idx_log_order (order_id, changed_at);

-- 2. Nieudane próby logowania (limit: 5 na login i 20 na adres IP w ciągu 15 minut).
CREATE TABLE IF NOT EXISTS login_attempts (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    login         VARCHAR(40)  NOT NULL,
    ip            VARCHAR(45)  NOT NULL,
    attempted_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_attempts_login (login, attempted_at),
    INDEX idx_attempts_ip (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
