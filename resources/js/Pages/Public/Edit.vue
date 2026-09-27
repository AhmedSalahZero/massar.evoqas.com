<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — "Edit my profile" (Step 10)
//  Location: resources/js/Pages/Public/Edit.vue
//  Route: GET /me/edit (seeker.profile.edit) — Public\MyProfileController
//  The same journey as registering (mode "edit"): it opens on the
//  summary; "Edit" on any part opens its questions; save with PATCH /me.
// ══════════════════════════════════════════════════════════════════

import { Link } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import IntakeJourney from '@/Components/Intake/IntakeJourney.vue';
import { useTranslations } from '@/composables/useTranslations';

defineProps({
    initial: { type: Object, required: true },
    options: { type: Object, required: true },
    backbone: { type: Boolean, default: true },
    limits: { type: Object, required: true },
});
const { t } = useTranslations();
</script>

<template>
    <PublicLayout :title="t('me.edit')" narrow>
        <h1 class="ph">{{ t('me.edit') }}</h1>
        <IntakeJourney mode="edit" :initial="initial" :options="options" :backbone="backbone" :limits="limits"
            :save-url="route('seeker.profile.update')" :picker-url="route('seeker.occupations')" :employer-url="route('seeker.employers')">
            <template #cancel><Link :href="route('seeker.profile')" class="btn btn-line">{{ t('common.cancel') }}</Link></template>
        </IntakeJourney>
    </PublicLayout>
</template>

<style scoped>
.ph { font-size: 24px; margin: 6px 0 14px; }
</style>
