<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — Matches panel on a profile (Step 13)
//  Location: resources/js/Components/Matches/MatchesPanel.vue
//  Used by: Pages/App/Beneficiaries/Show.vue
//
//  · every job and training the person was referred to: the current
//    stage (in the words of a Job or a Training), since when, "no change
//    for N days", "no longer eligible", and the full timeline
//    (stage, date, who, note)
//  · next stage / stop / back / restart            (matches.manage)
//  · "Suggested for this person": the open jobs and trainings whose
//    occupations fit the person's occupation, with the eligibility
//    result where it exists, and Check or Refer
// ══════════════════════════════════════════════════════════════════

import { computed, reactive, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import MatchActions from '@/Components/Matches/MatchActions.vue';
import MatchTimeline from '@/Components/Matches/MatchTimeline.vue';
import { usePermissions } from '@/composables/usePermissions';
import { useTranslations } from '@/composables/useTranslations';
import { formatDate } from '@/Utils/date';
import { KIND_ICON, RESULT_BADGE, sectionOf } from '@/Components/Assessments/elig';
import { FIT_BADGE, STAGE_BADGE, fitText, nextStage, stageShort, stageText, stopReasonText } from '@/Components/Matches/match';

const props = defineProps({
    number: { type: Number, required: true },
    name: { type: String, default: '' },
    items: { type: Array, default: () => [] },
    suggested: { type: Array, default: () => [] },
    stopReasons: { type: Object, default: () => ({}) },
});
const { t, locale } = useTranslations();
const { can } = usePermissions();
const date = (d) => (d ? formatDate(d, locale.value) : '');

const acts = ref(null);
const shown = reactive({});
const active = computed(() => props.items.filter((m) => m.status === 'active'));
const stopped = computed(() => props.items.filter((m) => m.status === 'stopped'));

// ── Suggested: Check (eligibility.check) or Refer (matches.manage) ─
const checking = ref(0);
const check = (o) => {
    checking.value = o.id;
    router.post(route('app.beneficiaries.eligibility', props.number), { opportunity_id: o.id }, { preserveScroll: true, onFinish: () => { checking.value = 0; } });
};
const refer = (o) => acts.value.refer({ opportunity_id: o.id, kind: o.kind, title: o.title, numbers: [props.number], names: [props.name], warnings: hiredNotes.value });
const hiredNotes = computed(() => active.value
    .filter((m) => (m.kind === 'job' && m.stage === 'done') || (m.kind === 'training' && m.stage === 'in_progress'))
    .map((m) => t(`mt.already_${m.kind === 'job' ? 'hired' : 'training'}`, { name: props.name, title: m.opportunity?.title || '' })));
</script>

<template>
    <div class="panel">
        <div class="panel-h">
            <div>
                <h3><AppIcon name="link" :size="16" />{{ t('mt.panel_title') }}</h3>
                <div class="sub">{{ t('mt.panel_sub') }}</div>
            </div>
            <Link v-if="items.length" :href="route('app.matches.index', { q: String(number) })" class="small">{{ t('mt.open_page') }}</Link>
        </div>

        <div v-if="!items.length" class="empty-row mt-2">{{ t('mt.panel_empty') }}</div>

        <div v-for="m in [...active, ...stopped]" :key="m.id" class="mres mt-3" :class="{ off: m.status === 'stopped' }">
            <div class="rtop">
                <div class="min0 grow">
                    <AppIcon :name="KIND_ICON[m.kind]" :size="14" class="kicon" />
                    <Link v-if="m.opportunity && can('opportunities.view')" :href="route(`app.${sectionOf(m.kind)}.show`, m.opportunity.id)" class="strong">{{ m.opportunity.title }}</Link>
                    <b v-else>{{ m.opportunity?.title }}</b>
                    <span class="tag plain ms">{{ t(`opp.kind_${m.kind}`) }}</span>
                    <span v-if="m.opportunity?.status === 'closed'" class="badge plain ms">{{ t('opp.status_closed') }}</span>
                    <div class="small mute">
                        <template v-if="m.opportunity?.employer || m.opportunity?.provider">{{ m.opportunity.employer || m.opportunity.provider }} · </template>
                        {{ t('mt.referred_by', { by: m.referred_by || '—', date: date(m.referred_on) }) }}
                    </div>
                </div>
                <span v-if="m.status === 'stopped'" class="badge plain">{{ t('mt.s_stopped') }}</span>
                <span v-else class="badge" :class="STAGE_BADGE[m.stage]">{{ stageText(m.kind, m.stage, t) }}</span>
            </div>

            <div class="small mt-2">
                <template v-if="m.status === 'stopped'">
                    {{ t('mt.stopped_at', { stage: stageText(m.kind, m.stage, t), date: date(m.stage_on) }) }} · {{ stopReasonText(m.kind, m.stop_reason, t) }}
                    <template v-if="m.stop_note"> — “{{ m.stop_note }}”</template>
                </template>
                <template v-else>{{ t('mt.since', { date: date(m.stage_on) }) }}</template>
            </div>
            <div v-if="m.follow_up" class="alert warning mt-2"><AppIcon name="alert" :size="14" /><span>{{ t('mt.follow_long', { n: m.days }) }}</span></div>
            <div v-if="m.no_longer_eligible" class="alert warning mt-2"><AppIcon name="alert" :size="14" /><span>{{ t('mt.no_longer_eligible_long', { result: t(`elig.r_${m.result}`) }) }}</span></div>

            <MatchTimeline v-if="shown[m.id]" class="mt-3" :kind="m.kind" :events="m.events || []" />

            <div class="acts mt-2">
                <button type="button" class="linkbtn" @click="shown[m.id] = !shown[m.id]">{{ shown[m.id] ? t('mt.hide_timeline') : t('mt.show_timeline', { n: (m.events || []).length }) }}</button>
                <template v-if="can('matches.manage')">
                    <template v-if="m.status === 'active'">
                        <button v-if="nextStage(m.stage)" type="button" class="linkbtn" @click="acts.open('move', m, m.opportunity?.title)">{{ t('mt.to_stage', { stage: stageShort(nextStage(m.stage), t, m.kind) }) }}</button>
                        <button v-if="m.stage !== 'done'" type="button" class="linkbtn" @click="acts.open('stop', m, m.opportunity?.title)">{{ t('mt.stop') }}</button>
                        <button v-if="m.stage !== 'referred'" type="button" class="linkbtn" @click="acts.open('back', m, m.opportunity?.title)">{{ t('mt.back') }}</button>
                    </template>
                    <button v-else-if="m.opportunity?.status === 'open'" type="button" class="linkbtn" @click="acts.open('restart', m, m.opportunity?.title)">{{ t('mt.restart') }}</button>
                </template>
            </div>
        </div>

        <!-- ── Suggested for this person ─────────────────────── -->
        <template v-if="suggested.length">
            <div class="kv-label mt-4 mb-2"><b>{{ t('mt.suggested') }}</b> <span class="small mute">· {{ t('mt.suggested_sub') }}</span></div>
            <div v-for="o in suggested" :key="o.id" class="srow">
                <AppIcon :name="KIND_ICON[o.kind]" :size="14" class="kicon" />
                <div class="min0 grow">
                    <Link v-if="can('opportunities.view')" :href="route(`app.${o.section}.show`, o.id)" class="strong">{{ o.title }}</Link>
                    <div class="xsmall mute">
                        {{ t(`opp.kind_${o.kind}`) }}<template v-if="o.employer || o.provider"> · {{ o.employer || o.provider }}</template>
                        <template v-if="o.deadline"> · {{ t('opp.deadline_on', { d: date(o.deadline) }) }}</template>
                    </div>
                </div>
                <span class="badge" :class="FIT_BADGE[o.fit.level]">{{ fitText(o.fit, t) }}</span>
                <span v-if="o.result" class="badge" :class="RESULT_BADGE[o.result]">{{ t(`elig.r_${o.result}`) }} · {{ o.score }}</span>
                <span v-else class="badge plain">{{ t('mt.not_checked') }}</span>
                <button v-if="o.result === 'eligible' && can('matches.manage')" type="button" class="btn btn-teal sm" @click="refer(o)">{{ t('mt.refer') }}</button>
                <button v-else-if="!o.result && can('matches.manage')" type="button" class="btn btn-line sm" @click="refer(o)" :title="t('mt.check_then_refer')">{{ t('mt.check_refer') }}</button>
                <button v-else-if="can('eligibility.check')" type="button" class="btn btn-line sm" :class="{ 'is-loading': checking === o.id }" :disabled="!!checking" @click="check(o)">{{ o.result ? t('elig.check_again_one') : t('elig.check') }}</button>
            </div>
        </template>

        <MatchActions ref="acts" :stop-reasons="stopReasons" />
    </div>
</template>

<style scoped>
.mres { border: 1px solid var(--ms-border); border-radius: var(--r-lg, 10px); padding: 12px 14px; }
.mres.off { opacity: .8; background: var(--ms-bg-hover); }
.rtop { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
.grow { flex: 1; }
.min0 { min-width: 0; }
.ms { margin-inline-start: 6px; }
.kicon { vertical-align: -2px; margin-inline-end: 5px; color: var(--ms-text-muted); }
.acts { display: flex; gap: 16px; flex-wrap: wrap; }
.acts .linkbtn { font-size: 12.5px; }
.srow { display: flex; gap: 10px; align-items: center; padding: 8px 0; border-top: 1px dashed var(--ms-border); flex-wrap: wrap; }
</style>
