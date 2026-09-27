<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — One Job / Training Program (Step 12)
//  Location: resources/js/Pages/App/Opportunities/Show.vue
//  Route: GET /app/{jobs|training}/{id} (opportunities.view) — App\OpportunityController@show
//
//  · the details (for a job, the salary range next to the Egypt market
//    wages of its occupations)
//  · its eligibility rules and result levels
//  · "Check everyone": the whole workspace (or, from the CV Bank, a
//    search result), a few hundred people at a time; leaving the page
//    and coming back continues it. After the rules change: "Check them again"
//  · the shortlist: Eligible first, sorted by score, filtered by result,
//    "needs a look"
//  · Edit, Copy, Close (with a reason), Open again, Delete (no results)
//  · its history
//  Step 13 (Matches):
//  · the shortlist shows each Eligible person's FIT (occupation and ESCO
//    skills — information only, never deciding), a Refer button, and tick
//    boxes to refer several at once; others say why they cannot be referred
//  · the Matches part: every person referred, grouped by stage, the seats
//    taken ("7 of 10"), next stage / stop / back / restart / timeline; when
//    all seats are taken, a warning and "Close as Filled"
// ══════════════════════════════════════════════════════════════════

import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Modal from '@/Components/Modal.vue';
import Pagination from '@/Components/Pagination.vue';
import { usePermissions } from '@/composables/usePermissions';
import { usePreferences } from '@/composables/usePreferences';
import { useTranslations } from '@/composables/useTranslations';
import { formatDate } from '@/Utils/date';
import { money, occIn } from '@/Components/Beneficiaries/ben';
import { editionPeriod } from '@/Components/Backbone/occ';
import { KIND_ICON, RESULT_BADGE, RESULTS, ruleText } from '@/Components/Assessments/elig';
import { costText, durationText, govList, occShown, salaryText } from '@/Components/Opportunities/opp';
import MatchActions from '@/Components/Matches/MatchActions.vue';
import { FIT_BADGE, STAGES, STAGE_BADGE, fitText, nextStage, skillTitle, skillsText, stageShort, stageText, stopReasonText } from '@/Components/Matches/match';

const props = defineProps({
    opportunity: { type: Object, required: true },
    market: { type: Object, default: null },
    counts: { type: Object, required: true },
    outdated: { type: Number, default: 0 },
    to_look: { type: Number, default: 0 },
    people: { type: Number, default: 0 },
    list: { type: Object, required: true },
    filters: { type: Object, required: true },
    run: { type: Object, default: null },
    options: { type: Object, default: () => ({}) },
    matches: { type: Array, default: null },           // Step 13 (null: no permission to see them)
    seats: { type: Object, default: () => ({ taken: 0, seats: 0, full: false }) },
    skills_asked: { type: Number, default: 0 },
    stop_reasons: { type: Array, default: () => [] },
});
const { t, locale } = useTranslations();
const { can } = usePermissions();
const { prefs } = usePreferences();

const o = computed(() => props.opportunity);
const sec = computed(() => o.value.section);
const open = computed(() => o.value.status === 'open');
const isJob = computed(() => o.value.kind === 'job');
const date = (d) => (d ? formatDate(d, locale.value) : '');
const when = (iso) => (iso ? formatDate(iso, locale.value, true) : '');

// ── Filters ───────────────────────────────────────────────────────
const f = reactive({ ...props.filters });
const load = () => router.get(route(`app.${sec.value}.show`, o.value.id),
    Object.fromEntries(Object.entries({ ...f, look: f.look ? 1 : '' }).filter(([k, v]) => v !== '' && !(k === 'sort' && v === 'score'))),
    { preserveState: true, preserveScroll: true, replace: true, only: ['list', 'filters'] });
let timer = null;
const onSearch = () => { clearTimeout(timer); timer = setTimeout(load, 350); };
const setResult = (r) => { f.result = r; load(); };

// ── Check everyone (a run moved forward by the browser) ───────────
const live = ref(props.run ? { ...props.run } : null);
watch(() => props.run, (r) => { if (r && (!live.value || r.id !== live.value.id || r.status !== live.value.status)) live.value = { ...r }; });
const running = computed(() => live.value?.status === 'running');
const pct = computed(() => (live.value?.total ? Math.round((live.value.done / live.value.total) * 100) : 0));
let stopped = false;
let driving = false;
async function drive() {
    if (driving) return;
    driving = true;
    while (!stopped && live.value?.status === 'running') {
        try {
            const { data } = await window.axios.post(route('app.eligibility.runs.step', live.value.id), { at: live.value.done, nonce: `${Date.now()}-${Math.random()}` });
            if (data.done === live.value.done && data.status === 'running') await new Promise((r) => setTimeout(r, 800));
            live.value = data;
        } catch {
            await new Promise((r) => setTimeout(r, 2000));   // a lost connection: try again
        }
    }
    driving = false;
    if (!stopped && live.value) router.reload({ only: ['list', 'counts', 'outdated', 'to_look', 'run'] });
}
onMounted(() => { if (running.value) drive(); });
onBeforeUnmount(() => { stopped = true; });
watch(running, (now, before) => { if (now && !before) drive(); });

