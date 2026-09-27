<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — PublicLayout (the public site for job seekers, Step 10)
//  Location: resources/js/Layouts/PublicLayout.vue
//
//      <PublicLayout :title="t('join.title')"> …page… </PublicLayout>
//
//  Top bar: brand, Home · How it works · Your data, language, theme,
//  and "Sign in" + "Register" (or "My profile" + "Sign out" for a
//  signed-in job seeker). Footer: privacy, and the small
//  "Staff sign-in (partner organisations)" link to /login — the only
//  way from the public site to the partners' workspace.
//  Classes come from app.css (the Massar style guide): .pub-top,
//  .pub-nav, .pub-wrap, .pub-foot.
// ══════════════════════════════════════════════════════════════════

import { computed, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import FlashMessages from '@/Components/FlashMessages.vue';
import { syncFromPage, usePreferences } from '@/composables/usePreferences';
import { useTranslations } from '@/composables/useTranslations';

defineProps({
    title: { type: String, default: '' },
    narrow: { type: Boolean, default: false },
    active: { type: String, default: '' },
});

const page = usePage();
const { t } = useTranslations();
const { prefs, toggleTheme, setLocale } = usePreferences();
watch(() => page.props, (p) => syncFromPage(p), { immediate: true });

const seeker = computed(() => page.props.seeker);
const signOut = () => router.post(route('seeker.logout'));
const year = new Date().getFullYear();
</script>

<template>
    <Head :title="title" />
    <header class="pub-top">
        <Link :href="route('home')" class="brand pbrand">
            <span class="brand-mark">M</span>
            <span class="brand-name">Massar <span>مسار</span></span>
        </Link>
        <nav class="pub-nav hide-sm">
            <Link :href="route('home')" :class="{ active: active === 'home' }">{{ t('pub.nav_home') }}</Link>
            <Link :href="route('home') + '#how'">{{ t('pub.nav_how') }}</Link>
            <Link :href="route('home') + '#privacy'">{{ t('pub.nav_data') }}</Link>
        </nav>
        <div class="top-end">
            <div class="seg" role="group" :aria-label="t('shell.language')">
                <button type="button" :aria-pressed="prefs.locale === 'ar'" @click="setLocale('ar')">عربي</button>
                <button type="button" :aria-pressed="prefs.locale === 'en'" @click="setLocale('en')">EN</button>
            </div>
            <button type="button" class="btn btn-ghost icon" :aria-label="t('shell.theme')" @click="toggleTheme">
                <AppIcon :name="prefs.theme === 'dark' ? 'sun' : 'moon'" :size="16" />
            </button>
            <template v-if="seeker">
                <Link :href="route('seeker.profile')" class="btn btn-line sm"><AppIcon name="user" :size="14" /><span class="hide-sm">{{ t('pub.my_profile') }}</span></Link>
                <button type="button" class="btn btn-ghost sm" @click="signOut"><AppIcon name="logout" :size="14" /><span class="hide-sm">{{ t('shell.logout') }}</span></button>
            </template>
            <template v-else>
                <Link :href="route('seeker.login')" class="btn btn-line sm">{{ t('pub.sign_in') }}</Link>
                <Link :href="route('seeker.join')" class="btn btn-primary sm hide-sm">{{ t('pub.register') }}</Link>
            </template>
        </div>
    </header>

    <main class="pub-wrap" :class="{ narrow }">
        <slot />
    </main>

    <footer class="pub-foot">
        <div class="in">
            <span>© {{ year }} Massar · {{ t('pub.foot') }}</span>
            <span class="foot-links">
                <Link :href="route('home') + '#privacy'">{{ t('pub.privacy') }}</Link>
                ·
                <a :href="route('login')" class="staff-link"><AppIcon name="lock" :size="13" />{{ t('pub.staff_sign_in') }}</a>
            </span>
        </div>
    </footer>
    <FlashMessages />
</template>

<style scoped>
.pbrand { padding: 0; min-height: 0; text-decoration: none; color: inherit; }
.brand-mark { flex-shrink: 0; }
.top-end { margin-inline-start: auto; display: flex; gap: 8px; align-items: center; }
.pub-wrap { min-height: calc(100vh - 62px - 70px); }
.pub-wrap.narrow { max-width: 760px; padding-top: 28px; }
.foot-links { display: flex; gap: 8px; align-items: center; }
.staff-link { display: inline-flex; gap: 5px; align-items: center; }
@media (max-width: 760px) {
    .hide-sm { display: none !important; }
    .pub-top { padding: 0 12px; gap: 8px; }
    .pbrand .brand-name { display: none; }
    .pub-wrap { padding-inline: 16px; }
}
</style>
