<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — Guided Intake (Scope v2 §3 · Step 9)
//  Location: resources/js/Pages/App/Intake/Journey.vue
//  Route: GET /app/intake (intake.index) — App\IntakeController
//
//  The journey itself is Components/Intake/IntakeJourney.vue — the same
//  one job seekers use on the public site (Step 10). Here: mode "staff",
//  reading the CV with POST intake.cv and saving with POST intake.store.
// ══════════════════════════════════════════════════════════════════

import AppLayout from '@/Layouts/AppLayout.vue';
import IntakeJourney from '@/Components/Intake/IntakeJourney.vue';
import { useTranslations } from '@/composables/useTranslations';

defineProps({
    options: { type: Object, required: true },
    backbone: { type: Boolean, default: true },
    can_upload: { type: Boolean, default: false },
    limits: { type: Object, required: true },
});

const { t } = useTranslations();
</script>

<template>
    <AppLayout :title="t('intake.title')">
        <div class="page-head">
            <div>
                <div class="page-eyebrow">{{ t('intake.eyebrow') }}</div>
                <h1 class="page-title">{{ t('intake.title') }}</h1>
                <div class="page-sub">{{ t('intake.sub') }}</div>
            </div>
        </div>
        <IntakeJourney mode="staff" :options="options" :backbone="backbone" :can-upload="can_upload" :limits="limits"
            :cv-url="route('app.intake.cv')" :save-url="route('app.intake.store')" />
    </AppLayout>
</template>
