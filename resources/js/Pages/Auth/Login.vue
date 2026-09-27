<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — Login page
//  Location: resources/js/Pages/Auth/Login.vue
//  Route: GET /login (login) → POST /login
//
//  Email + password. The page sends the language it is showing
//  (`locale`) so server errors come back in that language.
//  If the account's email is not verified yet, LoginRequest signs
//  the person out, emails a code, and answers with the error key
//  `needs_verification` — this page then moves to the verify screen.
//  There is no "register" link: accounts are created by the
//  organisation's administrator (or the Massar team for partners).
// ══════════════════════════════════════════════════════════════════

import { Link, router, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import Field from '@/Components/Field.vue';
import PasswordInput from '@/Components/PasswordInput.vue';
import { useTranslations } from '@/composables/useTranslations';

defineProps({
    canResetPassword: { type: Boolean, default: true },
    status: { type: String, default: null },
});

const { t, locale } = useTranslations();

const form = useForm({ email: '', password: '', remember: false, locale: locale.value });

function submit() {
    form.locale = locale.value;
    form.post(route('login'), {
        onError: (errors) => {
            if (errors.needs_verification) {
                router.visit(route('verification.notice'), {
                    data: { sent: errors.verification_code_sent },
                });
            }
        },
        onFinish: () => form.reset('password'),
    });
}
</script>

<template>
    <AuthLayout :title="t('auth.login')">
        <h1>{{ t('auth.login') }}</h1>
        <p class="sub">{{ t('auth.login_sub') }}</p>

        <div v-if="status" class="alert success mb-4"><span class="grow">{{ status }}</span></div>

        <form @submit.prevent="submit" novalidate>
            <Field :label="t('auth.email')" :error="form.errors.email">
                <input v-model="form.email" type="email" autocomplete="username" required autofocus>
            </Field>

            <Field :label="t('auth.password')" :error="form.errors.password">
                <template #label-end>
                    <Link v-if="canResetPassword" :href="route('password.request')" class="linkbtn">{{ t('auth.forgot') }}</Link>
                </template>
                <PasswordInput v-model="form.password" autocomplete="current-password" />
            </Field>

            <label class="checkline mt-4">
                <input v-model="form.remember" type="checkbox">
                <span>{{ t('auth.remember') }}</span>
            </label>

            <button type="submit" class="btn btn-primary lg block" :class="{ 'is-loading': form.processing }" :disabled="form.processing">
                {{ t('auth.login') }}
            </button>
        </form>

        <p class="small mute mt-6 text-center">{{ t('auth.no_account') }}</p>
    </AuthLayout>
</template>
