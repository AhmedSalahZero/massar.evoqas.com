<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — Eligibility panel on a profile (Step 11)
//  Location: resources/js/Components/Assessments/EligibilityPanel.vue
//  Used by: Pages/App/Beneficiaries/Show.vue
//
//  Every job and training the person was checked against: the result, the score,
//  every rule with ✓ ✗ ? – and what the profile holds, the case
//  worker's decision (with its reason, who and when), a warning when
//  the profile or the rules changed since, and notes.
//    · "Check against a job or training"   (eligibility.check)
//    · "Change the result"                 (eligibility.decide) — a reason is required
//    · "Notes"                             (eligibility.decide)
// ══════════════════════════════════════════════════════════════════

import { computed, reactive, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import Modal from '@/Components/Modal.vue';
import { usePermissions } from '@/composables/usePermissions';
import { useTranslations } from '@/composables/useTranslations';
import { formatDate } from '@/Utils/date';
import { KIND_ICON, RESULT_BADGE, ruleText, sectionOf, STATUS, valueText } from '@/Components/Assessments/elig';

const props = defineProps({
    number: { type: Number, required: true },
    items: { type: Array, default: () => [] },
    opportunities: { type: Array, default: () => [] },   // the open jobs and trainings
});
const { t, locale } = useTranslations();
const { can } = usePermissions();
const when = (iso) => (iso ? formatDate(iso, locale.value, true) : '');

// ── Check against a job or training ───────────────────────────────
const chosenId = ref('');
const checking = ref(false);
const checkAgainst = (id) => {
    if (!id) return;
    checking.value = true;
    router.post(route('app.beneficiaries.eligibility', props.number), { opportunity_id: id }, {
        preserveScroll: true, onFinish: () => { checking.value = false; chosenId.value = ''; },
    });
};
const openIds = computed(() => new Set(props.opportunities.map((p) => p.id)));
const jobs = computed(() => props.opportunities.filter((o) => o.kind === 'job'));
const trainings = computed(() => props.opportunities.filter((o) => o.kind === 'training'));

// ── Reasons shown / hidden ────────────────────────────────────────
const open = reactive({});

// ── Decide ────────────────────────────────────────────────────────
const deciding = ref(null);
const decideForm = useForm({ decision: '', reason: '' });
const openDecide = (a) => { decideForm.reset(); decideForm.clearErrors(); decideForm.decision = a.decision || ''; deciding.value = a; };
const decide = () => decideForm.post(route('app.eligibility.decide', deciding.value.id), { preserveScroll: true, onSuccess: () => { deciding.value = null; } });

// ── Notes ─────────────────────────────────────────────────────────
const noting = ref(null);
const notesForm = useForm({ notes: '' });
const openNotes = (a) => { notesForm.notes = a.notes || ''; notesForm.clearErrors(); noting.value = a; };
const saveNotes = () => notesForm.patch(route('app.eligibility.notes', noting.value.id), { preserveScroll: true, onSuccess: () => { noting.value = null; } });
</script>

<template>
    <div class="panel">
        <div class="panel-h">
            <div>
                <h3><AppIcon name="clipboard" :size="16" />{{ t('elig.panel_title') }}</h3>
                <div class="sub">{{ t('elig.panel_sub') }}</div>
            </div>
            <div v-if="can('eligibility.check') && opportunities.length" class="checkbar">
                <select v-model="chosenId" class="inp" :aria-label="t('elig.choose_opportunity')">
                    <option value="">{{ t('elig.choose_opportunity') }}</option>
                    <optgroup v-if="jobs.length" :label="t('nav.jobs')">
                        <option v-for="o in jobs" :key="o.id" :value="o.id">{{ o.title }}</option>
                    </optgroup>
                    <optgroup v-if="trainings.length" :label="t('nav.training')">
                        <option v-for="o in trainings" :key="o.id" :value="o.id">{{ o.title }}</option>
                    </optgroup>
                </select>
                <button type="button" class="btn btn-teal sm" :class="{ 'is-loading': checking }" :disabled="!chosenId || checking" @click="checkAgainst(chosenId)">{{ t('elig.check') }}</button>
            </div>
        </div>

        <div v-if="!items.length" class="empty-row mt-2">
            {{ t('elig.panel_empty') }}
            <template v-if="can('opportunities.manage') && !opportunities.length">
                <Link :href="route('app.jobs.create')">{{ t('opp.new_job') }}</Link> · <Link :href="route('app.training.create')">{{ t('opp.new_training') }}</Link>
            </template>
        </div>

        <div v-for="a in items" :key="a.id" class="res mt-3">
            <div class="rtop">
                <div class="min0 grow">
                    <AppIcon v-if="a.opportunity" :name="KIND_ICON[a.opportunity.kind]" :size="14" class="kicon" />
                    <Link v-if="can('opportunities.view') && a.opportunity" :href="route(`app.${sectionOf(a.opportunity.kind)}.show`, a.opportunity.id)" class="strong">{{ a.opportunity.title }}</Link>
                    <span v-if="a.opportunity" class="tag plain ms">{{ t(`opp.kind_${a.opportunity.kind}`) }}</span>
                    <span v-if="a.opportunity?.status === 'closed'" class="badge plain ms">{{ t('opp.status_closed') }}</span>
                    <div class="small mute">{{ t('elig.checked_by', { by: a.checked_by || '—', date: when(a.checked_at) }) }}</div>
                </div>
                <div class="scorebox">
                    <b class="num">{{ a.score }}</b><span class="mute small">/100</span>
                </div>
                <span class="badge" :class="RESULT_BADGE[a.result]">{{ t(`elig.r_${a.result}`) }}</span>
            </div>

            <div v-if="a.decision" class="decision mt-2">
                <AppIcon name="user" :size="13" />
                <span>
                    <b>{{ t('elig.decided', { result: t(`elig.r_${a.decision}`), by: a.decided_by || '—', date: when(a.decided_at) }) }}</b>
                    — “{{ a.decision_reason }}”
                    <span class="mute"> · {{ t('elig.auto_was', { result: t(`elig.r_${a.auto_result}`) }) }}</span>
                </span>
            </div>
            <div v-else-if="a.decided_at" class="small mute mt-2">{{ t('elig.back_to_auto', { by: a.decided_by || '—', date: when(a.decided_at) }) }} — “{{ a.decision_reason }}”</div>
            <div v-if="a.flag" class="alert warning mt-2"><AppIcon name="alert" :size="14" /><span>{{ t(`elig.flag_${a.flag}_long`) }}</span></div>
            <div v-else-if="a.outdated" class="alert warning mt-2"><AppIcon name="alert" :size="14" /><span>{{ t('elig.old_rules_long') }}</span></div>
            <div v-if="a.notes" class="notes mt-2"><AppIcon name="edit" :size="12" /> {{ a.notes }} <span class="mute xsmall">· {{ a.notes_by }}, {{ when(a.notes_at) }}</span></div>

            <ul v-if="open[a.id]" class="reasons mt-2">
                <li v-for="(r, i) in a.reasons" :key="i">
                    <span class="mark" :class="STATUS[r.status].cls">{{ STATUS[r.status].mark }}</span>
                    <span class="grow">
                        {{ ruleText(r.rule, t, locale) }}
                        <span class="mute"> — {{ valueText(r, t, locale) }}</span>
                    </span>
                    <span class="badge" :class="r.rule.mode === 'must' ? 'navy' : 'teal'">{{ r.rule.mode === 'must' ? t('elig.must') : (r.status === 'pass' || r.status === 'na' ? `+${r.rule.points}` : `0 / ${r.rule.points}`) }}</span>
                </li>
            </ul>

            <div class="acts mt-2">
                <button type="button" class="linkbtn" @click="open[a.id] = !open[a.id]">{{ open[a.id] ? t('elig.hide_reasons') : t('elig.show_reasons', { n: a.reasons.length }) }}</button>
                <button v-if="can('eligibility.check') && a.opportunity && openIds.has(a.opportunity.id)" type="button" class="linkbtn" :disabled="checking" @click="checkAgainst(a.opportunity.id)">{{ t('elig.check_again_one') }}</button>
                <button v-if="can('eligibility.decide')" type="button" class="linkbtn" @click="openDecide(a)">{{ t('elig.change_result') }}</button>
                <button v-if="can('eligibility.decide')" type="button" class="linkbtn" @click="openNotes(a)">{{ a.notes ? t('elig.edit_notes') : t('elig.add_notes') }}</button>
            </div>
        </div>

        <!-- ── Change the result ─────────────────────────────── -->
        <Modal :show="!!deciding" size="md" :title="t('elig.change_result')" @close="deciding = null">
            <template v-if="deciding">
                <p class="small mute">{{ t('elig.decide_sub', { program: deciding.opportunity?.title || '—', result: t(`elig.r_${deciding.auto_result}`), score: deciding.score }) }}</p>
                <div class="opts mt-3">
                    <label v-for="d in ['eligible', 'not_eligible', 'on_hold']" :key="d" class="opt">
                        <input v-model="decideForm.decision" type="radio" :value="d">
                        <span class="badge" :class="RESULT_BADGE[d]">{{ t(`elig.r_${d}`) }}</span>
                    </label>
                    <label v-if="deciding.decision" class="opt">
                        <input v-model="decideForm.decision" type="radio" value="auto">
                        <span>{{ t('elig.use_auto', { result: t(`elig.r_${deciding.auto_result}`) }) }}</span>
                    </label>
                </div>
                <div v-if="decideForm.errors.decision" class="fld-error mt-1">{{ decideForm.errors.decision }}</div>
                <label class="fld mt-3" :class="{ 'has-error': decideForm.errors.reason }">
                    <span class="fl"><span>{{ t('elig.reason') }}<span class="req">*</span></span></span>
                    <textarea v-model="decideForm.reason" rows="3" maxlength="500" dir="auto" :placeholder="t('elig.reason_ph')" />
                    <small>{{ t('elig.reason_hint') }}</small>
                    <span v-if="decideForm.errors.reason" class="fld-error">{{ decideForm.errors.reason }}</span>
                </label>
            </template>
            <template #footer>
                <button type="button" class="btn btn-line" @click="deciding = null">{{ t('common.cancel') }}</button>
                <button type="button" class="btn btn-primary" :class="{ 'is-loading': decideForm.processing }"
                        :disabled="decideForm.processing || !decideForm.decision || !decideForm.reason.trim()" @click="decide">{{ t('elig.save_decision') }}</button>
            </template>
        </Modal>

        <!-- ── Notes ─────────────────────────────────────────── -->
        <Modal :show="!!noting" size="md" :title="t('elig.notes')" @close="noting = null">
            <label class="fld">
                <textarea v-model="notesForm.notes" rows="4" maxlength="2000" dir="auto" :placeholder="t('elig.notes_ph')" />
                <span v-if="notesForm.errors.notes" class="fld-error">{{ notesForm.errors.notes }}</span>
            </label>
            <template #footer>
                <button type="button" class="btn btn-line" @click="noting = null">{{ t('common.cancel') }}</button>
                <button type="button" class="btn btn-primary" :class="{ 'is-loading': notesForm.processing }" :disabled="notesForm.processing" @click="saveNotes">{{ t('common.save') }}</button>
            </template>
        </Modal>
    </div>
</template>

<style scoped>
.checkbar { display: flex; gap: 8px; align-items: center; flex-wrap: nowrap; }
.checkbar select { min-width: 220px; max-width: 300px; }
.res { border: 1px solid var(--ms-border); border-radius: var(--r-lg, 10px); padding: 12px 14px; }
.rtop { display: flex; gap: 12px; align-items: center; flex-wrap: wrap; }
.grow { flex: 1; }
.min0 { min-width: 0; }
.ms { margin-inline-start: 6px; }
.kicon { vertical-align: -2px; margin-inline-end: 5px; color: var(--ms-text-muted); }
.scorebox b { font-size: 20px; font-weight: 800; }
.decision { display: flex; gap: 8px; align-items: flex-start; font-size: 12.5px; background: var(--ms-navy-dim); border: 1px solid var(--ms-navy-border); border-radius: 8px; padding: 8px 10px; }
.notes { font-size: 12.5px; background: var(--ms-bg-hover); border-radius: 8px; padding: 8px 10px; white-space: pre-wrap; }
.reasons { list-style: none; margin: 0; padding: 0; display: grid; gap: 6px; }
.reasons li { display: flex; gap: 10px; align-items: flex-start; font-size: 12.5px; padding: 6px 8px; border-radius: 6px; background: var(--ms-bg-hover); }
.mark { font-weight: 800; width: 14px; text-align: center; }
.acts { display: flex; gap: 16px; flex-wrap: wrap; }
.acts .linkbtn { font-size: 12.5px; }
</style>
