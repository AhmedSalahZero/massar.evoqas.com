<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — Review one CV (Scope v2 §3 Review Screen)
//  Location: resources/js/Pages/App/Cv/Review.vue
//  Route: GET /app/review-queue/{uuid} (cv.review) — App\ReviewQueueController@show
//
//  Left:  the text read from the CV, with contact details, known
//         headings, known skills and unrecognised headings coloured,
//         plus Download / Open for the original file (cv.download).
//  Right: warnings (possible duplicates, unreadable file, unknown
//         headings), the occupation the engine found or its
//         suggestions, then the SAME profile form as registration,
//         filled by the engine, each field marked Found / Check / Missing.
//  Bottom bar: how many fields are still marked; Reject · Add to an
//  existing profile · Approve and create profile. After a decision
//  the next CV in the queue opens.
//
//  Learned Rules (rules.manage): "Teach" next to an unknown heading, or
//  "Teach these words" for words selected on the CV. The rule is saved
//  for the workspace and the CV is read again at once. When the engine
//  could not settle the occupation, "Remember: <title> means this
//  occupation" (ticked) saves a title rule on Approve.
//  Add to an existing profile: find the person by name, mobile or
//  number, then "Compare and update" (Update.vue) or attach only.
// ══════════════════════════════════════════════════════════════════

import { computed, ref, watch } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import Modal from '@/Components/Modal.vue';
import Field from '@/Components/Field.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import BeneficiaryForm from '@/Components/Beneficiaries/BeneficiaryForm.vue';
import CvPaper from '@/Components/Cv/CvPaper.vue';
import { useTranslations } from '@/composables/useTranslations';
import { usePreferences } from '@/composables/usePreferences';
import { formatDate } from '@/Utils/date';
import { occIn } from '@/Components/Beneficiaries/ben';
import { STATUS_BADGE, sizeText } from '@/Components/Cv/cv';
import RuleFields from '@/Components/Rules/RuleFields.vue';
import { emptyRule, ruleData } from '@/Components/Rules/rules';

const props = defineProps({
    doc: { type: Object, required: true },
    initial: { type: Object, default: null },
    duplicates: { type: Array, default: () => [] },
    options: { type: Object, required: true },
    backbone: { type: Boolean, default: true },
    can_download: { type: Boolean, default: false },
    next: { type: String, default: null },
    waiting: { type: Number, default: 0 },
    teach: { type: Object, default: () => ({ can: false, sections: [], title: null }) },
});

const { t, locale } = useTranslations();
const { prefs } = usePreferences();
const formRef = ref(null);
// "Read again" and "Teach" read the CV again: the form is then built again from the new
// reading (its occupation, jobs …) — otherwise it would keep the values of the first reading.
const formKey = ref(0);
// (Compared by content: a failed Approve sends the same reading back, and the person's edits are kept.)
watch(() => JSON.stringify(props.initial), (now, before) => { if (now !== before) formKey.value++; });

const marked = computed(() => Object.values(props.doc.marks ?? {}).filter((m) => m === 'check').length
    + (props.doc.attention ?? []).filter((f) => ['name', 'gender', 'governorate', 'contact'].includes(f) && (props.doc.marks?.[f] ?? 'missing') === 'missing').length);
const unknownHeadings = computed(() => (props.doc.on_text?.unknown ?? []).map((i) => (props.doc.text || '').split('\n')[i]).filter(Boolean));
const textDir = computed(() => (props.doc.language === 'ar' ? 'rtl' : 'ltr'));
const profiles = computed(() => props.duplicates.filter((d) => d.type === 'profile'));

// ── Occupation suggestions ───────────────────────────────────────
const occ = props.doc.occupation;
const title = (block) => {
    const s = occIn(block, block.esco ? 'esco' : prefs.standard === 'esco' ? 'isco' : prefs.standard, locale.value);
    return `${s.code} · ${s.title}`;
};
const useOccupation = (block) => formRef.value?.setOccupation(block);

// ── Decisions ────────────────────────────────────────────────────
const approve = () => formRef.value?.save();

const rejecting = ref(false);
const rejectForm = useForm({});
const reject = () => rejectForm.post(route('app.review-queue.reject', props.doc.uuid), { onFinish: () => { rejecting.value = false; } });

