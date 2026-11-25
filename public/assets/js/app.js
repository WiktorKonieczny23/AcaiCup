/**
 * Konfigurator Acai Cup (zadania 19, 23, 24, 29–31 – FE).
 *
 * Przepływ: rozmiar → warstwy → toppingi → extra białko → podsumowanie
 * → wysłanie zamówienia → ekran potwierdzenia z numerem do odbioru.
 * Cały stan wyboru trzyma jeden obiekt `state`; po każdej zmianie
 * wywołujemy render(), który odświeża krok, kubek, cenę i wartości odżywcze.
 *
 * Dane: najpierw próbujemy API (api/menu.php). Jeśli nie odpowiada
 * (np. backend jeszcze nie gotowy), używamy pliku mock-menu.json
 * o identycznym kształcie danych.
 *
 * Bezpieczeństwo: wszystkie teksty z danych wstawiamy przez textContent,
 * nigdy przez innerHTML (ochrona przed XSS).
 *
 * UWAGA: cena liczona tutaj to tylko PODGLĄD. Przy zapisie zamówienia
 * serwer przelicza wszystko od nowa z bazy (src/pricing.php) i ignoruje
 * ceny z przeglądarki.
 */

import { renderCup, photoUrl } from './cup.js';
import { apiRequest, ApiError } from './api.js';
import { h, plural, money } from './dom.js';

// Zdjęcie główne (gotowy acai bowl z góry) – pierwszy krok konfiguratora.
const HERO = {
    url: 'https://images.unsplash.com/photo-1654923064926-be7e64267a31',
    author: 'Eiliv Aceron',
    source: 'https://unsplash.com/photos/NI8MeJiAN3I',
};

const API_URL = 'api/menu.php';
const MOCK_URL = 'assets/js/mock-menu.json';
const ORDERS_URL = 'api/orders.php';
const STATUS_URL = 'api/order-status.php';
const SOON_PAGE = 'platnosc-wkrotce.html';
const DRAFT_KEY = 'acai-draft';          // szkic kubka w sessionStorage na czas strony „wkrótce”
const STATUS_REFRESH_MS = 15000;         // co ile odświeżać status na ekranie potwierdzenia

const STEPS = [
    { id: 'size',     label: 'Rozmiar',  title: 'Wybierz rozmiar' },
    { id: 'layers',   label: 'Warstwy',  title: 'Skomponuj warstwy' },
    { id: 'toppings', label: 'Toppingi', title: 'Wybierz topping' },
    { id: 'protein',  label: 'Białko',   title: 'Dodatkowe białko' },
    { id: 'summary',  label: 'Podsumowanie', title: 'Podsumowanie' },
];

const state = {
    menu: null,          // dane z API lub mocka
    sizeCode: null,      // 'S' | 'M' | 'L'
    layers: {},          // numer warstwy → id produktu
    toppings: [],        // id produktów (kolejność wyboru); w L jeden może wystąpić 2× (podwójna porcja)
    extraToppings: 0,    // ile płatnych miejsc na toppingi ponad te wliczone w rozmiar
    protein: false,
    payment: 'cash',     // 'cash' | 'online'
    step: 0,             // indeks w STEPS
    maxStep: 0,          // najdalszy odblokowany krok
    sending: false,      // trwa wysyłanie zamówienia (blokuje podwójne kliknięcie)
    order: null,         // odpowiedź API po złożeniu zamówienia → ekran potwierdzenia
};

let statusTimer = null;  // odświeżanie statusu na ekranie potwierdzenia

const products = new Map(); // id → produkt (szybkie wyszukiwanie)

const $ = (id) => document.getElementById(id);
const grams = new Intl.NumberFormat('pl-PL', { maximumFractionDigits: 1 });

/* ------------------------------------------------------------------ */
/* Pomocnicze funkcje DOM                                              */
/* ------------------------------------------------------------------ */

// h(), plural() i money są w dom.js – wspólne z panelem personelu.

/* ------------------------------------------------------------------ */
/* Dane i obliczenia                                                   */
/* ------------------------------------------------------------------ */

async function loadMenu() {
    try {
        return { data: await apiRequest(API_URL), source: 'api' };
    } catch {
        const response = await fetch(MOCK_URL, { cache: 'no-cache' }); // zawsze sprawdź, czy plik się nie zmienił
        const json = await response.json();
        return { data: json.data, source: 'mock' };
    }
}

