/**
 * Panel personelu (zadania 34, 36, 37, 39 – FE).
 *
 * Start: GET api/admin/me.php
 *   - 200 → pokazujemy panel (rola + token CSRF z odpowiedzi),
 *   - 401 → pokazujemy formularz logowania.
 *
 * Zakładki:
 *   Zamówienia   – lista z filtrem statusu i przyciskami zmiany statusu (obie role),
 *   Produkty     – przełącznik dostępności (obie role), dodawanie i edycja (kierownik),
 *   Ceny         – ceny rozmiarów i białka (kierownik),
 *   Podsumowanie – sprzedaż w wybranym okresie (kierownik).
 *
 * Każde żądanie zmieniające dane wysyła token CSRF (nagłówek X-CSRF-Token).
 * Ukrywanie opcji kierownika przed pracownikiem to tylko wygoda – serwer i tak
 * sprawdza rolę w każdym endpoincie.
 */

import { apiRequest, ApiError } from './api.js';
import { h, money, plural } from './dom.js';

const API = '../api/admin/';
const ORDERS_REFRESH_MS = 20000;

const ROLE_NAMES = { employee: 'pracownik', manager: 'kierownik' };

// Teksty przycisków zmiany statusu (klucz = status docelowy).
const STATUS_ACTIONS = {
    preparing: 'Zacznij przygotowanie',
    ready: 'Gotowe do odbioru',
    collected: 'Wydane i zapłacone',
    cancelled: 'Anuluj',
};

const ORDER_FILTERS = [
    ['active', 'W toku'],
    ['new', 'Przyjęte'],
    ['preparing', 'W przygotowaniu'],
    ['ready', 'Gotowe'],
    ['collected', 'Odebrane'],
    ['cancelled', 'Anulowane'],
    ['all', 'Wszystkie'],
];

const SLOT_LABELS = { layer_1: 'Warstwa 1', layer_2: 'Warstwa 2', layer_3: 'Warstwa 3', layer_4: 'Warstwa 4' };

/**
 * Pozycje zamówienia do wyświetlenia: warstwy po kolei, a toppingi zgrupowane,
 * żeby podwójna porcja była widoczna jako „Banan ×2”.
 */
function orderLines(items) {
    const lines = items.filter((item) => item.slot.startsWith('layer_'))
        .map((item) => ({ label: SLOT_LABELS[item.slot], name: item.name, double: false }));
    const toppings = items.filter((item) => item.slot.startsWith('topping_')).map((item) => item.name);
    [...new Set(toppings)].forEach((name) => {
        const count = toppings.filter((t) => t === name).length;
        lines.push({ label: 'Topping', name: count > 1 ? `${name} ×${count}` : name, double: count > 1 });
    });
    return lines;
}

const state = {
    user: null,          // { login, role }
    csrf: null,          // token CSRF z sesji
    tab: 'orders',
    filter: 'active',
    catalog: null,       // dane z GET products.php
    editingId: null,     // id edytowanego produktu, 'new' dla nowego, null = formularz zamknięty
};

let ordersTimer = null;

const $ = (id) => document.getElementById(id);
const isManager = () => state.user?.role === 'manager';
const time = (datetime) => datetime.slice(11, 16); // "2026-11-24 12:05:00" → "12:05"

/* ------------------------------------------------------------------ */
/* Komunikacja z API                                                   */
/* ------------------------------------------------------------------ */

/** Wywołanie API panelu: dokleja token CSRF i obsługuje wygaśnięcie sesji. */
async function call(endpoint, { method = 'GET', body = null } = {}) {
    try {
        return await apiRequest(API + endpoint, { method, body, csrfToken: method === 'POST' ? state.csrf : null });
    } catch (error) {
        if (error instanceof ApiError && error.code === 'NOT_LOGGED_IN') {
            showLogin('Sesja wygasła. Zaloguj się ponownie.');
        }
        throw error;
    }
}