const starting = ref(false);
const start = (scope) => {
    starting.value = true;
    router.post(route(`app.${sec.value}.runs.start`, o.value.id), { scope }, { preserveScroll: true, onFinish: () => { starting.value = false; } });
};

// ── Actions ───────────────────────────────────────────────────────
const confirming = ref('');
const closing = ref(false);
const closeReason = ref('');
const busy = ref(false);
const act = (what, data = {}) => {
    busy.value = true;
    const opts = { preserveScroll: true, onFinish: () => { confirming.value = ''; busy.value = false; closing.value = false; } };
    if (what === 'delete') router.delete(route(`app.${sec.value}.destroy`, o.value.id), opts);
    else router.post(route(`app.${sec.value}.${what}`, o.value.id), data, opts);
};

// ── Display ───────────────────────────────────────────────────────
const occText = (block) => {
    if (!block) return t('bank.no_occupation');
    const s = occIn(block, prefs.standard, locale.value);
    return `${s.code} · ${s.title}`;
};
const sname = (code) => {
    for (const s of props.options.sectors || []) {
        const x = s.subs.find((y) => y.code === code);
        if (x) return `${locale.value === 'ar' ? s.name_ar : s.name_en} › ${locale.value === 'ar' ? x.name_ar : x.name_en}`;
    }
    return '';
};
const details = computed(() => {
    const x = o.value;
    const rows = [
        [t('opp.governorates'), govList(x.governorates, t) + (x.city ? ` · ${x.city}` : '')],
        [t('opp.seats'), String(x.seats)],
        [t('opp.deadline'), x.deadline ? date(x.deadline) : ''],
        [t('opp.dates'), [x.starts_on && t('opp.from_date', { d: date(x.starts_on) }), x.ends_on && t('opp.to_date', { d: date(x.ends_on) })].filter(Boolean).join(' ')],
        [t('opp.contact'), x.contact ? x.contact.name : ''],
    ];
    if (isJob.value) {
        rows.splice(0, 0, [t('opp.employer'), x.employer || t('opp.no_employer')]);
        rows.push([t('opp.sector'), x.sub_sector ? sname(x.sub_sector) : ''], [t('opp.job_type'), x.job_type ? t(`ben.job_${x.job_type}`) : ''],
            [t('opp.salary'), salaryText(x, t, locale.value)]);
    } else {
        rows.splice(0, 0, [t('opp.provider'), x.provider]);
        rows.push([t('opp.duration'), durationText(x, t)], [t('opp.format'), x.format ? t(`opp.format_${x.format}`) : ''],
            [t('opp.cost'), costText(x, t, locale.value)], [t('opp.certificate'), x.certificate || '']);
    }
    return rows.filter(([, v]) => v);
});
const period = computed(() => (props.market?.edition ? editionPeriod(props.market.edition, t) : ''));
const BAR = { eligible: 'green', check: 'orange', not_eligible: 'danger' };

// ── Refer (Step 13) ───────────────────────────────────────────────
const acts = ref(null);
const canRefer = computed(() => open.value && can('matches.manage'));
const referable = (a) => a.person && a.result === 'eligible' && !a.match;
const picked = reactive({});
const pickedRows = computed(() => props.list.data.filter((a) => picked[a.id] && referable(a)));
const pickable = computed(() => props.list.data.filter(referable));
const allPicked = computed(() => pickable.value.length > 0 && pickable.value.every((a) => picked[a.id]));
const pickAll = () => { const on = !allPicked.value; pickable.value.forEach((a) => { picked[a.id] = on; }); };
watch(() => props.list, () => { Object.keys(picked).forEach((k) => delete picked[k]); });
const reminders = (rows) => rows.flatMap((a) => (a.elsewhere || []).map((e) => t(`mt.already_${e.kind === 'job' ? 'hired' : 'training'}`, { name: a.person.name, title: e.title })));
const refer = (rows) => acts.value.refer({
    opportunity_id: o.value.id, kind: o.value.kind, title: o.value.title,
    numbers: rows.map((a) => a.person.number), names: rows.map((a) => a.person.name), warnings: reminders(rows),
});
const fitOpen = reactive({});

