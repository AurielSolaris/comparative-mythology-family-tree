import axios from 'axios';
import { component } from '../alpine';
import type { Suggestion } from '../explorer/explorer';

interface EntityPickerConfig {
    suggestUrl: string;
    /** The entity being edited, which can't be related to itself (null while creating one). */
    excludeId: number | null;
    /** An entity already chosen, shown when the form opens. */
    selected?: { id: number; label: string } | null;
}

/**
 * Search-as-you-type field that fills a hidden input with the chosen
 * entity's id. Clearing the field clears the choice.
 */
export function entityPicker(config: EntityPickerConfig) {
    return component({
        query: config.selected?.label ?? '',
        selectedId: config.selected ? String(config.selected.id) : '',
        results: [] as Suggestion[],
        open: false,
        highlighted: -1,

        async search() {
            this.selectedId = '';
            const query = this.query.trim();

            if (query === '') {
                this.open = false;

                return;
            }

            const { data } = await axios.get<{ data: Suggestion[] }>(config.suggestUrl, { params: { q: query } });

            if (query === this.query.trim()) {
                this.results = data.data.filter((entity) => entity.id !== config.excludeId);
                this.highlighted = this.results.length ? 0 : -1;
                this.open = true;
            }
        },

        move(step: number) {
            if (this.results.length) {
                this.highlighted = (this.highlighted + step + this.results.length) % this.results.length;
            }
        },

        chooseHighlighted() {
            const entity = this.results[this.highlighted];

            if (entity) {
                this.choose(entity);
            }
        },

        choose(entity: Suggestion) {
            this.selectedId = String(entity.id);
            this.query = `${entity.name} (${entity.religion})`;
            this.open = false;
        },
    });
}
