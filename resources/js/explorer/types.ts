export type RelationKind =
    | 'parent'
    | 'consort'
    | 'sibling'
    | 'council'
    | 'co_ruler'
    | 'equivalent'
    | 'cognate'
    | 'analogue'
    | 'fusion'
    | 'absorption'
    | 'avatar'
    | 'aspect'
    | 'derivation'
    | 'adoption'
    | 'interpretation'
    | 'supplanting'
    | 'fission'
    | 'demonization'
    | 'deification';

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
    region: string | null;
    origin: string | null;
    aliases: string[];
    /** In a side-by-side comparison: the slug of the column the node sits in. */
    lane: string | null;
    url: string;
    graphUrl: string;
}

/** The relation read from one end: "Avatar of", "Daughter of"…, and its place in a list of relatives. */
export interface RoleView {
    label: string;
    order: number;
}

/**
 * Parent edges point from parent to child, council edges from the head of
 * the council or court to the member, and directed syncretism edges from
 * the source to the result (a God to its avatar, a component to the fused
 * God…). Undirected edges have "from" as the entity drawn on the left.
 */
export interface GraphEdge {
    id: number;
    from: number;
    to: number;
    kind: RelationKind;
    /** Identity links (equivalents, fusions, avatars…) rather than family or rank. */
    syncretic: boolean;
    directed: boolean;
    /** The tradition (religion name) the relation comes from, if recorded. */
    tradition: string | null;
    certainty: { value: 'attested' | 'consensus' | 'hypothesis' | 'disputed'; label: string } | null;
    /** When the relation held, e.g. "Gupta period". */
    period: string | null;
    note: string | null;
    fromRole: RoleView;
    toRole: RoleView;
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
