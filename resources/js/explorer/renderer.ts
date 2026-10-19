import { elementSprite } from './elements';
import { COLUMN_GAP, CONSORT_GAP, type Lane, layoutGraph, NODE_HEIGHT, NODE_WIDTH, ROW_GAP } from './layout';
import { childWord, pairWords } from './terms';
import type { Graph, GraphEdge, GraphNode, Point, ReligionRef } from './types';

/** UIkit's default palette, so the canvas matches the surrounding components. */
export const COLORS = {
    background: '#ffffff',
    grid: '#e5e5e5',
    text: '#333333',
    muted: '#999999',
    border: '#e5e5e5',
    primary: '#1e87f0',
    area: '#cfe2f6',
    areaFill: 'rgba(30, 135, 240, 0.03)',
    parent: '#b4b4b4',
    consort: '#f0506e',
    sibling: '#999999',
    equivalent: '#1e87f0',
    cognate: '#32a852',
    analogue: '#8c8c8c',
    polemic: '#b03a2e',
    syncretism: '#7c4dff',
    council: '#faa05a',
    coRuler: '#c8962e',
};

const MIN_SCALE = 0.15;
const MAX_SCALE = 3;
const FIT_PADDING = 80;
const CLICK_TOLERANCE = 5;
const FONT = '"Instrument Sans", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif';
const LABEL_MAX_WIDTH = 170;
const LABEL_MIN_SCALE = 0.45;

/** Edge labels; directed syncretism reads from the source to the result, along the arrow. */
const KIND_LABELS: Record<Exclude<GraphEdge['kind'], 'parent'>, string> = {
    consort: 'consort',
    sibling: 'siblings',
    council: 'council/court',
    co_ruler: '♛ co-rulers',
    equivalent: '≈ equivalent',
    cognate: '⑂ cognates',
    analogue: '∼ analogues',
    fusion: 'fused into',
    absorption: 'absorbed into',
    avatar: 'avatar',
    aspect: 'form / aspect',
    derivation: 'later form',
    adoption: 'adopted as',
    interpretation: 'name used for',
    supplanting: 'supplanted by',
    fission: 'split into',
    demonization: 'demonized as',
    deification: 'deified as',
    name_derivation: 'gave its name to',
    polemic: '⚔ polemic',
};

/**
 * The area nodes can be dragged around in: the space the layout needs,
 * plus this share of its width and height on each side (10% in total),
 * and at least AREA_MIN_PADDING.
 */
const AREA_MARGIN = 0.05;
const AREA_MIN_PADDING = 32;

const ARROW_SIZE = 7;
/** Size of one element-icon pixel on the canvas, at 100% zoom. */
const ELEMENT_PIXEL = 2;
/** Room above the top row of a comparison for the religion titles. */
const LANE_HEADER = 70;
const LANE_PADDING = 40;

interface EdgeLabel {
    /** Places along the connector to try, best first. */
    candidates: Point[];
    text: string;
    color: string;
    maxWidth: number;
}

interface Rect {
    left: number;
    top: number;
    right: number;
    bottom: number;
}

const LABEL_HEIGHT = 18;
const LABEL_CANDIDATES = [0.5, 0.38, 0.62, 0.26, 0.74, 0.15, 0.85];

interface Callbacks {
    /** A node was clicked (null: empty space was clicked). */
    onSelect(node: GraphNode | null): void;
    /** A node was double-clicked. */
    onActivate(node: GraphNode): void;
    /** A switch-sides button was clicked. Buttons are only drawn when this is given. */
    onSwap?(edge: GraphEdge): void;
}

interface SwapButton {
    edge: GraphEdge;
    at: Point;
}

const SWAP_BUTTON_RADIUS = 11;

/**
 * Draws a graph as a family tree on a canvas, with drag to pan, wheel or
 * pinch to zoom, click to select and double-click to activate. Nodes can
 * be dragged to rearrange them within the tree's area.
 */
export class TreeCanvas {
    /** Swapped for an offscreen canvas's context while exporting a PNG. */
    private context: CanvasRenderingContext2D;
    private exporting = false;
    private graph: Graph | null = null;
    private positions = new Map<number, Point>();
    private lanes: Lane[] = [];
    /** Where nodes may be dragged (world coordinates); null without nodes. */
    private area: Rect | null = null;
    /** Positions of the nodes the viewer has dragged, kept across reloads of the same tree. */
    private readonly arranged = new Map<number, Point>();
    private nodes = new Map<number, GraphNode>();
    private selectedId: number | null = null;
    private hoverId: number | null = null;
    private swapButtons: SwapButton[] = [];
    private hoverSwapId: number | null = null;

    /** Screen space covered by floating panels, kept clear when fitting. */
    private insets = { top: 0, right: 0, bottom: 0, left: 0 };

    /** Screen = world * scale + offset (in CSS pixels). */
    private scale = 1;
    private offset: Point = { x: 0, y: 0 };

    private readonly pointers = new Map<number, Point>();
    private drag: { start: Point; offset: Point; moved: boolean } | null = null;
    private nodeDrag: { id: number; start: Point; origin: Point; moved: boolean } | null = null;
    private pinch: { distance: number; scale: number } | null = null;
    private frame = 0;

    constructor(
        private readonly canvas: HTMLCanvasElement,
        private readonly callbacks: Callbacks,
    ) {
        this.context = canvas.getContext('2d')!;

        new ResizeObserver(() => this.resize()).observe(canvas);
        document.fonts?.ready.then(() => this.requestDraw());

        canvas.addEventListener('pointerdown', (event) => this.pointerDown(event));
        canvas.addEventListener('pointermove', (event) => this.pointerMove(event));
        canvas.addEventListener('pointerup', (event) => this.pointerUp(event));
        canvas.addEventListener('pointercancel', (event) => this.pointerUp(event, true));
        canvas.addEventListener('pointerleave', () => this.setHover(null));
        canvas.addEventListener('wheel', (event) => this.wheel(event), { passive: false });
        canvas.addEventListener('dblclick', (event) => this.doubleClick(event));
        canvas.addEventListener('keydown', (event) => this.keyDown(event));

        this.resize();
    }