/** Zapisuje menu w stanie i w mapie produktów (id → produkt). */
function useMenu(data) {
    state.menu = data;
    products.clear();
    [...data.layers.flatMap((c) => c.products), ...data.toppings.products]
        .forEach((product) => products.set(product.id, product));
}

function currentSize() {
    return state.menu.sizes.find((size) => size.code === state.sizeCode) ?? null;
}

/** Kategorie warstw używane przez wybrany rozmiar (S: 1–2, M: 1–3, L: 1–4). */
function activeLayerCategories() {
    const size = currentSize();
    if (!size) return [];
    return state.menu.layers.filter((category) => category.position <= size.layers);
}

/** Wszystkie wybrane pozycje (warstwy, toppingi, białko) do sumowania. */
function selectedItems() {
    const items = [];
    for (const category of activeLayerCategories()) {
        const id = state.layers[category.position];
        if (id) items.push(products.get(id));
    }
    state.toppings.forEach((id) => items.push(products.get(id)));
    if (state.protein && state.menu.extra_protein) items.push(state.menu.extra_protein);
    return items;
}

/** Dodatkowe toppingi: { price, max, available } albo null, gdy menu ich nie oferuje. */
function extraToppingInfo() {
    const info = state.menu.extra_topping;
    return info && info.available ? info : null;
}

/** Ile toppingów mieści się w kubku: wliczone w rozmiar + dokupione miejsca. */
function toppingCapacity() {
    const size = currentSize();
    return size ? size.toppings + state.extraToppings : 0;
}

/** Ile razy topping jest w kubku (0, 1 albo 2 – podwójna porcja). */
function toppingCount(id) {
    return state.toppings.filter((t) => t === id).length;
}

/** Id toppingu z podwójną porcją albo null. */
function doubledTopping() {
    return state.toppings.find((id, index) => state.toppings.indexOf(id) !== index) ?? null;
}

/** Czy w tym rozmiarze można zamówić podwójną porcję toppingu (L). */
function canDouble() {
    return Boolean(currentSize()?.double_topping);
}

/** Dopłata za dodatkowe toppingi (wybrane ponad te wliczone w rozmiar). */
function extraToppingsCost() {
    const size = currentSize();
    const info = state.menu.extra_topping;
    if (!size || !info) return 0;
    return Math.max(0, state.toppings.length - size.toppings) * info.price;
}

/** Toppingi do wyświetlenia: „Banan ×2, Granola”. */
function toppingNames(ids, nameOf) {
    const names = [];
    ids.forEach((id) => {
        if (names.some((row) => row.id === id)) return;
        const count = ids.filter((x) => x === id).length;
        names.push({ id, text: count > 1 ? `${nameOf(id)} ×${count} (podwójna porcja)` : nameOf(id) });
    });
    return names.map((row) => row.text).join(', ');
}

function totals() {
    const size = currentSize();
    const extra = state.menu.extra_protein;
    const items = selectedItems();
    const sum = (key) => items.reduce((acc, item) => acc + item[key], 0);

    const allergens = [];
    items.forEach((item) => item.allergens.forEach((name) => {
        if (!allergens.includes(name)) allergens.push(name);
    }));

    return {
        price: size ? size.price + extraToppingsCost() + (state.protein && extra ? extra.price : 0) : null,
        kcal: sum('kcal'),
        protein: sum('protein_g'),
        carbs: sum('carbs_g'),
        fat: sum('fat_g'),
        allergens,
    };
}

/** Czy krok jest kompletny? Zwraca komunikat błędu albo null. */
function stepError(stepId) {
    const size = currentSize();
    switch (stepId) {
        case 'size':
            return size ? null : 'Wybierz rozmiar kubka.';
        case 'layers': {
            const missing = activeLayerCategories().filter((c) => !state.layers[c.position]);
            return missing.length ? `Wybierz składnik dla: ${missing.map((c) => c.name).join(', ')}.` : null;
        }
        case 'toppings': {
            if (!size) return 'Wybierz rozmiar kubka.';
            const missing = toppingCapacity() - state.toppings.length;
            if (missing <= 0) return null;
            return state.extraToppings > 0
                ? `Wybierz jeszcze ${plural(missing, 'topping', 'toppingi', 'toppingów')} albo zmniejsz liczbę dodatkowych.`
                : `Wybierz ${plural(size.toppings, 'topping', 'toppingi', 'toppingów')}.`;
        }
        default:
            return null;
    }
}

