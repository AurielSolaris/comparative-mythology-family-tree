import type { Alpine } from 'alpinejs';
import type { AxiosStatic } from 'axios';

declare global {
    interface Window {
        axios: AxiosStatic;
        Alpine: Alpine;
        UIkit: typeof import('uikit').default;
    }
}

export {};
