/**
 * Wizualizacja kubka w SVG (zadanie 20, FE).
 *
 * renderCup(svg, model) rysuje kubek od nowa przy każdej zmianie wyboru.
 * model = {
 *   sizeCode: 'S' | 'M' | 'L' | null,
 *   slots:    liczba warstw w rozmiarze (0, gdy nie wybrano rozmiaru),
 *   layers:   [{ name, color, image } | null, ...]   // indeks 0 = warstwa na dnie
 *   toppings: [{ name, color, image }, ...],
 *   protein:  gramy białka (np. 20) albo 0, gdy klient go nie dodał
 * }
 * image = { url, author, source } albo null.
 *
 * Każda warstwa jest wypełniona zdjęciem składnika (rzut z góry) przez <pattern>.
 * Pod zdjęciem leży kolor produktu – widać go, dopóki zdjęcie się ładuje,
 * albo gdy produkt nie ma zdjęcia lub zdjęcie się nie wczyta.
 *
 * Teksty wstawiamy przez textContent – nigdy przez innerHTML (ochrona przed XSS).
 */

const NS = 'http://www.w3.org/2000/svg';

// Kształt kubka dla rozmiaru L; mniejsze rozmiary są skalowane względem dna.
const CUP = { topY: 58, bottomY: 278, topLeft: 34, topRight: 206, bottomLeft: 62, bottomRight: 178 };
const FILL_TOP = 62;      // górna granica wypełnienia (tuż pod rantem)
const FILL_BOTTOM = 274;  // dno wewnątrz kubka
const SCALE = { S: 0.82, M: 0.91, L: 1 };

// Kopczyk toppingów nad rantem kubka.
const MOUND = { left: 38, right: 202, base: 62, peak: 32 };
const MOUND_PATH = `M ${MOUND.left} ${MOUND.base} C 48 44, 74 ${MOUND.peak}, 120 ${MOUND.peak} C 166 ${MOUND.peak}, 192 44, ${MOUND.right} ${MOUND.base} Z`;

/**
 * Adres zdjęcia przycięty do zadanego rozmiaru po stronie serwisu
 * (Unsplash i Pexels kadrują zdjęcie z parametrów w adresie).
 * Inne adresy, np. własne pliki z assets/img, zwraca bez zmian.
 */
export function photoUrl(image, width, height) {
    if (!image || !image.url) return null;
    const { url } = image;
    if (url.startsWith('https://images.unsplash.com/')) {
        return `${url}?w=${width}&h=${height}&fit=crop&crop=entropy&auto=format&q=70`;
    }
    if (url.startsWith('https://images.pexels.com/')) {
        return `${url}?auto=compress&cs=tinysrgb&w=${width}&h=${height}&fit=crop`;
    }
    return url;
}

/** Tworzy element SVG z atrybutami. */
function el(tag, attrs = {}, text = null) {
    const node = document.createElementNS(NS, tag);
    for (const [key, value] of Object.entries(attrs)) {
        node.setAttribute(key, String(value));
    }
    if (text !== null) {
        node.textContent = text;
    }
    return node;
}

/** Szerokość wnętrza kubka na danej wysokości (do skracania napisów). */
function widthAt(y) {
    const t = (y - CUP.topY) / (CUP.bottomY - CUP.topY);
    const left = CUP.topLeft + (CUP.bottomLeft - CUP.topLeft) * t;
    const right = CUP.topRight + (CUP.bottomRight - CUP.topRight) * t;
    return right - left;
}

/** Skraca nazwę, żeby zmieściła się w danej szerokości. */
function fitText(text, width, fontSize) {
    const maxChars = Math.floor((width - 28) / (fontSize * 0.58));
    return text.length <= maxChars ? text : text.slice(0, Math.max(maxChars - 1, 1)) + '…';
}

/** Ścieżka warstwy: falista góra, dół na dnie kubka. */
function layerPath(topY, seed) {
    const amp = 3 + (seed % 3);
    let d = `M 10 ${topY}`;
    for (let x = 10; x < 230; x += 22) {
        const dir = (Math.floor(x / 22) + seed) % 2 === 0 ? -amp : amp;
        d += ` Q ${x + 11} ${topY + dir} ${x + 22} ${topY}`;
    }
    return `${d} L 230 ${FILL_BOTTOM + 10} L 10 ${FILL_BOTTOM + 10} Z`;
}

/**
 * Wzór (pattern) ze zdjęciem składnika na zadanym prostokącie.
 * Zwraca wartość do atrybutu fill: url(#id) albo sam kolor, gdy brak zdjęcia.
 */
function texture(defs, id, item, box) {
    const href = photoUrl(item.image, Math.round(box.width * 2.2), Math.round(box.height * 2.2));
    if (!href) return item.color;

    const pattern = el('pattern', {
        id, patternUnits: 'userSpaceOnUse',
        x: box.x, y: box.y, width: box.width, height: box.height,
    });
    pattern.append(el('rect', { width: box.width, height: box.height, fill: item.color }));
    const image = el('image', {
        width: box.width, height: box.height,
        preserveAspectRatio: 'xMidYMid slice',
    });
    image.setAttribute('href', href);
    pattern.append(image);
    defs.append(pattern);
    return `url(#${id})`;
}

