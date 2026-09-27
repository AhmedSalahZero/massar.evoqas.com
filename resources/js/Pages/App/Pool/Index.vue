<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — Public Talent Pool, for partners (Scope v2 §3 · Step 10)
//  Location: resources/js/Pages/App/Pool/Index.vue
//  Route: GET /app/pool (pool.index) — App\PoolController
//
//  Job seekers who registered themselves on the public site, confirmed
//  their email and chose to be seen. The same search as the Searchable
//  CV Bank (every word in the CVs and profiles, occupation at any level
//  in any standard, governorate, years, CV language). Open a person to
//  see the profile and "Add to my workspace".
// ══════════════════════════════════════════════════════════════════

import { computed, reactive, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Pagination from '@/Components/Pagination.vue';
import { usePreferences } from '@/composables/usePreferences';
import { useTranslations } from '@/composables/useTranslations';
import { experience, occIn } from '@/Components/Beneficiaries/ben';

const props = defineProps({
    filters: { type: Object, required: true },
    occupation: { type: Object, default: null },
    list: { type: Object, default: null },
    total: { type: Number, default: 0 },
    ready: { type: Boolean, default: true },
    governorates: { type: Array, default: () => [] },
    sectors: { type: Array, default: () => [] },
});

const { t, locale } = useTranslations();
const { prefs } = usePreferences();

const f = reactive({ ...props.filters });
const load = () => router.get(route('app.pool.index'), Object.fromEntries(Object.entries(f).filter(([, v]) => v !== '' && v !== 0)),
    { preserveState: true, preserveScroll: true, replace: true });
const reset = () => { Object.assign(f, { q: '', occ: '', governorate: '', min_years: 0, cv_lang: '', sector: '' }); chosenOcc.value = null; load(); };
const filtered = computed(() => f.occ || f.governorate || f.min_years || f.cv_lang || f.sector);
const sname = (x) => (locale.value === 'ar' ? x.name_ar : x.name_en);

// ── Occupation filter: any standard, any level ────────────────────
const chosenOcc = ref(props.occupation);
const occQ = ref('');
const occResults = ref([]);
const occOpen = ref(false);
let timer = null;
let seq = 0;
watch(occQ, (q) => {
    clearTimeout(timer);
    if (!q.trim()) { occResults.value = []; return; }
    timer = setTimeout(async () => {
        const mine = ++seq;
        const { data } = await window.axios.get(route('app.cv-bank.occupations'), { params: { q: q.trim() } });
        if (mine === seq) occResults.value = data;
    }, 250);
});
const pickOcc = (o) => { chosenOcc.value = o; f.occ = o.value; occOpen.value = false; occQ.value = ''; load(); };
const closeLater = () => setTimeout(() => { occOpen.value = false; }, 200);
const clearOcc = () => { chosenOcc.value = null; f.occ = ''; load(); };
const STD = { isco: 'ISCO-08', enoc: 'ENOC', esco: 'ESCO' };
const levelName = (o) => (o.standard === 'esco' ? t('bank.lvl_esco') : t(`bank.lvl_${o.level}`));

const occText = (block) => {
    if (!block) return t('bank.no_occupation');
    const s = occIn(block, block.esco ? 'esco' : prefs.standard === 'esco' ? 'isco' : prefs.standard, locale.value);
    return `${s.code} · ${s.title}`;
};
</script>

<template>
    <AppLayout :title="t('pool.title')">
        <div class="page-head">
            <div>
                <h1 class="page-title">{{ t('pool.title') }}</h1>
                <div class="page-sub">{{ t('pool.sub', { n: total }) }}</div>
            </div>
        </div>

        <div v-if="!ready" class="alert warning mb-3"><AppIcon name="alert" :size="16" /><span>{{ t('bank.not_ready') }}</span></div>

        <form class="filter-bar" @submit.prevent="load">
            <div class="search grow">
                <AppIcon name="search" :size="16" />
                <input v-model="f.q" type="search" :placeholder="t('pool.search_ph')" :aria-label="t('common.search')" autofocus>
                <button type="submit" class="btn btn-teal sm">{{ t('common.search') }}</button>
            </div>
        </form>
        <div class="filter-bar">
            <div class="occ">
                <button v-if="chosenOcc" type="button" class="inp fsel wide chosen" @click="clearOcc">
                    <span class="tag plain">{{ STD[chosenOcc.standard] }}</span> {{ chosenOcc.code }} · {{ chosenOcc.title }} <AppIcon name="x" :size="13" />
                </button>
                <template v-else>
                    <input v-model="occQ" type="search" class="inp fsel wide" :placeholder="t('bank.occ_ph')" :aria-label="t('bank.occ')" @focus="occOpen = true" @blur="closeLater">
                    <div v-if="occOpen && occResults.length" class="occlist">
                        <button v-for="o in occResults" :key="o.value" type="button" @mousedown.prevent="pickOcc(o)">
                            <span class="tag plain">{{ STD[o.standard] }}</span>
                            <b><bdi dir="ltr">{{ o.code }}</bdi></b> {{ o.title }}
                            <span class="mute small"> · {{ levelName(o) }}</span>
                        </button>
                    </div>
                </template>
            </div>
            <select v-model="f.governorate" class="inp fsel" :aria-label="t('ben.f_governorate')" @change="load">
                <option value="">{{ t('ben.all_governorates') }}</option>
                <option v-for="g in governorates" :key="g" :value="g">{{ t(`gov.${g}`) }}</option>
            </select>
            <select v-model.number="f.min_years" class="inp fsel" :aria-label="t('ben.f_min_years')" @change="load">
                <option :value="0">{{ t('ben.any_experience') }}</option>
                <option v-for="y in [1, 2, 3, 5, 10]" :key="y" :value="y">{{ t('ben.min_years', { n: y }) }}</option>
            </select>
            <select v-model="f.sector" class="inp fsel wide" :aria-label="t('emp.sector')" @change="load">
                <option value="">{{ t('emp.any_sector') }}</option>
                <optgroup v-for="sec in sectors" :key="sec.code" :label="sname(sec)">
                    <option :value="sec.code">{{ t('emp.all_of', { name: sname(sec) }) }}</option>
                    <option v-for="x in sec.subs" :key="x.code" :value="x.code">{{ sname(x) }}</option>
                </optgroup>
            </select>
            <select v-model="f.cv_lang" class="inp fsel" :aria-label="t('bank.cv_lang')" @change="load">
                <option value="">{{ t('bank.any_lang') }}</option>
                <option value="ar">{{ t('bank.lang_ar') }}</option>
                <option value="en">{{ t('bank.lang_en') }}</option>
            </select>
            <button v-if="filtered" type="button" class="btn btn-ghost sm" @click="reset"><AppIcon name="x" :size="14" />{{ t('ben.clear_filters') }}</button>
        </div>

        <EmptyState v-if="!total" icon="globe" :title="t('pool.empty_title')" :text="t('pool.empty_text')" />
        <EmptyState v-else-if="!list || !list.data.length" icon="search" :title="t('bank.none_title')" :text="t('bank.none_text')" />

        <template v-else>
            <div class="small mute mb-2">{{ t('pool.found', { n: list.total }) }}</div>
            <Link v-for="r in list.data" :key="r.uuid" :href="route('app.pool.show', r.uuid)" class="panel tight result">
                <div class="top">
                    <span class="avatar">{{ r.initials }}</span>
                    <div class="min0 grow">
                        <b>{{ r.name }}</b>
                        <div class="meta">
                            {{ occText(r.occupation) }}
                            <template v-if="r.governorate"> · {{ t(`gov.${r.governorate}`) }}</template><template v-if="r.city">, {{ r.city }}</template>
                            <template v-if="r.experience"> · {{ experience(r.experience, t, locale) }}</template>
                        </div>
                    </div>
                    <span v-if="r.added" class="tag green"><AppIcon name="check" :size="12" /> {{ t('pool.in_workspace') }}</span>
                    <span v-if="r.notice_period" class="tag plain">{{ t('join.notice') }}: {{ t(`join.notice_${r.notice_period}`) }}</span>
                    <span v-if="r.cv_count" class="tag plain">{{ t('bank.cvs', { n: r.cv_count }) }}<template v-if="r.cv_languages.length"> · {{ r.cv_languages.map((l) => t(`bank.lang_${l}`)).join(' / ') }}</template></span>
                </div>
                <div v-if="r.snippets.length" class="snips">
                    <p v-for="(s, i) in r.snippets" :key="i" dir="auto">
                        <template v-for="(part, k) in s" :key="k"><mark v-if="part.hit">{{ part.t }}</mark><template v-else>{{ part.t }}</template></template>
                    </p>
                </div>
                <div v-else-if="r.found_by === 'occupation'" class="small c-teal mt-2"><AppIcon name="layers" :size="12" /> {{ t('bank.by_occupation') }}</div>
            </Link>
            <Pagination class="mt-4" :paginator="list" />
        </template>
    </AppLayout>
</template>

<style scoped>
.fsel { width: auto; min-width: 150px; max-width: 220px; }
.fsel.wide { max-width: 320px; }
.occ { position: relative; min-width: 260px; }
.occ .chosen { display: flex; align-items: center; gap: 6px; text-align: start; cursor: pointer; }
.occlist { position: absolute; z-index: 20; inset-inline: 0; top: calc(100% + 4px); background: var(--ms-bg-card); border: 1px solid var(--ms-border);
    border-radius: 8px; max-height: 320px; overflow-y: auto; box-shadow: 0 8px 24px rgba(0, 0, 0, .12); }
.occlist button { display: block; width: 100%; text-align: start; padding: 8px 10px; border: 0; background: none; font-size: 12.5px; cursor: pointer; }
.occlist button:hover { background: var(--ms-bg-hover); }
.result { display: block; text-decoration: none; color: inherit; margin-top: 10px; }
.result:hover { border-color: var(--ms-navy-border, var(--ms-border)); }
.result .top { display: flex; gap: 12px; align-items: center; }
.result .meta { font-size: 12px; color: var(--ms-text-muted); }
.snips { margin-top: 10px; padding-inline-start: 50px; }
.snips p { margin: 0 0 4px; font-size: 12.5px; color: var(--ms-text-secondary, inherit); line-height: 1.6; }
.snips mark { background: var(--ms-gold-dim, #fff3c4); color: inherit; padding: 0 2px; border-radius: 3px; font-weight: 700; }
.min0 { min-width: 0; }
.grow { flex: 1; }
</style>