    /**
     * Shows a graph. With keepView the zoom, position, selection and any
     * dragged nodes stay as they are (used after an edit that only
     * rearranges the same tree).
     */
    setGraph(graph: Graph | null, keepView = false): void {
        this.graph = graph;

        const layout = graph ? layoutGraph(graph) : { positions: new Map<number, Point>(), lanes: [] };
        this.positions = layout.positions;
        this.lanes = layout.lanes;
        this.nodes = new Map((graph?.nodes ?? []).map((node) => [node.id, node]));
        this.area = this.positions.size ? padArea(this.contentBounds()) : null;
        this.hoverId = null;
        this.hoverSwapId = null;
        this.nodeDrag = null;

        if (!keepView) {
            this.arranged.clear();
        }

        for (const [id, point] of this.arranged) {
            if (this.positions.has(id)) {
                this.positions.set(id, this.clampToArea(id, point));
            } else {
                this.arranged.delete(id);
            }
        }

        if (keepView && this.selectedId !== null && this.nodes.has(this.selectedId)) {
            this.requestDraw();

            return;
        }

        this.selectedId = graph?.focus ?? null;

        if (keepView) {
            this.requestDraw();
        } else {
            this.fit();
        }
    }

    select(id: number | null): void {
        this.selectedId = id;
        this.requestDraw();
    }

    setInsets(insets: Partial<typeof this.insets>): void {
        this.insets = { ...this.insets, ...insets };
    }

    /** Puts dragged nodes back where the layout placed them. */
    resetArrangement(): void {
        this.arranged.clear();
        this.setGraph(this.graph, true);
    }

    /** Zooms so the whole area is visible in the space not covered by panels. */
    fit(): void {
        const size = this.size();
        const width = size.width - this.insets.left - this.insets.right;
        const height = size.height - this.insets.top - this.insets.bottom;

        if (!this.area) {
            this.scale = 1;
            this.offset = { x: size.width / 2, y: size.height / 2 };
            this.requestDraw();

            return;
        }

        const bounds = this.area;

        this.scale = clamp(
            Math.min(
                (width - FIT_PADDING * 2) / (bounds.right - bounds.left),
                (height - FIT_PADDING * 2) / (bounds.bottom - bounds.top),
                1.2,
            ),
            MIN_SCALE,
            MAX_SCALE,
        );
        this.offset = {
            x: this.insets.left + width / 2 - ((bounds.left + bounds.right) / 2) * this.scale,
            y: this.insets.top + height / 2 - ((bounds.top + bounds.bottom) / 2) * this.scale,
        };
        this.requestDraw();
    }

    /** Zooms by the factor around a screen point (default: the centre). */
    zoomBy(factor: number, around?: Point): void {
        const { width, height } = this.size();
        const anchor = around ?? { x: width / 2, y: height / 2 };
        const scale = clamp(this.scale * factor, MIN_SCALE, MAX_SCALE);

        this.offset = {
            x: anchor.x - ((anchor.x - this.offset.x) * scale) / this.scale,
            y: anchor.y - ((anchor.y - this.offset.y) * scale) / this.scale,
        };
        this.scale = scale;
        this.requestDraw();
    }

    centerOn(id: number): void {
        const point = this.positions.get(id);

        if (!point) {
            return;
        }

        const { width, height } = this.size();
        this.offset = { x: width / 2 - point.x * this.scale, y: height / 2 - point.y * this.scale };
        this.requestDraw();
    }

    // --- input ----------------------------------------------------------------

    private pointerDown(event: PointerEvent): void {
        this.canvas.focus();
        this.canvas.setPointerCapture(event.pointerId);
        this.pointers.set(event.pointerId, this.local(event));

        if (this.pointers.size === 1) {
            const start = this.local(event);
            const node = this.swapButtonAt(start) ? null : this.nodeAt(start);

            if (node) {
                this.nodeDrag = { id: node.id, start, origin: { ...this.positions.get(node.id)! }, moved: false };
            } else {
                this.drag = { start, offset: { ...this.offset }, moved: false };
            }
        } else if (this.pointers.size === 2) {
            this.drag = null;
            this.nodeDrag = null;
            this.pinch = { distance: this.pointerDistance(), scale: this.scale };
        }
    }

    private pointerMove(event: PointerEvent): void {
        const point = this.local(event);

        if (!this.pointers.has(event.pointerId)) {
            const button = this.swapButtonAt(point);

            this.setHover(button ? null : (this.nodeAt(point)?.id ?? null), button?.edge.id ?? null);

            if (!button) {
                this.canvas.title = '';
            }

            return;
        }

        this.pointers.set(event.pointerId, point);

        if (this.pinch && this.pointers.size === 2) {
            const [a, b] = [...this.pointers.values()];
            const target = (this.pinch.scale * this.pointerDistance()) / this.pinch.distance;
            this.zoomBy(target / this.scale, { x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 });

            return;
        }

        if (this.nodeDrag) {
            const dx = point.x - this.nodeDrag.start.x;
            const dy = point.y - this.nodeDrag.start.y;

            this.nodeDrag.moved ||= Math.hypot(dx, dy) > CLICK_TOLERANCE;

            if (this.nodeDrag.moved) {
                const { id, origin } = this.nodeDrag;
                const moved = this.clampToArea(id, { x: origin.x + dx / this.scale, y: origin.y + dy / this.scale });

                this.canvas.style.cursor = 'grabbing';
                this.positions.set(id, moved);
                this.arranged.set(id, moved);
                this.requestDraw();
            }

            return;
        }

        if (this.drag) {
            const dx = point.x - this.drag.start.x;
            const dy = point.y - this.drag.start.y;

            this.drag.moved ||= Math.hypot(dx, dy) > CLICK_TOLERANCE;

            if (this.drag.moved) {
                this.canvas.style.cursor = 'grabbing';
                this.offset = { x: this.drag.offset.x + dx, y: this.drag.offset.y + dy };
                this.requestDraw();
            }
        }
    }

