/**
 * Jedno miejsce do rozmawiania z API (etap E2–E3, FE).
 *
 * apiRequest() wysyła żądanie, sprawdza wspólny format odpowiedzi
 * {"ok": true, "data": …} / {"ok": false, "error": {code, message}}
 * i zwraca samo `data`. Każdy błąd zamienia na ApiError z kodem i komunikatem
 * po polsku, który można od razu pokazać użytkownikowi.
 *
 * Używają go: app.js (konfigurator) i panel.js (panel personelu).
 */

export class ApiError extends Error {
    /**
     * @param {string} code    stały kod błędu z API, np. 'PRODUCT_UNAVAILABLE'
     * @param {string} message komunikat dla użytkownika
     * @param {number} status  kod HTTP (0 = brak połączenia)
     */
    constructor(code, message, status) {
        super(message);
        this.code = code;
        this.status = status;
    }
}

/**
 * @param {string} url  np. 'api/orders.php'
 * @param {{method?: string, body?: object|null, csrfToken?: string|null}} options
 * @returns {Promise<any>} pole `data` z odpowiedzi
 */
export async function apiRequest(url, { method = 'GET', body = null, csrfToken = null } = {}) {
    const headers = { Accept: 'application/json' };
    if (body !== null) headers['Content-Type'] = 'application/json';
    if (csrfToken) headers['X-CSRF-Token'] = csrfToken; // ochrona przed CSRF w panelu

    let response;
    try {
        response = await fetch(url, {
            method,
            headers,
            body: body !== null ? JSON.stringify(body) : undefined,
            cache: 'no-store',
            credentials: 'same-origin', // wysyłaj ciasteczko sesji tylko do własnej domeny
        });
    } catch {
        throw new ApiError('NETWORK', 'Brak połączenia z serwerem. Sprawdź internet i spróbuj ponownie.', 0);
    }

    let json = null;
    try {
        json = await response.json();
    } catch {
        // np. strona błędu serwera w HTML zamiast JSON-a
    }
    if (!json || typeof json.ok !== 'boolean') {
        throw new ApiError('BAD_RESPONSE', 'Serwer zwrócił nieoczekiwaną odpowiedź. Spróbuj za chwilę.', response.status);
    }
    if (!json.ok) {
        throw new ApiError(json.error?.code ?? 'ERROR', json.error?.message ?? 'Wystąpił błąd.', response.status);
    }
    return json.data;
}
