<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — One match's timeline (Step 13)
//  Location: resources/js/Components/Matches/MatchTimeline.vue
//
//  Newest first: referred, moved (and the stages skipped), corrected
//  (back one stage, with the reason), stopped (with the reason), restarted.
//  Each line: what happened, the date it happened, who, the date it was
//  recorded when different, and the note.
// ══════════════════════════════════════════════════════════════════

import { useTranslations } from '@/composables/useTranslations';
import { formatDate } from '@/Utils/date';
import { stageText, stopReasonText } from '@/Components/Matches/match';

defineProps({
    kind: { type: String, required: true },
    events: { type: Array, default: () => [] },
});
const { t, locale } = useTranslations();
const date = (d) => (d ? formatDate(d, locale.value) : '');
const when = (iso) => (iso ? formatDate(iso, locale.value, true) : '');
const recordedLater = (e) => e.at && e.on && e.at.slice(0, 10) !== e.on;
const COLOR = { referred: 'var(--ms-navy)', moved: 'var(--ms-teal)', corrected: 'var(--ms-orange)', stopped: 'var(--ms-danger)', restarted: 'var(--ms-green)' };
</script>

<template>
    <ul class="timeline mtl">
        <li v-for="e in events" :key="e.id" :style="`--c:${COLOR[e.action] || 'var(--ms-border)'}`">
            <div>
                <b v-if="e.action === 'referred'">{{ stageText(kind, 'referred', t) }}</b>
                <b v-else-if="e.action === 'moved'">{{ stageText(kind, e.to, t) }}</b>
                <b v-else-if="e.action === 'corrected'">{{ t('mt.ev_corrected', { from: stageText(kind, e.from, t), to: stageText(kind, e.to, t) }) }}</b>
                <b v-else-if="e.action === 'stopped'">{{ t('mt.ev_stopped', { reason: stopReasonText(kind, e.reason, t) }) }}</b>
                <b v-else-if="e.action === 'restarted'">{{ t('mt.ev_restarted', { stage: stageText(kind, e.to, t) }) }}</b>
                <span>
                    {{ date(e.on) }} · {{ e.by || '—' }}
                    <template v-if="recordedLater(e)"> · {{ t('mt.recorded_on', { date: when(e.at) }) }}</template>
                </span>
                <div v-if="e.skipped?.length" class="xsmall mute">{{ t('mt.skipped', { list: e.skipped.map((s) => stageText(kind, s, t)).join(', ') }) }}</div>
                <div v-if="e.note" class="small note-q" dir="auto">“{{ e.note }}”</div>
            </div>
        </li>
    </ul>
</template>

<style scoped>
.mtl li > div { min-width: 0; }
.note-q { color: var(--ms-text-secondary, inherit); margin-top: 2px; white-space: pre-wrap; }
</style>