/** Komunikat na górze panelu: type = 'success' | 'danger'. Znika po 5 s. */
function message(text, type = 'success') {
    const box = $('panel-message');
    box.textContent = text;
    box.className = `alert alert-${type}`;
    box.hidden = false;
    clearTimeout(message.timer);
    message.timer = setTimeout(() => { box.hidden = true; }, 5000);
}

/** Pokazuje błąd z API (np. brak uprawnień) jako czerwony komunikat. */
function showError(error) {
    if (error instanceof ApiError && error.code === 'NOT_LOGGED_IN') return; // już pokazano logowanie
    message(error.message || 'Wystąpił błąd.', 'danger');
}

/* ------------------------------------------------------------------ */
/* Logowanie                                                           */
/* ------------------------------------------------------------------ */

function showLogin(info = '') {
    clearInterval(ordersTimer);
    state.user = null;
    state.csrf = null;
    $('panel-view').hidden = true;
    $('user-box').hidden = true;
    $('login-view').hidden = false;
    $('login-error').textContent = info;
    $('login').focus();
}

function showPanel(data) {
    state.user = data.user;
    state.csrf = data.csrf_token;
    $('login-view').hidden = true;
    $('panel-view').hidden = false;
    $('user-box').hidden = false;
    $('user-login').textContent = state.user.login;
    $('user-role').textContent = ROLE_NAMES[state.user.role] ?? state.user.role;

    // Opcje kierownika widzi tylko kierownik.
    document.querySelectorAll('[data-role="manager"]').forEach((node) => {
        node.hidden = !isManager();
    });
    openTab('orders');
}

async function login(event) {
    event.preventDefault();
    const loginValue = $('login').value.trim();
    const password = $('password').value;
    if (!loginValue || !password) {
        $('login-error').textContent = 'Podaj login i hasło.';
        return;
    }
    const button = event.submitter ?? $('login-form').querySelector('button');
    button.disabled = true;
    try {
        const data = await apiRequest(API + 'login.php', { method: 'POST', body: { login: loginValue, password } });
        $('password').value = '';
        $('login-error').textContent = '';
        showPanel(data);
    } catch (error) {
        $('login-error').textContent = error.message;
    } finally {
        button.disabled = false;
    }
}

async function logout() {
    try {
        await call('logout.php', { method: 'POST' });
    } catch { /* i tak pokazujemy logowanie */ }
    showLogin('Wylogowano.');
}

/* ------------------------------------------------------------------ */
/* Zakładki                                                            */
/* ------------------------------------------------------------------ */

function openTab(tab) {
    if (!isManager() && (tab === 'prices' || tab === 'summary')) tab = 'orders';
    state.tab = tab;
    document.querySelectorAll('.panel-tab').forEach((button) => {
        const active = button.dataset.tab === tab;
        button.classList.toggle('is-active', active);
        if (active) button.setAttribute('aria-current', 'page'); else button.removeAttribute('aria-current');
    });
    for (const name of ['orders', 'products', 'prices', 'summary']) {
        $(`view-${name}`).hidden = name !== tab;
    }

    clearInterval(ordersTimer);
    if (tab === 'orders') {
        loadOrders();
        ordersTimer = setInterval(loadOrders, ORDERS_REFRESH_MS); // nowe zamówienia pojawiają się same
    }
    if (tab === 'products') loadProducts();
    if (tab === 'prices') loadPrices();
    if (tab === 'summary') loadSummary();
}

/* ------------------------------------------------------------------ */
/* Zamówienia                                                          */
/* ------------------------------------------------------------------ */

async function loadOrders() {
    try {
        const data = await call(`orders.php?status=${encodeURIComponent(state.filter)}`);
        renderOrderFilters(data.counts);
        renderOrders(data.orders);
        $('orders-updated').textContent = `Odświeżono ${new Date().toLocaleTimeString('pl-PL', { hour: '2-digit', minute: '2-digit', second: '2-digit' })}`;
    } catch (error) {
        showError(error);
    }
}

