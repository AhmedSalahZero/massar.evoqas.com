<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — Update from this CV (Scope v2 §3)
//  Location: resources/js/Pages/App/Cv/Update.vue
//  Route: GET /app/review-queue/{uuid}/update/{number} (cv.review) — ReviewQueueController@compare
//
//  A newer CV of someone already registered: what the CV says that the
//  profile does not, group by group (details, occupation, jobs,
//  education, skills, languages). Each change has a tick box:
//    ticked       additions — a new job, new skills, an empty field filled
//    not ticked   anything that REPLACES what the profile says
//    locked       a mobile or email another profile already uses
//  "Update the profile" makes only the ticked changes and adds the CV
//  to the profile; the history says "updated from CV …".
// ══════════════════════════════════════════════════════════════════

import { computed, reactive } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { useTranslations } from '@/composables/useTranslations';
import CvChangeList from '@/Components/Cv/CvChangeList.vue';

const props = defineProps({
    doc: { type: Object, required: true },
    profile: { type: Object, required: true },
    rows: { type: Array, required: true },
    options: { type: Object, default: () => ({}) },
});

const { t } = useTranslations();

const ticked = reactive(Object.fromEntries(props.rows.map((r) => [r.id, !!r.ticked && !r.blocked])));
const count = computed(() => Object.values(ticked).filter(Boolean).length);

const form = useForm({});
const save = () => form.transform(() => ({ ticked: Object.keys(ticked).filter((k) => ticked[k]) }))
    .post(route('app.review-queue.update', [props.doc.uuid, props.profile.number]));
</script>

<template>
    <AppLayout :title="t('cv.upd_title')">
        <Link :href="route('app.review-queue.show', doc.uuid)" class="back"><AppIcon name="arrow-left" :size="14" />{{ t('cv.review_title') }}</Link>

        <div class="page-head">
            <div>
                <div class="page-eyebrow">{{ t('cv.upd_title') }}</div>
                <h1 class="page-title">#{{ profile.number }} · {{ profile.name }}</h1>
                <div class="page-sub">{{ t('cv.upd_sub', { file: doc.file }) }}</div>
            </div>
            <div class="page-actions">
                <Link :href="route('app.beneficiaries.show', profile.number)" target="_blank" class="btn btn-line">{{ t('cv.upd_open_profile') }}</Link>
            </div>
        </div>

        <div v-if="form.errors.update || form.errors.cv" class="alert error mb-3"><AppIcon name="alert" :size="16" /><span>{{ form.errors.update || form.errors.cv }}</span></div>

        <EmptyState v-if="!rows.length" icon="check-circle" :title="t('cv.upd_nothing_title')" :text="t('cv.upd_nothing')" />

        <CvChangeList :rows="rows" :ticked="ticked" />

        <div class="stickyacts">
            <span class="grow">{{ t('cv.upd_count', { n: count }) }}</span>
            <Link :href="route('app.review-queue.show', doc.uuid)" class="btn btn-line">{{ t('common.cancel') }}</Link>
            <button type="button" class="btn btn-primary" :class="{ 'is-loading': form.processing }" :disabled="form.processing" @click="save">
                <AppIcon name="check" :size="15" />{{ count ? t('cv.upd_save', { n: count }) : t('cv.upd_attach_only') }}
            </button>
        </div>
    </AppLayout>
</template>

<style scoped>
.panel + .stickyacts, div + .stickyacts { margin-top: 14px; }
.grow { flex: 1; }
</style>