/** Napis na zielonej „etykiecie”, czytelny na każdym zdjęciu. */
function label(parent, text, x, y, fontSize) {
    const group = el('g', { class: 'cup-label' });
    parent.append(group);
    const textNode = el('text', { x, y, 'text-anchor': 'middle', 'font-size': fontSize, class: 'cup-label-text' }, text);
    group.append(textNode);
    const box = textNode.getBBox();
    group.insertBefore(el('rect', {
        x: box.x - 8, y: box.y - 3, width: box.width + 16, height: box.height + 6,
        rx: (box.height + 6) / 2, class: 'cup-label-pill',
    }), textNode);
}

export function renderCup(svg, model) {
    // Wyczyść poprzedni rysunek, zostaw <title> dla czytników ekranu.
    [...svg.childNodes].forEach((node) => {
        if (node.nodeName !== 'title') node.remove();
    });

    const outline = `${CUP.topLeft},${CUP.topY} ${CUP.topRight},${CUP.topY} ${CUP.bottomRight},${CUP.bottomY} ${CUP.bottomLeft},${CUP.bottomY}`;
    const defs = el('defs');
    const clip = el('clipPath', { id: 'cup-clip' });
    clip.append(el('polygon', { points: outline }));
    const rightHalf = el('clipPath', { id: 'mound-right' });
    rightHalf.append(el('rect', { x: 120, y: 0, width: 120, height: 100 }));
    defs.append(clip, rightHalf);
    svg.append(defs);

    // Cień pod kubkiem (poza skalowaniem, żeby zawsze leżał na "stole").
    svg.append(el('ellipse', { cx: 120, cy: 286, rx: 74, ry: 7, class: 'cup-shadow' }));

    const scale = SCALE[model.sizeCode] ?? 1;
    const group = el('g', { transform: `translate(120 ${CUP.bottomY}) scale(${scale}) translate(-120 -${CUP.bottomY})` });
    svg.append(group);

    const inside = el('g', { 'clip-path': 'url(#cup-clip)' });
    group.append(inside);
    inside.append(el('rect', { x: 0, y: 0, width: 240, height: 300, class: 'cup-inside' }));

    if (!model.slots) {
        group.append(el('polygon', { points: outline, class: 'cup-outline' }));
        group.append(el('text', { x: 120, y: 172, 'text-anchor': 'middle', class: 'cup-empty-label' }, 'Wybierz rozmiar'));
        return;
    }

    const slotHeight = (FILL_BOTTOM - FILL_TOP) / model.slots;
    const labels = [];

    // Rysujemy od górnej warstwy do dolnej: każda kolejna (niższa) zakrywa
    // dół poprzedniej, a jej falista góra zostaje widoczna.
    for (let i = model.slots - 1; i >= 0; i--) {
        const layer = model.layers[i] ?? null;
        const topY = FILL_BOTTOM - (i + 1) * slotHeight;
        const midY = topY + slotHeight / 2 + 4;

        if (layer) {
            const fill = texture(defs, `tex-layer-${i}`, layer, { x: 0, y: topY - 8, width: 240, height: slotHeight + 16 });
            inside.append(el('path', { d: layerPath(topY, i), fill, class: 'cup-layer' }));
            const fontSize = slotHeight < 40 ? 10.5 : 11.5;
            labels.push([fitText(layer.name, widthAt(midY), fontSize), midY, fontSize]);
        } else {
            inside.append(el('path', { d: layerPath(topY, i), class: 'cup-layer-empty' }));
            inside.append(el('text', {
                x: 120, y: midY, 'text-anchor': 'middle', class: 'cup-slot-label',
            }, `warstwa ${i + 1}`));
        }
    }

    // Etykiety na końcu, żeby żadna warstwa ich nie zasłoniła.
    labels.forEach(([text, y, size]) => label(inside, text, 120, y, size));

    // Toppingi: kopczyk nad rantem; przy dwóch toppingach prawa połowa ma drugi.
    if (model.toppings.length) {
        const box = { x: MOUND.left, y: MOUND.peak - 2, width: MOUND.right - MOUND.left, height: MOUND.base - MOUND.peak + 4 };
        const toppingsGroup = el('g', { class: 'cup-toppings' });
        model.toppings.forEach((topping, index) => {
            const fill = texture(defs, `tex-topping-${index}`, topping, box);
            const attrs = { d: MOUND_PATH, fill, class: 'cup-mound' };
            if (index === 1) attrs['clip-path'] = 'url(#mound-right)';
            toppingsGroup.append(el('path', attrs));
        });
        if (model.toppings.length > 1) {
            toppingsGroup.append(el('line', { x1: 120, y1: MOUND.peak, x2: 120, y2: MOUND.base, class: 'cup-mound-split' }));
        }
        group.append(toppingsGroup);
    }

    // Obrys i rant kubka na wierzchu.
    group.append(el('polygon', { points: outline, class: 'cup-outline' }));
    group.append(el('rect', { x: CUP.topLeft - 6, y: CUP.topY - 6, width: CUP.topRight - CUP.topLeft + 12, height: 9, rx: 4.5, class: 'cup-rim' }));
    group.append(el('path', { d: `M ${CUP.topLeft + 14} ${CUP.topY + 18} L ${CUP.bottomLeft + 10} ${CUP.bottomY - 16}`, class: 'cup-shine' }));

    // Znaczek extra białka.
    if (model.protein) {
        const badge = el('g', { class: 'protein-badge' });
        badge.append(el('circle', { cx: 210, cy: 30, r: 26 }));
        badge.append(el('text', { x: 210, y: 28, 'text-anchor': 'middle', class: 'protein-badge-big' }, `+${model.protein} g`));
        badge.append(el('text', { x: 210, y: 41, 'text-anchor': 'middle', class: 'protein-badge-small' }, 'białka'));
        svg.append(badge);
    }
}