function renderOrderFilters(counts) {
    const count = (key) => key === 'active'
        ? counts.new + counts.preparing + counts.ready
        : key === 'all' ? Object.values(counts).reduce((a, b) => a + b, 0) : counts[key];

    $('order-filters').replaceChildren(...ORDER_FILTERS.map(([key, label]) => h('button', {
        type: 'button',
        class: `filter-btn${state.filter === key ? ' is-active' : ''}`,
        'aria-pressed': state.filter === key ? 'true' : 'false',
        onClick: () => { state.filter = key; loadOrders(); },
    }, label, ' ', h('span', { class: 'filter-count' }, String(count(key))))));
}

function renderOrders(orders) {
    if (!orders.length) {
        $('order-list').replaceChildren(h('p', { class: 'text-secondary' }, 'Brak zamówień w tym statusie.'));
        return;
    }
    $('order-list').replaceChildren(...orders.map((order) => h('article', { class: 'order-card' },
        h('div', { class: 'd-flex justify-content-between align-items-start gap-2' },
            h('div', {},
                h('div', { class: 'order-card-code' }, order.order_code),
                h('div', { class: 'small text-secondary' }, `${time(order.created_at)} · rozmiar ${order.size}`),
            ),
            h('span', { class: 'order-status', 'data-status': order.status }, order.status_label),
        ),
        h('ul', { class: 'order-items' },
            orderLines(order.items).map((line) => h('li', { class: line.double ? 'fw-semibold' : null },
                h('span', { class: 'text-secondary' }, `${line.label}: `), line.name)),
            order.extra_toppings > 0
                ? h('li', { class: 'small text-secondary' }, `w tym ${plural(order.extra_toppings, 'dodatkowy topping', 'dodatkowe toppingi', 'dodatkowych toppingów')} (płatne)`)
                : null,
            order.extra_protein ? h('li', { class: 'fw-semibold' }, '+ białko serwatkowe') : null,
        ),
        h('div', { class: 'd-flex justify-content-between align-items-baseline mb-2' },
            h('strong', {}, money.format(order.total_price)),
            h('span', { class: 'small text-secondary' },
                order.payment_status === 'paid' ? 'zapłacone' : (order.payment_method === 'cash' ? 'do zapłaty przy kasie' : 'online – oczekuje')),
        ),
        h('div', { class: 'd-flex flex-wrap gap-2' },
            order.next_statuses.map((status) => h('button', {
                type: 'button',
                class: status === 'cancelled' ? 'btn btn-sm btn-outline-danger' : 'btn btn-sm btn-acai',
                onClick: (event) => changeStatus(order, status, event.currentTarget),
            }, STATUS_ACTIONS[status])),
        ),
    )));
}

async function changeStatus(order, status, button) {
    if (status === 'cancelled' && !window.confirm(`Anulować zamówienie ${order.order_code}?`)) return;
    button.disabled = true;
    try {
        const result = await call('order-status.php', { method: 'POST', body: { order_id: order.id, status } });
        message(`Zamówienie ${result.order_code}: ${result.status_label}.`);
        await loadOrders();
    } catch (error) {
        showError(error);
        button.disabled = false;
        loadOrders(); // ktoś inny mógł już zmienić status – pokaż aktualny stan
    }
}

/* ------------------------------------------------------------------ */
/* Produkty                                                            */
/* ------------------------------------------------------------------ */

async function loadProducts() {
    try {
        state.catalog = await call('products.php');
        renderProducts();
        renderProductForm();
    } catch (error) {
        showError(error);
    }
}

