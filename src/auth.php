<?php
/**
 * Logowanie personelu, role i ochrona CSRF (zadanie 33, BE – etap E3).
 *
 * Jak to działa w skrócie:
 *  1. login.php wywołuje attempt_login(): hasło sprawdza password_verify(),
 *     po sukcesie session_regenerate_id(true) nadaje NOWY identyfikator sesji,
 *     a w sesji zapisujemy id, login, rolę i losowy token CSRF.
 *  2. Każdy endpoint panelu zaczyna od require_role('employee', 'manager')
 *     albo require_role('manager'). Bez tego wywołania endpoint byłby publiczny!
 *  3. Każdy endpoint, który ZMIENIA dane (POST), wywołuje też require_csrf():
 *     przeglądarka musi odesłać token z sesji w nagłówku X-CSRF-Token.
 *     Obca strona nie zna tokenu, więc nie podrobi żądania w imieniu kierownika.
 *
 * Ukrywanie przycisków w panelu to tylko wygoda. Prawdziwa kontrola dostępu jest tutaj.
 */

declare(strict_types=1);

require_once __DIR__ . '/errors.php';

const SESSION_IDLE_SECONDS = 1800;   // wylogowanie po 30 min bezczynności
const LOGIN_MAX_ATTEMPTS   = 5;      // nieudane próby na jeden login…
const LOGIN_MAX_PER_IP     = 20;     // …albo z jednego adresu IP (atak na wiele loginów)…
const LOGIN_WINDOW_MINUTES = 15;     // …w ciągu tylu minut blokują logowanie

/** Uruchamia sesję z bezpiecznymi ustawieniami ciasteczka. */
function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    ini_set('session.use_strict_mode', '1');   // nie przyjmuj identyfikatora sesji wymyślonego przez klienta
    ini_set('session.use_only_cookies', '1');  // identyfikator tylko w ciasteczku, nigdy w adresie
    session_name('ACAI_PANEL');
    session_set_cookie_params([
        'lifetime' => 0,          // do zamknięcia przeglądarki
        'path'     => '/',
        'secure'   => $https,     // na serwerze z HTTPS ciasteczko leci tylko szyfrowanym połączeniem
        'httponly' => true,       // JavaScript nie odczyta ciasteczka (utrudnia kradzież przez XSS)
        'samesite' => 'Strict',   // przeglądarka nie dołączy ciasteczka do żądań z obcych stron
    ]);
    session_start();

    // Wylogowanie po bezczynności.
    $last = $_SESSION['last_activity'] ?? null;
    if ($last !== null && time() - $last > SESSION_IDLE_SECONDS) {
        $_SESSION = [];
        session_regenerate_id(true);
    }
    $_SESSION['last_activity'] = time();
}

/** Zalogowany użytkownik ['id', 'login', 'role'] albo null. */
function current_user(): ?array
{
    start_session();
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    return ['id' => (int) $_SESSION['user_id'], 'login' => $_SESSION['login'], 'role' => $_SESSION['role']];
}

/**
 * Przepuszcza tylko zalogowanych z jedną z podanych ról.
 * Brak logowania → 401, zła rola → 403. Zwraca zalogowanego użytkownika.
 *
 * Przykład: require_role('manager');  albo  require_role('employee', 'manager');
 */
function require_role(string ...$roles): array
{
    $user = current_user();
    if ($user === null) {
        throw new AppError('NOT_LOGGED_IN', 'Zaloguj się, aby kontynuować.', 401);
    }
    if (!in_array($user['role'], $roles, true)) {
        throw new AppError('FORBIDDEN', 'Nie masz uprawnień do tej operacji.', 403);
    }
    return $user;
}

/** Token CSRF tej sesji (tworzy go przy pierwszym użyciu). */
function csrf_token(): string
{
    start_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // 64 losowe znaki
    }
    return $_SESSION['csrf_token'];
}

/** Przerywa (403), jeśli nagłówek X-CSRF-Token nie zgadza się z tokenem z sesji. */
function require_csrf(): void
{
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    // hash_equals porównuje w stałym czasie – nie zdradza, ile znaków się zgadza.
    if (!is_string($sent) || $sent === '' || !hash_equals(csrf_token(), $sent)) {
        throw new AppError('CSRF_INVALID', 'Sesja wygasła lub żądanie jest nieprawidłowe. Odśwież stronę i spróbuj ponownie.', 403);
    }
}

/**
 * Sprawdza login i hasło. Po sukcesie zapisuje użytkownika w NOWEJ sesji.
 * Zwraca ['id', 'login', 'role'].
 */
function attempt_login(PDO $pdo, string $login, string $password): array
{
    $ip = substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 0, 45);

    // 1. Limit prób w ostatnich 15 minutach: 5 dla loginu, 20 dla adresu IP.
    $stmt = $pdo->prepare(
        'SELECT SUM(login = ?) AS by_login, SUM(ip = ?) AS by_ip
           FROM login_attempts
          WHERE attempted_at > NOW() - INTERVAL ' . LOGIN_WINDOW_MINUTES . ' MINUTE'
    );
    $stmt->execute([$login, $ip]);
    $recent = $stmt->fetch();
    if ((int) $recent['by_login'] >= LOGIN_MAX_ATTEMPTS || (int) $recent['by_ip'] >= LOGIN_MAX_PER_IP) {
        throw new AppError('TOO_MANY_ATTEMPTS', 'Za dużo nieudanych prób. Spróbuj ponownie za 15 minut.', 429);
    }

    // 2. Użytkownik i hasło.
    $stmt = $pdo->prepare('SELECT id, login, password_hash, role, is_active FROM users WHERE login = ?');
    $stmt->execute([$login]);
    $user = $stmt->fetch();

    $valid = $user && $user['is_active'] && password_verify($password, $user['password_hash']);
    if (!$valid) {
        $pdo->prepare('INSERT INTO login_attempts (login, ip) VALUES (?, ?)')->execute([substr($login, 0, 40), $ip]);
        // Ten sam komunikat dla złego loginu i złego hasła – nie zdradzamy, które konta istnieją.
        throw new AppError('INVALID_CREDENTIALS', 'Nieprawidłowy login lub hasło.', 401);
    }

    // 3. Sukces: czyścimy licznik prób tego loginu i tego adresu, zapisujemy datę logowania.
    $pdo->prepare('DELETE FROM login_attempts WHERE login = ? OR ip = ?')->execute([$login, $ip]);
    $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([$user['id']]);

    // Gdy PHP ma nowszy algorytm haszowania, przy okazji przeliczamy hash.
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
    }

    // 4. Nowa sesja: stary identyfikator przestaje działać (ochrona przed przejęciem sesji).
    start_session();
    session_regenerate_id(true);
    $_SESSION = [
        'user_id'       => (int) $user['id'],
        'login'         => $user['login'],
        'role'          => $user['role'],
        'last_activity' => time(),
        'csrf_token'    => bin2hex(random_bytes(32)),
    ];

    return ['id' => (int) $user['id'], 'login' => $user['login'], 'role' => $user['role']];
}

/** Wylogowanie: czyści sesję i usuwa ciasteczko. */
function logout(): void
{
    start_session();
    $_SESSION = [];
    $params = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires'  => time() - 3600,
        'path'     => $params['path'],
        'secure'   => $params['secure'],
        'httponly' => $params['httponly'],
        'samesite' => $params['samesite'],
    ]);
    session_destroy();
}
