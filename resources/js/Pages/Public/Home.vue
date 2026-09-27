<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — public home page (Step 10)
//  Location: resources/js/Pages/Public/Home.vue
//  Route: GET / (home) — Public\HomeController
//
//  As agreed in the demo: what Massar is, the two ways to register
//  (upload a CV / answer the questions), how it works, "your data
//  stays yours", questions. Staff sign in from the footer link.
// ══════════════════════════════════════════════════════════════════

import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();
const seeker = computed(() => usePage().props.seeker);
</script>

<template>
    <PublicLayout :title="t('pub.home_title')" active="home">
        <section class="pub-hero">
            <span class="badge green"><span class="dot"></span>{{ t('pub.free') }}</span>
            <h1 class="mt-3">{{ t('pub.hero') }}</h1>
            <p>{{ t('pub.hero_sub') }}</p>
        </section>

        <section v-if="seeker" class="doors-1">
            <div class="panel signed">
                <AppIcon name="check-circle" :size="22" />
                <div class="grow"><b>{{ t('pub.signed_in', { name: seeker.name }) }}</b></div>
                <Link :href="route('seeker.profile')" class="btn btn-primary">{{ t('pub.my_profile') }}</Link>
            </div>
        </section>
        <section v-else class="doors-2">
            <div class="door" style="--acc: var(--ms-green)">
                <div class="ic"><AppIcon name="upload" :size="22" /></div>
                <span class="tag green">{{ t('pub.cv_time') }}</span>
                <h3>{{ t('pub.cv_door') }}</h3>
                <p>{{ t('pub.cv_door_sub') }}</p>
                <Link :href="route('seeker.join', { door: 'cv' })" class="btn btn-primary lg block">{{ t('pub.cv_door') }}</Link>
            </div>
            <div class="door" style="--acc: var(--ms-navy)">
                <div class="ic"><AppIcon name="clipboard" :size="22" /></div>
                <span class="tag">{{ t('pub.q_time') }}</span>
                <h3>{{ t('pub.q_door') }}</h3>
                <p>{{ t('pub.q_door_sub') }}</p>
                <Link :href="route('seeker.join', { door: 'questions' })" class="btn btn-navy lg block">{{ t('pub.q_door_btn') }}</Link>
            </div>
        </section>

        <section id="how" class="block">
            <div class="sec-h"><h2>{{ t('pub.how') }}</h2></div>
            <div class="how">
                <div v-for="n in [1, 2, 3]" :key="n" class="panel">
                    <i class="num">{{ n }}</i>
                    <h3>{{ t(`pub.how${n}`) }}</h3>
                    <p class="mute">{{ t(`pub.how${n}_sub`) }}</p>
                </div>
            </div>
        </section>

        <section id="privacy" class="block">
            <div class="panel privacy">
                <div class="ic-lg"><AppIcon name="shield" :size="24" /></div>
                <div>
                    <h3>{{ t('pub.data') }}</h3>
                    <ul class="ticks">
                        <li>{{ t('pub.data1') }}</li>
                        <li>{{ t('pub.data2') }}</li>
                        <li>{{ t('pub.data3') }}</li>
                    </ul>
                </div>
            </div>
        </section>

        <section class="block">
            <div class="sec-h"><h2>{{ t('pub.faq') }}</h2></div>
            <details v-for="n in [1, 2, 3]" :key="n" class="faq panel">
                <summary>{{ t(`pub.faq${n}`) }}</summary>
                <p class="mute">{{ t(`pub.faq${n}_a`) }}</p>
            </details>
        </section>
    </PublicLayout>
</template>

<style scoped>
.pub-hero .badge { display: inline-flex; }
.doors-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; max-width: 860px; margin: 0 auto; }
.doors-1 { max-width: 860px; margin: 0 auto; }
.signed { display: flex; gap: 12px; align-items: center; color: var(--ms-green); }
.signed b { color: var(--ms-text-primary); }
.door .tag { align-self: flex-start; }
.door .btn { margin-top: 8px; }
.block { margin-top: 48px; }
.sec-h { text-align: center; margin-bottom: 16px; }
.sec-h h2 { margin: 0; font-size: 22px; }
.how { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; }
.how .panel + .panel { margin-top: 0; }
.how .num { font-style: normal; width: 30px; height: 30px; border-radius: 50%; background: var(--ms-brand-grad); color: #fff; display: grid; place-items: center; font-weight: 800; margin-bottom: 10px; }
.how p { margin: 6px 0 0; font-size: 13px; }
.privacy { display: flex; gap: 18px; align-items: flex-start; }
.ic-lg { width: 52px; height: 52px; border-radius: 14px; background: var(--ms-green-dim); color: var(--ms-green); display: grid; place-items: center; flex-shrink: 0; }
.ticks { list-style: none; padding: 0; margin: 10px 0 0; display: grid; gap: 8px; font-size: 13.5px; }
.ticks li { padding-inline-start: 24px; position: relative; }
.ticks li::before { content: '✓'; position: absolute; inset-inline-start: 0; color: var(--ms-green); font-weight: 800; }
.faq { max-width: 760px; margin-inline: auto; padding: 14px 18px; }
.faq + .faq { margin-top: 8px; }
.faq summary { cursor: pointer; font-weight: 700; font-size: 14px; }
.faq p { margin: 8px 0 0; font-size: 13px; }
.grow { flex: 1; }
@media (max-width: 760px) {
    .doors-2, .how { grid-template-columns: 1fr; }
    .privacy { flex-direction: column; }
}
</style>