function renderProducts() {
    const { categories, products, allergens, extras } = state.catalog;
    const allergenName = new Map(allergens.map((a) => [a.id, a.name]));

    const groups = categories.map((category) => {
        const rows = products.filter((p) => p.category_id === category.id).map((product) => h('tr', { class: product.is_active ? '' : 'is-hidden-product' },
            h('td', {}, colorDot(product.color_hex), product.name,
                product.is_active ? null : h('span', { class: 'badge text-bg-secondary ms-2' }, 'ukryty')),
            h('td', { class: 'small text-secondary' }, `${product.portion_g} g · ${Math.round(product.kcal)} kcal`),
            h('td', { class: 'small text-secondary' }, product.allergen_ids.map((id) => allergenName.get(id)).join(', ') || '—'),
            h('td', {}, availabilitySwitch('product', product)),
            isManager() ? h('td', { class: 'text-end' }, h('button', {
                type: 'button', class: 'btn btn-sm btn-outline-secondary',
                onClick: () => { state.editingId = product.id; renderProductForm(); $('product-form-box').scrollIntoView({ behavior: 'smooth' }); },
            }, 'Edytuj')) : null,
        ));
        return h('section', { class: 'mb-4' },
            h('h2', { class: 'h6 panel-subtitle' }, category.type === 'layer' ? `Warstwa ${category.position} · ${category.name}` : category.name),
            h('div', { class: 'table-responsive' }, h('table', { class: 'table align-middle panel-table' },
                h('thead', {}, h('tr', {},
                    h('th', {}, 'Produkt'), h('th', {}, 'Porcja'), h('th', {}, 'Alergeny'), h('th', {}, 'Dostępny'),
                    isManager() ? h('th', {}, h('span', { class: 'visually-hidden' }, 'Akcje')) : null)),
                h('tbody', {}, rows),
            )),
        );
    });

    // Białko serwatkowe (tabela extras) – też można je wyłączyć, gdy się skończy.
    groups.push(h('section', { class: 'mb-4' },
        h('h2', { class: 'h6 panel-subtitle' }, 'Dodatki'),
        h('table', { class: 'table align-middle panel-table' }, h('tbody', {},
            extras.map((extra) => h('tr', {},
                h('td', {}, extra.name),
                h('td', { class: 'small text-secondary' }, `${extra.portion_g} g · +${money.format(extra.price)}`),
                h('td', {}, availabilitySwitch('extra', extra)),
            )),
        )),
    ));

    $('product-list').replaceChildren(...groups);
}

/** Kolorowa kropka produktu. Kolor ustawiamy przez CSSOM (style.backgroundColor), co jest zgodne z CSP. */
function colorDot(color) {
    const dot = h('span', { class: 'color-dot', 'aria-hidden': 'true' });
    dot.style.backgroundColor = color;
    return dot;
}

/** Przełącznik dostępności produktu lub dodatku (pracownik i kierownik). */
function availabilitySwitch(kind, item) {
    const id = `avail-${kind}-${item.id}`;
    const input = h('input', {
        id, type: 'checkbox', role: 'switch', class: 'form-check-input', checked: item.is_available,
        onChange: async (event) => {
            const checkbox = event.currentTarget;
            checkbox.disabled = true;
            try {
                const result = await call('product-availability.php', { method: 'POST', body: { kind, id: item.id, available: checkbox.checked } });
                item.is_available = result.available;
                message(`${result.name}: ${result.available ? 'dostępne' : 'niedostępne'}.`);
            } catch (error) {
                checkbox.checked = item.is_available; // cofnij przełącznik
                showError(error);
            } finally {
                checkbox.disabled = false;
            }
        },
    });
    return h('div', { class: 'form-check form-switch m-0' }, input,
        h('label', { class: 'visually-hidden', for: id }, `Dostępność: ${item.name}`));
}

