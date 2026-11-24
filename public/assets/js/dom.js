/**
 * Wspólne pomocnicze funkcje DOM dla konfiguratora (app.js) i panelu (panel.js).
 *
 * Bezpieczeństwo: tekst zawsze trafia do strony jako węzeł tekstowy
 * (document.createTextNode / textContent), nigdy przez innerHTML.
 * Nawet jeśli w nazwie produktu ktoś wpisze <script>, przeglądarka pokaże
 * to jako zwykły tekst (ochrona przed XSS).
 */

/**
 * Tworzy element HTML; dzieci mogą być tekstem lub elementami.
 * Przykład: h('button', { class: 'btn', onClick: save }, 'Zapisz')
 */
export function h(tag, props = {}, ...children) {
    const node = document.createElement(tag);
    for (const [key, value] of Object.entries(props)) {
        if (value === null || value === undefined || value === false) continue;
        if (key === 'class') node.className = value;
        else if (key === 'onClick') node.addEventListener('click', value);
        else if (key === 'onChange') node.addEventListener('change', value);
        else if (key === 'onSubmit') node.addEventListener('submit', value);
        else if (key === 'disabled' || key === 'checked' || key === 'required') node[key] = Boolean(value);
        else if (key === 'value') node.value = value;
        else node.setAttribute(key, value);
    }
    for (const child of children.flat()) {
        if (child === null || child === undefined || child === false) continue;
        node.append(child instanceof Node ? child : document.createTextNode(String(child)));
    }
    return node;
}

/** Odmiana liczebników: plural(2, 'warstwa', 'warstwy', 'warstw') → "2 warstwy". */
export function plural(n, one, few, many) {
    if (n === 1) return `${n} ${one}`;
    const last = n % 10, lastTwo = n % 100;
    return (last >= 2 && last <= 4 && (lastTwo < 12 || lastTwo > 14)) ? `${n} ${few}` : `${n} ${many}`;
}

/** Kwota w złotych: money.format(24) → "24,00 zł". */
export const money = new Intl.NumberFormat('pl-PL', { style: 'currency', currency: 'PLN' });
