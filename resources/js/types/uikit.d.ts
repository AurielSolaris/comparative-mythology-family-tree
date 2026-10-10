declare module 'uikit' {
    type Component = (element: Element | string, options?: Record<string, unknown>) => unknown;

    interface UIkit {
        use(plugin: (uikit: UIkit) => void): UIkit;
        notification(message: string | Record<string, unknown>, options?: string | Record<string, unknown>): unknown;
        modal: Component & {
            alert(message: string): Promise<void>;
            confirm(message: string): Promise<void>;
            prompt(message: string, value?: string): Promise<string | null>;
        };
        [component: string]: unknown;
    }

    const UIkit: UIkit;
    export default UIkit;
}

declare module 'uikit/dist/js/uikit-icons' {
    const Icons: (uikit: import('uikit').default) => void;
    export default Icons;
}
