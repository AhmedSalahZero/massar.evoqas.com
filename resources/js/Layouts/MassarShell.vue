<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — MassarShell (the app frame)
//  Location: resources/js/Layouts/MassarShell.vue
//
//  The frame every signed-in screen sits in, exactly as in the Massar
//  mockups and app.css section 04:
//
//    · Sidebar — grouped navigation. Collapses to icons with the
//      arrow button (remembered on this device); on phones it becomes
//      a slide-out drawer opened by the ☰ button. Items marked `soon`
//      show a "Soon" pill (planned modules — they still open their
//      Coming Soon page).
//    · Top bar — workspace · page title, the occupation Standard
//      switch (ENOC | ISCO-08 | ESCO), EN | عربي, theme, and the user
//      menu (profile, sign out).
//    · Subscription banner when the partner's subscription is ending.
//    · Flash messages as toasts.
//
//  It is not used directly by pages: AppLayout (partner workspace)
//  and AdminLayout (Massar platform) pass it their navigation.
//
//  Nav item shape: { label, icon, route, active?: 'app.team.*',
//                    soon?: true, permission?: 'team.manage', count?: 3 }
// ══════════════════════════════════════════════════════════════════

import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import FlashMessages from '@/Components/FlashMessages.vue';
import { syncFromPage, usePreferences } from '@/composables/usePreferences';
import { usePermissions } from '@/composables/usePermissions';
import { useTranslations } from '@/composables/useTranslations';
import { formatDate } from '@/Utils/date';

const props = defineProps({
    title: { type: String, default: '' },
    workspace: { type: String, default: '' },
    nav: { type: Array, required: true },       // [{ group, items: [...] }]
    homeRoute: { type: String, required: true },
    profileRoute: { type: String, required: true },
});

const page = usePage();
const { t, locale } = useTranslations();
const { prefs, toggleTheme, setLocale, setStandard, toggleCollapsed } = usePreferences();
const { user, can } = usePermissions();

const drawer = ref(false);
const menuOpen = ref(false);

// Keep preferences in step with every page the server sends.
watch(() => page.props, (p) => syncFromPage(p), { immediate: true });

// Close the mobile drawer whenever a new page loads.
let off;
onMounted(() => { off = router.on('navigate', () => { drawer.value = false; menuOpen.value = false; }); });
onBeforeUnmount(() => off?.());

const visibleNav = computed(() =>
    props.nav
        .map((g) => ({ ...g, items: g.items.filter((i) => !i.permission || can(i.permission)) }))
        .filter((g) => g.items.length),
);

function isActive(item) {
    try { return route().current(item.active ?? item.route); } catch { return false; }
}

const company = computed(() => user.value?.company ?? null);
const support = computed(() => page.props.support ?? {});
const isLocal = computed(() => page.props.app?.env === 'local');

const STANDARDS = [['enoc', 'ENOC'], ['isco', 'ISCO-08'], ['esco', 'ESCO']];

function logout() {
    menuOpen.value = false;
    router.post(route('logout'), {}, { preserveState: false, preserveScroll: false });
}
</script>

