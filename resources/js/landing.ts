import UIkit from 'uikit';
import Icons from 'uikit/dist/js/uikit-icons';

UIkit.use(Icons);

/**
 * Pixel sprites for the landing page, drawn on <canvas data-sprite="…">.
 * Each row is one line of pixels; every character is a colour from
 * {@link palette}, and "." is transparent.
 */
const sprites: Record<string, string[]> = {
    bull: [
        'g............g',
        'gg..........gg',
        '.gg........gg.',
        '..ggddddddgg..',
        '...dddddddd...',
        '.dddddddddddd.',
        '...dkddddkd...',
        '...dddddddd...',
        '....dddddd....',
        '....yyyyyy....',
        '....ykyyky....',
        '.....yyyy.....',
    ],
    bolt: [
        '....yyyg',
        '...yyyg.',
        '...yyg..',
        '..yyyg..',
        '..yyg...',
        '.yyyyyyg',
        '....yyg.',
        '...yyg..',
        '...yg...',
        '..yg....',
        '..g.....',
        '.g......',
    ],
    moon: [
        '....bbbbb...',
        '..bbbbl.....',
        '.bbbl.......',
        '.bbl........',
        'bbbl........',
        'bbl.........',
        'bbl.........',
        'bbbl........',
        '.bbl........',
        '.bbbl.......',
        '..bbbbl.....',
        '....bbbbb...',
    ],
    ziggurat: [
        '......gggg......',
        '......gyyg......',
        '....gggddggg....',
        '....gyyddyyg....',
        '..gggyyddyyggg..',
        '..gyyyyddyyyyg..',
        'gggyyyyddyyyyggg',
        'gyyyyyyddyyyyyyg',
        'gggggggggggggggg',
    ],
    trident: [
        'b...b...b',
        'b...b...b',
        'b...b...b',
        'bb..b..bb',
        '.bbbbbbb.',
        '....b....',
        '....d....',
        '....d....',
        '....d....',
        '....d....',
        '....d....',
        '....d....',
        '....k....',
    ],
    tablet: [
        '.dddddddddd.',
        'dyyyyyyyyyyd',
        'dygyggyygyyd',
        'dyyyyyyyyyyd',
        'dyggygyyggyd',
        'dyyyyyyyyyyd',
        'dyygyggyygyd',
        'dyyyyyyyyyyd',
        'dygygyygggyd',
        'dyyyyyyyyyyd',
        '.dddddddddd.',
    ],
};

/**
 * Sprite colours come from CSS custom properties so they follow the
 * light/dark palette.
 */
const palette: Record<string, string> = {
    g: '--px-gold',
    y: '--px-sand',
    b: '--px-blue',
    l: '--px-sky',
    d: '--px-brown',
    k: '--px-ink',
};

function drawSprite(canvas: HTMLCanvasElement): void {
    const rows = sprites[canvas.dataset.sprite ?? ''];

    if (!rows) {
        return;
    }

    const pixel = Number(canvas.dataset.pixel ?? 5);
    const ratio = window.devicePixelRatio || 1;
    const columns = Math.max(...rows.map((row) => row.length));
    const styles = getComputedStyle(canvas);

    canvas.width = columns * pixel * ratio;
    canvas.height = rows.length * pixel * ratio;
    canvas.style.width = `${columns * pixel}px`;
    canvas.style.height = `${rows.length * pixel}px`;

    const context = canvas.getContext('2d');

    if (!context) {
        return;
    }

    const size = pixel * ratio;

    rows.forEach((row, y) => {
        [...row].forEach((character, x) => {
            const variable = palette[character];

            if (!variable) {
                return;
            }

            context.fillStyle = styles.getPropertyValue(variable).trim();
            context.fillRect(x * size, y * size, size, size);
        });
    });
}

function drawAllSprites(): void {
    document.querySelectorAll<HTMLCanvasElement>('canvas[data-sprite]').forEach(drawSprite);
}

drawAllSprites();
window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', drawAllSprites);
