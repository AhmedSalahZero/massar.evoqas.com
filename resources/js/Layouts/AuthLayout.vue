<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — AuthLayout (sign-in screens)
//  Location: resources/js/Layouts/AuthLayout.vue
//
//      <AuthLayout :title="t('auth.login')"> …form… </AuthLayout>
//
//  Used by sign in, forgot/reset password, email code and confirm
//  password. A brand panel on one side, the form card on the other.
//
//  Brand panel (design from massar_2; hidden on phones by app.css):
//    • background photo: public/images/login-bg.jpg, under a tint
//      made from the theme colours so the text stays readable in
//      BOTH dark and light mode. To change the photo, replace that
//      file (keep the same name) — no code change needed. If the
//      file is missing, the panel just shows the tint colours.
//    • eyebrow, headline, intro, four key points, the three
//      standards, and a footer. Wording (EN/AR) lives in
//      resources/js/lang/translations.js → login.*
//
//  Page split: photo 75% / sign-in 25% (see the <style> block below).
//  The language and theme switches work for guests too — language is
//  saved in the session so server messages come back in that language.
// ══════════════════════════════════════════════════════════════════

import { watch } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import FlashMessages from '@/Components/FlashMessages.vue';
import { syncFromPage, usePreferences } from '@/composables/usePreferences';
import { useTranslations } from '@/composables/useTranslations';

defineProps({ title: { type: String, default: '' } });

const page = usePage();
const { t, locale } = useTranslations();
const { prefs, toggleTheme, setLocale } = usePreferences();

watch(() => page.props, (p) => syncFromPage(p), { immediate: true });

// The four key points on the brand panel: icon + colour + wording keys.
const points = [
    { icon: 'layers', color: 'var(--ms-teal)', h: 'login.p1_h', p: 'login.p1_p' },
    { icon: 'file', color: 'var(--ms-navy)', h: 'login.p2_h', p: 'login.p2_p' },
    { icon: 'trend', color: 'var(--ms-green)', h: 'login.p3_h', p: 'login.p3_p' },
    { icon: 'shield', color: 'var(--ms-gold)', h: 'login.p4_h', p: 'login.p4_p' },
];
</script>

<template>
    <Head :title="title" />
    <div class="auth">
        <aside class="auth-side auth-photo">
            <div class="brand">
                <div class="brand-mark">M</div>
                <div class="brand-name">Massar <span v-if="locale === 'ar'">مسار</span></div>
            </div>

            <div class="auth-copy">
                <div class="page-eyebrow">{{ t('login.side_eyebrow') }}</div>
                <h2>{{ t('login.side_title') }}</h2>
                <p>{{ t('login.side_text') }}</p>

                <ul class="auth-points">
                    <li v-for="pt in points" :key="pt.h">
                        <span class="pt-ic" :style="{ '--c': pt.color }"><AppIcon :name="pt.icon" :size="17" /></span>
                        <span>
                            <b>{{ t(pt.h) }}</b>
                            <span>{{ t(pt.p) }}</span>
                        </span>
                    </li>
                </ul>

                <div class="tags mt-4">
                    <span class="tag teal code">ENOC</span>
                    <span class="tag teal code">ISCO-08</span>
                    <span class="tag teal code">ESCO</span>
                </div>
            </div>

            <div class="small mute auth-foot">© {{ new Date().getFullYear() }} {{ t('login.side_foot') }}</div>
        </aside>

        <main class="auth-main">
            <div class="auth-card">
                <div class="flex items-center justify-between mb-6">
                    <div class="brand" style="padding:0">
                        <div class="brand-mark">M</div>
                        <div class="brand-name">{{ locale === 'ar' ? 'مسار' : 'Massar' }}</div>
                    </div>
                    <div class="flex gap-2">
                        <div class="seg" role="group">
                            <button type="button" :aria-pressed="prefs.locale === 'en'" @click="setLocale('en')">EN</button>
                            <button type="button" :aria-pressed="prefs.locale === 'ar'" @click="setLocale('ar')">عربي</button>
                        </div>
                        <button type="button" class="icon-btn" :aria-label="t('shell.theme')" @click="toggleTheme">
                            <AppIcon :name="prefs.theme === 'dark' ? 'sun' : 'moon'" :size="16" />
                        </button>
                    </div>
                </div>
                <slot />
            </div>
        </main>
    </div>
    <FlashMessages />
