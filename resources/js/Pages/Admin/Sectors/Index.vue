<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — Sectors (Super Admin · Step 10.5)
//  Location: resources/js/Pages/Admin/Sectors/Index.vue
//  Route: GET /admin/sectors (platform.backbone) — Admin\SectorProposalController
//
//  "Other" sub-sectors typed on jobs, most used first. For each:
//    Add to the list   a new official sub-sector (English + Arabic name)
//    It is …           an existing sub-sector
//    Decline           the jobs keep what was typed
//  Adding or choosing an existing one moves every job that typed it to
//  the official sub-sector. Below: the official list.
//  The list itself comes from employers.xlsx (php artisan employers:import).
// ══════════════════════════════════════════════════════════════════

import { reactive, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Modal from '@/Components/Modal.vue';
import Field from '@/Components/Field.vue';
import { useTranslations } from '@/composables/useTranslations';

const props = defineProps({
    sectors: { type: Array, default: () => [] },
    open: { type: Array, default: () => [] },
    resolved: { type: Array, default: () => [] },
});
const { t, locale } = useTranslations();
const name = (x) => (locale.value === 'ar' ? x.name_ar : x.name_en);
const sectorName = (code) => { const s = props.sectors.find((x) => x.code === code); return s ? name(s) : code; };
const subName = (code) => { for (const s of props.sectors) { const x = s.subs.find((y) => y.code === code); if (x) return `${code} · ${name(x)}`; } return code; };

const adding = ref(null);
const addForm = reactive({ name_en: '', name_ar: '', errors: {} });
function startAdd(p) { adding.value = p; addForm.name_en = /[a-z]/i.test(p.label) ? p.label : ''; addForm.name_ar = /[؀-ۿ]/.test(p.label) ? p.label : ''; addForm.errors = {}; }
function add() {
    router.post(route('admin.sectors.add', adding.value.id), { name_en: addForm.name_en, name_ar: addForm.name_ar }, {
        preserveScroll: true, onSuccess: () => { adding.value = null; }, onError: (e) => { addForm.errors = e; },
    });
}
const mergeTo = reactive({});
const merge = (p) => mergeTo[p.id] && router.post(route('admin.sectors.merge', p.id), { code: mergeTo[p.id] }, { preserveScroll: true });
const decline = (p) => router.post(route('admin.sectors.decline', p.id), {}, { preserveScroll: true });
</script>

<template>
    <AdminLayout :title="t('sec.title')">
        <div class="page-head">
            <div>
                <h1 class="page-title">{{ t('sec.title') }}</h1>
                <div class="page-sub">{{ t('sec.sub') }}</div>
            </div>
        </div>

        <div class="panel">
            <h3><AppIcon name="inbox" :size="16" />{{ t('sec.proposals') }}</h3>
            <div class="sub">{{ t('sec.proposals_sub') }}</div>
            <EmptyState v-if="!open.length" icon="check-circle" :title="t('sec.none_title')" :text="t('sec.none_text')" />
            <div v-for="p in open" :key="p.id" class="prow">
                <div class="grow min0">
                    <b>{{ p.label }}</b>
                    <div class="small mute">{{ sectorName(p.sector) }} · {{ t('sec.times', { n: p.times }) }}</div>
                </div>
                <button type="button" class="btn btn-primary sm" @click="startAdd(p)"><AppIcon name="plus" :size="13" />{{ t('sec.add') }}</button>
                <select v-model="mergeTo[p.id]" class="inp sm-sel" @change="merge(p)">
                    <option :value="undefined">{{ t('sec.is_existing') }}</option>
                    <option v-for="x in (sectors.find((s) => s.code === p.sector)?.subs ?? [])" :key="x.code" :value="x.code">{{ name(x) }}</option>
                </select>
                <button type="button" class="btn btn-ghost sm" @click="decline(p)">{{ t('sec.decline') }}</button>
            </div>
        </div>

        <div class="panel mt-4">
            <h3><AppIcon name="layers" :size="16" />{{ t('sec.official') }}</h3>
            <div class="sub">{{ t('sec.official_sub') }}</div>
            <div class="cols">
                <div v-for="s in sectors" :key="s.code">
                    <b>{{ name(s) }}</b>
                    <ul><li v-for="x in s.subs" :key="x.code"><span class="mute small">{{ x.code }}</span> {{ name(x) }}</li></ul>
                </div>
            </div>
        </div>

        <div v-if="resolved.length" class="panel mt-4">
            <h3><AppIcon name="activity" :size="16" />{{ t('sec.resolved') }}</h3>
            <div v-for="p in resolved" :key="p.id" class="small rrow">
                <b>{{ p.label }}</b> — {{ t(`sec.st_${p.status}`) }}<template v-if="p.code"> · {{ subName(p.code) }}</template>
            </div>
        </div>

        <Modal :show="!!adding" :title="t('sec.add_title')" size="sm" @close="adding = null">
            <p class="small mute">{{ t('sec.add_sub', { sector: adding ? sectorName(adding.sector) : '' }) }}</p>
            <Field :label="t('sec.name_en')" :error="addForm.errors.name_en"><input v-model="addForm.name_en" type="text" dir="ltr" maxlength="120"></Field>
            <Field :label="t('sec.name_ar')" :error="addForm.errors.name_ar"><input v-model="addForm.name_ar" type="text" dir="rtl" maxlength="120"></Field>
            <template #footer>
                <button type="button" class="btn btn-line" @click="adding = null">{{ t('common.cancel') }}</button>
                <button type="button" class="btn btn-primary" @click="add">{{ t('sec.add') }}</button>
            </template>
        </Modal>
    </AdminLayout>
</template>

<style scoped>
.prow { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; padding: 10px 0; border-top: 1px solid var(--ms-border); }
.prow:first-of-type { border-top: 0; }
.sm-sel { width: auto; max-width: 240px; padding: 5px 8px; font-size: 12px; }
.cols { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; }
.cols ul { margin: 6px 0 0; padding-inline-start: 16px; font-size: 13px; display: grid; gap: 2px; }
.rrow + .rrow { margin-top: 4px; }
.grow { flex: 1; }
.min0 { min-width: 0; }
@media (max-width: 760px) { .cols { grid-template-columns: 1fr; } }
</style>
