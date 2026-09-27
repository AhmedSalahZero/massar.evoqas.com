<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — Jobs / Training Programs list (Step 12)
//  Location: resources/js/Pages/App/Opportunities/Index.vue
//  Route: GET /app/jobs · GET /app/training (opportunities.view) — App\OpportunityController@index
//
//  The workspace's jobs (or trainings), open / closed. Each card: title,
//  employer or provider, occupations (in the top-bar standard), where,
//  seats, deadline, and how many people are Eligible / Check / Not
//  eligible / On hold, and (Step 13) the seats taken: "7 of 10 seats
//  taken". Search by title (and employer or provider); filter by
//  occupation at any level and by governorate.
// ══════════════════════════════════════════════════════════════════

import { reactive, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Pagination from '@/Components/Pagination.vue';
import { usePermissions } from '@/composables/usePermissions';
import { usePreferences } from '@/composables/usePreferences';
import { useTranslations } from '@/composables/useTranslations';
import { formatDate } from '@/Utils/date';
import { STANDARD_LABELS } from '@/Components/Backbone/occ';
import { KIND_ICON, RESULT_BADGE, RESULTS } from '@/Components/Assessments/elig';
import { govList, occShown } from '@/Components/Opportunities/opp';

const props = defineProps({
    kind: { type: String, required: true },
    section: { type: String, required: true },
    list: { type: Object, required: true },
    filters: { type: Object, required: true },
    occupation: { type: Object, default: null },
    tabs: { type: Object, required: true },
    governorates: { type: Array, default: () => [] },
});
const { t, locale } = useTranslations();
const { can } = usePermissions();
const { prefs } = usePreferences();

const f = reactive({ ...props.filters });
const load = () => router.get(route(`app.${props.section}.index`),
    Object.fromEntries(Object.entries(f).filter(([k, v]) => v !== '' && !(k === 'tab' && v === 'open'))),
    { preserveState: true, preserveScroll: true, replace: true });
let timer = null;
const onSearch = () => { clearTimeout(timer); timer = setTimeout(load, 350); };
const go = (tab) => { f.tab = tab; load(); };

// ── Occupation filter: any standard, any level ────────────────────
const chosenOcc = ref(props.occupation);
const occQ = ref('');
const occResults = ref([]);
const occOpen = ref(false);
let otimer = null;
let seq = 0;
watch(occQ, (q) => {
    clearTimeout(otimer);
    if (!q.trim()) { occResults.value = []; return; }
    otimer = setTimeout(async () => {
        const mine = ++seq;
        const { data } = await window.axios.get(route('app.cv-bank.occupations'), { params: { q: q.trim() } });
        if (mine === seq) occResults.value = data;
    }, 250);
});
const pickOcc = (o) => { chosenOcc.value = o; f.occ = o.value; occQ.value = ''; occOpen.value = false; load(); };
const clearOcc = () => { chosenOcc.value = null; f.occ = ''; load(); };
const closeLater = () => setTimeout(() => { occOpen.value = false; }, 200);
const filtered = () => f.q || f.occ || f.governorate;
const reset = () => { Object.assign(f, { q: '', occ: '', governorate: '' }); chosenOcc.value = null; load(); };

const date = (d) => (d ? formatDate(d, locale.value) : '');
</script>

<template>
    <AppLayout :title="t(`nav.${kind === 'job' ? 'jobs' : 'training'}`)">
        <div class="page-head">
            <div>
                <div class="page-eyebrow">{{ t('nav.opportunities_group') }}</div>
                <h1 class="page-title">{{ t(`nav.${kind === 'job' ? 'jobs' : 'training'}`) }}</h1>
                <div class="page-sub">{{ t(`opp.list_sub_${kind}`) }}</div>
            </div>
            <div v-if="can('opportunities.manage')" class="page-actions">
                <Link :href="route(`app.${section}.create`)" class="btn btn-primary"><AppIcon name="plus" :size="15" />{{ t(`opp.new_${kind}`) }}</Link>
            </div>
        </div>

        <div class="toolbar mb-4">
            <div class="seg" role="group">
                <button type="button" :aria-pressed="f.tab === 'open'" @click="go('open')">{{ t('opp.tab_open') }} · {{ tabs.open }}</button>
                <button type="button" :aria-pressed="f.tab === 'closed'" @click="go('closed')">{{ t('opp.tab_closed') }} · {{ tabs.closed }}</button>
            </div>
            <input v-model="f.q" type="search" class="inp grow" :placeholder="t(`opp.search_ph_${kind}`)" @input="onSearch">
            <div class="occf">
                <span v-if="chosenOcc" class="tag teal">{{ STANDARD_LABELS[chosenOcc.standard] }} {{ chosenOcc.code }} · {{ chosenOcc.title }}<span class="x" role="button" @click="clearOcc">×</span></span>
                <template v-else>
                    <input v-model="occQ" type="search" class="inp" :placeholder="t('opp.filter_occ')" @focus="occOpen = true" @blur="closeLater">
                    <div v-if="occOpen && occResults.length" class="occlist">
                        <button v-for="o in occResults" :key="o.value" type="button" @mousedown.prevent="pickOcc(o)">
                            <span class="tag plain">{{ STANDARD_LABELS[o.standard] }}</span> <b><bdi dir="ltr">{{ o.code }}</bdi></b> {{ o.title }}
                        </button>
                    </div>
                </template>
            </div>
            <select v-model="f.governorate" class="inp sel" :aria-label="t('opp.governorates')" @change="load">
                <option value="">{{ t('opp.any_governorate') }}</option>
                <option v-for="g in governorates" :key="g" :value="g">{{ t(`gov.${g}`) }}</option>
            </select>
            <button v-if="filtered()" type="button" class="btn btn-line sm" @click="reset">{{ t('opp.clear_filters') }}</button>
        </div>

        <EmptyState v-if="!list.data.length && !filtered()" :icon="KIND_ICON[kind]"
                    :title="t(f.tab === 'open' ? `opp.empty_title_${kind}` : 'opp.empty_closed_title')"
                    :text="t(f.tab === 'open' ? `opp.empty_${kind}` : 'opp.empty_closed')" />
        <EmptyState v-else-if="!list.data.length" icon="search" :title="t('opp.none_found')" :text="t('opp.none_found_text')" />

        <div v-else class="cards">
            <Link v-for="o in list.data" :key="o.id" :href="route(`app.${section}.show`, o.id)" class="panel acc card" :class="o.status === 'open' ? 'acc-teal' : ''">
                <div class="flex items-center justify-between gap-2">
                    <h3 class="nm" dir="auto">{{ o.title }}</h3>
                    <span class="badge" :class="o.status === 'open' ? 'green' : 'plain'">{{ t(`opp.status_${o.status}`) }}</span>
                </div>
                <div v-if="o.employer || o.provider" class="small mt-1" dir="auto">
                    <AppIcon :name="kind === 'job' ? 'building' : 'graduation'" :size="12" /> {{ kind === 'job' ? o.employer : o.provider }}
                </div>
                <div class="small mute mt-1 truncate">{{ o.occupations.slice(0, 2).map((x) => occShown(x, prefs.standard, locale)).join(' | ') }}<template v-if="o.occupations.length > 2"> {{ t('opp.more_n', { n: o.occupations.length - 2 }) }}</template></div>
                <div class="small mute mt-1">
                    {{ govList(o.governorates, t) }}<template v-if="o.city"> · {{ o.city }}</template>
                    · {{ t('opp.seats_n', { n: o.seats }) }}
                </div>
                <div class="seatline mt-2">
                    <span class="small" :class="o.seats_taken >= o.seats ? 'c-orange' : ''"><b>{{ t('mt.seats_taken', { taken: o.seats_taken, seats: o.seats }) }}</b></span>
                    <div class="meter sm"><i :style="`width:${Math.min(100, Math.round((o.seats_taken / (o.seats || 1)) * 100))}%;--c:var(--ms-${o.seats_taken >= o.seats ? 'orange' : 'teal'})`" /></div>
                </div>
                <div v-if="o.deadline" class="small mt-1" :class="o.deadline_passed ? 'c-orange' : 'mute'">
                    <AppIcon name="calendar" :size="12" /> {{ o.deadline_passed ? t('opp.deadline_passed_on', { d: date(o.deadline) }) : t('opp.deadline_on', { d: date(o.deadline) }) }}
                </div>
                <div v-if="o.status === 'closed' && o.close_reason" class="small mute mt-1">{{ t(`opp.reason_${o.close_reason}`) }}</div>
                <div class="res mt-3">
                    <span v-for="r in RESULTS" :key="r" class="badge" :class="o.counts[r] ? RESULT_BADGE[r] : 'plain'">{{ t(`elig.r_${r}`) }} · {{ o.counts[r] }}</span>
                </div>
                <div class="small mute mt-2">{{ t('elig.checked_n', { n: o.counts.total }) }} · {{ t('opp.rules_n', { n: o.rules_count }) }}</div>
            </Link>
        </div>
        <Pagination v-if="list.data.length" class="mt-4" :paginator="list" />
    </AppLayout>
</template>

<style scoped>
.toolbar { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
.toolbar .grow { flex: 1; min-width: 180px; max-width: 300px; }
.toolbar .sel { width: auto; min-width: 150px; }
.occf { position: relative; min-width: 220px; }
.occlist { position: absolute; z-index: 20; inset-inline: 0; top: calc(100% + 4px); background: var(--ms-bg-card); border: 1px solid var(--ms-border);
    border-radius: 8px; max-height: 300px; overflow-y: auto; box-shadow: 0 8px 24px rgba(0, 0, 0, .12); min-width: 300px; }
.occlist button { display: block; width: 100%; text-align: start; padding: 8px 10px; border: 0; background: none; font-size: 12.5px; cursor: pointer; color: inherit; }
.occlist button:hover { background: var(--ms-bg-hover); }
.cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(310px, 1fr)); gap: 14px; }
.card { display: block; text-decoration: none; color: inherit; }
.card:hover { border-color: var(--ms-navy-border); }
.nm { margin: 0; font-size: 15px; }
.res { display: flex; gap: 6px; flex-wrap: wrap; }
.tag { white-space: normal; }
.seatline .meter { margin-top: 4px; }
</style>