/* ------------------------------------------------------------------ */
/* Akcje użytkownika                                                   */
/* ------------------------------------------------------------------ */

function chooseSize(code) {
    state.sizeCode = code;
    const size = currentSize();
    // Mniejszy rozmiar: usuń warstwy i toppingi, które się nie mieszczą.
    for (const position of Object.keys(state.layers)) {
        if (Number(position) > size.layers) delete state.layers[position];
    }
    if (!size.double_topping) state.toppings = [...new Set(state.toppings)]; // podwójna porcja tylko w L
    state.toppings = state.toppings.slice(0, toppingCapacity());
    render();
}

function chooseLayer(position, productId) {
    state.layers[position] = productId;
    render();
}

function toggleTopping(productId) {
    const max = toppingCapacity();

    if (state.toppings.includes(productId)) {
        state.toppings = state.toppings.filter((id) => id !== productId); // odznaczenie (także podwójnej porcji)
    } else if (max === 1) {
        state.toppings = [productId];             // jeden topping: zamiana
    } else if (state.toppings.length < max) {
        state.toppings.push(productId);
    } else {
        $('step-error').textContent = extraToppingInfo() && state.extraToppings < extraToppingInfo().max
            ? 'Wszystkie miejsca są zajęte. Odznacz topping albo dodaj dodatkowy poniżej.'
            : `Możesz wybrać ${plural(max, 'topping', 'toppingi', 'toppingów')}. Odznacz jeden, żeby wybrać inny.`;
        return;
    }
    render();
}

/** Podwójna porcja (tylko L): ten sam topping zajmuje dwa miejsca. */
function toggleDouble(productId) {
    if (toppingCount(productId) > 1) {
        state.toppings.splice(state.toppings.lastIndexOf(productId), 1); // wróć do pojedynczej porcji
        render();
        return;
    }
    const other = doubledTopping();
    const toppings = [...state.toppings];
    if (other !== null) toppings.splice(toppings.lastIndexOf(other), 1); // podwójna może być tylko jedna
    if (toppings.length >= toppingCapacity()) {
        $('step-error').textContent = 'Podwójna porcja zajmuje dwa miejsca. Odznacz inny topping albo dodaj dodatkowy.';
        return;
    }
    toppings.splice(toppings.indexOf(productId) + 1, 0, productId);
    state.toppings = toppings;
    render();
}

/** Zmienia liczbę dokupionych miejsc na toppingi (+1 / −1). */
function changeExtraToppings(delta) {
    const info = extraToppingInfo();
    if (!info) return;
    state.extraToppings = Math.min(info.max, Math.max(0, state.extraToppings + delta));
    // Mniej miejsc: usuwamy ostatnio wybrane toppingi, które się już nie mieszczą.
    state.toppings = state.toppings.slice(0, toppingCapacity());
    render();
}

/** Pierwszy krok, który nie jest kompletny (albo null, gdy wszystko wybrane). */
function firstIncompleteStep() {
    const index = STEPS.findIndex((step) => stepError(step.id) !== null);
    return index === -1 ? null : index;
}

function goTo(step) {
    state.step = step;
    state.maxStep = Math.max(state.maxStep, step);
    render();
    $('step-title').focus();
}

function next() {
    const current = STEPS[state.step];
    const error = stepError(current.id);
    if (error) {
        $('step-error').textContent = error;
        return;
    }
    if (current.id === 'summary') {
        submitOrder();
        return;
    }
    goTo(state.step + 1);
}

/* ------------------------------------------------------------------ */
/* Zamówienie (etap E2)                                                */
/* ------------------------------------------------------------------ */

/** Dane wysyłane do POST /api/orders.php – same wybory, bez cen. */
function orderPayload() {
    const layers = {};
    activeLayerCategories().forEach((category) => {
        layers[category.position] = state.layers[category.position];
    });
    return {
        size: state.sizeCode,
        layers,
        toppings: [...state.toppings],
        extra_protein: state.protein,
        payment_method: state.payment,
    };
}

