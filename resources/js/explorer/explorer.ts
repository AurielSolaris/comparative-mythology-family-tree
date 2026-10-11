import axios from 'axios';
import { component } from '../alpine';
import { paintLegendIcons } from './elements';
import { religionColor, TreeCanvas } from './renderer';
import { type Role, ROLE_ORDER, roleLabel } from './terms';
import type { Graph, GraphEdge, GraphNode, ReligionRef } from './types';

export interface ExplorerConfig {
    appName: string;
    graphUrl: string | null;
    suggestUrl: string;
    /** URLs with a __SLUG__ placeholder. */
    religionPageUrl: string;
    religionGraphUrl: string;
    /** Admin edit URL with an __ID__ placeholder, for administrators only. */
    adminEditUrl: string | null;
    /** Admin switch-sides URL with an __ID__ placeholder, for administrators only. */
    swapSidesUrl: string | null;
    religions: { slug: string; name: string }[];
    /** Compare page and graph URLs; "?religions=a,b" is appended. */
    religionsPageUrl: string;
    compareGraphUrl: string;
    /** Religions shown on load: one for a religion page, several for a comparison. */
    selectedReligions: string[];
}

/** How many religions the side-by-side view takes. */
export const COMPARE_MIN = 2;
export const COMPARE_MAX = 4;

export interface Suggestion {
    id: number;
    name: string;
    religion: string;
    gender: string | null;
    element: string | null;
    region: string | null;
    aliases: string[];
    url: string;
    graphUrl: string;
}

interface Relative {
    role: Role;
    label: string;
    node: GraphNode;
    tradition: string | null;
    note: string | null;
}

interface HistoryState {
    graphUrl: string | null;
    title: string;
}

/**
 * Width of the search panel (left) and details panel (right) plus margins.
 * On wide screens the tree is fitted between them instead of underneath.
 */
const SEARCH_PANEL_INSET = 350;
const DETAILS_PANEL_INSET = 350;
const WIDE_SCREEN = 960;

/** The selected node's relations among the loaded graph, from its point of view. */
export function relativesOf(node: GraphNode, graph: Graph): Relative[] {
    const nodes = new Map(graph.nodes.map((other) => [other.id, other]));
    const relatives: Relative[] = [];

    for (const edge of graph.edges) {
        if (edge.from !== node.id && edge.to !== node.id) {
            continue;
        }

        const isFrom = edge.from === node.id;
        const other = nodes.get(isFrom ? edge.to : edge.from);

        if (!other) {
            continue;
        }

        const role: Role = {
            parent: isFrom ? 'parent' : 'child',
            council: isFrom ? 'councilHead' : 'councilMember',
            consort: 'consort',
            sibling: 'sibling',
            equivalent: 'equivalent',
            co_ruler: 'coRuler',
        }[edge.kind] as Role;

        relatives.push({ role, label: roleLabel(role, node), node: other, tradition: edge.tradition, note: edge.note });
    }

    return relatives.sort(
        (a, b) => ROLE_ORDER.indexOf(a.role) - ROLE_ORDER.indexOf(b.role) || a.node.name.localeCompare(b.node.name),
    );
}

