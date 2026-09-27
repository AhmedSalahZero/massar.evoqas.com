<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — "Replace my CV": what the new CV would change (Step 10)
//  Location: resources/js/Pages/Public/CvChanges.vue
//  Route: GET/POST /me/cv/{uuid} (seeker.cv.changes / .apply) — Public\MyProfileController
//
//  The same list as staff see for "Update from this CV": additions are
//  ticked, anything that REPLACES what the profile says is not. Only
//  the ticked changes are made; the new CV replaces the old one.
// ══════════════════════════════════════════════════════════════════

import { computed, reactive } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import CvChangeList from '@/Components/Cv/CvChangeList.vue';
import { useTranslations } from '@/composables/useTranslations';

const props = defineProps({
    doc: { type: Object, required: true },
    profile: { type: Object, required: true },
    rows: { type: Array, required: true },
});
const { t } = useTranslations();
const ticked = reactive(Object.fromEntries(props.rows.map((r) => [r.id, !!r.ticked && !r.blocked])));
const count = computed(() => Object.values(ticked).filter(Boolean).length);
const form = useForm({});
const save = () => form.transform(() => ({ ticked: Object.keys(ticked).filter((k) => ticked[k]) })).post(route('seeker.cv.apply', props.doc.uuid));
</script>

<template>
    <PublicLayout :title="t('me.changes_title')" narrow>
        <h1 class="ph">{{ t('me.changes_title') }}</h1>
        <p class="mute mb-4">{{ t('me.changes_sub', { file: doc.file }) }}</p>
        <div v-if="form.errors.update || form.errors.cv" class="alert warning mb-3"><AppIcon name="alert" :size="16" /><span>{{ form.errors.update || form.errors.cv }}</span></div>
        <div v-if="!rows.length" class="panel"><AppIcon name="check-circle" :size="18" /> {{ t('me.changes_none') }}</div>
        <CvChangeList :rows="rows" :ticked="ticked" />
        <div class="navbar">
            <Link :href="route('seeker.profile')" class="btn btn-line">{{ t('common.cancel') }}</Link>
            <button type="button" class="btn btn-primary" :class="{ 'is-loading': form.processing }" :disabled="form.processing" @click="save">
                <AppIcon name="check" :size="15" />{{ count ? t('me.changes_save', { n: count }) : t('me.changes_cv_only') }}
            </button>
        </div>
    </PublicLayout>
</template>

<style scoped>
.ph { font-size: 24px; margin: 6px 0; }
.navbar { display: flex; justify-content: space-between; gap: 10px; margin-top: 16px; }
</style>