async function submitOrder() {
    if (state.sending) return;

    // Walidacja w przeglądarce: czy wszystkie kroki są kompletne.
    const incomplete = firstIncompleteStep();
    if (incomplete !== null) {
        goTo(incomplete);
        $('step-error').textContent = stepError(STEPS[incomplete].id);
        return;
    }

    state.sending = true;
    $('btn-next').disabled = true;
    $('btn-next').textContent = 'Wysyłam…';
    $('step-error').textContent = '';

    try {
        state.order = await apiRequest(ORDERS_URL, { method: 'POST', body: orderPayload() });
        state.sending = false;
        render();
        startStatusRefresh();
        $('step-title').focus();
    } catch (error) {
        state.sending = false;
        await handleOrderError(error);
    }
}

/** Reakcja na błąd z API: każda sytuacja ma czytelny komunikat. */
async function handleOrderError(error) {
    const code = error instanceof ApiError ? error.code : 'ERROR';

    if (code === 'PAYMENT_UNAVAILABLE') {
        // Płatność online jeszcze nie działa: zapamiętaj kubek i pokaż stronę „wkrótce”.
        saveDraft();
        window.location.href = SOON_PAGE;
        return;
    }

    let message = error.message || 'Nie udało się złożyć zamówienia. Spróbuj ponownie.';

    if (['PRODUCT_UNAVAILABLE', 'PRODUCT_NOT_FOUND', 'EXTRA_UNAVAILABLE'].includes(code)) {
        // Ktoś z personelu właśnie oznaczył produkt jako niedostępny – pobierz świeże menu,
        // usuń z kubka to, czego już nie ma, i wróć do kroku, który trzeba poprawić.
        try {
            const { data } = await loadMenu();
            useMenu(data);
            dropUnavailableChoices();
        } catch { /* zostaje stare menu */ }
        const incomplete = firstIncompleteStep();
        if (incomplete !== null) {
            goTo(incomplete);
        } else {
            render();
        }
    } else {
        render();
    }
    $('step-error').textContent = message;
}

/** Usuwa z wyborów produkty, które zniknęły z menu albo są niedostępne. */
function dropUnavailableChoices() {
    const ok = (id) => products.get(id)?.available === true;
    for (const [position, id] of Object.entries(state.layers)) {
        if (!ok(id)) delete state.layers[position];
    }
    state.toppings = state.toppings.filter(ok);
    if (!extraToppingInfo()) state.extraToppings = 0;
    state.extraToppings = Math.min(state.extraToppings, extraToppingInfo()?.max ?? 0);
    if (currentSize()) state.toppings = state.toppings.slice(0, toppingCapacity());
    if (state.protein && !state.menu.extra_protein?.available) state.protein = false;
}

/** Ekran potwierdzenia co 15 s pyta serwer o status („Gotowe do odbioru”). */
function startStatusRefresh() {
    clearInterval(statusTimer);
    statusTimer = setInterval(async () => {
        if (!state.order) return clearInterval(statusTimer);
        try {
            const status = await apiRequest(`${STATUS_URL}?code=${encodeURIComponent(state.order.order_code)}`);
            state.order.status = status.status;
            state.order.status_label = status.status_label;
            state.order.payment_status = status.payment_status;
            renderOrderStatus();
            if (['collected', 'cancelled'].includes(status.status)) clearInterval(statusTimer);
        } catch { /* chwilowy brak sieci – spróbujemy za 15 s */ }
    }, STATUS_REFRESH_MS);
}

/** „Złóż kolejne zamówienie” – czysty konfigurator od pierwszego kroku. */
function resetOrder() {
    clearInterval(statusTimer);
    Object.assign(state, { sizeCode: null, layers: {}, toppings: [], extraToppings: 0, protein: false, payment: 'cash', step: 0, maxStep: 0, order: null });
    render();
    $('step-title').focus();
}

/* Szkic kubka na czas wizyty na stronie „płatność online wkrótce” ------- */

function saveDraft() {
    try {
        sessionStorage.setItem(DRAFT_KEY, JSON.stringify({
            sizeCode: state.sizeCode, layers: state.layers, toppings: state.toppings,
            extraToppings: state.extraToppings, protein: state.protein,
        }));
    } catch { /* tryb prywatny / brak pamięci – klient po prostu wybierze od nowa */ }
}

