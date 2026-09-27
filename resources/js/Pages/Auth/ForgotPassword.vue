<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — Forgot Password page
//  Location: resources/js/Pages/Auth/ForgotPassword.vue
//  Route: GET /forgot-password (password.request) → POST (password.email)
//
//  Sends a reset link to the email. On success the server returns to
//  the login page with a "link sent" message.
// ══════════════════════════════════════════════════════════════════

import { Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import Field from '@/Components/Field.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { useTranslations } from '@/composables/useTranslations';

defineProps({ status: { type: String, default: null } });
const { t, locale } = useTranslations();
const form = useForm({ email: '', locale: locale.value });

function submit() {
    form.locale = locale.value;
    form.post(route('password.email'));
}
</script>

<template>
    <AuthLayout :title="t('auth.forgot_title')">
        <h1>{{ t('auth.forgot_title') }}</h1>
        <p class="sub">{{ t('auth.forgot_sub') }}</p>

        <div v-if="status" class="alert success mb-4"><span class="grow">{{ status }}</span></div>

        <form @submit.prevent="submit" novalidate>
            <Field :label="t('auth.email')" :error="form.errors.email">
                <input v-model="form.email" type="email" autocomplete="username" required autofocus>
            </Field>
            <button type="submit" class="btn btn-primary lg block" :class="{ 'is-loading': form.processing }" :disabled="form.processing">
                {{ t('auth.send_link') }}
            </button>
        </form>

        <div class="auth-links">
            <Link :href="route('login')" class="linkbtn"><AppIcon name="arrow-left" :size="13" /> {{ t('auth.back_to_login') }}</Link>
        </div>
    </AuthLayout>
</template>