// ── Add to an existing profile: find the person ───────────────────
const attaching = ref(false);
const attachForm = useForm({ number: '' });
const who = ref(null);              // the chosen profile { number, name, … }
const findQ = ref('');
const found = ref([]);
const finding = ref(false);
let findTimer = null;
let findSeq = 0;
const openAttach = (dup = null) => {
    who.value = dup ? { number: dup.number, name: dup.name } : null;
    findQ.value = ''; found.value = []; attachForm.clearErrors(); attaching.value = true;
};
watch(findQ, (q) => {
    clearTimeout(findTimer);
    if (q.trim().length < 1) { found.value = []; return; }
    findTimer = setTimeout(async () => {
        const mine = ++findSeq; finding.value = true;
        try {
            const { data } = await window.axios.get(route('app.review-queue.profiles'), { params: { q: q.trim() } });
            if (mine === findSeq) found.value = data;
        } finally { if (mine === findSeq) finding.value = false; }
    }, 300);
});
const attach = () => {
    attachForm.number = who.value?.number ?? '';
    attachForm.post(route('app.review-queue.attach', props.doc.uuid), { preserveScroll: true, onSuccess: () => { attaching.value = false; } });
};
const compare = () => router.visit(route('app.review-queue.compare', [props.doc.uuid, who.value.number]));
const canCompare = computed(() => !!props.initial && props.doc.status !== 'unreadable');

// ── Learned Rules ────────────────────────────────────────────────
const teaching = ref(false);
const ruleForm = useForm(emptyRule());
const openTeach = (over = {}) => { ruleForm.defaults(emptyRule(over)); ruleForm.reset(); ruleForm.clearErrors(); teaching.value = true; };
const teachSelected = () => {
    const words = (window.getSelection?.().toString() || '').replace(/\s+/g, ' ').trim().slice(0, 100);
    openTeach({ phrase: words.replace(/[:：\s]+$/, '') });
};
// (not called "teach": that name is the prop saying whether this person may teach)
const saveRule = () => ruleForm.transform(ruleData).post(route('app.review-queue.teach', props.doc.uuid), {
    preserveScroll: true, onSuccess: () => { teaching.value = false; },
});
const learnTitle = ref(true);
const rereadForm = useForm({});
const reread = () => rereadForm.post(route('app.review-queue.reread', props.doc.uuid), { preserveScroll: true });
const extra = computed(() => ({ learn_title: !!(props.teach.can && props.teach.title && learnTitle.value) }));

const fileUrl = (view = false) => route('app.cv-files.show', props.doc.uuid) + (view ? '?view=1' : '');
const decided = computed(() => rejectForm.processing || attachForm.processing);
</script>

