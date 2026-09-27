<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — Searchable CV Bank (Scope v2 §3)
//  Location: resources/js/Pages/App/CvBank/Index.vue
//  Route: GET /app/cv-bank (beneficiaries.view) — App\CvBankController@index
//
//  Search the people of this workspace by every word in their CVs and
//  profiles (Arabic spelling differences ignored), plus occupation
//  synonyms ("bookkeeper" finds the accountants). Filters: occupation
//  (ISCO-08 / ENOC / ESCO, any level — searched), governorate, minimum
//  experience, CV language. Each result shows the CV lines with the
//  words highlighted, or says it was found through the occupation.
// ══════════════════════════════════════════════════════════════════

import { computed, reactive, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Pagination from '@/Components/Pagination.vue';
import Modal from '@/Components/Modal.vue';
import { sectionOf } from '@/Components/Assessments/elig';
import { usePreferences } from '@/composables/usePreferences';
import { useTranslations } from '@/composables/useTranslations';
import { experience, occIn } from '@/Components/Beneficiaries/ben';

const props = defineProps({
    filters: { type: Object, required: true },
    occupation: { type: Object, default: null },
    searched: { type: Boolean, default: false },
    list: { type: Object, default: null },
    total: { type: Number, default: 0 },
    ready: { type: Boolean, default: true },
    governorates: { type: Array, default: () => [] },
    sectors: { type: Array, default: () => [] },
    opportunities: { type: Array, default: () => [] },   // Step 12: open jobs and trainings, for "Check these people"
    report: { type: Object, default: null },             // Step 14: the people behind a report's number
});

const { t, locale } = useTranslations();
const { prefs } = usePreferences();

const f = reactive({ ...props.filters });
const load = () => router.get(route('app.cv-bank.index'), Object.fromEntries(Object.entries(f).filter(([, v]) => v !== '' && v !== 0)),
    { preserveState: true, preserveScroll: true, replace: true });
const reset = () => { Object.assign(f, { q: '', occ: '', governorate: '', min_years: 0, cv_lang: '', sector: '', stage: '', report: '' }); chosenOcc.value = null; load(); };
const clearReport = () => { f.report = ''; load(); };
const filtered = computed(() => f.occ || f.governorate || f.min_years || f.cv_lang || f.sector || f.stage);

// ── Check every person found against a job or training ───────────
const checking = ref(false);
const programId = ref('');
const sending = ref(false);
const checkFound = () => {
    sending.value = true;
    const o = props.opportunities.find((x) => x.id === programId.value);
    if (!o) return;
    router.post(route(`app.${sectionOf(o.kind)}.runs.start`, o.id), { scope: 'search', filters: { ...props.filters } }, {
        onFinish: () => { sending.value = false; },
    });
};
const jobs = computed(() => props.opportunities.filter((o) => o.kind === 'job'));
const trainings = computed(() => props.opportunities.filter((o) => o.kind === 'training'));
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
    <AppLayout :title="t('bank.title')">
        <div class="page-head">
            <div>
                <h1 class="page-title">{{ t('bank.title') }}</h1>
                <div class="page-sub">{{ t('bank.sub', { n: total }) }}</div>
            </div>
        </div>

        <div v-if="report" class="alert mb-3 rep">
            <AppIcon name="chart" :size="16" />
            <span class="grow">
                <b>{{ t('rep.from_report') }}</b>
                <template v-if="report.row"> · {{ report.row }}</template><template v-if="report.col"> · {{ report.col }}</template>
                <span class="small mute d-block">{{ report.summary.join(' · ') }}</span>
            </span>
            <button type="button" class="btn btn-line sm" @click="clearReport">{{ t('rep.clear_report') }}</button>
        </div>
        <div v-if="!ready" class="alert warning mb-3"><AppIcon name="alert" :size="16" /><span>{{ t('bank.not_ready') }}</span></div>

        <form class="filter-bar" @submit.prevent="load">
            <div class="search grow">
                <AppIcon name="search" :size="16" />
                <input v-model="f.q" type="search" :placeholder="t('bank.search_ph')" :aria-label="t('common.search')" autofocus>
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
            <select v-model="f.stage" class="inp fsel" :aria-label="t('bank.stage')" @change="load">
                <option value="">{{ t('bank.any_stage') }}</option>
                <option v-for="s in ['not_assessed', 'assessed', 'eligible', 'matched', 'placed']" :key="s" :value="s">{{ t(`bank.stage_${s}`) }}</option>
            </select>
            <select v-model="f.cv_lang" class="inp fsel" :aria-label="t('bank.cv_lang')" @change="load">
                <option value="">{{ t('bank.any_lang') }}</option>
                <option value="ar">{{ t('bank.lang_ar') }}</option>
                <option value="en">{{ t('bank.lang_en') }}</option>
            </select>
            <button v-if="filtered" type="button" class="btn btn-ghost sm" @click="reset"><AppIcon name="x" :size="14" />{{ t('ben.clear_filters') }}</button>
        </div>

        <EmptyState v-if="!searched" icon="search" :title="t('bank.start_title')" :text="t('bank.start_text')" />
        <EmptyState v-else-if="!list || !list.data.length" icon="search" :title="t('bank.none_title')" :text="t('bank.none_text')" />

        <template v-else>
            <div class="foundbar mb-2">
                <span class="small mute">{{ t('bank.found', { n: list.total }) }}</span>
                <button v-if="opportunities.length" type="button" class="btn btn-line sm" @click="checking = true"><AppIcon name="clipboard" :size="14" />{{ t('bank.check_found') }}</button>
            </div>
            <Link v-for="r in list.data" :key="r.number" :href="route('app.beneficiaries.show', r.number)" class="panel tight result">
                <div class="top">
                    <span class="avatar">{{ r.initials }}</span>
                    <div class="min0 grow">
                        <b>{{ r.name }}</b>
                        <div class="meta">
                            <bdi dir="ltr">#{{ r.number }}</bdi>
                            · {{ occText(r.occupation) }}
                            <template v-if="r.governorate"> · {{ t(`gov.${r.governorate}`) }}</template><template v-if="r.city">, {{ r.city }}</template>
                            <template v-if="r.experience"> · {{ experience(r.experience, t, locale) }}</template>
                        </div>
                    </div>
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

        <Modal :show="checking" size="sm" :title="t('bank.check_found')" @close="checking = false">
            <p class="small mute">{{ t('bank.check_found_sub', { n: list?.total || 0 }) }}</p>
            <label class="fld mt-3">
                <span class="fl">{{ t('elig.choose_opportunity') }}</span>
                <select v-model="programId">
                    <option value="">—</option>
                    <optgroup v-if="jobs.length" :label="t('nav.jobs')">
                        <option v-for="o in jobs" :key="o.id" :value="o.id">{{ o.title }}</option>
                    </optgroup>
                    <optgroup v-if="trainings.length" :label="t('nav.training')">
                        <option v-for="o in trainings" :key="o.id" :value="o.id">{{ o.title }}</option>
                    </optgroup>
                </select>
            </label>
            <template #footer>
                <button type="button" class="btn btn-line" @click="checking = false">{{ t('common.cancel') }}</button>
                <button type="button" class="btn btn-teal" :class="{ 'is-loading': sending }" :disabled="!programId || sending" @click="checkFound">{{ t('elig.check') }}</button>
            </template>
        </Modal>
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
.foundbar { display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap; }
.grow { flex: 1; }
.rep .grow { flex: 1; }
.d-block { display: block; }
</style>
