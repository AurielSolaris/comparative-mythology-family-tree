/**
 * Pixel icons for entities' elements, drawn on the canvas nodes. Same
 * format as the landing page sprites: each row is one line of pixels,
 * every character a colour from {@link PALETTE}, and "." is transparent.
 */
const SPRITES = {
    book: [
        '.nnnnnnnn',
        'nnnnnnnnn',
        'nnnhhhnnn',
        'nnnnnnnnn',
        'nnnnnnnnn',
        'nnnnnnnnn',
        'nnnnnnnnn',
        'npppppppn',
        '.nnnnnnnn',
    ],
    bolt: [
        '....yyyy.',
        '...yyyy..',
        '...yyy...',
        '..yyy....',
        '..yyyyyy.',
        '.yyyyyy..',
        '....yyy..',
        '...yyy...',
        '...yy....',
        '..yy.....',
        '..y......',
    ],
    sword: [
        '....s....',
        '...sws...',
        '...sws...',
        '...sws...',
        '...sws...',
        '...sws...',
        '...sws...',
        '.hhhhhhh.',
        '....n....',
        '....n....',
        '...hhh...',
    ],
    leaf: [
        '.....gggG',
        '...ggggGg',
        '..ggggGgg',
        '.ggggGgg.',
        '.gggGggg.',
        '.ggGggg..',
        '.gGggg...',
        '.Gggg....',
        'G........',
    ],
    droplet: [
        '....b....',
        '....b....',
        '...bbb...',
        '...bbb...',
        '..bbbbb..',
        '.bblbbbb.',
        '.blbbbbb.',
        '.blbbbbb.',
        '.bbbbbbb.',
        '..bbbbb..',
        '...bbb...',
    ],
    flame: [
        '....r....',
        '...rr....',
        '...rrr...',
        '..rroor..',
        '..rooor..',
        '.rrooyor.',
        '.rooyyor.',
        '.royyyor.',
        '.royyyor.',
        '..ryyyr..',
        '...rrr...',
    ],
    sun: [
        '....o....',
        '.o..o..o.',
        '..ryyyr..',
        '..yyyyy..',
        'ooyyyyyoo',
        '..yyyyy..',
        '..ryyyr..',
        '.o..o..o.',
        '....o....',
    ],
    heart: [
        '.mm...mm.',
        'mPmm.mmmm',
        'mPmmmmmmm',
        'mmmmmmmmm',
        '.mmmmmmm.',
        '..mmmmm..',
        '...mmm...',
        '....m....',
    ],
    /** A raised open hand, as when swearing an oath. */
    hand: [
        '...q.q...',
        '.q.q.q.q.',
        '.q.q.q.q.',
        '.q.q.q.q.',
        '.qqqqqqq.',
        'qqqqqqqq.',
        '.qqqqqqq.',
        '..qqqqq..',
        '..qqqqq..',
        '..nnnnn..',
    ],
    /** Two matching figures: the "is like" badge in comparisons, not an element. */
    twins: [
        '.vvv...vvv.',
        '.vvv...vvv.',
        '..v.....v..',
        'vvvvv.vvvvv',
        '..v.....v..',
        '.v.v...v.v.',
        'v...v.v...v',
    ],
} satisfies Record<string, string[]>;

type ElementIcon = keyof typeof SPRITES;

const PALETTE: Record<string, string> = {
    b: '#1e87f0',
    l: '#9fd0ff',
    m: '#e8457a',
    P: '#ffb8cc',
    g: '#4caf50',
    G: '#2e7d32',
    h: '#c8962e',
    n: '#8b5a2b',
    o: '#f7931e',
    p: '#f3e6c4',
    q: '#e0a878',
    r: '#e0402f',
    s: '#9aa3ad',
    v: '#7c4dff',
    w: '#e6eaee',
    y: '#f5b301',
};

/**
 * Words (lowercase) that map an element to an icon. An element like
 * "Sea / Storm" takes the icon of its first recognised word.
 */
const KEYWORDS: Record<string, ElementIcon> = {
    wisdom: 'book',
    knowledge: 'book',
    learning: 'book',
    writing: 'book',
    storm: 'bolt',
    storms: 'bolt',
    thunder: 'bolt',
    lightning: 'bolt',
    weather: 'bolt',
    war: 'sword',
    battle: 'sword',
    warfare: 'sword',
    fertility: 'leaf',
    harvest: 'leaf',
    agriculture: 'leaf',
    vegetation: 'leaf',
    growth: 'leaf',
    sea: 'droplet',
    seas: 'droplet',
    ocean: 'droplet',
    water: 'droplet',
    waters: 'droplet',
    river: 'droplet',
    rivers: 'droplet',
    rain: 'droplet',
    fire: 'flame',
    flame: 'flame',
    flames: 'flame',
    hearth: 'flame',
    oath: 'hand',
    oaths: 'hand',
    covenant: 'hand',
    vow: 'hand',
    vows: 'hand',
    love: 'heart',
    desire: 'heart',
    passion: 'heart',
    sun: 'sun',
    solar: 'sun',
    daylight: 'sun',
    dawn: 'sun',
};

/** Each sprite pre-rendered at one canvas pixel per sprite pixel. */
const rendered = new Map<ElementIcon, HTMLCanvasElement>();

function iconFor(element: string | null): ElementIcon | null {
    if (!element) {
        return null;
    }

    for (const word of element.toLowerCase().split(/[^\p{L}]+/u)) {
        if (word in KEYWORDS) {
            return KEYWORDS[word];
        }
    }

    return null;
}

function render(icon: ElementIcon): HTMLCanvasElement {
    const cached = rendered.get(icon);

    if (cached) {
        return cached;
    }

    const rows = SPRITES[icon];
    const canvas = document.createElement('canvas');
    canvas.width = Math.max(...rows.map((row) => row.length));
    canvas.height = rows.length;
    const context = canvas.getContext('2d')!;

    rows.forEach((row, y) => {
        [...row].forEach((character, x) => {
            if (PALETTE[character]) {
                context.fillStyle = PALETTE[character];
                context.fillRect(x, y, 1, 1);
            }
        });
    });

    rendered.set(icon, canvas);

    return canvas;
}

/**
 * The pixel icon for an element, or null when the element has none.
 */
export function elementSprite(element: string | null): HTMLCanvasElement | null {
    const icon = iconFor(element);

    return icon ? render(icon) : null;
}

/** The pixel "twins" icon heading the syncretism part of the legend. */
export function likenessSprite(): HTMLCanvasElement {
    return render('twins');
}

/**
 * Paints the legend's <canvas data-legend-icon="…"> elements: an element
 * name (e.g. "Storm"), or "likeness" for the twins icon.
 */
export function paintLegendIcons(root: ParentNode, pixel = 2): void {
    const ratio = window.devicePixelRatio || 1;

    root.querySelectorAll<HTMLCanvasElement>('canvas[data-legend-icon]').forEach((canvas) => {
        const name = canvas.dataset.legendIcon ?? '';
        const sprite = name === 'likeness' ? likenessSprite() : elementSprite(name);

        if (!sprite) {
            return;
        }

        canvas.width = sprite.width * pixel * ratio;
        canvas.height = sprite.height * pixel * ratio;
        canvas.style.width = `${sprite.width * pixel}px`;
        canvas.style.height = `${sprite.height * pixel}px`;

        const context = canvas.getContext('2d')!;
        context.imageSmoothingEnabled = false;
        context.drawImage(sprite, 0, 0, canvas.width, canvas.height);
    });
}