    private pointerUp(event: PointerEvent, cancelled = false): void {
        const gesture = this.drag ?? this.nodeDrag;

        if (!cancelled && gesture && !gesture.moved && this.pointers.size === 1) {
            const button = this.swapButtonAt(this.local(event));

            if (button) {
                this.callbacks.onSwap?.(button.edge);
            } else {
                const node = this.nodeAt(this.local(event));
                this.select(node?.id ?? null);
                this.callbacks.onSelect(node);
            }
        }

        this.pointers.delete(event.pointerId);
        this.drag = null;
        this.nodeDrag = null;

        if (this.pointers.size < 2) {
            this.pinch = null;
        }

        this.canvas.style.cursor = this.hoverId !== null || this.hoverSwapId !== null ? 'pointer' : 'grab';
    }

    private wheel(event: WheelEvent): void {
        event.preventDefault();
        this.zoomBy(Math.exp(-event.deltaY * 0.0015), this.local(event));
    }

    private doubleClick(event: MouseEvent): void {
        if (this.swapButtonAt(this.local(event))) {
            return;
        }

        const node = this.nodeAt(this.local(event));

        if (node) {
            this.callbacks.onActivate(node);
        }
    }

    private keyDown(event: KeyboardEvent): void {
        const actions: Record<string, () => void> = {
            '+': () => this.zoomBy(1.25),
            '=': () => this.zoomBy(1.25),
            '-': () => this.zoomBy(0.8),
            '0': () => this.fit(),
            r: () => this.resetArrangement(),
        };

        if (actions[event.key]) {
            event.preventDefault();
            actions[event.key]();
        }
    }

    private setHover(id: number | null, swapId: number | null = null): void {
        if (id !== this.hoverId || swapId !== this.hoverSwapId) {
            this.hoverId = id;
            this.hoverSwapId = swapId;
            this.canvas.style.cursor = id !== null || swapId !== null ? 'pointer' : 'grab';
            this.canvas.title = swapId !== null ? 'Switch sides' : '';
            this.requestDraw();
        }
    }

    private swapButtonAt(screen: Point): SwapButton | null {
        const x = (screen.x - this.offset.x) / this.scale;
        const y = (screen.y - this.offset.y) / this.scale;

        return (
            this.swapButtons.find(
                (button) => Math.hypot(x - button.at.x, y - button.at.y) <= SWAP_BUTTON_RADIUS + 2,
            ) ?? null
        );
    }

    private nodeAt(screen: Point): GraphNode | null {
        const x = (screen.x - this.offset.x) / this.scale;
        const y = (screen.y - this.offset.y) / this.scale;

        for (const [id, point] of this.positions) {
            if (Math.abs(x - point.x) <= NODE_WIDTH / 2 && Math.abs(y - point.y) <= NODE_HEIGHT / 2) {
                return this.nodes.get(id) ?? null;
            }
        }

        return null;
    }

    /** The space the layout's nodes, comparison columns and arcs (with their labels or badges) take up. */
    private contentBounds(): Rect {
        const xs = [...this.positions.values()].map((point) => point.x);
        const ys = [...this.positions.values()].map((point) => point.y);
        const lanePadding = this.lanes.length ? LANE_PADDING : 0;
        const apexes = [
            ...(this.graph?.edges ?? []).flatMap((edge) => {
                const side = this.arcSide(edge);
                const from = this.positions.get(edge.from);
                const to = this.positions.get(edge.to);

                return side && from && to ? [arcApex(from, to, side).y] : [];
            }),
        ];
        const reach = LABEL_HEIGHT / 2;

        return {
            left: Math.min(...xs) - NODE_WIDTH / 2 - lanePadding,
            right: Math.max(...xs) + NODE_WIDTH / 2 + lanePadding,
            top: Math.min(Math.min(...ys) - NODE_HEIGHT / 2 - (this.lanes.length ? LANE_HEADER : 0), ...apexes.map((y) => y - reach)),
            bottom: Math.max(Math.max(...ys) + NODE_HEIGHT / 2 + lanePadding, ...apexes.map((y) => y + reach)),
        };
    }

    /**
     * Which way an edge bulges when drawEdge draws it as an arc (-1 above,
     * 1 below), or null when it is drawn as an elbow or a straight line.
     */
    private arcSide(edge: GraphEdge): -1 | 1 | null {
        const from = this.positions.get(edge.from);
        const to = this.positions.get(edge.to);

        if (!from || !to) {
            return null;
        }

        switch (edge.kind) {
            case 'sibling':
            case 'co_ruler':
                return -1;
            case 'consort':
                return areAdjacentPartners(from, to) ? null : -1;
            case 'council':
                return to.y - from.y < NODE_HEIGHT ? -1 : null;
            default:
                return edge.syncretic ? this.syncreticSide(edge) : null;
        }
    }

    /**
     * Syncretism arcs run below the pair; when one pair has several (say
     * cognates that were later equated), they alternate below and above.
     */
    private syncreticSide(edge: GraphEdge): -1 | 1 {
        const samePair = (this.graph?.edges ?? []).filter(
            (other) =>
                other.syncretic &&
                ((other.from === edge.from && other.to === edge.to) || (other.from === edge.to && other.to === edge.from)),
        );

        return samePair.indexOf(edge) % 2 === 0 ? 1 : -1;
    }

