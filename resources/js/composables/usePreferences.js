// ══════════════════════════════════════════════════════════════════
//  Massar — usePreferences
//  Location: resources/js/composables/usePreferences.js
//
//  The four interface preferences, held in ONE shared reactive state
//  (every component sees the same values):
//
//    theme      → 'dark' | 'light'     → <html class="light">
//    locale     → 'en' | 'ar'          → <html lang dir>
//    standard   → 'enoc' | 'isco' | 'esco'  (occupation standard switch)
//    collapsed  → sidebar collapsed to icons (this device only)
//
//  Signed in: the saved values on the user (props.auth.user) win, and
//  every change is saved in the background (routes preferences.*),
//  so they follow the person to any device. The standard is saved
//  with a plain request (see setStandard) because pages reload their
//  lists when it changes.
//  Guest: theme is kept in localStorage; language is posted to the
//  guest.locale route so server messages match.
//
//  initPreferences(pageProps) runs once in app.js before the first
//  paint; syncFromPage() is called by the layouts on every page.
// ══════════════════════════════════════════════════════════════════

import { reactive, readonly } from 'vue';
import { router } from '@inertiajs/vue3';

const KEYS = { theme: 'massar_theme', collapsed: 'massar_sidebar_collapsed' };

const state = reactive({
    theme: 'dark',
    locale: 'en',
    standard: 'enoc',
    collapsed: false,
    signedIn: false,
});

function read(key, fallback) {
    try { return localStorage.getItem(key) ?? fallback; } catch { return fallback; }
}
function write(key, value) {
    try { localStorage.setItem(key, value); } catch { /* private mode — ignore */ }
}

function applyTheme(theme) {
    document.documentElement.classList.toggle('light', theme === 'light');
}
function applyLocale(locale) {
    document.documentElement.setAttribute('lang', locale);
    document.documentElement.setAttribute('dir', locale === 'ar' ? 'rtl' : 'ltr');
}

const background = { preserveState: true, preserveScroll: true, only: ['auth', 'locale', 'flash'] };

// The last occupation standard the SERVER reported. A page can arrive
// with an old copy of the user (partial reloads keep earlier props), so
// the standard is only taken from the server when the server's value
// actually changed — never to undo a choice the user just made here.
let serverStandard = null;

export function syncFromPage(props) {
    const user = props?.auth?.user;
    state.signedIn = !!user;
    state.locale = (user?.language ?? props?.locale ?? 'en') === 'ar' ? 'ar' : 'en';
    // Guests without a saved choice: the theme the server painted the page
    // with (dark in the app; light on the public site — app.blade.php).
    state.theme = user?.theme ?? read(KEYS.theme, document.documentElement.classList.contains('light') ? 'light' : 'dark');
    const fromServer = user?.occupation_standard ?? 'enoc';
    if (fromServer !== serverStandard) {
        serverStandard = fromServer;
        state.standard = fromServer;
    }
    applyTheme(state.theme);
    applyLocale(state.locale);
}

export function initPreferences(props) {
    state.collapsed = read(KEYS.collapsed, '0') === '1';
    syncFromPage(props);
}

export function usePreferences() {
    function setTheme(theme) {
        state.theme = theme;
        applyTheme(theme);
        write(KEYS.theme, theme);
        if (state.signedIn) router.patch(route('preferences.theme'), { theme }, background);
    }

    function toggleTheme() {
        setTheme(state.theme === 'dark' ? 'light' : 'dark');
    }

    function setLocale(locale) {
        state.locale = locale;
        applyLocale(locale);
        router[state.signedIn ? 'patch' : 'post'](
            route(state.signedIn ? 'preferences.locale' : 'guest.locale'),
            { locale },
            { preserveState: true, preserveScroll: true },
        );
    }

    // Saved quietly in the background (not as an Inertia visit), so it
    // can never be cancelled by — or cancel — the page reloading its
    // list in the new standard at the same moment.
    function setStandard(standard) {
        if (state.standard === standard) return;
        state.standard = standard;
        if (state.signedIn) {
            // serverStandard is deliberately NOT updated here: it must keep
            // matching what pages report, so an old copy is recognised as old.
            window.axios.patch(route('preferences.standard'), { standard })
                .catch(() => { /* kept for this visit; saved again on the next change */ });
        }
    }

    function toggleCollapsed() {
        state.collapsed = !state.collapsed;
        write(KEYS.collapsed, state.collapsed ? '1' : '0');
    }

    return { prefs: readonly(state), setTheme, toggleTheme, setLocale, setStandard, toggleCollapsed };
}