export function explorer(config: ExplorerConfig) {
    // Kept outside Alpine's reactive state: proxying the renderer would slow
    // every draw call.
    let tree: TreeCanvas | null = null;

    return component({
        graph: null as Graph | null,
        loading: false,
        error: null as string | null,
        selected: null as GraphNode | null,
        currentGraphUrl: config.graphUrl,
        religion: config.selectedReligions.length === 1 ? config.selectedReligions[0] : '',
        compareOpen: false,
        compared: config.selectedReligions.length > 1 ? [...config.selectedReligions] : ([] as string[]),
        gender: '',
        element: '',
        depth: '2',
        query: '',
        suggestions: [] as Suggestion[],
        suggestionsOpen: false,
        highlighted: -1,

        init() {
            tree = new TreeCanvas(this.$refs.canvas as HTMLCanvasElement, {
                onSelect: (node) => (this.selected = node),
                onActivate: (node) => this.focusOn(node),
                onSwap: config.swapSidesUrl ? (edge) => this.swapSides(edge) : undefined,
            });

            paintLegendIcons(this.$el);

            history.replaceState({ graphUrl: config.graphUrl, title: document.title } satisfies HistoryState, '');

            window.addEventListener('popstate', (event: PopStateEvent) => {
                const state = event.state as HistoryState | null;

                document.title = state?.title ?? config.appName;
                this.load(state?.graphUrl ?? null);
            });

            this.load(config.graphUrl);
        },

        get isEntityGraph(): boolean {
            return this.graph?.focus != null;
        },

        /** Religions side by side, where "is like" links are drawn. */
        get isComparison(): boolean {
            return (this.graph?.lanes?.length ?? 0) > 0;
        },

        get relatives(): Relative[] {
            return this.selected && this.graph ? relativesOf(this.selected, this.graph) : [];
        },

        get editUrl(): string | null {
            return config.adminEditUrl && this.selected
                ? config.adminEditUrl.replace('__ID__', String(this.selected.id))
                : null;
        },

        religionColor(religion: ReligionRef): string {
            return religionColor(religion);
        },

        async load(graphUrl: string | null, keepView = false) {
            this.currentGraphUrl = graphUrl;
            this.error = null;

            if (!graphUrl) {
                this.graph = null;
                this.selected = null;
                tree?.setGraph(null);

                return;
            }

            this.loading = !keepView;

            try {
                const { data } = await axios.get<Graph>(graphUrl, {
                    params: graphUrl.includes('/graph/entities/') ? { depth: this.depth } : {},
                });

                if (graphUrl !== this.currentGraphUrl) {
                    return;
                }

                this.graph = data;

                if (keepView) {
                    this.selected = data.nodes.find((node) => node.id === this.selected?.id) ?? null;
                    tree?.setGraph(data, true);

                    return;
                }

                this.selected = data.nodes.find((node) => node.id === data.focus) ?? null;

                if (data.focus !== null) {
                    this.religion = '';
                }
                const wide = window.innerWidth >= WIDE_SCREEN;

                tree?.setInsets({
                    left: wide ? SEARCH_PANEL_INSET : 0,
                    right: this.selected && wide ? DETAILS_PANEL_INSET : 0,
                });
                tree?.setGraph(data);
            } catch {
                this.error = 'This tree could not be loaded. Please try again.';
            } finally {
                this.loading = false;
            }
        },

        navigate(pageUrl: string, graphUrl: string, title: string) {
            const fullTitle = `${title} · ${config.appName}`;

            history.pushState({ graphUrl, title: fullTitle } satisfies HistoryState, '', pageUrl);
            document.title = fullTitle;
            this.load(graphUrl);
        },

        focusOn(node: GraphNode) {
            this.navigate(node.url, node.graphUrl, node.name);
        },

        selectNode(node: GraphNode) {
            this.selected = node;
            tree?.select(node.id);
            tree?.centerOn(node.id);
        },

        closeDetails() {
            this.selected = null;
            tree?.select(null);
        },

        openReligion() {
            const religion = config.religions.find((candidate) => candidate.slug === this.religion);

            if (religion) {
                this.navigate(
                    config.religionPageUrl.replace('__SLUG__', religion.slug),
                    config.religionGraphUrl.replace('__SLUG__', religion.slug),
                    religion.name,
                );
            }
        },

        reload() {
            this.load(this.currentGraphUrl);
        },

        get canCompare(): boolean {
            return this.compared.length >= COMPARE_MIN && this.compared.length <= COMPARE_MAX;
        },

        /** Ticks or unticks a religion; the tick order is the left-to-right order. */
        toggleCompared(slug: string) {
            this.compared = this.compared.includes(slug)
                ? this.compared.filter((other) => other !== slug)
                : [...this.compared, slug].slice(-COMPARE_MAX);
        },

        comparedPosition(slug: string): number {
            return this.compared.indexOf(slug) + 1;
        },

        openComparison() {
            if (!this.canCompare) {
                return;
            }

            const query = `?religions=${this.compared.map(encodeURIComponent).join(',')}`;
            const names = this.compared.map(
                (slug) => config.religions.find((religion) => religion.slug === slug)?.name ?? slug,
            );

            this.religion = '';
            this.compareOpen = false;
            this.navigate(config.religionsPageUrl + query, config.compareGraphUrl + query, names.join(' · '));
        },

        async swapSides(edge: GraphEdge) {
            if (!config.swapSidesUrl) {
                return;
            }

            try {
                await axios.patch(config.swapSidesUrl.replace('__ID__', String(edge.id)));
                await this.load(this.currentGraphUrl, true);
            } catch {
                window.UIkit.notification('Could not switch sides. Are you still logged in?', { status: 'danger' });
            }
        },

        async suggest() {
            const query = this.query.trim();

            if (query === '') {
                this.suggestionsOpen = false;

                return;
            }

            const params = {
                q: query,
                ...(this.gender ? { gender: this.gender } : {}),
                ...(this.element ? { element: this.element } : {}),
            };
            const { data } = await axios.get<{ data: Suggestion[] }>(config.suggestUrl, { params });

            if (query === this.query.trim()) {
                this.suggestions = data.data;
                this.highlighted = data.data.length ? 0 : -1;
                this.suggestionsOpen = true;
            }
        },

        moveHighlight(step: number) {
            if (this.suggestions.length) {
                this.highlighted = (this.highlighted + step + this.suggestions.length) % this.suggestions.length;
            }
        },

        openHighlighted() {
            const suggestion = this.suggestions[this.highlighted];

            if (suggestion) {
                this.openSuggestion(suggestion);
            }
        },

        openSuggestion(suggestion: Suggestion) {
            this.query = '';
            this.suggestionsOpen = false;
            this.navigate(suggestion.url, suggestion.graphUrl, suggestion.name);
        },

        zoomIn() {
            tree?.zoomBy(1.25);
        },

        zoomOut() {
            tree?.zoomBy(0.8);
        },

        fit() {
            tree?.fit();
        },

        resetArrangement() {
            tree?.resetArrangement();
        },

        /** Downloads the whole tree, as currently arranged, as a PNG named after the page. */
        async downloadPng() {
            const blob = await tree?.exportPng();

            if (!blob) {
                return;
            }

            const name = document.title
                .toLowerCase()
                .normalize('NFKD')
                .replace(/[^\p{L}\p{N}]+/gu, '-')
                .replace(/^-+|-+$/g, '');
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = `${name || 'family-tree'}.png`;
            link.click();
            setTimeout(() => URL.revokeObjectURL(link.href), 1000);
        },
    });
}
