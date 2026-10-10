import type { Graph, Point, ReligionRef } from './types';

export const NODE_WIDTH = 168;
export const NODE_HEIGHT = 52;
export const COLUMN_GAP = 56;
export const ROW_GAP = 150;

/** Partners sit further apart so their double line and its label read clearly. */
export const CONSORT_GAP = 120;

const COMPONENT_GAP = 200;
const LANE_GAP = 240;
const SLOT = NODE_WIDTH + COLUMN_GAP;
const SWEEPS = 6;

export interface Lane extends ReligionRef {
    /** Horizontal extent of the lane's nodes (centre of the outermost ones). */
    left: number;
    right: number;
}

export interface Layout {
    /** Centre point of every node. */
    positions: Map<number, Point>;
    /** Columns of a side-by-side comparison, left to right; empty otherwise. */
    lanes: Lane[];
}

interface Neighbour {
    id: number;
    /** Generation difference: +1 for a child or council member, -1 the other way, 0 otherwise. */
    delta: number;
}

/**
 * Lays the graph out as a family tree: generations become rows (parents
 * above children, heads of councils above members, consorts and siblings
 * beside each other), and each row is ordered so children sit under their
 * parents.
 *
 * Without lanes, disconnected groups are placed side by side, the one
 * holding the focus first. With lanes (a comparison of religions), every
 * religion gets its own column; generations are shared across columns, so
 * a parent in one religion still sits above its child in another.
 */
export function layoutGraph(graph: Graph): Layout {
    const ids = graph.nodes.map((node) => node.id);
    const neighbours = new Map<number, Neighbour[]>(ids.map((id) => [id, []]));
    const parents = new Map<number, number[]>(ids.map((id) => [id, []]));
    const children = new Map<number, number[]>(ids.map((id) => [id, []]));
    const consorts = new Map<number, number[]>(ids.map((id) => [id, []]));
    /** Pairs drawn side by side in a chosen order: [left, right]. */
    const sideBySide: [number, number][] = [];

    for (const edge of graph.edges) {
        if (!neighbours.has(edge.from) || !neighbours.has(edge.to)) {
            continue;
        }

        const directional = edge.kind === 'parent' || edge.kind === 'council';
        const delta = directional ? 1 : 0;
        neighbours.get(edge.from)!.push({ id: edge.to, delta });
        neighbours.get(edge.to)!.push({ id: edge.from, delta: -delta });

        if (directional) {
            children.get(edge.from)!.push(edge.to);
            parents.get(edge.to)!.push(edge.from);
        } else if (edge.kind === 'consort') {
            consorts.get(edge.from)!.push(edge.to);
            consorts.get(edge.to)!.push(edge.from);
        }

        if (edge.swappable) {
            sideBySide.push([edge.from, edge.to]);
        }
    }

    // Generations, found breadth-first so the focus and the oldest
    // ancestors anchor each connected group.
    const level = new Map<number, number>();
    const groups: number[][] = [];
    const starts = [
        ...(graph.focus !== null ? [graph.focus] : []),
        ...ids.filter((id) => parents.get(id)!.length === 0),
        ...ids,
    ];

    for (const start of starts) {
        if (level.has(start) || !neighbours.has(start)) {
            continue;
        }

        const group = [start];
        level.set(start, 0);

        for (let i = 0; i < group.length; i++) {
            const id = group[i];

            for (const neighbour of neighbours.get(id)!) {
                if (!level.has(neighbour.id)) {
                    level.set(neighbour.id, level.get(id)! + neighbour.delta);
                    group.push(neighbour.id);
                }
            }
        }

        groups.push(group);
    }

    const columns: { lane: ReligionRef | null; ids: number[] }[] = graph.lanes
        ? graph.lanes.map((lane) => ({
              lane,
              ids: graph.nodes.filter((node) => node.lane === lane.slug).map((node) => node.id),
          }))
        : groups.map((group) => ({ lane: null, ids: group }));

    const positions = new Map<number, Point>();
    const lanes: Lane[] = [];
    let cursor = 0;

    for (const column of columns) {
        const x = layoutGroup(column.ids, level, parents, children, consorts, sideBySide);
        const xs = column.ids.map((id) => x.get(id)!);
        const width = xs.length ? Math.max(...xs) - Math.min(...xs) : 0;
        const shift = xs.length ? cursor - Math.min(...xs) : 0;

        for (const id of column.ids) {
            positions.set(id, { x: x.get(id)! + shift, y: level.get(id)! * ROW_GAP });
        }

        if (column.lane) {
            lanes.push({ ...column.lane, left: cursor, right: cursor + width });
        }

        cursor += width + SLOT + (column.lane ? LANE_GAP : COMPONENT_GAP);
    }

    return { positions, lanes };
}