<template>
    <AppLayout :title="t('cv.review_title')">
        <Link :href="route('app.review-queue.index')" class="back"><AppIcon name="arrow-left" :size="14" />{{ t('cv.queue_title') }}</Link>

        <div class="page-head">
            <div>
                <div class="page-eyebrow">{{ t('cv.of_waiting', { n: waiting }) }}</div>
                <h1 class="page-title">{{ doc.name || t('cv.review_title') }}</h1>
                <div class="page-sub">
                    <span class="badge" :class="STATUS_BADGE[doc.status]">{{ t(`cv.st_${doc.status}`) }}</span>
                    · <bdi>{{ doc.file }}</bdi> · {{ t('cv.uploaded', { date: formatDate(doc.uploaded_at, locale, true), by: doc.uploaded_by || '—' }) }}
                </div>
            </div>
            <div class="page-actions">
                <button v-if="doc.text || doc.problem === 'no_pdf_reader'" type="button" class="btn btn-line" :class="{ 'is-loading': rereadForm.processing }" :disabled="rereadForm.processing"
                        :title="t(doc.text ? 'cv.reread_hint' : 'cv.reread_pdf_hint')" @click="reread"><AppIcon name="sparkles" :size="14" />{{ t('cv.reread') }}</button>
                <Link v-if="next" :href="route('app.review-queue.show', next)" class="btn btn-line">{{ t('cv.skip') }}</Link>
            </div>
        </div>

        <div class="split">
            <!-- ── The CV ──────────────────────────────────────────── -->
            <div class="cvpane">
                <div class="cvbar">
                    <b>{{ t('cv.original') }} <span class="mute small">· {{ sizeText(doc.size, locale) }}</span></b>
                    <button v-if="teach.can && doc.text" type="button" class="btn btn-line sm" :title="t('rules.teach_selected_hint')" @mousedown.prevent @click="teachSelected">
                        <AppIcon name="sparkles" :size="14" />{{ t('rules.teach_selected') }}
                    </button>
                    <span v-if="can_download" class="flex gap-2">
                        <a v-if="doc.extension === 'pdf'" :href="fileUrl(true)" target="_blank" rel="noopener" class="btn btn-line sm"><AppIcon name="eye" :size="14" />{{ t('cv.view') }}</a>
                        <a :href="fileUrl()" class="btn btn-line sm"><AppIcon name="file" :size="14" />{{ t('cv.download') }}</a>
                    </span>
                </div>
                <CvPaper v-if="doc.text" :text="doc.text" :marks="doc.on_text" :dir="textDir" />
                <div v-else class="paper"><p>{{ t('cv.no_text') }}</p><p v-if="doc.problem" class="mute">{{ t(`cv.p_${doc.problem}`) }}</p></div>
            </div>

            <!-- ── Checks and the form ─────────────────────────────── -->
            <div>
                <div v-if="doc.problem" class="alert warning mb-3"><AppIcon name="alert" :size="16" /><span>{{ t(`cv.p_${doc.problem}`) }}</span></div>

                <div v-if="duplicates.length" class="teach mb-3">
                    <h4><AppIcon name="users" :size="15" />{{ t('cv.dup_title') }}</h4>
                    <div v-for="(d, i) in duplicates" :key="i" class="duprow">
                        <template v-if="d.type === 'profile'">
                            <Link :href="route('app.beneficiaries.show', d.number)" target="_blank">{{ t('cv.dup_profile', { n: d.number, name: d.name }) }}</Link>
                            <button type="button" class="btn btn-navy sm" @click="openAttach(d)">{{ t('cv.attach_here', { n: d.number }) }}</button>
                        </template>
                        <Link v-else-if="d.type === 'cv'" :href="route('app.review-queue.show', d.uuid)">{{ t('cv.dup_cv', { file: d.file }) }}</Link>
                        <span v-else>{{ t('cv.dup_file', { file: d.file }) }}<template v-if="d.number"> · #{{ d.number }}</template></span>
                    </div>
                </div>

                <div v-if="unknownHeadings.length" class="teach mb-3">
                    <h4>⚠ {{ t('cv.unknown_title') }}</h4>
                    <div v-for="h in unknownHeadings" :key="h" class="duprow">
                        <span class="hl hl-u">{{ h }}</span>
                        <button v-if="teach.can" type="button" class="btn btn-line sm" @click="openTeach({ kind: 'heading', phrase: h.replace(/[:：\s]+$/, '') })">
                            <AppIcon name="sparkles" :size="13" />{{ t('rules.teach') }}
                        </button>
                    </div>
                    <p class="mt-2">{{ t(teach.can ? 'rules.unknown_text' : 'cv.unknown_text') }}</p>
                </div>

                <BeneficiaryForm :key="formKey" ref="formRef" :initial="initial" :options="options" :backbone="backbone"
                                 :marks="doc.marks" :notes="doc.notes" :extra="extra"
                                 :submit="{ method: 'post', url: route('app.review-queue.approve', doc.uuid) }">
                    <template #occupation-help>
                        <div v-if="occ" class="occhelp">
                            <div class="small mute" v-if="occ.title">{{ t('cv.occ_from', { title: occ.title }) }}</div>
                            <div class="small" :class="['exact', 'rule'].includes(occ.status) ? 'c-green' : 'c-orange'">{{ t(`cv.occ_${occ.status}`) }}</div>
                            <div v-if="occ.status !== 'exact' && occ.candidates.length" class="opts mt-2">
                                <button v-for="(c, i) in occ.candidates" :key="i" type="button" class="opt" @click="useOccupation(c.block)">
                                    <span class="grow">{{ title(c.block) }}<span v-if="c.match" class="mute small"> · {{ c.match }}</span></span>
                                    <span class="tag plain">{{ t('cv.match', { n: c.score }) }}</span>
                                    <span class="btn btn-line xs">{{ t('cv.use') }}</span>
                                </button>
                            </div>
                            <label v-if="teach.can && teach.title" class="learn mt-2">
                                <input v-model="learnTitle" type="checkbox">
                                <span>{{ t('rules.remember_title', { title: teach.title }) }}</span>
                            </label>
                        </div>
                    </template>

                    <template #actions="{ save, processing }">
                        <div class="stickyacts">
                            <span class="grow">
                                <template v-if="marked"><b class="c-orange">{{ marked }}</b> {{ t('cv.marked_left') }}</template>
                                <template v-else>{{ t('cv.all_clear') }}</template>
                            </span>
                            <button type="button" class="btn btn-line" :disabled="processing || decided" @click="rejecting = true">{{ t('cv.reject') }}</button>
                            <button type="button" class="btn btn-line" :disabled="processing || decided" @click="openAttach()">{{ t('cv.attach') }}</button>
                            <button type="submit" class="btn btn-primary" :class="{ 'is-loading': processing }" :disabled="processing || decided">
                                <AppIcon name="check" :size="15" />{{ t('cv.approve') }}
                            </button>
                        </div>
                    </template>
                </BeneficiaryForm>
            </div>
        </div>

        <ConfirmDialog :show="rejecting" danger :message="t('cv.reject_confirm')" :confirm-label="t('cv.reject')"
                       :processing="rejectForm.processing" @confirm="reject" @close="rejecting = false" />

        <Modal :show="attaching" size="md" :title="t('cv.attach_title')" @close="attaching = false">
            <template v-if="!who">
                <p class="small mute">{{ t('cv.find_text') }}</p>
                <Field :label="t('cv.find_label')" :error="attachForm.errors.number || attachForm.errors.cv">
                    <input v-model="findQ" type="search" autofocus :placeholder="t('cv.find_ph')">
                </Field>
                <div v-if="finding" class="small mute">{{ t('cv.finding') }}</div>
                <div v-else-if="findQ.trim() && !found.length" class="small mute">{{ t('cv.find_none') }}</div>
                <div class="opts">
                    <button v-for="p in found" :key="p.number" type="button" class="opt" @click="who = p">
                        <span class="grow"><b>#{{ p.number }} · {{ p.name }}</b>
                            <span class="mute small"><template v-if="p.phone"> · <bdi>{{ p.phone }}</bdi></template><template v-if="p.city"> · {{ p.city }}</template></span></span>
                        <span class="btn btn-line xs">{{ t('cv.choose') }}</span>
                    </button>
                </div>
            </template>
            <template v-else>
                <div class="chosen">
                    <b>#{{ who.number }} · {{ who.name }}</b>
                    <button type="button" class="linkbtn" @click="who = null">{{ t('cv.choose_other') }}</button>
                </div>
                <p class="small mute mt-2">{{ t(canCompare ? 'cv.attach_choose' : 'cv.attach_text') }}</p>
                <div v-if="attachForm.errors.number || attachForm.errors.cv" class="small c-danger">{{ attachForm.errors.number || attachForm.errors.cv }}</div>
            </template>
            <template #footer>
                <button type="button" class="btn btn-line" @click="attaching = false">{{ t('common.cancel') }}</button>
                <template v-if="who">
                    <button type="button" class="btn btn-line" :class="{ 'is-loading': attachForm.processing }" :disabled="attachForm.processing" @click="attach">{{ t('cv.attach_only') }}</button>
                    <button v-if="canCompare" type="button" class="btn btn-primary" @click="compare"><AppIcon name="layers" :size="14" />{{ t('cv.compare') }}</button>
                </template>
            </template>
        </Modal>

        <Modal :show="teaching" size="md" :title="t('rules.teach_title')" @close="teaching = false">
            <RuleFields :form="ruleForm" :sections="teach.sections" />
            <p class="small mute mt-2">{{ t('rules.teach_note') }}</p>
            <template #footer>
                <button type="button" class="btn btn-line" @click="teaching = false">{{ t('common.cancel') }}</button>
                <button type="button" class="btn btn-primary" :class="{ 'is-loading': ruleForm.processing }" :disabled="ruleForm.processing" @click="saveRule">
                    <AppIcon name="sparkles" :size="14" />{{ t('rules.teach_save') }}
                </button>
            </template>
        </Modal>
    </AppLayout>
</template>

<style scoped>
.duprow { display: flex; gap: 10px; align-items: center; justify-content: space-between; flex-wrap: wrap; font-size: 13px; margin-top: 8px; }
.teach h4 .hl { margin-inline-start: 4px; }
.occhelp { margin-bottom: 12px; }
.opt { width: 100%; text-align: start; }
.opt .grow { flex: 1; min-width: 0; font-size: 13px; }
.btn.xs { padding: 3px 8px; font-size: 11.5px; }
.learn { display: flex; gap: 8px; align-items: flex-start; font-size: 12.5px; cursor: pointer; }
.learn input { margin-top: 2px; }
.opts .opt + .opt { margin-top: 6px; }
.chosen { display: flex; justify-content: space-between; gap: 10px; align-items: center; padding: 10px 12px; border: 1px solid var(--ms-border); border-radius: 8px; }
.linkbtn { background: none; border: 0; padding: 0; color: var(--ms-navy); cursor: pointer; font-size: 12px; }
</style>
