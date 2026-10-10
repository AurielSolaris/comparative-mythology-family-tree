import type { AlpineComponent } from 'alpinejs';

/**
 * Types an Alpine component object: its own state and methods are inferred,
 * and `this` also knows Alpine's magics ($refs, $el, ...).
 */
export function component<T>(data: AlpineComponent<T>): AlpineComponent<T> {
    return data;
}