// ── The Matches part ──────────────────────────────────────────────
const mlist = computed(() => props.matches || []);
const groups = computed(() => STAGES.map((st) => ({ stage: st, items: mlist.value.filter((m) => m.status === 'active' && m.stage === st) })));
const stoppedList = computed(() => mlist.value.filter((m) => m.status === 'stopped'));
const showStopped = ref(false);
const seatPct = computed(() => (props.seats.seats ? Math.min(100, Math.round((props.seats.taken / props.seats.seats) * 100)) : 0));
const followN = computed(() => mlist.value.filter((m) => m.follow_up).length);
</script>

<template>
    <AppLayout :title="o.title">
        <Link :href="route(`app.${sec}.index`)" class="back"><AppIcon name="arrow-left" :size="14" />{{ t(`nav.${sec === 'jobs' ? 'jobs' : 'training'}`) }}</Link>

        <div class="page-head">
            <div>
                <div class="page-eyebrow"><AppIcon :name="KIND_ICON[o.kind]" :size="13" /> {{ t(`opp.kind_${o.kind}`) }}</div>
                <h1 class="page-title">{{ o.title }}
                    <span class="badge" :class="open ? 'green' : 'plain'">{{ t(`opp.status_${o.status}`) }}<template v-if="!open && o.close_reason"> · {{ t(`opp.reason_${o.close_reason}`) }}</template></span>
                    <span v-if="o.deadline_passed" class="badge orange">{{ t('opp.deadline_passed') }}</span>
                </h1>
                <div class="page-sub occs">
                    <span v-for="x in o.occupations" :key="x.value" class="tag teal">{{ occShown(x, prefs.standard, locale) }}</span>
                </div>
            </div>
            <div v-if="can('opportunities.manage')" class="page-actions">
                <Link :href="route(`app.${sec}.edit`, o.id)" class="btn btn-line"><AppIcon name="edit" :size="14" />{{ t('common.edit') }}</Link>
                <button type="button" class="btn btn-line" :disabled="busy" @click="act('copy')"><AppIcon name="plus" :size="14" />{{ t('elig.copy') }}</button>
                <button v-if="open" type="button" class="btn btn-line" @click="closeReason = ''; closing = true"><AppIcon name="lock" :size="14" />{{ t('elig.close') }}</button>
                <button v-else type="button" class="btn btn-line" :disabled="busy" @click="act('reopen')"><AppIcon name="check" :size="14" />{{ t('elig.reopen') }}</button>
                <button v-if="!counts.total" type="button" class="btn btn-line c-danger" @click="confirming = 'delete'"><AppIcon name="trash" :size="14" />{{ t('common.delete') }}</button>
            </div>
        </div>

        <!-- ── Counts ───────────────────────────────────────────── -->
        <div class="grid-4">
            <button v-for="(r, i) in ['eligible', 'check', 'not_eligible', 'on_hold']" :key="r" type="button" class="stat-card sbtn"
                    :class="[['green', 'orange', 'danger', 'navy'][i], { on: f.result === r }]" @click="setResult(f.result === r ? '' : r)">
                <div class="stat-label">{{ t(`elig.r_${r}`) }}</div>
                <div class="stat-value">{{ counts[r] }}</div>
                <div class="stat-foot">{{ t('elig.of_checked', { n: counts.total }) }}</div>
            </button>
        </div>

        <!-- ── Details ──────────────────────────────────────────── -->
        <div class="panel mt-4">
            <h3><AppIcon :name="KIND_ICON[o.kind]" :size="16" />{{ t('opp.s_details') }}</h3>
            <p v-if="o.description" class="desc" dir="auto">{{ o.description }}</p>
            <dl class="kv mt-2">
                <template v-for="[k, v] in details" :key="k"><dt>{{ k }}</dt><dd dir="auto">{{ v }}</dd></template>
            </dl>
            <div v-if="isJob && market && market.groups.length" class="market mt-3">
                <div class="small"><b>{{ t('opp.market_title') }}</b></div>
                <ul>
                    <li v-for="g in market.groups" :key="g.code" class="small">
                        <bdi dir="ltr">{{ g.code }}</bdi> · {{ locale === 'ar' ? (g.title_ar || g.title_en) : g.title_en }}:
                        <template v-if="g.avg">{{ t('opp.market_avg', { n: money(g.avg, locale) }) }}</template>
                        <template v-if="g.avg && g.private"> · </template>
                        <template v-if="g.private">{{ t('opp.market_private', { n: money(g.private, locale) }) }}</template>
                    </li>
                </ul>
                <div class="xsmall mute">{{ t('opp.market_note', { period }) }}</div>
            </div>
        </div>

        <div class="grid-2 mt-4" style="align-items:start">
            <!-- ── Eligibility rules ────────────────────────────── -->
            <div class="panel">
                <div class="panel-h">
                    <h3><AppIcon name="check-circle" :size="16" />{{ t('opp.s_eligibility') }}</h3>
                    <Link v-if="can('opportunities.manage')" :href="route(`app.${sec}.edit`, o.id)" class="small">{{ t('common.edit') }}</Link>
                </div>
                <div class="band mt-2" aria-hidden="true">
                    <i class="b-no" :style="`width:${o.check_from}%`" /><i class="b-check" :style="`width:${o.eligible_from - o.check_from}%`" /><i class="b-yes" :style="`width:${100 - o.eligible_from}%`" />
                </div>
                <div class="bandtxt">
                    <span><i class="dot b-yes" />{{ t('elig.band_eligible', { from: o.eligible_from }) }}</span>
                    <span v-if="o.eligible_from > o.check_from"><i class="dot b-check" />{{ t('elig.band_check', { from: o.check_from, to: o.eligible_from - 1 }) }}</span>
                    <span v-if="o.check_from > 0"><i class="dot b-no" />{{ t('elig.band_no', { to: o.check_from - 1 }) }}</span>
                </div>
                <ul class="rlist mt-3">
                    <li v-for="(r, i) in o.rules" :key="i">
                        <span class="badge" :class="r.mode === 'must' ? 'navy' : 'teal'">{{ r.mode === 'must' ? t('elig.must') : t('elig.pts_n', { n: r.points }) }}</span>
                        <span>{{ ruleText(r, t, locale) }}</span>
                    </li>
                </ul>
            </div>

            <!-- ── Check everyone ───────────────────────────────── -->
            <div class="panel acc acc-teal">
                <h3><AppIcon name="users" :size="16" />{{ t('elig.s_everyone') }}</h3>
                <template v-if="running">
                    <div class="sub">{{ t(`elig.running_${live.scope}`) }}</div>
                    <div class="meter lg mt-3"><i :style="`width:${pct}%;--c:var(--ms-teal)`" /></div>
                    <div class="small mt-2"><b>{{ t('elig.progress', { done: live.done, total: live.total }) }}</b> · {{ t('elig.keep_open') }}</div>
                </template>
                <template v-else>
                    <div class="sub">{{ t('opp.everyone_sub') }}</div>
                    <div v-if="outdated && open" class="alert warning mt-3">
                        <AppIcon name="alert" :size="16" />
                        <span class="grow">{{ t('elig.outdated', { n: outdated }) }}</span>
                        <button v-if="can('eligibility.check')" type="button" class="btn btn-orange sm" :disabled="starting" @click="start('outdated')">{{ t('elig.check_again') }}</button>
                    </div>
                    <div v-if="!open" class="alert mt-3"><AppIcon name="lock" :size="16" /><span>{{ t('opp.closed_note') }}</span></div>
                    <div v-else-if="can('eligibility.check')" class="mt-3 flex gap-2 wrap">
                        <button type="button" class="btn btn-teal" :class="{ 'is-loading': starting }" :disabled="starting || !people" @click="start('all')">
                            <AppIcon name="users" :size="15" />{{ t('elig.check_all', { n: people }) }}
                        </button>
                        <Link :href="route('app.cv-bank.index')" class="btn btn-line"><AppIcon name="search" :size="15" />{{ t('elig.from_bank') }}</Link>
                    </div>
                    <p v-if="open" class="small mute mt-2">{{ t('opp.from_bank_hint') }}</p>
                    <div v-if="live" class="lastrun mt-3">
                        <div class="small"><b>{{ t('elig.last_run') }}</b> · {{ t(`elig.scope_${live.scope}`) }} · {{ t('elig.run_by', { by: live.by || '—', date: when(live.finished_at || live.at) }) }}</div>
                        <div class="res mt-2">
                            <span class="badge plain">{{ t('elig.checked_n', { n: live.done }) }}</span>
                            <span v-for="r in RESULTS" v-show="live.counts[r]" :key="r" class="badge" :class="RESULT_BADGE[r]">{{ t(`elig.r_${r}`) }} · {{ live.counts[r] }}</span>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- ── Matches (Step 13) ───────────────────────────────── -->
        <template v-if="matches">
            <div class="section-label">{{ t('mt.part_title') }}</div>
            <div class="panel">
                <div class="panel-h">
                    <div>
                        <h3><AppIcon name="link" :size="16" />{{ t('mt.part_title') }} · {{ mlist.length }}</h3>
                        <div class="sub">{{ t(`mt.part_sub_${o.kind}`) }}</div>
                    </div>
                    <div class="seatbox">
                        <div class="small"><b>{{ t('mt.seats_taken', { taken: seats.taken, seats: seats.seats }) }}</b></div>
                        <div class="meter"><i :style="`width:${seatPct}%;--c:var(--ms-${seats.full ? 'orange' : 'teal'})`" /></div>
                    </div>
                </div>
                <div v-if="seats.full && open" class="alert warning mt-3">
                    <AppIcon name="alert" :size="16" />
                    <span class="grow">{{ t('mt.seats_full', { taken: seats.taken, seats: seats.seats }) }}</span>
                    <button v-if="can('opportunities.manage')" type="button" class="btn btn-orange sm" :disabled="busy" @click="act('close', { reason: 'filled' })">{{ t('mt.close_filled') }}</button>
                </div>
                <div v-if="!open && mlist.length" class="small mute mt-2"><AppIcon name="lock" :size="12" /> {{ t('mt.closed_continue') }}</div>

                <div class="stagecounts mt-3">
                    <span v-for="g in groups" :key="g.stage" class="badge" :class="g.items.length ? STAGE_BADGE[g.stage] : 'plain'">{{ stageShort(g.stage, t, o.kind) }} · {{ g.items.length }}</span>
                    <span class="badge plain">{{ t('mt.s_stopped') }} · {{ stoppedList.length }}</span>
                    <span v-if="followN" class="badge orange"><AppIcon name="alert" :size="11" /> {{ t('mt.follow_n', { n: followN }) }}</span>
                </div>

                <div v-if="!mlist.length" class="empty-row mt-3">{{ t(`mt.none_yet_${o.kind}`) }}</div>
                <template v-for="g in groups" :key="g.stage">
                    <div v-if="g.items.length" class="mgroup mt-3">
                        <div class="mgh"><span class="badge" :class="STAGE_BADGE[g.stage]">{{ stageText(o.kind, g.stage, t) }}</span> <span class="small mute">{{ g.items.length }}</span></div>
                        <div v-for="m in g.items" :key="m.id" class="mline">
                            <span class="avatar sm">{{ m.person?.initials }}</span>
                            <div class="min0 grow">
                                <Link v-if="m.person" :href="route('app.beneficiaries.show', m.person.number)" class="strong">{{ m.person.name }}</Link>
                                <div class="small mute truncate">
                                    <bdi dir="ltr">#{{ m.person?.number }}</bdi> · {{ occText(m.person?.occupation) }} · {{ t('mt.since', { date: date(m.stage_on) }) }}
                                </div>
                                <div class="flags">
                                    <span v-if="m.follow_up" class="xsmall c-orange"><AppIcon name="alert" :size="11" /> {{ t('mt.no_change_days', { n: m.days }) }}</span>
                                    <span v-if="m.no_longer_eligible" class="xsmall c-danger"><AppIcon name="alert" :size="11" /> {{ t('mt.no_longer_eligible', { result: t(`elig.r_${m.result}`) }) }}</span>
                                </div>
                            </div>
                            <div class="macts">
                                <template v-if="can('matches.manage')">
                                    <button v-if="nextStage(m.stage)" type="button" class="btn btn-teal sm" @click="acts.open('move', m, m.person?.name)">
                                        {{ t('mt.to_stage', { stage: stageShort(nextStage(m.stage), t, o.kind) }) }}
                                    </button>
                                    <button v-if="m.stage !== 'done'" type="button" class="btn btn-line sm" @click="acts.open('stop', m, m.person?.name)">{{ t('mt.stop') }}</button>
                                    <button v-if="m.stage !== 'referred'" type="button" class="linkbtn small" @click="acts.open('back', m, m.person?.name)">{{ t('mt.back') }}</button>
                                </template>
                                <button type="button" class="linkbtn small" @click="acts.open('timeline', m, m.person?.name)">{{ t('mt.timeline') }}</button>
                            </div>
                        </div>
                    </div>
                </template>
                <div v-if="stoppedList.length" class="mt-3">
                    <button type="button" class="linkbtn small" @click="showStopped = !showStopped">
                        {{ showStopped ? t('mt.hide_stopped') : t('mt.show_stopped', { n: stoppedList.length }) }}
                    </button>
                    <div v-if="showStopped" class="mgroup mt-2">
                        <div v-for="m in stoppedList" :key="m.id" class="mline">
                            <span class="avatar sm">{{ m.person?.initials }}</span>
                            <div class="min0 grow">
                                <Link v-if="m.person" :href="route('app.beneficiaries.show', m.person.number)" class="strong">{{ m.person.name }}</Link>
                                <div class="small mute">
                                    {{ t('mt.stopped_at', { stage: stageText(o.kind, m.stage, t), date: date(m.stage_on) }) }} · {{ stopReasonText(o.kind, m.stop_reason, t) }}
                                    <template v-if="m.stop_note"> — “{{ m.stop_note }}”</template>
                                </div>
                            </div>
                            <div class="macts">
                                <button v-if="can('matches.manage') && open" type="button" class="btn btn-line sm" @click="acts.open('restart', m, m.person?.name)">{{ t('mt.restart') }}</button>
                                <button type="button" class="linkbtn small" @click="acts.open('timeline', m, m.person?.name)">{{ t('mt.timeline') }}</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        <!-- ── The shortlist ────────────────────────────────────── -->
        <div class="section-label">{{ t('opp.s_shortlist') }}</div>
        <div class="toolbar mb-3">
            <div class="seg" role="group">
                <button type="button" :aria-pressed="!f.result" @click="setResult('')">{{ t('common.all') }} · {{ counts.total }}</button>
                <button v-for="r in RESULTS" :key="r" type="button" :aria-pressed="f.result === r" @click="setResult(r)">{{ t(`elig.r_${r}`) }}</button>
            </div>
            <button type="button" class="chip" :aria-pressed="!!f.look" @click="f.look = !f.look; load()">
                <AppIcon name="alert" :size="12" /> {{ t('elig.needs_look', { n: to_look + outdated }) }}
            </button>
            <select v-model="f.sort" class="inp sel" :aria-label="t('elig.sort')" @change="load">
                <option value="score">{{ t('elig.sort_score') }}</option>
                <option v-if="matches" value="fit">{{ t('mt.sort_fit') }}</option>
                <option value="recent">{{ t('elig.sort_recent') }}</option>
            </select>
            <input v-model="f.q" type="search" class="inp grow" :placeholder="t('elig.search_ph')" @input="onSearch">
            <button v-if="canRefer && pickedRows.length" type="button" class="btn btn-primary sm" @click="refer(pickedRows)">
                <AppIcon name="link" :size="14" />{{ t('mt.refer_selected', { n: pickedRows.length }) }}
            </button>
        </div>
        <p v-if="matches && list.data.length" class="xsmall mute mb-2">{{ t('mt.fit_note') }}<template v-if="!skills_asked"> {{ t('mt.no_job_skills_note') }}</template></p>

        <EmptyState v-if="!list.data.length" icon="clipboard" :title="t(counts.total ? 'elig.none_found' : 'elig.no_results_title')"
                    :text="t(counts.total ? 'elig.none_found_text' : 'elig.no_results')" />
        <div v-else class="table-wrap compact">
            <table>
                <thead>
                    <tr>
                        <th v-if="canRefer" class="ck"><input type="checkbox" :checked="allPicked" :disabled="!pickable.length" :aria-label="t('mt.pick_all')" @change="pickAll"></th>
                        <th>{{ t('elig.col_person') }}</th>
                        <th>{{ t('elig.col_score') }}</th>
                        <th>{{ t('elig.col_result') }}</th>
                        <th v-if="matches">{{ t('mt.col_fit') }}</th>
                        <th v-if="matches">{{ t('mt.col_match') }}</th>
                        <th>{{ t('elig.col_checked') }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="a in list.data" :key="a.id" class="clickable" @click="a.person && router.visit(route('app.beneficiaries.show', a.person.number))">
                        <td v-if="canRefer" class="ck" @click.stop>
                            <input v-if="referable(a)" v-model="picked[a.id]" type="checkbox" :aria-label="t('mt.pick', { name: a.person.name })">
                        </td>
                        <td>
                            <div v-if="a.person" class="name-cell">
                                <span class="avatar sm">{{ a.person.initials }}</span>
                                <div class="min0">
                                    <Link :href="route('app.beneficiaries.show', a.person.number)" class="strong" @click.stop>{{ a.person.name }}</Link>
                                    <div class="small mute truncate">
                                        <bdi dir="ltr">#{{ a.person.number }}</bdi> · {{ occText(a.person.occupation) }}
                                        · {{ t(`gov.${a.person.governorate}`) }}<template v-if="a.person.age !== null"> · {{ t('ben.age', { n: a.person.age }) }}</template>
                                    </div>
                                </div>
                            </div>
                        </td>
                        <td class="score">
                            <b class="num">{{ a.score }}</b>
                            <div class="meter sm"><i :style="`width:${a.score}%;--c:var(--ms-${BAR[a.auto]})`" /></div>
                        </td>
                        <td>
                            <span class="badge" :class="RESULT_BADGE[a.result]">{{ t(`elig.r_${a.result}`) }}</span>
                            <div v-if="a.decision" class="xsmall mute mt-1">{{ t('elig.by_decision', { auto: t(`elig.r_${a.auto}`) }) }}</div>
                            <div v-if="a.flag" class="xsmall c-orange mt-1"><AppIcon name="alert" :size="11" /> {{ t(`elig.flag_${a.flag}`) }}</div>
                            <div v-else-if="a.outdated" class="xsmall c-orange mt-1"><AppIcon name="alert" :size="11" /> {{ t('elig.old_rules') }}</div>
                            <div v-if="a.must_failed" class="xsmall c-danger mt-1">{{ t('elig.must_failed_n', { n: a.must_failed }) }}</div>
                            <div v-if="a.missing" class="xsmall c-orange mt-1">{{ t('elig.missing_n', { n: a.missing }) }}</div>
                        </td>
                        <td v-if="matches" class="fit" @click.stop>
                            <template v-if="a.fit">
                                <span class="badge" :class="FIT_BADGE[a.fit.occupation.level]">{{ fitText(a.fit.occupation, t) }}</span>
                                <div class="xsmall mt-1">
                                    <button v-if="a.fit.skills.state !== 'no_job_skills'" type="button" class="linkbtn xsmall" @click="fitOpen[a.id] = !fitOpen[a.id]">{{ skillsText(a.fit.skills, t) }}</button>
                                    <span v-else class="mute">{{ skillsText(a.fit.skills, t) }}</span>
                                </div>
                                <div v-if="fitOpen[a.id]" class="skl xsmall mt-1">
                                    <div v-if="a.fit.skills.found.length"><b class="c-green">✓</b> {{ a.fit.skills.found.map((x) => skillTitle(x, locale)).join(' · ') }}</div>
                                    <div v-if="a.fit.skills.lacking.length" class="mute"><b>–</b> {{ a.fit.skills.lacking.map((x) => skillTitle(x, locale)).join(' · ') }}</div>
                                </div>
                            </template>
                        </td>
                        <td v-if="matches" class="mcell" @click.stop>
                            <template v-if="a.match">
                                <span class="badge" :class="a.match.status === 'stopped' ? 'plain' : STAGE_BADGE[a.match.stage]">
                                    {{ a.match.status === 'stopped' ? t('mt.s_stopped') : stageShort(a.match.stage, t, o.kind) }}
                                </span>
                            </template>
                            <template v-else-if="a.result === 'eligible'">
                                <button v-if="canRefer" type="button" class="btn btn-teal sm" @click="refer([a])">{{ t('mt.refer') }}</button>
                                <span v-else-if="!open" class="xsmall mute">{{ t('mt.closed_no_refer') }}</span>
                            </template>
                            <template v-else-if="canRefer">
                                <span class="xsmall mute">{{ t('mt.only_eligible') }}</span>
                                <Link v-if="can('eligibility.decide') && a.person" :href="route('app.beneficiaries.show', a.person.number) + '#eligibility'" class="xsmall d-block">{{ t('elig.change_result') }}</Link>
                            </template>
                            <div v-for="(e, i) in a.elsewhere" :key="i" class="xsmall c-orange mt-1">
                                {{ t(`mt.already_${e.kind === 'job' ? 'hired' : 'training'}_short`, { title: e.title }) }}
                            </div>
                        </td>
                        <td class="small mute">{{ when(a.checked_at) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <Pagination v-if="list.data.length" class="mt-4" :paginator="list" />

        <!-- ── History ──────────────────────────────────────────── -->
        <div class="panel mt-4">
            <h3><AppIcon name="activity" :size="16" />{{ t('opp.s_history') }}</h3>
            <ul class="hist mt-2">
                <li v-for="(h, i) in o.history" :key="i" class="small">
                    <b>{{ t(`opp.h_${h.action}`, { reason: h.reason ? t(`opp.reason_${h.reason}`) : '', from: h.from || '' }) }}</b>
                    <span class="mute"> · {{ h.by || '—' }}, {{ when(h.at) }}</span>
                </li>
            </ul>
        </div>

        <!-- ── Close, with a reason ─────────────────────────────── -->
        <Modal :show="closing" size="sm" :title="t('opp.close_title')" @close="closing = false">
            <p class="small mute">{{ t('opp.close_sub') }}</p>
            <div class="opts mt-3">
                <label v-for="r in options.choices?.close_reasons || []" :key="r" class="opt">
                    <input v-model="closeReason" type="radio" :value="r"><span><b>{{ t(`opp.reason_${r}`) }}</b> — {{ t(`opp.reason_${r}_hint`) }}</span>
                </label>
            </div>
            <template #footer>
                <button type="button" class="btn btn-line" @click="closing = false">{{ t('common.cancel') }}</button>
                <button type="button" class="btn btn-primary" :class="{ 'is-loading': busy }" :disabled="!closeReason || busy" @click="act('close', { reason: closeReason })">{{ t('elig.close') }}</button>
            </template>
        </Modal>
        <ConfirmDialog :show="confirming === 'delete'" danger :message="t('opp.delete_confirm')" :confirm-label="t('common.delete')" @confirm="act('delete')" @close="confirming = ''" />
        <MatchActions ref="acts" :stop-reasons="stop_reasons" />
    </AppLayout>
</template>

<style scoped>
.page-title .badge { vertical-align: middle; margin-inline-start: 8px; }
.occs { display: flex; gap: 6px; flex-wrap: wrap; margin-top: 6px; }
.tag { white-space: normal; }
.sbtn { text-align: start; cursor: pointer; font: inherit; }
.stat-card.danger { --accent: var(--ms-danger); --accent-border: var(--ms-danger-border); }
.sbtn.on { box-shadow: var(--ms-ring); }
.desc { white-space: pre-wrap; font-size: 13.5px; margin: 6px 0 0; }
.kv { display: grid; grid-template-columns: max-content 1fr; gap: 6px 18px; margin: 0; font-size: 13px; }
.kv dt { color: var(--ms-text-muted); }
.kv dd { margin: 0; }
.market { border-top: 1px dashed var(--ms-border); padding-top: 10px; }
.market ul { margin: 6px 0; padding-inline-start: 18px; }
.band { display: flex; height: 10px; border-radius: 5px; overflow: hidden; background: var(--ms-bg-hover); }
.band i { display: block; height: 100%; }
.b-yes { background: var(--ms-green); } .b-check { background: var(--ms-orange); } .b-no { background: var(--ms-danger); }
.bandtxt { display: flex; gap: 14px; flex-wrap: wrap; font-size: 12px; margin-top: 8px; }
.bandtxt .dot { display: inline-block; width: 9px; height: 9px; border-radius: 3px; margin-inline-end: 5px; vertical-align: -1px; }
.rlist { list-style: none; margin: 0; padding: 0; display: grid; gap: 8px; }
.rlist li { display: flex; gap: 10px; align-items: flex-start; font-size: 13px; }
.rlist .badge { min-width: 72px; justify-content: center; }
.res { display: flex; gap: 6px; flex-wrap: wrap; }
.lastrun { border-top: 1px dashed var(--ms-border); padding-top: 10px; }
.wrap { flex-wrap: wrap; }
.toolbar { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
.toolbar .sel { width: auto; min-width: 150px; }
.toolbar .grow { flex: 1; min-width: 180px; max-width: 320px; }
.score { min-width: 90px; }
.score .meter { margin-top: 4px; max-width: 90px; }
.clickable { cursor: pointer; }
.min0 { min-width: 0; }
.hist { list-style: none; margin: 0; padding: 0; display: grid; gap: 6px; }
.opts { display: grid; gap: 8px; }
.opt { display: flex; gap: 8px; align-items: flex-start; font-size: 13px; cursor: pointer; }
.seatbox { min-width: 180px; }
.seatbox .meter { margin-top: 6px; }
.stagecounts { display: flex; gap: 6px; flex-wrap: wrap; }
.mgroup { border: 1px solid var(--ms-border); border-radius: var(--r-lg, 10px); padding: 4px 12px; }
.mgh { padding: 8px 0 4px; }
.mline { display: flex; gap: 10px; align-items: center; padding: 8px 0; border-top: 1px dashed var(--ms-border); flex-wrap: wrap; }
.mgh + .mline { border-top: 0; }
.mgroup > .mline:first-child { border-top: 0; }
.mline .grow { flex: 1; }
.flags { display: flex; gap: 10px; flex-wrap: wrap; }
.macts { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.ck { width: 34px; }
.fit { min-width: 150px; max-width: 240px; }
.skl { background: var(--ms-bg-hover); border-radius: 6px; padding: 6px 8px; display: grid; gap: 4px; }
.mcell { min-width: 120px; }
.d-block { display: block; }
@media (max-width: 640px) { .kv { grid-template-columns: 1fr; } .kv dd { margin-bottom: 6px; } }
</style>
