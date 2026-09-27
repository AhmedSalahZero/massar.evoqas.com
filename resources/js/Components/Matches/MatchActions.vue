<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — Match dialogs (Step 13)
//  Location: resources/js/Components/Matches/MatchActions.vue
//  Used by: the Job / Training page, the profile, the Matches page
//
//      <MatchActions ref="acts" :stop-reasons="stop_reasons" />
//      acts.value.open('move', match, 'Sara Mostafa')
//      acts.value.refer({ opportunity_id, numbers: [12, 15], kind, title, names })
//
//  refer     the date it happened (today, or earlier) and a note
//  move      the next stage, or a later one (the ones between are skipped)
//  back      back one stage — a reason is required (a correction)
//  stop      a reason from the list (and words for "Other"), the date, a note
//  restart   a reason is required
//  timeline  the whole timeline of one match
//  Every change goes to the server (matches.manage); the page then reloads.
// ══════════════════════════════════════════════════════════════════

import { computed, ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import Modal from '@/Components/Modal.vue';
import MatchTimeline from '@/Components/Matches/MatchTimeline.vue';
import { useTranslations } from '@/composables/useTranslations';
import { formatDate, todayIso } from '@/Utils/date';
import { STAGES, nextStage, stageText, stopReasonText } from '@/Components/Matches/match';

const props = defineProps({
    stopReasons: { type: [Object, Array], default: () => ({}) },   // {job: […], training: […]} or the list of one kind
});
const emit = defineEmits(['done']);
const { t, locale } = useTranslations();

const action = ref('');
const m = ref(null);
const label = ref('');
const today = todayIso();
const form = useForm({ to: '', on: today, note: '', reason: '' });

const reasons = computed(() => (Array.isArray(props.stopReasons) ? props.stopReasons : (props.stopReasons?.[m.value?.kind] || [])));
const later = computed(() => (m.value ? STAGES.slice(STAGES.indexOf(m.value.stage) + 1) : []));
const skipped = computed(() => (m.value && form.to ? STAGES.slice(STAGES.indexOf(m.value.stage) + 1, STAGES.indexOf(form.to)) : []));
const previous = computed(() => (m.value ? STAGES[STAGES.indexOf(m.value.stage) - 1] : null));
const minDate = computed(() => m.value?.stage_on || '');

function open(what, match, who = '') {
    form.reset();
    form.clearErrors();
    form.on = today;
    m.value = match;
    label.value = who;
    action.value = what;
    if (what === 'move') form.to = nextStage(match.stage) || '';
    if (what === 'timeline' && !match.events) loadTimeline(match);
}

// ── Refer (one or several people) ─────────────────────────────────
const referring = ref(null);
const referForm = useForm({ opportunity_id: null, numbers: [], on: today, note: '' });
function refer(r) {
    referForm.reset();
    referForm.clearErrors();
    referForm.opportunity_id = r.opportunity_id;
    referForm.numbers = r.numbers;
    referForm.on = today;
    referring.value = r;
}
const sendRefer = () => referForm.post(route('app.matches.store'), {
    preserveScroll: true,
    onSuccess: () => { referring.value = null; emit('done'); },
});

// ── Timeline ──────────────────────────────────────────────────────
const events = ref(null);
async function loadTimeline(match) {
    events.value = null;
    try {
        const { data } = await window.axios.get(route('app.matches.timeline', match.id));
        events.value = data.events;
    } catch {
        events.value = [];
    }
}

const close = () => { action.value = ''; };
const send = () => {
    const url = route(`app.matches.${action.value}`, m.value.id);
    const data = {
        move: { to: form.to, on: form.on, note: form.note },
        back: { reason: form.reason },
        stop: { reason: form.reason, on: form.on, note: form.note },
        restart: { reason: form.reason },
    }[action.value];
    form.transform(() => data).post(url, { preserveScroll: true, onSuccess: () => { close(); emit('done'); } });
};
const canSend = computed(() => {
    if (form.processing) return false;
    if (action.value === 'move') return !!form.to && !!form.on;
    if (action.value === 'stop') return !!form.reason && !!form.on && (form.reason !== 'other' || !!form.note.trim());
    return !!form.reason.trim();
});
const TITLES = { move: 'mt.move_title', back: 'mt.back_title', stop: 'mt.stop_title', restart: 'mt.restart_title', timeline: 'mt.timeline' };

defineExpose({ open, refer });
</script>

<template>
    <!-- ── Move · back · stop · restart ─────────────────────── -->
    <Modal :show="!!action && action !== 'timeline'" size="md" :title="action ? t(TITLES[action]) : ''" @close="close">
        <template v-if="m">
            <p class="small mute">
                <b v-if="label">{{ label }} · </b>{{ t('mt.now_at', { stage: stageText(m.kind, m.stage, t) }) }}
                <template v-if="m.stage_on"> · {{ t('mt.since', { date: formatDate(m.stage_on, locale) }) }}</template>
            </p>

            <template v-if="action === 'move'">
                <div class="opts mt-3" role="radiogroup">
                    <label v-for="s in later" :key="s" class="opt">
                        <input v-model="form.to" type="radio" :value="s">
                        <span><b>{{ stageText(m.kind, s, t) }}</b><template v-if="s === nextStage(m.stage)"> · <span class="mute">{{ t('mt.next') }}</span></template></span>
                    </label>
                </div>
                <div v-if="skipped.length" class="alert warning mt-2 small">{{ t('mt.will_skip', { list: skipped.map((s) => stageText(m.kind, s, t)).join(', ') }) }}</div>
                <div v-if="form.to === 'done'" class="small mute mt-2">{{ t('mt.final_note') }}</div>
                <div v-if="form.errors.to" class="fld-error mt-1">{{ form.errors.to }}</div>
            </template>

            <template v-if="action === 'stop'">
                <div class="opts mt-3" role="radiogroup">
                    <label v-for="r in reasons" :key="r" class="opt">
                        <input v-model="form.reason" type="radio" :value="r"><span>{{ stopReasonText(m.kind, r, t) }}</span>
                    </label>
                </div>
                <div v-if="form.errors.reason" class="fld-error mt-1">{{ form.errors.reason }}</div>
            </template>

            <label v-if="action === 'move' || action === 'stop'" class="fld mt-3" :class="{ 'has-error': form.errors.on }">
                <span class="fl"><span>{{ t('mt.happened_on') }}<span class="req">*</span></span></span>
                <input v-model="form.on" type="date" class="inp" :max="today" :min="minDate">
                <small>{{ t('mt.happened_hint') }}</small>
                <span v-if="form.errors.on" class="fld-error">{{ form.errors.on }}</span>
            </label>

            <label v-if="action === 'move' || action === 'stop'" class="fld mt-3" :class="{ 'has-error': form.errors.note }">
                <span class="fl"><span>{{ t('mt.note') }}<span v-if="action === 'stop' && form.reason === 'other'" class="req">*</span></span></span>
                <textarea v-model="form.note" rows="2" maxlength="500" dir="auto" :placeholder="t(action === 'stop' && form.reason === 'other' ? 'mt.other_ph' : 'mt.note_ph')" />
                <span v-if="form.errors.note" class="fld-error">{{ form.errors.note }}</span>
            </label>

            <template v-if="action === 'back' || action === 'restart'">
                <p v-if="action === 'back'" class="small mt-3">{{ t('mt.back_sub', { stage: stageText(m.kind, previous, t) }) }}</p>
                <p v-else class="small mt-3">{{ t('mt.restart_sub', { stage: stageText(m.kind, m.stage, t) }) }}</p>
                <label class="fld mt-2" :class="{ 'has-error': form.errors.reason }">
                    <span class="fl"><span>{{ t('mt.reason') }}<span class="req">*</span></span></span>
                    <textarea v-model="form.reason" rows="3" maxlength="500" dir="auto" :placeholder="t('mt.reason_ph')" />
                    <small>{{ t('mt.reason_hint') }}</small>
                    <span v-if="form.errors.reason" class="fld-error">{{ form.errors.reason }}</span>
                </label>
            </template>
        </template>
        <template #footer>
            <button type="button" class="btn btn-line" @click="close">{{ t('common.cancel') }}</button>
            <button type="button" class="btn" :class="[action === 'stop' ? 'btn-danger' : 'btn-primary', { 'is-loading': form.processing }]" :disabled="!canSend" @click="send">
                {{ t(`mt.do_${action || 'move'}`) }}
            </button>
        </template>
    </Modal>

    <!-- ── Timeline ─────────────────────────────────────────── -->
    <Modal :show="action === 'timeline'" size="md" :title="t('mt.timeline')" @close="close">
        <template v-if="m">
            <p v-if="label" class="small mute"><b>{{ label }}</b></p>
            <div v-if="!(m.events || events)" class="small mute mt-2">{{ t('mt.loading') }}</div>
            <MatchTimeline v-else class="mt-3" :kind="m.kind" :events="m.events || events" />
        </template>
        <template #footer>
            <button type="button" class="btn btn-line" @click="close">{{ t('common.close') }}</button>
        </template>
    </Modal>

    <!-- ── Refer ────────────────────────────────────────────── -->
    <Modal :show="!!referring" size="md" :title="t('mt.refer_title')" @close="referring = null">
        <template v-if="referring">
            <p class="small">
                {{ referring.numbers.length === 1 ? t('mt.refer_one', { name: referring.names?.[0] || '', title: referring.title })
                    : t('mt.refer_many', { n: referring.numbers.length, title: referring.title }) }}
            </p>
            <p class="small mute">{{ t('mt.refer_sub', { stage: stageText(referring.kind, 'referred', t) }) }}</p>
            <div v-for="(w, i) in referring.warnings || []" :key="i" class="alert warning mt-2 small">{{ w }}</div>
            <label class="fld mt-3" :class="{ 'has-error': referForm.errors.on }">
                <span class="fl"><span>{{ t('mt.happened_on') }}<span class="req">*</span></span></span>
                <input v-model="referForm.on" type="date" class="inp" :max="today">
                <small>{{ t('mt.happened_hint') }}</small>
                <span v-if="referForm.errors.on" class="fld-error">{{ referForm.errors.on }}</span>
            </label>
            <label class="fld mt-3" :class="{ 'has-error': referForm.errors.note }">
                <span class="fl"><span>{{ t('mt.note') }}</span></span>
                <textarea v-model="referForm.note" rows="2" maxlength="500" dir="auto" :placeholder="t('mt.note_ph')" />
                <span v-if="referForm.errors.note" class="fld-error">{{ referForm.errors.note }}</span>
            </label>
            <div v-for="k in ['opportunity_id', 'numbers']" v-show="referForm.errors[k]" :key="k" class="fld-error mt-1">{{ referForm.errors[k] }}</div>
        </template>
        <template #footer>
            <button type="button" class="btn btn-line" @click="referring = null">{{ t('common.cancel') }}</button>
            <button type="button" class="btn btn-primary" :class="{ 'is-loading': referForm.processing }" :disabled="referForm.processing || !referForm.on" @click="sendRefer">
                {{ referring && referring.numbers.length > 1 ? t('mt.refer_n', { n: referring.numbers.length }) : t('mt.refer') }}
            </button>
        </template>
    </Modal>
</template>
