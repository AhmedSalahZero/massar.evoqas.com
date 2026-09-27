<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — Rule Requests (Scope v2 §3 Learned Rules, layer 2)
//  Location: resources/js/Pages/Admin/RuleRequests/Index.vue
//  Route: GET /admin/rule-requests (platform.rules) — Admin\RuleRequestController@index
//
//  Tabs:
//    Waiting       rules partners proposed. Promote → a Massar rule for
//                  every partner; Decline → with a note the partner
//                  sees. When Massar already has a rule for the same
//                  words, its current meaning is shown next to it.
//    Decided       the latest answers.
//    Massar rules  the rules every partner uses; one can be removed.
//  Only the words and their meaning are shown — never a CV or a person.
// ══════════════════════════════════════════════════════════════════

import { ref } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import Modal from '@/Components/Modal.vue';
import ConfirmDialog from '@/Components/ConfirmDialog.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Field from '@/Components/Field.vue';
import Pagination from '@/Components/Pagination.vue';
import { usePreferences } from '@/composables/usePreferences';
import { useTranslations } from '@/composables/useTranslations';
import { formatDate } from '@/Utils/date';
import { meaning, PROPOSAL_BADGE } from '@/Components/Rules/rules';

const props = defineProps({
    list: { type: Object, required: true },
    tab: { type: String, required: true },
    counts: { type: Object, required: true },
});

const { t, locale } = useTranslations();
const { prefs } = usePreferences();
const show = (tab) => router.get(route('admin.rule-requests.index'), { tab }, { preserveScroll: true, replace: true });
const means = (r) => meaning(r, t, prefs.standard, locale.value);

// ── Decide ────────────────────────────────────────────────────────
const deciding = ref(null);          // { rule, action: 'promote' | 'decline' }
const form = useForm({ note: '' });
const openDecide = (rule, action) => { form.reset(); form.clearErrors(); deciding.value = { rule, action }; };
const decide = () => form.post(route(`admin.rule-requests.${deciding.value.action}`, deciding.value.rule.id), {
    preserveScroll: true, onSuccess: () => { deciding.value = null; },
});

const removing = ref(null);
const remove = () => router.delete(route('admin.massar-rules.destroy', removing.value.id), { preserveScroll: true, onFinish: () => { removing.value = null; } });
</script>

<template>
    <AdminLayout :title="t('rules.req_title')">
        <div class="page-head">
            <div>
                <h1 class="page-title">{{ t('rules.req_title') }}</h1>
                <div class="page-sub">{{ t('rules.req_sub') }}</div>
            </div>
        </div>

        <div class="seg mb-4" role="group">
            <button type="button" :aria-pressed="tab === 'pending'" @click="show('pending')">{{ t('rules.req_pending') }} · {{ counts.pending }}</button>
            <button type="button" :aria-pressed="tab === 'decided'" @click="show('decided')">{{ t('rules.req_decided') }}</button>
            <button type="button" :aria-pressed="tab === 'massar'" @click="show('massar')">{{ t('rules.tab_massar') }} · {{ counts.massar }}</button>
        </div>

        <EmptyState v-if="!list.data.length" icon="sparkles" :title="t(`rules.req_empty_${tab}`)" :text="t('rules.req_empty_text')" />

        <div v-else class="table-wrap compact">
            <table>
                <thead>
                    <tr>
                        <th>{{ t('rules.col_words') }}</th>
                        <th>{{ t('rules.col_means') }}</th>
                        <th>{{ tab === 'massar' ? t('rules.col_from') : t('rules.col_partner') }}</th>
                        <th>{{ t('rules.col_used') }}</th>
                        <th />
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="r in list.data" :key="r.id">
                        <td>
                            <span class="badge plain">{{ t(`rules.kind_${r.kind}`) }}</span>
                            <b class="phrase"><bdi>{{ r.phrase }}</bdi></b>
                        </td>
                        <td>
                            → {{ means(r) }}
                            <div v-if="r.massar_now" class="small c-orange mt-1">
                                <AppIcon name="alert" :size="12" /> {{ t('rules.req_replaces', { means: means(r.massar_now) }) }}
                            </div>
                        </td>
                        <td>
                            {{ r.company || '—' }}
                            <div v-if="tab !== 'massar' && r.proposal" class="small mute">{{ t('rules.req_by', { by: r.proposal.by || '—', date: formatDate(r.proposal.at, locale) }) }}</div>
                        </td>
                        <td>{{ t('rules.uses', { n: r.uses }) }}</td>
                        <td class="acts">
                            <template v-if="tab === 'pending'">
                                <button type="button" class="btn btn-primary xs" @click="openDecide(r, 'promote')"><AppIcon name="check" :size="13" />{{ t('rules.promote') }}</button>
                                <button type="button" class="btn btn-line xs" @click="openDecide(r, 'decline')">{{ t('rules.decline') }}</button>
                            </template>
                            <template v-else-if="tab === 'decided' && r.proposal">
                                <span class="badge" :class="PROPOSAL_BADGE[r.proposal.status]">{{ t(`rules.p_${r.proposal.status}`) }}</span>
                                <div class="small mute">{{ r.proposal.decided_by }} · {{ formatDate(r.proposal.decided_at, locale) }}</div>
                                <div v-if="r.proposal.note" class="small mute">“{{ r.proposal.note }}”</div>
                            </template>
                            <button v-else-if="tab === 'massar'" type="button" class="btn btn-line xs c-danger" @click="removing = r">{{ t('common.delete') }}</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <Pagination v-if="list.data.length" class="mt-4" :paginator="list" />

        <Modal :show="!!deciding" size="sm" :title="deciding ? t(`rules.${deciding.action}_title`) : ''" @close="deciding = null">
            <template v-if="deciding">
                <p class="small">
                    <b><bdi>{{ deciding.rule.phrase }}</bdi></b> → {{ means(deciding.rule) }}
                </p>
                <p class="small mute">{{ t(`rules.${deciding.action}_text`) }}</p>
                <Field :label="t('rules.note')" :error="form.errors.note" optional>
                    <textarea v-model="form.note" rows="2" maxlength="300" :placeholder="t('rules.note_ph')"></textarea>
                </Field>
            </template>
            <template #footer>
                <button type="button" class="btn btn-line" @click="deciding = null">{{ t('common.cancel') }}</button>
                <button type="button" class="btn" :class="[deciding?.action === 'promote' ? 'btn-primary' : 'btn-line', { 'is-loading': form.processing }]" :disabled="form.processing" @click="decide">
                    {{ deciding ? t(`rules.${deciding.action}`) : '' }}
                </button>
            </template>
        </Modal>

        <ConfirmDialog :show="!!removing" danger :message="t('rules.massar_delete_confirm', { words: removing?.phrase || '' })" :confirm-label="t('common.delete')"
                       @confirm="remove" @close="removing = null" />
    </AdminLayout>
</template>

<style scoped>
.phrase { margin-inline-start: 6px; }
.acts { text-align: end; white-space: nowrap; }
.acts .btn + .btn { margin-inline-start: 6px; }
.btn.xs { padding: 3px 8px; font-size: 11.5px; }
</style>