/** Po powrocie ze strony „wkrótce” (?powrot=platnosc) odtwarza kubek i otwiera podsumowanie. */
function restoreDraft() {
    let draft = null;
    try {
        draft = JSON.parse(sessionStorage.getItem(DRAFT_KEY) ?? 'null');
        sessionStorage.removeItem(DRAFT_KEY);
    } catch { /* brak szkicu */ }

    const returning = new URLSearchParams(window.location.search).get('powrot') === 'platnosc';
    if (returning) history.replaceState(null, '', window.location.pathname); // czysty adres
    if (!returning || !draft || !state.menu.sizes.some((s) => s.code === draft.sizeCode)) return;

    state.sizeCode = draft.sizeCode;
    state.layers = draft.layers ?? {};
    state.toppings = Array.isArray(draft.toppings) ? draft.toppings : [];
    state.extraToppings = Number.isInteger(draft.extraToppings) ? draft.extraToppings : 0;
    state.protein = Boolean(draft.protein);
    state.payment = 'cash';
    dropUnavailableChoices();

    const incomplete = firstIncompleteStep();
    state.step = incomplete ?? STEPS.length - 1;
    state.maxStep = STEPS.length - 1;
}

/* ------------------------------------------------------------------ */
/* Widoki kroków                                                       */
/* ------------------------------------------------------------------ */

/** Karta wyboru (przycisk z aria-pressed). */
function optionCard({ selected, disabled, onClick, swatch, title, meta, extra }) {
    return h('button', {
        type: 'button',
        class: `option-card${selected ? ' is-selected' : ''}`,
        'aria-pressed': selected ? 'true' : 'false',
        disabled,
        onClick,
    },
        swatch ? productSwatch(swatch) : null,
        h('span', { class: 'option-text' },
            h('span', { class: 'option-title' }, title),
            meta ? h('span', { class: 'option-meta' }, meta) : null,
            extra ? h('span', { class: 'option-extra' }, extra) : null,
        ),
    );
}

/** Miniatura zdjęcia składnika; kolor produktu jest tłem, dopóki zdjęcie się nie wczyta. */
function productSwatch(item) {
    const swatch = h('span', { class: 'swatch', 'aria-hidden': 'true' });
    swatch.style.backgroundColor = item.color ?? '#e8ebe6'; // przez CSSOM – zgodne z CSP
    const thumb = photoUrl(item.image, 112, 112);
    if (thumb) swatch.style.backgroundImage = `url("${thumb}")`;
    return swatch;
}

function productCard(product, selected, onClick) {
    const allergens = product.allergens.length ? `Alergeny: ${product.allergens.join(', ')}` : 'Bez alergenów z listy UE';
    return optionCard({
        selected,
        disabled: !product.available,
        onClick,
        swatch: product,
        title: product.name,
        meta: product.available ? `${product.portion_g} g · ${Math.round(product.kcal)} kcal` : 'chwilowo niedostępne',
        extra: product.available ? allergens : null,
    });
}

function viewSize() {
    const heroImg = h('img', { src: photoUrl(HERO, 1200, 520), alt: 'Acai bowl z owocami i granolą, widok z góry', width: '1200', height: '520' });
    const hero = h('figure', { class: 'hero-photo' }, heroImg);
    heroImg.addEventListener('error', () => { hero.hidden = true; }); // bez internetu: bez pustej ramki
    return [hero, h('div', { class: 'option-grid option-grid-3' },
        state.menu.sizes.map((size) => optionCard({
            selected: size.code === state.sizeCode,
            onClick: () => chooseSize(size.code),
            title: `${size.code} · ${size.name}`,
            meta: `${plural(size.layers, 'warstwa', 'warstwy', 'warstw')} · ${plural(size.toppings, 'topping', 'toppingi', 'toppingów')}`
                + (size.double_topping ? ' · możliwa podwójna porcja' : ''),
            extra: money.format(size.price),
        })),
    )];
}

function viewLayers() {
    return activeLayerCategories().map((category) => h('fieldset', { class: 'layer-group' },
        h('legend', {}, `Warstwa ${category.position} · ${category.name}`),
        h('div', { class: 'option-grid' },
            category.products.map((product) => productCard(
                product,
                state.layers[category.position] === product.id,
                () => chooseLayer(category.position, product.id),
            )),
        ),
    ));
}

