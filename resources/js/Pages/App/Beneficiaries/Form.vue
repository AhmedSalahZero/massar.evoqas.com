<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — Register / edit a beneficiary
//  Location: resources/js/Pages/App/Beneficiaries/Form.vue
//  Routes: GET /app/beneficiaries/create        (beneficiaries.create)
//          GET /app/beneficiaries/{number}/edit (beneficiaries.edit)
//  Saves to App\BeneficiaryController@store / @update, which check
//  everything again (SaveBeneficiaryRequest).
//
//  The form itself is the shared BeneficiaryForm component (the CV
//  review screen uses the same one). Only a name, gender, governorate
//  and a mobile or email are needed to register; the rest can be
//  completed later. If the same mobile or email already belongs to
//  someone in this workspace, the server says who, and the case
//  worker confirms it is a different person or opens that one.
// ══════════════════════════════════════════════════════════════════

import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import BeneficiaryForm from '@/Components/Beneficiaries/BeneficiaryForm.vue';
import { useTranslations } from '@/composables/useTranslations';
import { names } from '@/Components/Beneficiaries/ben';

const props = defineProps({
    beneficiary: { type: Object, default: null },
    options: { type: Object, required: true },
    backbone: { type: Boolean, default: true },
});

const { t, locale } = useTranslations();
const editing = computed(() => !!props.beneficiary);
const b = props.beneficiary ?? {};
const title = computed(() => (editing.value ? t('ben.edit_title', { name: names(b, locale.value)[0] }) : t('ben.register')));
const back = computed(() => (editing.value ? route('app.beneficiaries.show', b.number) : route('app.beneficiaries.index')));
</script>

<template>
    <AppLayout :title="title">
        <Link :href="back" class="back">
            <AppIcon name="arrow-left" :size="14" />{{ editing ? names(b, locale)[0] : t('ben.title') }}
        </Link>

        <div class="page-head">
            <div>
                <div v-if="editing" class="page-eyebrow"><bdi dir="ltr">#{{ b.number }}</bdi></div>
                <h1 class="page-title">{{ title }}</h1>
                <div class="page-sub">{{ t(editing ? 'ben.edit_sub' : 'ben.register_sub') }}</div>
            </div>
        </div>

        <BeneficiaryForm :initial="beneficiary" :options="options" :backbone="backbone" :cancel-href="back"
                         :submit="editing ? { method: 'patch', url: route('app.beneficiaries.update', b.number) } : { method: 'post', url: route('app.beneficiaries.store') }"
                         :submit-label="editing ? t('common.save_changes') : t('ben.register_save')" />
    </AppLayout>
</template>