    /**
     * The nearest centre for the node that keeps it (and, in a comparison,
     * its column's header and padding) inside the area. In a comparison a
     * node also stays within its religion's column.
     */
    private clampToArea(id: number, point: Point): Point {
        if (!this.area) {
            return point;
        }

        const lanePadding = this.lanes.length ? LANE_PADDING : 0;
        let left = this.area.left + NODE_WIDTH / 2 + lanePadding;
        let right = this.area.right - NODE_WIDTH / 2 - lanePadding;
        const top = this.area.top + NODE_HEIGHT / 2 + (this.lanes.length ? LANE_HEADER : 0);
        const bottom = this.area.bottom - NODE_HEIGHT / 2 - lanePadding;

        const laneSlug = this.nodes.get(id)?.lane;
        const lane = laneSlug ? this.lanes.find((candidate) => candidate.slug === laneSlug) : undefined;

        if (lane) {
            left = Math.max(left, lane.left - LANE_PADDING / 2);
            right = Math.min(right, lane.right + LANE_PADDING / 2);
        }

        return { x: clamp(point.x, left, Math.max(left, right)), y: clamp(point.y, top, Math.max(top, bottom)) };
    }

    private local(event: MouseEvent): Point {
        const rect = this.canvas.getBoundingClientRect();

        return { x: event.clientX - rect.left, y: event.clientY - rect.top };
    }

    private pointerDistance(): number {
        const [a, b] = [...this.pointers.values()];

        return Math.hypot(a.x - b.x, a.y - b.y) || 1;
    }

    // --- drawing --------------------------------------------------------------

    private size(): { width: number; height: number } {
        return { width: this.canvas.clientWidth, height: this.canvas.clientHeight };
    }

    private resize(): void {
        const ratio = window.devicePixelRatio || 1;
        const { width, height } = this.size();

        this.canvas.width = Math.round(width * ratio);
        this.canvas.height = Math.round(height * ratio);
        this.draw();
    }

    private requestDraw(): void {
        this.frame ||= requestAnimationFrame(() => {
            this.frame = 0;
            this.draw();
        });
    }

    private draw(): void {
        this.paint(window.devicePixelRatio || 1, this.size());
    }

    /**
     * Renders the whole tree (its full area, at 100% zoom) to a PNG, without
     * the dot grid, the drag area frame, hover highlights or switch-sides
     * buttons. Resolves to null when there is nothing to export.
     */
    exportPng(pixelRatio = 2): Promise<Blob | null> {
        if (!this.graph || !this.area) {
            return Promise.resolve(null);
        }

        const padding = 24;
        const width = Math.ceil(this.area.right - this.area.left + padding * 2);
        const height = Math.ceil(this.area.bottom - this.area.top + padding * 2);
        // Browsers refuse canvases much past 16k pixels a side.
        const ratio = Math.min(pixelRatio, 16000 / width, 16000 / height);

        const target = document.createElement('canvas');
        target.width = Math.round(width * ratio);
        target.height = Math.round(height * ratio);

        const saved = { context: this.context, offset: this.offset, scale: this.scale, hoverId: this.hoverId, hoverSwapId: this.hoverSwapId };

        this.context = target.getContext('2d')!;
        this.offset = { x: padding - this.area.left, y: padding - this.area.top };
        this.scale = 1;
        this.hoverId = null;
        this.hoverSwapId = null;
        this.exporting = true;

        try {
            this.paint(ratio, { width, height });
        } finally {
            Object.assign(this, saved);
            this.exporting = false;
            this.requestDraw();
        }

        return new Promise((resolve) => target.toBlob(resolve, 'image/png'));
    }

    private paint(ratio: number, { width, height }: { width: number; height: number }): void {
        const context = this.context;

        context.setTransform(ratio, 0, 0, ratio, 0, 0);
        context.fillStyle = COLORS.background;
        context.fillRect(0, 0, width, height);

        if (!this.exporting) {
            this.drawGrid(width, height);
        }

        if (!this.graph) {
            return;
        }

        context.translate(this.offset.x, this.offset.y);
        context.scale(this.scale, this.scale);

        if (!this.exporting) {
            this.drawArea();
        }

        this.drawLanes();

        const active = this.hoverId ?? this.selectedId;
        const edges = [...this.graph.edges].sort(
            (a, b) => Number(this.touches(a, active)) - Number(this.touches(b, active)),
        );

        const labels: EdgeLabel[] = [];

        for (const edge of edges) {
            const label = this.drawEdge(edge, this.touches(edge, active));

            if (label) {
                labels.push(label);
            }
        }

        // The node being dragged is drawn last, on top of the others.
        const dragged = this.nodeDrag?.moved ? this.nodeDrag.id : null;

        for (const node of this.graph.nodes) {
            if (node.id !== dragged) {
                this.drawNode(node);
            }
        }

        if (dragged !== null && this.nodes.has(dragged)) {
            this.drawNode(this.nodes.get(dragged)!);
        }

        this.swapButtons = this.scale >= LABEL_MIN_SCALE && !this.exporting ? this.placeSwapButtons() : [];

        if (this.scale >= LABEL_MIN_SCALE) {
            // Highlighted edges were drawn last; give their labels first pick.
            this.drawLabels(labels.reverse(), this.swapButtons);
        }

        for (const button of this.swapButtons) {
            this.drawSwapButton(button);
        }
    }

    /**
     * A switch-sides button for each swappable pair drawn in the same row:
     * just above a partner's double line, or on top of a sibling arc.
     */
    private placeSwapButtons(): SwapButton[] {
        if (!this.callbacks.onSwap || !this.graph) {
            return [];
        }

        const buttons: SwapButton[] = [];

        for (const edge of this.graph.edges) {
            const from = this.positions.get(edge.from);
            const to = this.positions.get(edge.to);

            if (!edge.swappable || !from || !to || Math.abs(from.y - to.y) > 1) {
                continue;
            }

            buttons.push({
                edge,
                at: edge.kind === 'consort' && areAdjacentPartners(from, to)
                    ? { x: (from.x + to.x) / 2, y: from.y - NODE_HEIGHT / 2 + 4 }
                    : arcApex(from, to, -1),
            });
        }

        return buttons;
    }

