<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — Confirm Password page
//  Location: resources/js/Pages/Auth/ConfirmPassword.vue
//  Route: GET /confirm-password (password.confirm) → POST
//
//  Shown before a sensitive action protected by the
//  'password.confirm' middleware. After confirming, the person goes
//  back to what they were doing.
// ══════════════════════════════════════════════════════════════════

import { useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import Field from '@/Components/Field.vue';
import PasswordInput from '@/Components/PasswordInput.vue';
import { useTranslations } from '@/composables/useTranslations';

const { t } = useTranslations();
const form = useForm({ password: '' });

function submit() {
    form.post(route('password.confirm'), { onFinish: () => form.reset() });
}
</script>

<template>
    <AuthLayout :title="t('auth.confirm_title')">
        <h1>{{ t('auth.confirm_title') }}</h1>
        <p class="sub">{{ t('auth.confirm_sub') }}</p>
        <form @submit.prevent="submit" novalidate>
            <Field :label="t('auth.password')" :error="form.errors.password">
                <PasswordInput v-model="form.password" autocomplete="current-password" />
            </Field>
            <button type="submit" class="btn btn-primary lg block" :class="{ 'is-loading': form.processing }" :disabled="form.processing">
                {{ t('auth.confirm_submit') }}
            </button>
        </form>
    </AuthLayout>
</template>
