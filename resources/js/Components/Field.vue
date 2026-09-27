<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — Field
//  Location: resources/js/Components/Field.vue
//
//  The label + input + hint + error block every form uses, so forms
//  stay short and every field looks and behaves the same:
//
//      <Field :label="t('common.email')" :error="form.errors.email" required>
//          <input v-model="form.email" type="email">
//      </Field>
//
//  Adds the .has-error style when `error` is set. `wide` spans both
//  columns inside a .form-grid. `optional` shows a quiet "optional".
// ══════════════════════════════════════════════════════════════════

import AppIcon from '@/Components/AppIcon.vue';
import { useTranslations } from '@/composables/useTranslations';

defineProps({
    label: { type: String, default: '' },
    error: { type: String, default: '' },
    hint: { type: String, default: '' },
    required: { type: Boolean, default: false },
    optional: { type: Boolean, default: false },
    wide: { type: Boolean, default: false },
});
const { t } = useTranslations();
</script>

<template>
    <label class="fld" :class="{ 'has-error': !!error, wide }">
        <span v-if="label" class="fl">
            <span>{{ label }}<span v-if="required" class="req">*</span></span>
            <span v-if="optional" class="optional">{{ t('common.optional') }}</span>
            <slot name="label-end" />
        </span>
        <slot />
        <small v-if="hint && !error">{{ hint }}</small>
        <span v-if="error" class="fld-error"><AppIcon name="alert" :size="12" />{{ error }}</span>
    </label>
</template>