/** Formularz dodawania / edycji produktu (tylko kierownik). */
function renderProductForm() {
    const box = $('product-form-box');
    if (!isManager() || state.editingId === null || !state.catalog) {
        box.replaceChildren();
        return;
    }
    const { categories, allergens, products } = state.catalog;
    const product = state.editingId === 'new'
        ? { category_id: categories[0].id, name: '', color_hex: '#7B3F8C', portion_g: 50, kcal: 0, protein_g: 0, carbs_g: 0, fat_g: 0,
            allergen_ids: [], image_url: '', image_author: '', image_source: '', is_available: true, is_active: true }
        : products.find((p) => p.id === state.editingId);

    const field = (name, label, attrs = {}) => h('div', { class: attrs.wide ? 'col-12 col-md-6' : 'col-6 col-md-3' },
        h('label', { class: 'form-label small', for: `pf-${name}` }, label),
        h('input', { id: `pf-${name}`, name, class: 'form-control', value: product[name] ?? '', ...attrs, wide: null }),
    );
    const check = (name, label) => h('div', { class: 'form-check' },
        h('input', { id: `pf-${name}`, name, type: 'checkbox', class: 'form-check-input', checked: product[name] }),
        h('label', { class: 'form-check-label', for: `pf-${name}` }, label));

    const form = h('form', { class: 'product-form', novalidate: '', onSubmit: saveProduct },
        h('h2', { class: 'h5 mb-3 panel-subtitle' }, state.editingId === 'new' ? 'Nowy produkt' : `Edycja: ${product.name}`),
        h('div', { class: 'row g-3' },
            h('div', { class: 'col-12 col-md-6' },
                h('label', { class: 'form-label small', for: 'pf-category_id' }, 'Kategoria'),
                h('select', { id: 'pf-category_id', name: 'category_id', class: 'form-select' },
                    categories.map((c) => h('option', { value: String(c.id), selected: c.id === product.category_id ? '' : null },
                        c.type === 'layer' ? `Warstwa ${c.position} · ${c.name}` : c.name))),
            ),
            field('name', 'Nazwa', { wide: true, maxlength: '60', required: true }),
            field('color_hex', 'Kolor warstwy', { type: 'color' }),
            field('portion_g', 'Porcja [g]', { type: 'number', min: '1', max: '1000', step: '1' }),
            field('kcal', 'kcal', { type: 'number', min: '0', max: '2000', step: '0.1' }),
            field('protein_g', 'Białko [g]', { type: 'number', min: '0', max: '200', step: '0.1' }),
            field('carbs_g', 'Węglowodany [g]', { type: 'number', min: '0', max: '200', step: '0.1' }),
            field('fat_g', 'Tłuszcz [g]', { type: 'number', min: '0', max: '200', step: '0.1' }),
            field('image_url', 'Zdjęcie (adres lub assets/img/products/…)', { wide: true, maxlength: '255' }),
            field('image_author', 'Autor zdjęcia', { wide: false, maxlength: '80' }),
            field('image_source', 'Strona zdjęcia (https://…)', { wide: false, maxlength: '255' }),
            h('fieldset', { class: 'col-12' },
                h('legend', { class: 'form-label small' }, 'Alergeny'),
                h('div', { class: 'allergen-checks' }, allergens.map((a) => h('div', { class: 'form-check' },
                    h('input', { id: `pf-al-${a.id}`, type: 'checkbox', class: 'form-check-input', name: 'allergen', value: String(a.id), checked: product.allergen_ids.includes(a.id) }),
                    h('label', { class: 'form-check-label', for: `pf-al-${a.id}` }, a.name)))),
            ),
            h('div', { class: 'col-12 d-flex flex-wrap gap-4' },
                check('is_available', 'Dostępny teraz'),
                check('is_active', 'Widoczny w menu (odznacz, żeby ukryć na stałe)'),
            ),
        ),
        h('p', { id: 'product-form-error', class: 'text-danger small mt-3 mb-0', role: 'alert' }),
        h('div', { class: 'd-flex gap-2 mt-3' },
            h('button', { type: 'submit', class: 'btn btn-acai' }, 'Zapisz'),
            h('button', { type: 'button', class: 'btn btn-outline-secondary', onClick: () => { state.editingId = null; renderProductForm(); } }, 'Anuluj'),
        ),
    );
    box.replaceChildren(form);
}

