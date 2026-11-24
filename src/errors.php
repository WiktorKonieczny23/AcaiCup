<?php
/**
 * Obsługa błędów w jednym miejscu (zadanie 43, BE – etap E4).
 *
 * Zasada: użytkownik widzi tylko ogólny komunikat i stały kod błędu,
 * a szczegóły (treść wyjątku, plik, linia) trafiają do logu poza public/.
 *
 * Dwa rodzaje błędów:
 *  - AppError – błąd „przewidziany”, np. zły rozmiar, produkt niedostępny,
 *    brak logowania. Ma kod dla frontendu, komunikat dla klienta i kod HTTP.
 *  - każdy inny wyjątek (np. PDOException) – błąd „nieprzewidziany”.
 *    Klient dostaje „Coś poszło nie tak”, a my mamy wpis w logu.
 */

declare(strict_types=1);

require_once __DIR__ . '/response.php';

/** Błąd, który można bezpiecznie pokazać użytkownikowi. */
class AppError extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,   // np. "PRODUCT_UNAVAILABLE"
        string $message,                     // tekst dla użytkownika (po polsku)
        public readonly int $httpStatus = 400
    ) {
        parent::__construct($message);
    }
}

/**
 * Dopisuje linię do logu aplikacji: logs/app.log w katalogu projektu
 * (obok public/, więc nie da się go pobrać z przeglądarki).
 */
function app_log(string $message): void
{
    $file = app_config()['log_file'] ?? (__DIR__ . '/../logs/app.log');
    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0750, true);
    }
    $line = sprintf("[%s] %s\n", date('Y-m-d H:i:s'), $message);
    // Gdy nie da się pisać do pliku, użyj zwykłego logu PHP/Apache.
    if (@file_put_contents($file, $line, FILE_APPEND | LOCK_EX) === false) {
        error_log('[acai-cup] ' . $message);
    }
}

/**
 * Rejestruje jeden wspólny „łapacz” wyjątków i błędów PHP.
 * Wywoływana raz, w src/bootstrap.php, na początku każdego endpointu.
 */
function register_error_handlers(): void
{
    // Ostrzeżenia i notice PHP zamieniamy na wyjątki – nie mogą „przeciec” do odpowiedzi JSON.
    ini_set('display_errors', '0');
    set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
        if (!(error_reporting() & $severity)) {
            return false; // błąd wyciszony operatorem @
        }
        throw new ErrorException($message, 0, $severity, $file, $line);
    });

    set_exception_handler(function (Throwable $e): void {
        if ($e instanceof AppError) {
            json_error($e->errorCode, $e->getMessage(), $e->httpStatus);
        }

        // Nieprzewidziany błąd: szczegóły do logu, klient dostaje ogólny komunikat.
        $where = ($_SERVER['REQUEST_METHOD'] ?? 'CLI') . ' ' . ($_SERVER['REQUEST_URI'] ?? '');
        app_log(sprintf('%s: %s in %s:%d | %s', get_class($e), $e->getMessage(), $e->getFile(), $e->getLine(), $where));

        $message = 'Coś poszło nie tak. Spróbuj ponownie za chwilę.';
        if (app_config()['debug'] ?? false) {
            $message .= ' [debug: ' . $e->getMessage() . ']'; // tylko lokalnie, nigdy na serwerze
        }
        json_error('SERVER_ERROR', $message, 500);
    });
}