    /** A round button with a two-headed arrow. */
    private drawSwapButton(button: SwapButton): void {
        const context = this.context;
        const hovered = button.edge.id === this.hoverSwapId;

        context.save();
        context.shadowColor = 'rgba(0, 0, 0, 0.12)';
        context.shadowBlur = hovered ? 10 : 6;
        context.fillStyle = hovered ? COLORS.primary : COLORS.background;
        context.beginPath();
        context.arc(button.at.x, button.at.y, SWAP_BUTTON_RADIUS, 0, Math.PI * 2);
        context.fill();
        context.restore();

        context.save();
        context.strokeStyle = COLORS.primary;
        context.lineWidth = 1;
        context.beginPath();
        context.arc(button.at.x, button.at.y, SWAP_BUTTON_RADIUS, 0, Math.PI * 2);
        context.stroke();

        // Two-headed horizontal arrow.
        const half = 5.5;
        const head = 3;
        context.strokeStyle = hovered ? '#ffffff' : COLORS.primary;
        context.lineWidth = 1.5;
        context.lineCap = 'round';
        context.lineJoin = 'round';
        context.beginPath();
        context.moveTo(button.at.x - half, button.at.y);
        context.lineTo(button.at.x + half, button.at.y);
        context.moveTo(button.at.x - half + head, button.at.y - head);
        context.lineTo(button.at.x - half, button.at.y);
        context.lineTo(button.at.x - half + head, button.at.y + head);
        context.moveTo(button.at.x + half - head, button.at.y - head);
        context.lineTo(button.at.x + half, button.at.y);
        context.lineTo(button.at.x + half - head, button.at.y + head);
        context.stroke();
        context.restore();
    }

    /**
     * Draws each label at the first of its candidate spots that covers
     * neither an entity nor another label; labels with no free spot are
     * left out (the connector and the details panel still show the relation).
     */
    private drawLabels(labels: EdgeLabel[], buttons: SwapButton[]): void {
        const context = this.context;
        const occupied: Rect[] = [
            ...[...this.positions.values()].map((point) => ({
                left: point.x - NODE_WIDTH / 2 - 4,
                right: point.x + NODE_WIDTH / 2 + 4,
                top: point.y - NODE_HEIGHT / 2 - 4,
                bottom: point.y + NODE_HEIGHT / 2 + 4,
            })),
            ...buttons.map((button) => ({
                left: button.at.x - SWAP_BUTTON_RADIUS - 3,
                right: button.at.x + SWAP_BUTTON_RADIUS + 3,
                top: button.at.y - SWAP_BUTTON_RADIUS - 3,
                bottom: button.at.y + SWAP_BUTTON_RADIUS + 3,
            })),
        ];

        context.font = `500 11px ${FONT}`;

        for (const label of labels) {
            const text = this.truncate(label.text, label.maxWidth - 14);
            const width = context.measureText(text).width + 14;

            const spot = label.candidates
                .map((at): Rect => ({
                    left: at.x - width / 2,
                    right: at.x + width / 2,
                    top: at.y - LABEL_HEIGHT / 2,
                    bottom: at.y + LABEL_HEIGHT / 2,
                }))
                .find((rect) => !occupied.some((other) => overlaps(rect, other)));

            if (spot && text !== '…') {
                occupied.push(spot);
                this.drawLabel(text, spot, label.color);
            }
        }
    }

    /** A faint dashed frame showing how far nodes can be dragged. */
    private drawArea(): void {
        if (!this.area) {
            return;
        }

        const context = this.context;
        const { left, top, right, bottom } = this.area;

        context.save();
        context.fillStyle = COLORS.areaFill;
        context.strokeStyle = COLORS.area;
        context.lineWidth = 1.5;
        context.setLineDash([8, 6]);
        context.beginPath();
        context.roundRect(left, top, right - left, bottom - top, 16);
        context.fill();
        context.stroke();
        context.restore();
    }

    /** A tinted column with a title for each religion in a comparison. */
    private drawLanes(): void {
        if (!this.lanes.length) {
            return;
        }

        const context = this.context;
        const ys = [...this.positions.values()].map((point) => point.y);
        const top = (ys.length ? Math.min(...ys) : 0) - NODE_HEIGHT / 2 - LANE_HEADER;
        const bottom = (ys.length ? Math.max(...ys) : 0) + NODE_HEIGHT / 2 + LANE_PADDING;

        for (const lane of this.lanes) {
            const left = lane.left - NODE_WIDTH / 2 - LANE_PADDING;
            const width = lane.right - lane.left + NODE_WIDTH + LANE_PADDING * 2;
            const color = religionColor(lane);

            context.save();
            context.globalAlpha = 0.07;
            context.fillStyle = color;
            context.beginPath();
            context.roundRect(left, top, width, bottom - top, 12);
            context.fill();
            context.globalAlpha = 1;
            context.fillStyle = color;
            context.fillRect(left, top, width, 4);
            context.font = `600 20px ${FONT}`;
            context.textAlign = 'center';
            context.textBaseline = 'middle';
            context.fillText(this.truncate(lane.name, width - 24), left + width / 2, top + LANE_HEADER / 2);
            context.restore();
        }
    }

    private drawGrid(width: number, height: number): void {
        const spacing = 28 * this.scale;

        if (spacing < 10) {
            return;
        }

        const context = this.context;
        context.fillStyle = COLORS.grid;

        for (let x = this.offset.x % spacing; x < width; x += spacing) {
            for (let y = this.offset.y % spacing; y < height; y += spacing) {
                context.fillRect(x, y, 1.5, 1.5);
            }
        }
    }