async function saveProduct(event) {
    event.preventDefault();
    const form = event.currentTarget;
    const value = (name) => form.elements[name].value;
    const body = {
        category_id: Number(value('category_id')),
        name: value('name').trim(),
        color_hex: value('color_hex'),
        portion_g: Number(value('portion_g')),
        kcal: Number(value('kcal')),
        protein_g: Number(value('protein_g')),
        carbs_g: Number(value('carbs_g')),
        fat_g: Number(value('fat_g')),
        image_url: value('image_url').trim(),
        image_author: value('image_author').trim(),
        image_source: value('image_source').trim(),
        allergen_ids: [...form.querySelectorAll('input[name="allergen"]:checked')].map((input) => Number(input.value)),
        is_available: form.elements.is_available.checked,
        is_active: form.elements.is_active.checked,
    };
    if (state.editingId !== 'new') body.id = state.editingId;

    // Walidacja w przeglądarce – szybka podpowiedź; serwer sprawdza to samo jeszcze raz.
    if (body.name.length < 2) {
        $('product-form-error').textContent = 'Nazwa musi mieć co najmniej 2 znaki.';
        return;
    }

    try {
        await call('products.php', { method: 'POST', body });
        message(`Zapisano produkt „${body.name}”.`);
        state.editingId = null;
        await loadProducts();
    } catch (error) {
        $('product-form-error').textContent = error.message;
    }
}

/* ------------------------------------------------------------------ */
/* Ceny (kierownik)                                                    */
/* ------------------------------------------------------------------ */

async function loadPrices() {
    try {
        renderPrices(await call('prices.php'));
    } catch (error) {
        showError(error);
    }
}

function renderPrices(data) {
    const priceInput = (id, label, value, hint) => h('div', { class: 'price-row' },
        h('label', { for: id }, h('strong', {}, label), h('span', { class: 'small text-secondary d-block' }, hint)),
        h('div', { class: 'input-group' },
            h('input', { id, type: 'number', min: '0.01', max: '999.99', step: '0.01', class: 'form-control', value: value.toFixed(2), required: true }),
            h('span', { class: 'input-group-text' }, 'zł')),
    );

    $('prices-form').replaceChildren(
        ...data.sizes.map((size) => priceInput(`price-${size.code}`, `${size.code} · ${size.name}`, size.price,
            `${size.layers} warstwy/warstw + ${size.toppings} topping(i)`)),
        data.protein ? priceInput('price-protein', `Dopłata: ${data.protein.name.toLowerCase()}`, data.protein.price, `${data.protein.portion_g} g`) : null,
        data.extra_topping ? priceInput('price-extra-topping', 'Dodatkowy topping', data.extra_topping.price, 'za każdy topping ponad te w cenie rozmiaru') : null,
        h('p', { id: 'prices-error', class: 'text-danger small', role: 'alert' }),
        h('button', { type: 'submit', class: 'btn btn-acai' }, 'Zapisz ceny'),
    );
    $('prices-form').dataset.sizes = data.sizes.map((s) => s.code).join(',');
}

async function savePrices(event) {
    event.preventDefault();
    const form = $('prices-form');
    const sizes = {};
    for (const code of form.dataset.sizes.split(',')) {
        sizes[code] = Number($(`price-${code}`).value);
    }
    const body = { sizes };
    if ($('price-protein')) body.protein = Number($('price-protein').value);
    if ($('price-extra-topping')) body.extra_topping = Number($('price-extra-topping').value);

    const all = [...Object.values(sizes), body.protein ?? 1, body.extra_topping ?? 1];
    if (all.some((v) => !(v > 0 && v < 1000))) {
        $('prices-error').textContent = 'Każda cena musi być kwotą od 0,01 do 999,99 zł.';
        return;
    }
    try {
        renderPrices(await call('prices.php', { method: 'POST', body }));
        message('Zapisano ceny. Klienci zobaczą je po odświeżeniu konfiguratora.');
    } catch (error) {
        $('prices-error').textContent = error.message;
    }
}

