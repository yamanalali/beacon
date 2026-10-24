import { mountOffline } from "./lib/offline";
import { createInertiaApp, type ResolvedComponent } from "@inertiajs/react";
import { resolvePageComponent } from "laravel-vite-plugin/inertia-helpers";
import { createRoot } from "react-dom/client";

createInertiaApp({
    title: (title) => title || "بيكون",
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.tsx`,
            import.meta.glob<ResolvedComponent>(["./Pages/**/*.tsx", "!./Pages/**/*.test.tsx"]),
        ),
    setup({ el, App, props }) {
        if (!el) {
            return;
        }

        createRoot(el).render(<App {...props} />);
        mountOffline(props.initialPage.props, props.initialPage.url);
    },
    progress: {
        color: "#176b60",
    },
});
