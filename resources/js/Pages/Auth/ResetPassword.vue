<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — Reset Password page
//  Location: resources/js/Pages/Auth/ResetPassword.vue
//  Route: GET /reset-password/{token} (password.reset) → POST (password.store)
//
//  Reached from the emailed link. On success the person is signed in
//  and sent home.
// ══════════════════════════════════════════════════════════════════

import { useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import Field from '@/Components/Field.vue';
import PasswordInput from '@/Components/PasswordInput.vue';
import { useTranslations } from '@/composables/useTranslations';

const props = defineProps({
    email: { type: String, default: '' },
    token: { type: String, required: true },
});
const { t } = useTranslations();

const form = useForm({ token: props.token, email: props.email, password: '', password_confirmation: '' });

function submit() {
    form.post(route('password.store'), { onFinish: () => form.reset('password', 'password_confirmation') });
}
</script>

<template>
    <AuthLayout :title="t('auth.reset_title')">
        <h1>{{ t('auth.reset_title') }}</h1>
        <p class="sub">{{ t('auth.password_hint') }}</p>

        <form @submit.prevent="submit" novalidate>
            <Field :label="t('auth.email')" :error="form.errors.email">
                <input v-model="form.email" type="email" autocomplete="username" required>
            </Field>
            <Field :label="t('auth.new_password')" :error="form.errors.password">
                <PasswordInput v-model="form.password" autocomplete="new-password" />
            </Field>
            <Field :label="t('auth.confirm_new_password')" :error="form.errors.password_confirmation">
                <PasswordInput v-model="form.password_confirmation" autocomplete="new-password" />
            </Field>
            <button type="submit" class="btn btn-primary lg block" :class="{ 'is-loading': form.processing }" :disabled="form.processing">
                {{ t('auth.reset_submit') }}
            </button>
        </form>
    </AuthLayout>
</template>
