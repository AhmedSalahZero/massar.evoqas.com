<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — Learned Rules (Scope v2 §3, layer 1)
//  Location: resources/js/Pages/App/Rules/Index.vue
//  Route: GET /app/rules (rules.view) — App\LearnedRuleController@index
//
//  Two tabs:
//    Our rules     what this workspace taught the CV reading engine:
//                  add, change, delete (rules.manage), propose to
//                  Massar / take back a proposal (rules.propose), and
//                  the Super Admin's answer.
//    Massar rules  rules every partner uses (read-only here). A rule of
//                  ours for the same words comes first.
// ══════════════════════════════════════════════════════════════════

import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import Modal from '@/Components/Modal.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Pagination from '@/Components/Pagination.vue';
import RuleFields from '@/Components/Rules/RuleFields.vue';
import { usePermissions } from '@/composables/usePermissions';
import { usePreferences } from '@/composables/usePreferences';
import { useTranslations } from '@/composables/useTranslations';
import { formatDate } from '@/Utils/date';
import { emptyRule, meaning, ruleData, PROPOSAL_BADGE } from '@/Components/Rules/rules';

const props = defineProps({
    list: { type: Object, required: true },
    tab: { type: String, required: true },
    kind: { type: String, default: '' },
    q: { type: String, default: '' },
    counts: { type: Object, required: true },
    sections: { type: Array, required: true },
    backbone: { type: Boolean, default: true },
});

const { t, locale } = useTranslations();
const { can } = usePermissions();
const { prefs } = usePreferences();

const search = ref(props.q);
const go = (over = {}) => router.get(route('app.rules.index'),
    { tab: props.tab, kind: props.kind || undefined, q: search.value || undefined, ...over }, { preserveState: true, preserveScroll: true, replace: true });
let timer = null;
const onSearch = () => { clearTimeout(timer); timer = setTimeout(() => go({ page: undefined }), 350); };

// ── Add / change ──────────────────────────────────────────────────
const editing = ref(null);          // null = closed, 0 = new, id = change
const form = useForm(emptyRule());
const openNew = () => { form.defaults(emptyRule()); form.reset(); form.clearErrors(); editing.value = 0; };
const openEdit = (r) => {
    const values = emptyRule({ kind: r.kind, phrase: r.phrase, section: r.section || '', occupation: r.occupation, skill_name: r.skill_name || '' });
    form.defaults(values); form.reset(); form.clearErrors(); editing.value = r.id;
};
const save = () => {
    const send = form.transform(ruleData);
    const opts = { preserveScroll: true, onSuccess: () => { editing.value = null; } };
    editing.value ? send.patch(route('app.rules.update', editing.value), opts) : send.post(route('app.rules.store'), opts);
};

// ── Delete / propose ──────────────────────────────────────────────
const deleting = ref(null);
const remove = () => router.delete(route('app.rules.destroy', deleting.value.id), { preserveScroll: true, onFinish: () => { deleting.value = null; } });
const propose = (r) => router.post(route('app.rules.propose', r.id), {}, { preserveScroll: true });
const withdraw = (r) => router.post(route('app.rules.withdraw', r.id), {}, { preserveScroll: true });
</script>