<template>
    <Head :title="title" />
    <div v-if="isLocal" class="env-bar">{{ t('shell.env_banner') }}</div>

    <div class="app" :class="{ collapsed: prefs.collapsed, drawer }">
        <!-- ── Sidebar ─────────────────────────────────────────── -->
        <aside class="sidebar" :aria-label="t('shell.menu')">
            <div class="brand">
                <div class="brand-mark">M</div>
                <div class="brand-name">{{ locale === 'ar' ? 'مسار' : 'Massar' }}</div>
                <button type="button" class="collapse-btn" @click="toggleCollapsed"
                        :title="prefs.collapsed ? t('shell.expand') : t('shell.collapse')"
                        :aria-label="prefs.collapsed ? t('shell.expand') : t('shell.collapse')">
                    <AppIcon name="chevron-left" :size="15" />
                </button>
            </div>

            <nav v-for="group in visibleNav" :key="group.group" class="nav-group">
                <div class="nav-label">{{ t(group.group) }}</div>
                <Link v-for="item in group.items" :key="item.route" :href="route(item.route)"
                      class="nav-item" :class="{ active: isActive(item) }" :title="t(item.label)"
                      :aria-current="isActive(item) ? 'page' : null">
                    <AppIcon :name="item.icon" />
                    <span class="t">{{ t(item.label) }}</span>
                    <span v-if="item.soon" class="soon">{{ t('shell.soon') }}</span>
                    <span v-else-if="item.count" class="cnt">{{ item.count }}</span>
                </Link>
            </nav>

            <div class="side-foot">
                <slot name="sidebar-foot" />
            </div>
        </aside>
        <div class="scrim" @click="drawer = false"></div>

        <!-- ── Main ────────────────────────────────────────────── -->
        <div class="main">
            <header class="topbar">
                <button type="button" class="icon-btn menu-btn" :aria-label="t('shell.menu')" @click="drawer = !drawer">
                    <AppIcon name="menu" :size="16" />
                </button>

                <div class="topbar-left">
                    <b class="ws">{{ workspace }}</b>
                    <span v-if="title" class="ws">·</span>
                    <span>{{ title }}</span>
                </div>

                <div class="topbar-right">
                    <span class="seg-l std-l">{{ t('shell.standard') }}</span>
                    <div class="seg std" role="group" :aria-label="t('shell.standard')">
                        <button v-for="[key, label] in STANDARDS" :key="key" type="button"
                                :aria-pressed="prefs.standard === key" @click="setStandard(key)">{{ label }}</button>
                    </div>

                    <div class="seg" role="group" :aria-label="t('shell.language')">
                        <button type="button" :aria-pressed="prefs.locale === 'en'" @click="setLocale('en')">EN</button>
                        <button type="button" :aria-pressed="prefs.locale === 'ar'" @click="setLocale('ar')">عربي</button>
                    </div>

                    <button type="button" class="icon-btn theme-btn" :title="t('shell.theme')" :aria-label="t('shell.theme')" @click="toggleTheme">
                        <AppIcon :name="prefs.theme === 'dark' ? 'sun' : 'moon'" :size="16" />
                    </button>

                    <div class="dropdown">
                        <button type="button" class="user-chip" :aria-expanded="menuOpen" aria-haspopup="menu" @click="menuOpen = !menuOpen">
                            <span class="avatar">{{ user?.initials }}</span>
                            <span class="who">
                                <span class="nm">{{ user?.name }}</span>
                                <span class="rl">{{ user?.job_title || user?.role_label }}</span>
                            </span>
                        </button>
                        <!-- Click-away layer: sits UNDER the menu (inside the same
                             top-bar layer), so clicks outside close the menu while
                             "My profile" and "Sign out" stay clickable. -->
                        <div v-if="menuOpen" class="menu-backdrop" aria-hidden="true" @click="menuOpen = false"></div>
                        <div v-if="menuOpen" class="menu" role="menu" @click="menuOpen = false">
                            <div class="mh">{{ user?.email }}</div>
                            <Link :href="route(profileRoute)" role="menuitem"><AppIcon name="user" :size="15" /><span><b>{{ t('shell.profile') }}</b></span></Link>
                            <div class="sep"></div>
                            <button type="button" role="menuitem" class="danger" @click="logout"><AppIcon name="logout" :size="15" /><span><b>{{ t('shell.logout') }}</b></span></button>
                        </div>
                    </div>
                </div>
            </header>

            <main class="content">
                <div v-if="company?.expiring_soon" class="alert warning mb-4">
                    <AppIcon name="calendar" :size="16" />
                    <span class="grow">{{ t('shell.expiring', { days: company.days_left, date: formatDate(company.subscription_ends_at, locale) }) }}</span>
                    <a v-if="support.email" class="btn btn-line sm" :href="`mailto:${support.email}`">{{ t('shell.renew_email') }}</a>
                    <a v-if="support.phone" class="btn btn-line sm" :href="`https://wa.me/${String(support.phone).replace(/\D/g, '')}`" target="_blank" rel="noopener">{{ t('shell.renew_whatsapp') }}</a>
                </div>
                <slot />
            </main>
        </div>
    </div>

    <FlashMessages />
</template>
