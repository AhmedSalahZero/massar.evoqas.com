<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — Verify Email page (6-digit code)
//  Location: resources/js/Pages/Auth/VerifyEmail.vue
//  Route: GET /verify-email (verification.notice)
//         → POST /verify-email (verification.verify-code)
//         → POST /verify-email/resend (verification.resend)
//
//  Works while signed out: `email` comes from the server (the address
//  LoginRequest just emailed). With no email known, the page asks the
//  person to sign in again. `?sent=0` means a code was emailed very
//  recently, so none was sent this time.
// ══════════════════════════════════════════════════════════════════

import { computed } from 'vue';
import { Link, useForm } from '@inertiajs/vue3';
import AuthLayout from '@/Layouts/AuthLayout.vue';
import Field from '@/Components/Field.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { useTranslations } from '@/composables/useTranslations';

const props = defineProps({
    email: { type: String, default: null },
    status: { type: String, default: null },
});
const { t, locale } = useTranslations();

const cameFromLogin = computed(() => new URLSearchParams(window.location.search).get('sent'));

const form = useForm({ email: props.email ?? '', code: '', locale: locale.value });
const resend = useForm({ email: props.email ?? '', locale: locale.value });

function submit() {
    form.locale = locale.value;
    form.post(route('verification.verify-code'));
}
function sendAgain() {
    resend.locale = locale.value;
    resend.post(route('verification.resend'), { preserveScroll: true });
}
</script>

<template>
    <AuthLayout :title="t('auth.verify_title')">
        <h1>{{ t('auth.verify_title') }}</h1>

        <template v-if="email">
            <p class="sub">{{ t('auth.verify_sub') }}</p>

            <div v-if="cameFromLogin !== null" class="alert warning mb-4">
                <AppIcon name="mail" :size="16" />
                <span class="grow">{{ cameFromLogin === '1' ? t('auth.needs_verification') : t('auth.needs_verification_wait') }}</span>
            </div>
            <div v-if="status === 'verification-code-sent'" class="alert success mb-4">
                <span class="grow">{{ t('auth.resend_sent') }}</span>
            </div>

            <p class="small mute">{{ t('auth.code_sent_to') }} <b class="ltr" style="color:var(--ms-text-primary)">{{ email }}</b></p>

            <form @submit.prevent="submit" novalidate>
                <Field :label="t('auth.code')" :error="form.errors.code || form.errors.email">
                    <input v-model="form.code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="6"
                           dir="ltr" autofocus style="font-size:22px;letter-spacing:.4em;text-align:center;font-weight:800">
                </Field>
                <button type="submit" class="btn btn-primary lg block" :class="{ 'is-loading': form.processing }" :disabled="form.processing">
                    {{ t('auth.verify_submit') }}
                </button>
            </form>

            <div class="auth-links">
                <button type="button" class="linkbtn" :disabled="resend.processing" @click="sendAgain">{{ t('auth.resend') }}</button>
                <Link :href="route('login')" class="linkbtn">{{ t('auth.back_to_login') }}</Link>
            </div>
        </template>

        <template v-else>
            <div class="alert warning mt-4"><AppIcon name="info" :size="16" /><span class="grow">{{ t('auth.verify_unknown') }}</span></div>
            <Link :href="route('login')" class="btn btn-primary lg block">{{ t('auth.login') }}</Link>
        </template>
    </AuthLayout>
</template>