    private touches(edge: GraphEdge, id: number | null): boolean {
        return id !== null && (edge.from === id || edge.to === id);
    }

    /**
     * Draws the connector and returns where its label goes. Parent lines are
     * labelled by the child ("son", "daughter", "child"); every other kind by
     * its name. Tradition and note follow when recorded.
     */
    private drawEdge(edge: GraphEdge, highlighted: boolean): EdgeLabel | null {
        const from = this.positions.get(edge.from);
        const to = this.positions.get(edge.to);

        if (!from || !to) {
            return null;
        }

        const context = this.context;
        context.save();
        context.lineWidth = highlighted ? 2.5 : 1.5;
        context.globalAlpha = highlighted || this.hoverId === null ? 1 : 0.75;

        let candidates: Point[];
        let color: string;
        let maxWidth = LABEL_MAX_WIDTH;

        switch (edge.kind) {
            case 'parent':
                color = highlighted ? COLORS.text : COLORS.parent;
                context.strokeStyle = color;
                context.fillStyle = color;
                candidates = this.drawParentEdge(from, to, this.crossesLanes(edge));
                break;
            case 'council':
                color = COLORS.council;
                context.strokeStyle = color;
                context.fillStyle = color;
                context.setLineDash([8, 4, 2, 4]);
                candidates = this.drawDirectedCurve(from, to);
                break;
            case 'consort':
                color = COLORS.consort;
                context.strokeStyle = color;
                ({ candidates, maxWidth } = this.drawConsortEdge(from, to));
                break;
            case 'sibling':
                color = COLORS.sibling;
                context.strokeStyle = color;
                context.setLineDash([6, 5]);
                candidates = this.drawArc(from, to, -1);
                break;
            case 'co_ruler':
                color = COLORS.coRuler;
                context.strokeStyle = color;
                context.lineWidth += 0.5;
                candidates = this.drawArc(from, to, -1);
                break;
            default:
                ({ candidates, color } = this.drawSyncreticEdge(edge, from, to));
                break;
        }

        context.restore();

        const kind =
            edge.kind === 'parent'
                ? childWord(this.nodes.get(edge.to))
                : edge.kind === 'consort' || edge.kind === 'sibling'
                  ? pairWords(edge.kind, ...this.leftToRight(edge))
                  : KIND_LABELS[edge.kind];
        const doubt = edge.certainty?.value === 'hypothesis' || edge.certainty?.value === 'disputed' ? edge.certainty.value : null;
        const text = [kind, doubt, edge.tradition, edge.period, edge.note].filter(Boolean).join(' · ');

        return { candidates, text, color: edge.kind === 'parent' ? COLORS.muted : color, maxWidth };
    }

    /**
     * Equivalents as blue dots, cognates green, analogues grey, polemics red; the other
     * (directed) kinds as a dashed purple arc with an arrow at the result.
     * Hypotheses and disputed links are drawn fainter. Expects the line
     * width and alpha already set; returns label spots and the colour.
     */
    private drawSyncreticEdge(edge: GraphEdge, from: Point, to: Point): { candidates: Point[]; color: string } {
        const context = this.context;
        const side = this.syncreticSide(edge);
        const colors: Partial<Record<GraphEdge['kind'], string>> = {
            equivalent: COLORS.equivalent,
            cognate: COLORS.cognate,
            analogue: COLORS.analogue,
            polemic: COLORS.polemic,
        };
        const color = colors[edge.kind] ?? COLORS.syncretism;

        if (edge.certainty?.value === 'hypothesis' || edge.certainty?.value === 'disputed') {
            context.globalAlpha *= 0.55;
        }

        context.strokeStyle = color;
        context.fillStyle = color;

        if (edge.directed) {
            context.setLineDash([10, 5]);
        } else {
            context.setLineDash(edge.kind === 'analogue' ? [1, 7] : [2, 5]);
            context.lineCap = 'round';
        }

        const candidates = this.drawArc(from, to, side);

        if (edge.directed) {
            context.setLineDash([]);
            this.drawArrowhead({ x: to.x, y: to.y + (side * NODE_HEIGHT) / 2 }, side === 1 ? -Math.PI / 2 : Math.PI / 2);
        }

        return { candidates, color };
    }

    /** The edge's two entities in the order they are drawn, left first. */
    private leftToRight(edge: GraphEdge): [GraphNode | undefined, GraphNode | undefined] {
        const from = this.nodes.get(edge.from);
        const to = this.nodes.get(edge.to);
        const fromX = this.positions.get(edge.from)?.x ?? 0;
        const toX = this.positions.get(edge.to)?.x ?? 0;

        return fromX <= toX ? [from, to] : [to, from];
    }

    /** A rounded tag with the relation's name. */
    private drawLabel(text: string, rect: Rect, color: string): void {
        const context = this.context;

        context.save();
        context.fillStyle = COLORS.background;
        context.strokeStyle = color;
        context.lineWidth = 1;
        context.beginPath();
        context.roundRect(rect.left, rect.top, rect.right - rect.left, LABEL_HEIGHT, LABEL_HEIGHT / 2);
        context.fill();
        context.stroke();

        context.fillStyle = color;
        context.font = `500 11px ${FONT}`;
        context.textAlign = 'center';
        context.textBaseline = 'middle';
        context.fillText(text, (rect.left + rect.right) / 2, (rect.top + rect.bottom) / 2 + 0.5);
        context.restore();
    }

    /** Whether the edge joins entities in different columns of a comparison. */
    private crossesLanes(edge: GraphEdge): boolean {
        const from = this.nodes.get(edge.from)?.lane ?? null;
        const to = this.nodes.get(edge.to)?.lane ?? null;

        return from !== null && to !== null && from !== to;
    }