</template>

<style scoped>
/* ── Page split: photo 75% · sign-in 25% ─────────────────────────
   app.css splits the sign-in page 50/50. Here the photo side gets 3
   parts and the sign-in side 1 part (75% / 25%). The sign-in side
   never goes below 380px so the form stays comfortable on smaller
   laptops. Only on screens wider than 980px — below that app.css
   hides the photo panel and shows the form alone (phones, tablets).
   To change the split, edit the two numbers: 3fr (photo) 1fr (form). */
@media (min-width: 981px) {
    .auth { grid-template-columns: minmax(0, 3fr) minmax(380px, 1fr); }
}

/* ── Background photo with a theme-coloured tint ─────────────────
   Uses only app.css colour tokens, so dark and light themes each get
   a matching tint automatically. If the photo is missing, the panel
   simply shows the tint colours — nothing breaks. */
.auth-photo {
    background-image:
        linear-gradient(
            165deg,
            color-mix(in srgb, var(--ms-bg-header) 94%, transparent) 0%,
            color-mix(in srgb, var(--ms-bg-header) 86%, transparent) 45%,
            color-mix(in srgb, var(--ms-bg) 80%, transparent) 100%
        ),
        url('/images/login-bg.jpg');
    background-size: cover;
    background-position: center;
}

/* Light mode: the same tint as dark mode hid the photo (a light colour at
   80–94% covers it). Here the tint is strong only behind the text and
   fades out towards the other side, so the photo shows. The text side is
   the left in English and the right in Arabic. */
:global(html.light .auth-photo) {
    background-image:
        linear-gradient(
            90deg,
            color-mix(in srgb, var(--ms-bg-header) 92%, transparent) 0%,
            color-mix(in srgb, var(--ms-bg-header) 82%, transparent) 38%,
            color-mix(in srgb, var(--ms-bg-header) 35%, transparent) 70%,
            color-mix(in srgb, var(--ms-bg-header) 10%, transparent) 100%
        ),
        url('/images/login-bg.jpg');
}
:global(html.light[dir="rtl"] .auth-photo) {
    background-image:
        linear-gradient(
            270deg,
            color-mix(in srgb, var(--ms-bg-header) 92%, transparent) 0%,
            color-mix(in srgb, var(--ms-bg-header) 82%, transparent) 38%,
            color-mix(in srgb, var(--ms-bg-header) 35%, transparent) 70%,
            color-mix(in srgb, var(--ms-bg-header) 10%, transparent) 100%
        ),
        url('/images/login-bg.jpg');
}

/* Keep the text above app.css's decorative teal glow (::after). */
.auth-photo > * { position: relative; z-index: 1; }

.auth-copy { max-width: 470px; }
.auth-copy h2 { margin-top: 2px; }

/* ── The four key points ─────────────────────────────────────── */
.auth-points {
    list-style: none;
    margin: 22px 0 0;
    padding: 0;
    display: grid;
    gap: 14px;
}
.auth-points li { display: flex; gap: 12px; align-items: flex-start; }
.auth-points .pt-ic {
    width: 34px;
    height: 34px;
    border-radius: 10px;
    flex-shrink: 0;
    display: grid;
    place-items: center;
    color: var(--c);
    background: color-mix(in srgb, var(--c) 16%, transparent);
    border: 1px solid color-mix(in srgb, var(--c) 30%, transparent);
}
.auth-points b { display: block; font-size: 13.5px; color: var(--ms-text-primary); }
.auth-points li > span:last-child > span { display: block; font-size: 12.5px; color: var(--ms-text-muted); margin-top: 2px; line-height: 1.5; }

.auth-foot { position: relative; z-index: 1; }
</style>