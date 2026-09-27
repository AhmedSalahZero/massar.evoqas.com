<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — PasswordInput
//  Location: resources/js/Components/PasswordInput.vue
//
//  A password box with a show/hide eye button. Use inside <Field>:
//      <PasswordInput v-model="form.password" autocomplete="new-password" />
//  The button sits on the inline-end side, so it mirrors in Arabic.
// ══════════════════════════════════════════════════════════════════

import { ref } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import { useTranslations } from '@/composables/useTranslations';

defineProps({
    autocomplete: { type: String, default: 'current-password' },
    placeholder: { type: String, default: '' },
});
const model = defineModel({ type: String, default: '' });
const visible = ref(false);
const { t } = useTranslations();
</script>

<template>
    <div class="inp-wrap" style="position:relative">
        <input v-model="model" :type="visible ? 'text' : 'password'" :autocomplete="autocomplete"
               :placeholder="placeholder" dir="ltr" style="padding-inline-end:40px !important">
        <button type="button" class="btn-ghost"
                style="position:absolute;inset-inline-end:6px;border:0;background:none;color:var(--ms-text-muted);padding:6px;border-radius:6px"
                :aria-label="visible ? t('auth.hide_password') : t('auth.show_password')" @click="visible = !visible">
            <AppIcon :name="visible ? 'eye-off' : 'eye'" :size="16" />
        </button>
    </div>
</template>