    /**
     * An elbow from the bottom of the parent to the top of the child; a
     * smooth curve when they are in different columns of a comparison or
     * when the generations disagree. Returns label spots along it.
     */
    private drawParentEdge(parent: Point, child: Point, crossesLanes = false): Point[] {
        const context = this.context;
        const start = { x: parent.x, y: parent.y + NODE_HEIGHT / 2 };
        const end = { x: child.x, y: child.y - NODE_HEIGHT / 2 };

        context.beginPath();
        context.moveTo(start.x, start.y);

        if (end.y - start.y > 16 && !crossesLanes) {
            // Just above the child's row, or halfway when a dragged child sits closer.
            const middle = Math.max((start.y + end.y) / 2, end.y - (ROW_GAP - NODE_HEIGHT) / 2);
            const radius = Math.max(0, Math.min(10, Math.abs(end.x - start.x) / 2, (middle - start.y) / 2));

            context.arcTo(start.x, middle, end.x, middle, radius);
            context.arcTo(end.x, middle, end.x, end.y, radius);
            context.lineTo(end.x, end.y - ARROW_SIZE);
            context.stroke();
            this.drawArrowhead(end, Math.PI / 2);

            return [
                { x: end.x, y: (middle + end.y) / 2 },
                { x: (start.x + end.x) / 2, y: middle },
                { x: start.x, y: (start.y + middle) / 2 },
            ];
        }

        const reach = Math.max(60, (end.y - start.y) / 2);
        const controls = [start, { x: start.x, y: start.y + reach }, { x: end.x, y: end.y - reach }, end] as const;
        context.bezierCurveTo(controls[1].x, controls[1].y, controls[2].x, controls[2].y, end.x, end.y);
        context.stroke();
        this.drawArrowhead(end, Math.atan2(end.y - controls[2].y, end.x - controls[2].x));

        return LABEL_CANDIDATES.map((t) => bezierPoint(controls, t));
    }

    /**
     * A double line between partners side by side, or an arc over the top
     * when they're in different rows or someone sits between them. Returns
     * label spots and room.
     */
    private drawConsortEdge(a: Point, b: Point): { candidates: Point[]; maxWidth: number } {
        const context = this.context;

        if (!areAdjacentPartners(a, b)) {
            return { candidates: this.drawArc(a, b, -1), maxWidth: LABEL_MAX_WIDTH };
        }

        const [left, right] = a.x < b.x ? [a, b] : [b, a];
        const startX = left.x + NODE_WIDTH / 2;
        const endX = right.x - NODE_WIDTH / 2;

        for (const dy of [-2.5, 2.5]) {
            context.beginPath();
            context.moveTo(startX, left.y + dy);
            context.lineTo(endX, right.y + dy);
            context.stroke();
        }

        return {
            candidates: [{ x: (startX + endX) / 2, y: (left.y + right.y) / 2 }],
            maxWidth: Math.min(LABEL_MAX_WIDTH, endX - startX - 8),
        };
    }

    /**
     * A curve with an arrow from a superior to a subordinate entity (head of
     * a council to a member): downwards when the member is lower, otherwise
     * an arc over the top. Returns label spots along it.
     */
    private drawDirectedCurve(from: Point, to: Point): Point[] {
        const context = this.context;

        if (to.y - from.y < NODE_HEIGHT) {
            const candidates = this.drawArc(from, to, -1);
            const bulge = -(40 + Math.min(Math.abs(from.x - to.x), 600) * 0.12);
            const end = { x: to.x, y: to.y - NODE_HEIGHT / 2 };

            context.setLineDash([]);
            this.drawArrowhead(end, Math.atan2(-bulge, 0));

            return candidates;
        }

        const start = { x: from.x, y: from.y + NODE_HEIGHT / 2 };
        const end = { x: to.x, y: to.y - NODE_HEIGHT / 2 };
        const controls = [start, { x: start.x, y: start.y + 70 }, { x: end.x, y: end.y - 70 }, end] as const;

        context.beginPath();
        context.moveTo(start.x, start.y);
        context.bezierCurveTo(controls[1].x, controls[1].y, controls[2].x, controls[2].y, end.x, end.y);
        context.stroke();
        context.setLineDash([]);
        this.drawArrowhead(end, Math.PI / 2);

        return LABEL_CANDIDATES.map((t) => bezierPoint(controls, t));
    }

    /** A filled arrowhead with its tip at the point, pointing along the angle. */
    private drawArrowhead(tip: Point, angle: number): void {
        const context = this.context;

        context.save();
        context.setLineDash([]);
        context.translate(tip.x, tip.y);
        context.rotate(angle);
        context.beginPath();
        context.moveTo(0, 0);
        context.lineTo(-ARROW_SIZE * 1.4, -ARROW_SIZE * 0.7);
        context.lineTo(-ARROW_SIZE * 1.4, ARROW_SIZE * 0.7);
        context.closePath();
        context.fill();
        context.restore();
    }

    /** A curve bulging above (-1) or below (1) the two nodes. Returns label spots along it. */
    private drawArc(a: Point, b: Point, side: -1 | 1): Point[] {
        const context = this.context;
        const edgeY = (point: Point) => point.y + (side * NODE_HEIGHT) / 2;
        const bulge = side * (40 + Math.min(Math.abs(a.x - b.x), 600) * 0.12);
        const controls = [
            { x: a.x, y: edgeY(a) },
            { x: a.x, y: edgeY(a) + bulge },
            { x: b.x, y: edgeY(b) + bulge },
            { x: b.x, y: edgeY(b) },
        ] as const;

        context.beginPath();
        context.moveTo(controls[0].x, controls[0].y);
        context.bezierCurveTo(controls[1].x, controls[1].y, controls[2].x, controls[2].y, controls[3].x, controls[3].y);
        context.stroke();

        return LABEL_CANDIDATES.map((t) => bezierPoint(controls, t));
    }