function viewToppings() {
    const size = currentSize();
    const info = extraToppingInfo();
    const used = state.toppings.length;
    const capacity = toppingCapacity();

    const grid = h('div', { class: 'option-grid' },
        state.menu.toppings.products.map((product) => {
            const card = productCard(product, state.toppings.includes(product.id), () => toggleTopping(product.id));
            const count = toppingCount(product.id);
            if (count > 1) card.querySelector('.option-title').append(h('span', { class: 'qty-badge' }, '×2'));
            return card;
        }),
    );

    // Licznik: ile miejsc zajętych i ile kosztują dodatkowe.
    const status = h('p', { class: 'topping-status mb-0' },
        h('strong', {}, `${used} / ${capacity}`), ' ',
        `wybranych · ${plural(size.toppings, 'topping', 'toppingi', 'toppingów')} w cenie`,
        state.extraToppings ? ` + ${state.extraToppings} dodatkowe (${money.format(state.extraToppings * info.price)})` : '',
    );

    const panels = [status];

    // Dodatkowe toppingi: dostępne w każdym rozmiarze.
    if (info) {
        panels.push(h('div', { class: 'extra-toppings' },
            h('div', {},
                h('div', { class: 'fw-semibold' }, 'Dodatkowe toppingi'),
                h('div', { class: 'small text-secondary' }, `+${money.format(info.price)} za każdy, najwyżej ${info.max}`),
            ),
            h('div', { class: 'qty-control', role: 'group', 'aria-label': 'Liczba dodatkowych toppingów' },
                h('button', {
                    type: 'button', class: 'btn btn-outline-secondary btn-sm', 'aria-label': 'Usuń dodatkowy topping',
                    disabled: state.extraToppings === 0, onClick: () => changeExtraToppings(-1),
                }, '−'),
                h('span', { class: 'qty-value', 'aria-live': 'polite' }, String(state.extraToppings)),
                h('button', {
                    type: 'button', class: 'btn btn-outline-secondary btn-sm', 'aria-label': 'Dodaj dodatkowy topping',
                    disabled: state.extraToppings >= info.max, onClick: () => changeExtraToppings(1),
                }, '+'),
            ),
        ));
    }

    // Podwójna porcja: tylko w L, dla jednego z wybranych toppingów.
    if (canDouble()) {
        const chosen = [...new Set(state.toppings)];
        const doubled = doubledTopping();
        panels.push(h('div', { class: 'double-topping' },
            h('div', { class: 'fw-semibold' }, 'Podwójna porcja jednego toppingu'),
            h('div', { class: 'small text-secondary mb-2' },
                'W dużym kubku możesz wybrać jeden topping podwójnie – zajmie dwa miejsca zamiast dwóch różnych.'),
            chosen.length
                ? h('div', { class: 'd-flex flex-wrap gap-2' }, chosen.map((id) => h('button', {
                    type: 'button',
                    class: `btn btn-sm ${doubled === id ? 'btn-acai' : 'btn-outline-secondary'}`,
                    'aria-pressed': doubled === id ? 'true' : 'false',
                    onClick: () => toggleDouble(id),
                }, `${products.get(id).name} ×2`)))
                : h('div', { class: 'small text-secondary' }, 'Najpierw wybierz topping z listy powyżej.'),
        ));
    }

    return [grid, h('div', { class: 'topping-options' }, ...panels)];
}

function viewProtein() {
    const extra = state.menu.extra_protein;
    if (!extra) return h('p', { class: 'text-secondary' }, 'Dodatek jest obecnie niedostępny.');

    return h('div', { class: 'option-grid option-grid-2' },
        optionCard({
            selected: !state.protein,
            onClick: () => { state.protein = false; render(); },
            title: 'Bez dodatku',
            meta: '+0,00 zł',
        }),
        optionCard({
            selected: state.protein,
            disabled: !extra.available,
            onClick: () => { state.protein = true; render(); },
            swatch: extra,
            title: `Dodaj ${extra.portion_g} g – ${extra.name.toLowerCase()}`,
            meta: `+${money.format(extra.price)} · ${Math.round(extra.kcal)} kcal · ${grams.format(extra.protein_g)} g białka`,
            extra: extra.allergens.length ? `Alergeny: ${extra.allergens.join(', ')}` : null,
        }),
    );
}