/* ------------------------------------------------------------------ */
/* Podsumowanie sprzedaży (kierownik)                                  */
/* ------------------------------------------------------------------ */

async function loadSummary(event) {
    event?.preventDefault();
    if (!$('summary-from').value) {
        const today = new Date();
        const weekAgo = new Date(today.getTime() - 6 * 86400000);
        const iso = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
        $('summary-from').value = iso(weekAgo);
        $('summary-to').value = iso(today);
    }
    try {
        const params = new URLSearchParams({ from: $('summary-from').value, to: $('summary-to').value });
        renderSummary(await call(`summary.php?${params}`));
    } catch (error) {
        showError(error);
    }
}

function renderSummary(data) {
    const tile = (label, value) => h('div', { class: 'stat-tile' }, h('span', { class: 'stat-label' }, label), h('strong', { class: 'stat-value' }, value));
    const table = (headers, rows) => h('table', { class: 'table panel-table' },
        h('thead', {}, h('tr', {}, headers.map((x) => h('th', {}, x)))),
        h('tbody', {}, rows.map((cells) => h('tr', {}, cells.map((c) => h('td', {}, c))))));

    $('summary-result').replaceChildren(
        h('div', { class: 'stat-grid' },
            tile('Zamówienia', String(data.orders)),
            tile('Przychód', money.format(data.revenue)),
            tile('Średnia wartość', money.format(data.average)),
            tile('Z białkiem', String(data.with_protein)),
            tile('Anulowane', String(data.cancelled)),
        ),
        h('p', { class: 'small text-secondary mt-2' }, 'Bez zamówień anulowanych. Kwoty według cen z chwili zakupu.'),
        h('div', { class: 'row g-4 mt-1' },
            h('div', { class: 'col-lg-6' }, h('h2', { class: 'h6 panel-subtitle' }, 'Według rozmiaru'),
                table(['Rozmiar', 'Zamówienia', 'Przychód'], data.by_size.map((s) => [`${s.code} · ${s.name}`, String(s.orders), money.format(s.revenue)]))),
            h('div', { class: 'col-lg-6' }, h('h2', { class: 'h6 panel-subtitle' }, 'Najczęściej wybierane składniki'),
                data.top_products.length
                    ? table(['Składnik', 'Ile razy'], data.top_products.map((p) => [p.name, String(p.times)]))
                    : h('p', { class: 'text-secondary' }, 'Brak danych.')),
            h('div', { class: 'col-12' }, h('h2', { class: 'h6 panel-subtitle' }, 'Dzień po dniu'),
                data.by_day.length
                    ? table(['Dzień', 'Zamówienia', 'Przychód'], data.by_day.map((d) => [d.day, String(d.orders), money.format(d.revenue)]))
                    : h('p', { class: 'text-secondary' }, 'Brak zamówień w tym okresie.')),
        ),
    );
}

/* ------------------------------------------------------------------ */
/* Start                                                               */
/* ------------------------------------------------------------------ */

async function init() {
    $('login-form').addEventListener('submit', login);
    $('btn-logout').addEventListener('click', logout);
    document.querySelectorAll('.panel-tab').forEach((button) => {
        button.addEventListener('click', () => openTab(button.dataset.tab));
    });
    $('btn-add-product').addEventListener('click', () => { state.editingId = 'new'; renderProductForm(); });
    $('prices-form').addEventListener('submit', savePrices);
    $('summary-form').addEventListener('submit', loadSummary);

    try {
        showPanel(await apiRequest(API + 'me.php'));
    } catch (error) {
        if (error instanceof ApiError && error.code === 'NOT_LOGGED_IN') {
            showLogin();
        } else {
            showLogin(error.message);
        }
    }
}

init();
