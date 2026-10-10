import { component } from '../alpine';

/**
 * A form that asks for confirmation in a UIkit modal before submitting.
 */
export function confirmedForm(message: string) {
    return component({
        confirmSubmit(event: SubmitEvent) {
            event.preventDefault();

            const form = event.target as HTMLFormElement;
            const text = document.createElement('p');
            text.textContent = message;

            window.UIkit.modal.confirm(text.outerHTML).then(
                () => form.submit(),
                () => {},
            );
        },
    });
}