function viewSummary() {
    const size = currentSize();
    const rows = [['Rozmiar', `${size.code} · ${size.name}`]];
    activeLayerCategories().forEach((category) => {
        rows.push([`Warstwa ${category.position} (${category.name.toLowerCase()})`, products.get(state.layers[category.position]).name]);
    });
    rows.push(['Toppingi', toppingNames(state.toppings, (id) => products.get(id).name)]);
    const paid = state.toppings.length - size.toppings;
    if (paid > 0) rows.push(['Dodatkowe toppingi', `${paid} × ${money.format(state.menu.extra_topping.price)} = ${money.format(extraToppingsCost())}`]);
    rows.push(['Extra białko', state.protein ? `tak, ${state.menu.extra_protein.portion_g} g` : 'nie']);

    const payment = (value, title, meta) => optionCard({
        selected: state.payment === value,
        onClick: () => { state.payment = value; render(); },
        title,
        meta,
    });

    return [
        h('dl', { class: 'summary-list' },
            rows.map(([label, value]) => h('div', {}, h('dt', {}, label), h('dd', {}, value))),
        ),
        h('h2', { class: 'h6 mt-4 mb-2' }, 'Płatność'),
        h('div', { class: 'option-grid option-grid-2' },
            payment('cash', 'Przy kasie', 'Płacisz przy odbiorze, podając numer zamówienia'),
            payment('online', 'Online', 'Przelewy24 – wkrótce dostępne'),
        ),
    ];
}

/** Ekran po złożeniu zamówienia: numer do odbioru, status, kwota, skład. */
function viewConfirmation() {
    const order = state.order;
    const rows = [['Rozmiar', order.size]];
    order.items.filter((item) => item.slot.startsWith('layer_'))
        .forEach((item) => rows.push([`Warstwa ${item.slot.slice(6)}`, item.name]));
    const toppings = order.items.filter((item) => item.slot.startsWith('topping_')).map((item) => item.name);
    rows.push(['Toppingi', toppingNames(toppings, (name) => name)]);
    if (order.extra_toppings > 0) rows.push(['Dodatkowe toppingi', String(order.extra_toppings)]);
    rows.push(['Extra białko', order.extra_protein ? 'tak' : 'nie']);

    return [
        h('div', { class: 'order-code-box' },
            h('span', { class: 'order-code-label' }, 'Twój numer zamówienia'),
            h('strong', { class: 'order-code', id: 'order-code' }, order.order_code),
            h('span', { class: 'order-status', id: 'order-status', 'aria-live': 'polite' }, order.status_label),
        ),
        h('p', { class: 'mt-3 mb-1' },
            'Do zapłaty przy kasie: ', h('strong', {}, money.format(order.total_price)), '.'),
        h('p', { class: 'text-secondary' },
            'Podaj ten numer przy ladzie. Status na tej stronie odświeża się sam – gdy zobaczysz „Gotowe do odbioru”, kubek czeka na Ciebie.'),
        h('dl', { class: 'summary-list' },
            rows.map(([label, value]) => h('div', {}, h('dt', {}, label), h('dd', {}, value))),
        ),
        h('button', { type: 'button', class: 'btn btn-acai mt-4', onClick: resetOrder }, 'Złóż kolejne zamówienie'),
    ];
}

/** Aktualizuje tylko status na ekranie potwierdzenia (bez rysowania całości). */
function renderOrderStatus() {
    const node = $('order-status');
    if (!node || !state.order) return;
    node.textContent = state.order.status_label;
    node.dataset.status = state.order.status;
}

const VIEWS = { size: viewSize, layers: viewLayers, toppings: viewToppings, protein: viewProtein, summary: viewSummary };

