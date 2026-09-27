<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — a job seeker registers (Step 10)
//  Location: resources/js/Pages/Public/Join.vue
//  Route: GET /join?door=cv|questions (seeker.join) — Public\JoinController
//
//  The journey is Components/Intake/IntakeJourney.vue in mode "join":
//  the same questions as the staff Guided Intake, plus the notice
//  period, consent and the sign-in. The bar at the top shows the four
//  stages agreed in the demo.
// ══════════════════════════════════════════════════════════════════

import { computed, ref } from 'vue';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import IntakeJourney from '@/Components/Intake/IntakeJourney.vue';
import { useTranslations } from '@/composables/useTranslations';

const props = defineProps({
    door: { type: String, default: 'cv' },
    options: { type: Object, required: true },
    backbone: { type: Boolean, default: true },
    limits: { type: Object, required: true },
});

const { t } = useTranslations();
const phase = ref(props.door === 'questions' ? 'steps' : 'start');
const stepId = ref('');
const stage = computed(() => {
    if (phase.value === 'steps') return ['consent', 'account'].includes(stepId.value) ? 2 : 1;
    return ({ start: 0, reading: 0, review: 3 })[phase.value] ?? 1;
});
const stages = computed(() => [props.door === 'questions' ? 'join.st_questions' : 'join.st_cv', 'join.st_answers', 'join.st_consent', 'join.st_check']);
</script>

<template>
    <PublicLayout :title="t('join.title')" narrow>
        <ol class="steps">
            <li v-for="(s, i) in stages" :key="s" :class="{ done: i < stage, current: i === stage }">{{ t(s) }}</li>
        </ol>
        <IntakeJourney mode="join" :door="door" :options="options" :backbone="backbone" :limits="limits"
            :cv-url="route('seeker.join.cv')" :save-url="route('seeker.join.store')" :picker-url="route('seeker.occupations')" :employer-url="route('seeker.employers')"
            @phase="phase = $event" @step="stepId = $event" />
    </PublicLayout>
</template>
