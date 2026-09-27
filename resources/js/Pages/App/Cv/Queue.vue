<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — Review Queue (Scope v2 §3 Review Queue)
//  Location: resources/js/Pages/App/Cv/Queue.vue
//  Route: GET /app/review-queue (cv.review) — App\ReviewQueueController@index
//
//  The CVs the reading engine was not sure about, newest first:
//  needs review, possible duplicate, could not be read. Each row shows
//  the name found, the job title, how many fields to check, and opens
//  the review screen.
// ══════════════════════════════════════════════════════════════════

import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Pagination from '@/Components/Pagination.vue';
import { useTranslations } from '@/composables/useTranslations';
import { formatDate } from '@/Utils/date';
import { STATUS_BADGE } from '@/Components/Cv/cv';

const props = defineProps({
    list: { type: Object, required: true },
    status: { type: String, default: '' },
    counts: { type: Object, required: true },
});

const { t, locale } = useTranslations();
const total = computed(() => Object.values(props.counts).reduce((a, b) => a + b, 0));
const show = (s) => router.get(route('app.review-queue.index'), s ? { status: s } : {}, { preserveScroll: true });
</script>

<template>
    <AppLayout :title="t('cv.queue_title')">
        <div class="page-head">
            <div>
                <h1 class="page-title">{{ t('cv.queue_title') }}</h1>
                <div class="page-sub">{{ t('cv.queue_sub') }}</div>
            </div>
            <div class="page-actions">
                <Link :href="route('app.cv-upload.index')" class="btn btn-primary"><AppIcon name="upload" :size="15" />{{ t('cv.upload_title') }}</Link>
            </div>
        </div>

        <div class="seg mb-4" role="group">
            <button type="button" :aria-pressed="!status" @click="show('')">{{ t('cv.all') }} · {{ total }}</button>
            <button v-for="(n, s) in counts" :key="s" type="button" :aria-pressed="status === s" @click="show(s)">{{ t(`cv.st_${s}`) }} · {{ n }}</button>
        </div>

        <EmptyState v-if="!list.data.length" icon="inbox" :title="t('cv.queue_empty_title')" :text="t('cv.queue_empty')">
            <Link :href="route('app.cv-upload.index')" class="btn btn-primary sm">{{ t('cv.upload_title') }}</Link>
        </EmptyState>

        <Link v-for="d in list.data" :key="d.uuid" :href="route('app.review-queue.show', d.uuid)" class="qrow">
            <span class="ft-ico" :class="{ doc: d.extension !== 'pdf' }">{{ d.extension.toUpperCase() }}</span>
            <div class="min0">
                <h4>{{ d.name || t('cv.no_name') }} <span v-if="d.title" class="mute small">· {{ d.title }}</span></h4>
                <p>
                    <bdi>{{ d.file }}</bdi> · {{ t('cv.uploaded', { date: formatDate(d.uploaded_at, locale, true), by: d.uploaded_by || '—' }) }}
                    <template v-if="d.problem"> · {{ t(`cv.p_${d.problem}`) }}</template>
                </p>
            </div>
            <span v-if="d.attention.length" class="tag orange">{{ t('cv.fields_marked', { n: d.attention.length }) }}</span>
            <span v-else />
            <span class="badge" :class="STATUS_BADGE[d.status]">{{ t(`cv.st_${d.status}`) }}</span>
        </Link>

        <Pagination v-if="list.data.length" class="mt-4" :paginator="list" />
    </AppLayout>
</template>

<style scoped>
.min0 { min-width: 0; }
.min0 p { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.seg { display: inline-flex; flex-wrap: wrap; }
@media (max-width: 760px) { .qrow { grid-template-columns: 40px minmax(0, 1fr); } }
</style>
