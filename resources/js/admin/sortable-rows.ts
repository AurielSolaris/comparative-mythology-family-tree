import axios from 'axios';
import { component } from '../alpine';

/**
 * Saves the order of rows rearranged with UIkit's sortable component.
 * Each row carries its record id in data-id.
 */
export function sortableRows(url: string) {
    return component({
        async save() {
            const ids = [...this.$el.querySelectorAll<HTMLElement>(':scope > [data-id]')].map((row) =>
                Number(row.dataset.id),
            );

            try {
                await axios.patch(url, { ids });
                window.UIkit.notification('Order saved', { status: 'success', pos: 'bottom-right', timeout: 1500 });
            } catch {
                window.UIkit.notification('The new order could not be saved. Reload and try again.', {
                    status: 'danger',
                    pos: 'bottom-right',
                });
            }
        },
    });
}
