<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — Upload CVs (Scope v2 §3 Bulk CV Upload)
//  Location: resources/js/Pages/App/Cv/Upload.vue
//  Route: GET /app/cv-upload (cv.upload) — App\CvUploadController
//
//  Drop or choose up to 50 PDF / Word CVs. The page starts one upload
//  (a batch), then sends the files two at a time; each file is read on
//  the server at once and its row shows the result:
//      Added · Needs review · Possible duplicate · Could not be read
//  A summary counts them and links to the review queue. Files that are
//  not PDF/Word, or too big, are refused here before being sent.
//  Each file carries its own client_id, so two CVs sent within seconds
//  are never mistaken for a double-click.
// ══════════════════════════════════════════════════════════════════

import { computed, reactive, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { useTranslations } from '@/composables/useTranslations';
import { formatDate } from '@/Utils/date';
import { STATUS_BADGE, sizeText } from '@/Components/Cv/cv';

const props = defineProps({
    limits: { type: Object, required: true },
    pdf_ready: { type: Boolean, default: true },
    waiting: { type: Number, default: 0 },
    batches: { type: Array, default: () => [] },
});

const { t, locale } = useTranslations();
const rows = ref([]);            // { key, file, name, size, ext, state, progress, result, error }
const over = ref(false);
const notice = ref('');
const busy = computed(() => rows.value.some((r) => r.state === 'waiting' || r.state === 'sending'));
const maxMb = computed(() => Math.round(props.limits.max_kb / 1024));
const newlyWaiting = computed(() => rows.value.filter((r) => r.result && ['review', 'duplicate', 'unreadable'].includes(r.result.status)).length);

const summary = computed(() => {
    const s = { added: 0, review: 0, duplicate: 0, unreadable: 0 };
    rows.value.forEach((r) => { if (r.result && s[r.result.status] !== undefined) s[r.result.status]++; });
    return s;
});

const uid = () => `${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 10)}`;

function onDrop(e) {
    over.value = false;
    take(e.dataTransfer?.files);
}
function onPick(e) {
    take(e.target.files);
    e.target.value = '';
}

async function take(fileList) {
    if (!fileList?.length || busy.value) return;
    notice.value = '';
    let files = Array.from(fileList);
    if (files.length > props.limits.max_files) {
        notice.value = t('cv.too_many', { n: props.limits.max_files });
        files = files.slice(0, props.limits.max_files);
    }
    const fresh = files.map((f) => {
        const ext = (f.name.split('.').pop() || '').toLowerCase();
        const row = reactive({ key: uid(), file: f, name: f.name, size: f.size, ext, state: 'waiting', progress: 0, result: null, error: '' });
        if (!props.limits.extensions.includes(ext)) { row.state = 'refused'; row.error = t('cv.skipped'); }
        else if (f.size > props.limits.max_kb * 1024) { row.state = 'refused'; row.error = t('cv.too_big', { mb: maxMb.value }); }
        return row;
    });
    rows.value = [...fresh, ...rows.value];
    const toSend = fresh.filter((r) => r.state === 'waiting');
    if (!toSend.length) return;

    let batch;
    try {
        const { data } = await window.axios.post(route('app.cv-upload.batch'), { files: toSend.length, client_id: uid() });
        batch = data.batch;
    } catch (e) {
        toSend.forEach((r) => { r.state = 'failed'; r.error = message(e); });
        return;
    }
    // Two at a time: fast, and one slow file never holds up the rest.
    const queue = [...toSend];
    const worker = async () => { while (queue.length) await send(queue.shift(), batch); };
    await Promise.all([worker(), worker()]);
}

async function send(row, batch) {
    row.state = 'sending';
    row.error = '';
    const body = new FormData();
    body.append('file', row.file);
    body.append('client_id', uid());
    try {
        const { data } = await window.axios.post(route('app.cv-upload.file', batch), body, {
            headers: { Accept: 'application/json' },
            onUploadProgress: (p) => { row.progress = p.total ? Math.min(90, Math.round((p.loaded / p.total) * 90)) : 50; },
        });
        row.result = data.document;
        row.progress = 100;
        row.state = 'done';
    } catch (e) {
        row.state = 'failed';
        row.error = message(e);
    }
}

async function retry(row) {
    // A retried file starts its own small upload.
    try {
        const { data } = await window.axios.post(route('app.cv-upload.batch'), { files: 1, client_id: uid() });
        await send(row, data.batch);
    } catch (e) {
        row.state = 'failed'; row.error = message(e);
    }
}

function message(e) {
    const r = e?.response;
    if (r?.status === 413) return t('cv.too_big', { mb: maxMb.value });
    return r?.data?.errors?.file?.[0] || r?.data?.message || t('cv.failed');
}

const reviewLink = (r) => (r.result?.beneficiary ? route('app.beneficiaries.show', r.result.beneficiary.number) : route('app.review-queue.show', r.result.uuid));
</script>

<template>
    <AppLayout :title="t('cv.upload_title')">
        <div class="page-head">
            <div>
                <h1 class="page-title">{{ t('cv.upload_title') }}</h1>
                <div class="page-sub">{{ t('cv.upload_sub') }}</div>
            </div>
            <div class="page-actions">
                <Link :href="route('app.review-queue.index')" class="btn btn-line">
                    <AppIcon name="inbox" :size="15" />{{ t('cv.open_queue', { n: waiting + newlyWaiting }) }}
                </Link>
            </div>
        </div>

        <div v-if="!pdf_ready" class="alert warning mb-4"><AppIcon name="alert" :size="16" /><span>{{ t('cv.pdf_missing') }}</span></div>
        <div v-if="notice" class="alert info mb-4"><AppIcon name="info" :size="16" /><span>{{ notice }}</span></div>

        <!-- Drop zone -->
        <div class="drop" :class="{ over }" @dragover.prevent="over = true" @dragleave.prevent="over = false" @drop.prevent="onDrop">
            <div class="ic"><AppIcon name="upload" :size="26" /></div>
            <h3>{{ t('cv.drop_title') }}</h3>
            <p class="mute">{{ t('cv.drop_hint', { n: limits.max_files, mb: maxMb }) }}</p>
            <div class="row">
                <label class="btn btn-navy file-pick" :class="{ disabled: busy }">
                    {{ rows.length ? t('cv.upload_more') : t('cv.choose') }}
                    <input type="file" multiple :accept="limits.extensions.map((e) => '.' + e).join(',')" :disabled="busy" @change="onPick">
                </label>
            </div>
        </div>

        <!-- Summary of this session -->
        <template v-if="rows.length">
            <div class="sumbar">
                <div class="ok"><b>{{ summary.added }}</b><span>{{ t('cv.sum_added') }}</span></div>
                <div class="rev"><b>{{ summary.review }}</b><span>{{ t('cv.sum_review') }}</span></div>
                <div class="dup"><b>{{ summary.duplicate }}</b><span>{{ t('cv.sum_duplicate') }}</span></div>
                <div><b>{{ summary.unreadable }}</b><span>{{ t('cv.sum_unreadable') }}</span></div>
            </div>
            <div v-if="busy" class="small mute mb-2"><AppIcon name="info" :size="13" /> {{ t('cv.keep_open') }}</div>

            <div v-for="r in rows" :key="r.key" class="frow">
                <span class="ft-ico" :class="{ doc: r.ext !== 'pdf' }">{{ r.ext.toUpperCase().slice(0, 4) || '?' }}</span>
                <div class="nm">
                    <b :title="r.name">{{ r.name }}</b>
                    <span>
                        {{ sizeText(r.size, locale) }}
                        <template v-if="r.result?.name"> · {{ r.result.name }}</template>
                        <template v-if="r.result?.problem"> · {{ t(`cv.p_${r.result.problem}`) }}</template>
                        <template v-if="r.error"> · <span class="c-danger">{{ r.error }}</span></template>
                    </span>
                </div>
                <div class="meter"><i :style="{ width: (r.state === 'done' ? 100 : r.progress) + '%', '--c': r.state === 'failed' || r.state === 'refused' ? 'var(--ms-danger)' : (r.state === 'done' ? 'var(--ms-green)' : 'var(--ms-orange)') }" /></div>
                <div class="res">
                    <span v-if="r.state === 'waiting'" class="badge plain">{{ t('cv.waiting_file') }}</span>
                    <span v-else-if="r.state === 'sending'" class="badge navy"><span class="dot" />{{ t('cv.reading') }}</span>
                    <span v-else-if="r.state === 'refused'" class="badge danger">{{ t('cv.failed') }}</span>
                    <template v-else-if="r.state === 'failed'">
                        <span class="badge danger">{{ t('cv.failed') }}</span>
                        <button type="button" class="btn btn-line xs" @click="retry(r)">{{ t('cv.retry') }}</button>
                    </template>
                    <template v-else-if="r.result">
                        <span class="badge" :class="STATUS_BADGE[r.result.status]">{{ t(`cv.st_${r.result.status}`) }}</span>
                        <Link :href="reviewLink(r)" class="small">{{ r.result.beneficiary ? t('cv.open_profile', { n: r.result.beneficiary.number }) : t('cv.review_it') }}</Link>
                    </template>
                </div>
            </div>
        </template>

        <!-- Recent uploads in this workspace -->
        <div class="panel mt-4">
            <h3><AppIcon name="activity" :size="16" />{{ t('cv.recent') }}</h3>
            <div v-if="!batches.length" class="empty-row">{{ t('cv.recent_none') }}</div>
            <div v-for="b in batches" :key="b.id" class="status-row">
                <span class="label">{{ formatDate(b.at, locale, true) }} <span class="mute small">{{ t('cv.batch_by', { n: b.files, by: b.by || '—' }) }}</span></span>
                <span class="flex gap-2 wrap">
                    <span v-for="(n, s) in b.counts" :key="s" class="badge" :class="STATUS_BADGE[s]">{{ n }} · {{ t(`cv.st_${s}`) }}</span>
                </span>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.frow { margin-top: 8px; }
.res { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
.file-pick.disabled { opacity: .6; pointer-events: none; }
.btn.xs { padding: 3px 8px; font-size: 11.5px; }
@media (max-width: 760px) {
    .frow { grid-template-columns: 38px minmax(0, 1fr); }
    .frow .meter, .frow .res { grid-column: 2; }
    .sumbar { grid-template-columns: 1fr 1fr; }
}
</style>