<template>
    <AppLayout :title="t('rules.title')">
        <div class="page-head">
            <div>
                <h1 class="page-title">{{ t('rules.title') }}</h1>
                <div class="page-sub">{{ t('rules.sub') }}</div>
            </div>
            <div v-if="tab === 'ours' && can('rules.manage')" class="page-actions">
                <button type="button" class="btn btn-primary" @click="openNew"><AppIcon name="plus" :size="15" />{{ t('rules.add') }}</button>
            </div>
        </div>

        <div class="toolbar mb-4">
            <div class="seg" role="group">
                <button type="button" :aria-pressed="tab === 'ours'" @click="go({ tab: 'ours', page: undefined })">{{ t('rules.tab_ours') }} · {{ counts.ours }}</button>
                <button type="button" :aria-pressed="tab === 'massar'" @click="go({ tab: 'massar', page: undefined })">{{ t('rules.tab_massar') }} · {{ counts.massar }}</button>
            </div>
            <div class="seg" role="group">
                <button type="button" :aria-pressed="!kind" @click="go({ kind: undefined, page: undefined })">{{ t('rules.all_kinds') }}</button>
                <button v-for="k in ['heading', 'title', 'skill', 'employer']" :key="k" type="button" :aria-pressed="kind === k" @click="go({ kind: k, page: undefined })">{{ t(`rules.kinds_${k}`) }}</button>
            </div>
            <input v-model="search" type="search" class="grow" :placeholder="t('rules.search')" @input="onSearch">
        </div>

        <p v-if="tab === 'massar'" class="small mute mb-3">{{ t('rules.massar_note') }}</p>

        <EmptyState v-if="!list.data.length" icon="book" :title="t(tab === 'ours' ? 'rules.empty_title' : 'rules.empty_massar_title')"
                    :text="t(tab === 'ours' ? 'rules.empty' : 'rules.empty_massar')" />

        <div v-else class="table-wrap compact">
            <table>
                <thead>
                    <tr>
                        <th>{{ t('rules.col_words') }}</th>
                        <th>{{ t('rules.col_means') }}</th>
                        <th>{{ t('rules.col_used') }}</th>
                        <th v-if="tab === 'ours'">{{ t('rules.col_massar') }}</th>
                        <th />
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="r in list.data" :key="r.id">
                        <td>
                            <span class="badge plain">{{ t(`rules.kind_${r.kind}`) }}</span>
                            <b class="phrase"><bdi>{{ r.phrase }}</bdi></b>
                            <div class="small mute">{{ t(r.source === 'review' ? 'rules.taught_review' : r.source === 'promoted' ? 'rules.taught_promoted' : 'rules.taught_page', { by: r.by || '—', date: formatDate(r.at, locale) }) }}</div>
                        </td>
                        <td>→ {{ meaning(r, t, prefs.standard, locale) }}</td>
                        <td>
                            {{ t('rules.uses', { n: r.uses }) }}
                            <div v-if="r.last_used_at" class="small mute">{{ t('rules.last_used', { date: formatDate(r.last_used_at, locale) }) }}</div>
                        </td>
                        <td v-if="tab === 'ours'">
                            <template v-if="r.proposal">
                                <span class="badge" :class="PROPOSAL_BADGE[r.proposal.status]">{{ t(`rules.p_${r.proposal.status}`) }}</span>
                                <div v-if="r.proposal.note" class="small mute mt-1">“{{ r.proposal.note }}”</div>
                            </template>
                            <span v-else class="mute small">—</span>
                        </td>
                        <td class="acts">
                            <template v-if="tab === 'ours'">
                                <button v-if="can('rules.propose') && r.proposal?.status !== 'pending' && r.proposal?.status !== 'promoted'" type="button" class="btn btn-line xs" @click="propose(r)">{{ t('rules.propose') }}</button>
                                <button v-if="can('rules.propose') && r.proposal?.status === 'pending'" type="button" class="btn btn-line xs" @click="withdraw(r)">{{ t('rules.withdraw') }}</button>
                                <button v-if="can('rules.manage')" type="button" class="btn btn-line xs" @click="openEdit(r)">{{ t('common.edit') }}</button>
                                <button v-if="can('rules.manage')" type="button" class="btn btn-line xs c-danger" @click="deleting = r">{{ t('common.delete') }}</button>
                            </template>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <Pagination v-if="list.data.length" class="mt-4" :paginator="list" />

        <Modal :show="editing !== null" size="md" :title="t(editing ? 'rules.edit' : 'rules.add')" @close="editing = null">
            <RuleFields :form="form" :sections="sections" />
            <template #footer>
                <button type="button" class="btn btn-line" @click="editing = null">{{ t('common.cancel') }}</button>
                <button type="button" class="btn btn-primary" :class="{ 'is-loading': form.processing }" :disabled="form.processing" @click="save">{{ t('rules.save') }}</button>
            </template>
        </Modal>

        <ConfirmDialog :show="!!deleting" danger :message="t('rules.delete_confirm', { words: deleting?.phrase || '' })" :confirm-label="t('common.delete')"
                       @confirm="remove" @close="deleting = null" />
    </AppLayout>
</template>

<style scoped>
.toolbar { display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
.toolbar input { min-width: 200px; max-width: 320px; }
.phrase { margin-inline-start: 6px; }
.acts { text-align: end; white-space: nowrap; }
.acts .btn + .btn { margin-inline-start: 6px; }
.btn.xs { padding: 3px 8px; font-size: 11.5px; }
</style>