    private drawNode(node: GraphNode): void {
        const point = this.positions.get(node.id);

        if (!point) {
            return;
        }

        const context = this.context;
        const isFocus = node.id === this.graph?.focus;
        const isSelected = node.id === this.selectedId;
        const isHovered = node.id === this.hoverId;
        const left = point.x - NODE_WIDTH / 2;
        const top = point.y - NODE_HEIGHT / 2;

        context.save();
        context.shadowColor = 'rgba(0, 0, 0, 0.08)';
        context.shadowBlur = isHovered || isSelected ? 18 : 10;
        context.shadowOffsetY = 3;
        context.fillStyle = isFocus ? COLORS.primary : COLORS.background;
        context.beginPath();
        context.roundRect(left, top, NODE_WIDTH, NODE_HEIGHT, 6);
        context.fill();
        context.restore();

        context.save();
        context.beginPath();
        context.roundRect(left, top, NODE_WIDTH, NODE_HEIGHT, 6);
        context.clip();

        if (!isFocus) {
            context.fillStyle = religionColor(node.religion);
            context.fillRect(left, top, 4, NODE_HEIGHT);
        }

        context.restore();

        context.lineWidth = isSelected ? 2 : isHovered ? 1.5 : 1;
        context.strokeStyle = isSelected || isHovered ? COLORS.primary : isFocus ? COLORS.primary : COLORS.border;
        context.beginPath();
        context.roundRect(left, top, NODE_WIDTH, NODE_HEIGHT, 6);
        context.stroke();

        const sprite = elementSprite(node.element);
        const spriteWidth = sprite ? sprite.width * ELEMENT_PIXEL : 0;

        if (sprite) {
            const spriteHeight = sprite.height * ELEMENT_PIXEL;

            context.save();
            context.imageSmoothingEnabled = false;
            context.drawImage(sprite, left + NODE_WIDTH - 10 - spriteWidth, point.y - spriteHeight / 2, spriteWidth, spriteHeight);
            context.restore();
        }

        const textLeft = left + 14;
        const textWidth = NODE_WIDTH - 24 - (sprite ? spriteWidth + 6 : 0);

        context.textAlign = 'left';
        context.textBaseline = 'middle';
        context.fillStyle = isFocus ? '#ffffff' : COLORS.text;
        context.font = `600 14px ${FONT}`;

        if (this.scale < 0.45) {
            context.fillText(this.truncate(node.name, textWidth), textLeft, point.y);

            return;
        }

        context.fillText(this.truncate(node.name, textWidth), textLeft, point.y - 8);
        context.fillStyle = isFocus ? 'rgba(255, 255, 255, 0.8)' : COLORS.muted;
        context.font = `12px ${FONT}`;

        const symbol = node.gender ? `  ${node.gender.symbol}` : '';
        const symbolWidth = context.measureText(symbol).width;

        const religions = [node.religion, ...node.otherReligions].map((religion) => religion.name).join(' / ');

        context.fillText(this.truncate(religions, textWidth - symbolWidth) + symbol, textLeft, point.y + 10);
    }

    private truncate(text: string, maxWidth: number): string {
        if (this.context.measureText(text).width <= maxWidth) {
            return text;
        }

        let end = text.length;

        while (end > 1 && this.context.measureText(`${text.slice(0, end)}…`).width > maxWidth) {
            end--;
        }

        return `${text.slice(0, end)}…`;
    }
}

/**
 * A religion's colour: the one admins chose, or a stable one derived from
 * its slug. Used for node stripes, comparison columns and labels.
 */
export function religionColor(religion: Pick<ReligionRef, 'slug' | 'color'>): string {
    if (religion.color) {
        return religion.color;
    }

    let hash = 0;

    for (const character of religion.slug) {
        hash = (hash * 31 + character.charCodeAt(0)) | 0;
    }

    return `hsl(${Math.abs(hash) % 360}, 62%, 52%)`;
}

/** The bounds grown by AREA_MARGIN of their size on each side (at least AREA_MIN_PADDING). */
function padArea(bounds: Rect): Rect {
    const padX = Math.max((bounds.right - bounds.left) * AREA_MARGIN, AREA_MIN_PADDING);
    const padY = Math.max((bounds.bottom - bounds.top) * AREA_MARGIN, AREA_MIN_PADDING);

    return { left: bounds.left - padX, right: bounds.right + padX, top: bounds.top - padY, bottom: bounds.bottom + padY };
}

/** Whether two partners sit next to each other in the same row (with nothing between). */
function areAdjacentPartners(a: Point, b: Point): boolean {
    return Math.abs(a.y - b.y) <= 1 && Math.abs(a.x - b.x) - NODE_WIDTH <= CONSORT_GAP + COLUMN_GAP;
}

/** The top (-1) or bottom (1) of the arc drawArc draws between two nodes. */
function arcApex(a: Point, b: Point, side: -1 | 1): Point {
    const bulge = side * (40 + Math.min(Math.abs(a.x - b.x), 600) * 0.12);

    return { x: (a.x + b.x) / 2, y: (a.y + b.y) / 2 + (side * NODE_HEIGHT) / 2 + bulge * 0.75 };
}

/** The point at t (0..1) along a cubic Bezier curve. */
function bezierPoint(controls: readonly [Point, Point, Point, Point], t: number): Point {
    const u = 1 - t;
    const weights = [u * u * u, 3 * u * u * t, 3 * u * t * t, t * t * t];

    return {
        x: controls.reduce((sum, point, i) => sum + point.x * weights[i], 0),
        y: controls.reduce((sum, point, i) => sum + point.y * weights[i], 0),
    };
}

function overlaps(a: Rect, b: Rect): boolean {
    return a.left < b.right && b.left < a.right && a.top < b.bottom && b.top < a.bottom;
}

function clamp(value: number, min: number, max: number): number {
    return Math.min(max, Math.max(min, value));
}
