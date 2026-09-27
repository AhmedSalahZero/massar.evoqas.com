// ══════════════════════════════════════════════════════════════════
//  Massar — Frontend Entry Point
//  Location: resources/js/app.js
//
//  Boots Vue 3 + Inertia:
//    · pages are resolved from resources/js/Pages/<Name>.vue
//    · ZiggyVue gives every component route('app.dashboard') etc.
//    · preferences (theme, language, sidebar) are applied from the
//      first page's props before anything renders — see
//      composables/usePreferences.js
//    · the page-loading bar uses Massar teal (#1490A8)
//
//  Translations are NOT a plugin: components call useTranslations()
//  (resources/js/lang/translations.js), which follows the current
//  language reactively.
// ══════════════════════════════════════════════════════════════════

import '../css/app.css';
import './bootstrap';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { initPreferences } from '@/composables/usePreferences';
import { syncCsrfMetaFromPage } from '@/Utils/csrf';

const appName = import.meta.env.VITE_APP_NAME || 'Massar';

createInertiaApp({
    title: (title) => (title ? `${title} · ${appName}` : appName),

    resolve: (name) =>
        resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),

    setup({ el, App, props, plugin }) {
        initPreferences(props.initialPage.props);
        syncCsrfMetaFromPage(props.initialPage);

        document.addEventListener('inertia:finish', (event) => {
            syncCsrfMetaFromPage(event.detail?.page);
        });

        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .mount(el);
    },

    progress: {
        color: '#1490A8',
        showSpinner: false,
    },
});