/**
 * Horizontal positions for one group of nodes. Rows are swept top-down
 * (pulling nodes under their parents) and bottom-up (pulling parents over
 * their children) until they settle. Relatives outside the group are ignored.
 */
function layoutGroup(
    group: number[],
    level: Map<number, number>,
    parents: Map<number, number[]>,
    children: Map<number, number[]>,
    consorts: Map<number, number[]>,
    sideBySide: [number, number][],
): Map<number, number> {
    const rows = new Map<number, number[]>();

    for (const id of group) {
        const row = rows.get(level.get(id)!) ?? [];
        row.push(id);
        rows.set(level.get(id)!, row);
    }

    const levels = [...rows.keys()].sort((a, b) => a - b);
    const x = new Map<number, number>();

    for (const l of levels) {
        rows.get(l)!.forEach((id, index) => x.set(id, index * SLOT));
    }

    for (let sweep = 0; sweep < SWEEPS; sweep++) {
        const downward = sweep % 2 === 0;
        const order = downward ? levels : [...levels].reverse();

        for (const l of order) {
            const row = rows.get(l)!;
            const anchors = downward ? parents : children;

            const desired = new Map<number, number>();

            for (const id of row) {
                const placed = anchors.get(id)!.filter((other) => level.get(other) !== l && x.has(other));
                desired.set(id, placed.length ? average(placed.map((other) => x.get(other)!)) : x.get(id)!);
            }

            // Consorts share the average of their targets, so they stay together.
            for (const id of row) {
                const partners = consorts.get(id)!.filter((other) => level.get(other) === l && x.has(other));

                if (partners.length) {
                    desired.set(id, average([id, ...partners].map((other) => desired.get(other) ?? x.get(other)!)));
                }
            }

            row.sort((a, b) => desired.get(a)! - desired.get(b)! || x.get(a)! - x.get(b)!);
            keepConsortsAdjacent(row, consorts);
            keepChosenSides(row, sideBySide);
            placeRow(row, desired, x, consorts);
        }
    }

    return x;
}

/**
 * Moves consorts next to their partner within the row. Someone with several
 * partners gets one on each side before any go further out.
 */
function keepConsortsAdjacent(row: number[], consorts: Map<number, number[]>): void {
    for (let i = 0; i < row.length; i++) {
        const id = row[i];

        for (const partner of consorts.get(id)!) {
            const at = row.indexOf(partner);
            const self = row.indexOf(id);

            if (at === -1 || Math.abs(at - self) === 1) {
                continue;
            }

            const leftIsFree = self === 0 || !consorts.get(id)!.includes(row[self - 1]);

            row.splice(at, 1);
            const selfNow = row.indexOf(id);
            row.splice(leftIsFree ? selfNow : selfNow + 1, 0, partner);
        }

        i = Math.max(i, row.indexOf(id));
    }
}

/** Puts each side-by-side pair in its chosen left/right order. */
function keepChosenSides(row: number[], sideBySide: [number, number][]): void {
    for (const [left, right] of sideBySide) {
        const leftAt = row.indexOf(left);
        const rightAt = row.indexOf(right);

        if (leftAt > rightAt && rightAt !== -1) {
            row[leftAt] = right;
            row[rightAt] = left;
        }
    }
}

/**
 * Places the row's nodes in order at least one slot apart (wider between
 * consorts), as close to their desired positions as possible.
 */
function placeRow(
    row: number[],
    desired: Map<number, number>,
    x: Map<number, number>,
    consorts: Map<number, number[]>,
): void {
    let previous = -Infinity;
    const placed = row.map((id, index) => {
        const isPartnerOfPrevious = index > 0 && consorts.get(id)!.includes(row[index - 1]);

        return (previous = Math.max(desired.get(id)!, previous + (isPartnerOfPrevious ? NODE_WIDTH + CONSORT_GAP : SLOT)));
    });

    // The left-to-right pass only pushes right; shift back so the row is
    // centred on where it wanted to be.
    const shift = average(row.map((id) => desired.get(id)!)) - average(placed);

    row.forEach((id, index) => x.set(id, placed[index] + shift));
}

function average(values: number[]): number {
    return values.reduce((sum, value) => sum + value, 0) / values.length;
}
