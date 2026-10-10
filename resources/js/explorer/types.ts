export type RelationKind = 'parent' | 'consort' | 'sibling' | 'equivalent' | 'council';

export interface ReligionRef {
    name: string;
    slug: string;
    /** Colour chosen by admins (#rrggbb), or null to derive one from the slug. */
    color: string | null;
}

export interface GraphNode {
    id: number;
    name: string;
    slug: string;
    /** Primary religion. */
    religion: ReligionRef;
    /** Other religions the entity also belongs to. */
    otherReligions: ReligionRef[];
    gender: { value: string; label: string; symbol: string } | null;
    element: string | null;
    /** The entity this one was syncretised from or is most like. */
    syncreticOrigin: { id: number; name: string; religion: string; url: string; graphUrl: string } | null;
    region: string | null;
    origin: string | null;
    aliases: string[];
    /** In a side-by-side comparison: the slug of the column the node sits in. */
    lane: string | null;
    url: string;
    graphUrl: string;
}

/**
 * Parent edges point from parent to child, council edges from the head of
 * the council or court to the member. The other kinds are undirected; for
 * them "from" is the entity drawn on the left.
 */
export interface GraphEdge {
    id: number;
    from: number;
    to: number;
    kind: RelationKind;
    /** The tradition (religion name) the relation comes from, if recorded. */
    tradition: string | null;
    note: string | null;
    /** Whether admins can switch which side each entity is drawn on. */
    swappable: boolean;
}

export interface Graph {
    focus: number | null;
    nodes: GraphNode[];
    edges: GraphEdge[];
    /** Religions shown side by side (left to right), or null for a single tree. */
    lanes: ReligionRef[] | null;
    truncated: boolean;
}

export interface Point {
    x: number;
    y: number;
}