function hintFor(stepId) {
    const size = currentSize();
    switch (stepId) {
        case 'size': return 'Rozmiar decyduje o liczbie warstw i toppingów w cenie. Dopłacasz tylko za dodatki.';
        case 'layers': return 'Każda warstwa ma własną kategorię. Liczymy od dna kubka.';
        case 'toppings': {
            const base = size.toppings === 1 ? 'W cenie jest jeden topping.' : `W cenie są ${size.toppings} toppingi.`;
            const more = extraToppingInfo() ? ` Kolejne możesz dodać za ${money.format(state.menu.extra_topping.price)} każdy.` : '';
            return base + more;
        }
        case 'protein': {
            const extra = state.menu.extra_protein;
            return extra ? `Opcjonalnie: ${extra.portion_g} g białka serwatkowego za ${money.format(extra.price)}.` : '';
        }
        default: return 'Sprawdź skład i wybierz płatność. Cenę i tak przeliczy serwer.';
    }
}

/* ------------------------------------------------------------------ */
/* Render całości                                                      */
/* ------------------------------------------------------------------ */

function renderStepper() {
    $('stepper').replaceChildren(...STEPS.map((step, index) => {
        const isCurrent = index === state.step;
        const button = h('button', {
            type: 'button',
            class: 'stepper-btn',
            disabled: index > state.maxStep,
            onClick: () => goTo(index),
        }, h('span', { class: 'stepper-num' }, String(index + 1)), h('span', { class: 'stepper-label' }, step.label));
        if (isCurrent) button.setAttribute('aria-current', 'step');
        return h('li', { class: `stepper-item${isCurrent ? ' is-current' : ''}${index < state.step ? ' is-done' : ''}` }, button);
    }));
}

function renderPreview() {
    const size = currentSize();
    const layerList = [];
    for (let position = 1; position <= (size?.layers ?? 0); position++) {
        const id = state.layers[position];
        layerList.push(id ? products.get(id) : null);
    }

    renderCup($('cup'), {
        sizeCode: state.sizeCode,
        slots: size?.layers ?? 0,
        layers: layerList,
        toppings: state.toppings.map((id) => products.get(id)),
        protein: state.protein && state.menu.extra_protein ? state.menu.extra_protein.portion_g : 0,
    });

    const t = totals();
    $('price').textContent = t.price === null ? '–' : money.format(t.price);
    $('n-kcal').textContent = Math.round(t.kcal);
    $('m-price').textContent = $('price').textContent;
    $('m-kcal').textContent = `${Math.round(t.kcal)} kcal`;
    $('n-protein').textContent = `${grams.format(t.protein)} g`;
    $('n-carbs').textContent = `${grams.format(t.carbs)} g`;
    $('n-fat').textContent = `${grams.format(t.fat)} g`;
    $('allergens').replaceChildren(...(t.allergens.length
        ? t.allergens.map((name) => h('span', { class: 'badge allergen' }, name))
        : [h('span', { class: 'text-secondary small' }, 'brak')]));
}

function render() {
    $('step-error').textContent = '';

    // Po złożeniu zamówienia: ekran potwierdzenia zamiast kroków.
    const done = state.order !== null;
    $('stepper').hidden = done;
    $('step-nav').hidden = done;
    if (done) {
        $('step-title').textContent = 'Dziękujemy! Zamówienie przyjęte';
        $('step-hint').textContent = '';
        $('step-body').replaceChildren(...viewConfirmation());
        renderOrderStatus();
        renderPreview();
        return;
    }

    const step = STEPS[state.step];
    $('step-title').textContent = step.title;
    $('step-hint').textContent = hintFor(step.id);
    $('step-body').replaceChildren(...[VIEWS[step.id]()].flat());

    $('btn-back').disabled = state.step === 0 || state.sending;
    $('btn-next').disabled = state.sending;
    $('btn-next').textContent = step.id === 'summary' ? 'Złóż zamówienie' : 'Dalej';

    renderStepper();
    renderPreview();
}

/* ------------------------------------------------------------------ */
/* Start                                                               */
/* ------------------------------------------------------------------ */

async function init() {
    $('btn-back').addEventListener('click', () => goTo(state.step - 1));
    $('btn-next').addEventListener('click', next);
    $('step-title').setAttribute('tabindex', '-1');

    try {
        const { data, source } = await loadMenu();
        useMenu(data);
        $('data-source').hidden = source !== 'mock';
        restoreDraft();
        render();
    } catch (error) {
        console.error(error);
        const box = $('load-error');
        box.textContent = 'Nie udało się wczytać menu. Odśwież stronę lub spróbuj za chwilę.';
        box.hidden = false;
    }
}

init();
