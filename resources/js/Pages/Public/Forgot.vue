<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — job seekers: "Forgot your password?" (Step 10)
//  Location: resources/js/Pages/Public/Forgot.vue
//  Route: GET/POST /sign-in/forgot — Public\SeekerAuthController
// ══════════════════════════════════════════════════════════════════

import { Link, useForm } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import Field from '@/Components/Field.vue';
import { useTranslations } from '@/composables/useTranslations';

defineProps({ status: { type: String, default: null } });
const { t } = useTranslations();
const form = useForm({ email: '' });
const submit = () => form.post(route('seeker.password.email'));
</script>

<template>
    <PublicLayout :title="t('pub.forgot')" narrow>
        <div class="panel box">
            <h1>{{ t('pub.forgot') }}</h1>
            <div class="sub">{{ t('pub.forgot_sub') }}</div>
            <div v-if="status" class="alert success mb-3"><AppIcon name="check" :size="16" /><span>{{ status }}</span></div>
            <form @submit.prevent="submit">
                <Field :label="t('ben.f_email')" :error="form.errors.email"><input v-model="form.email" type="email" dir="ltr" autocomplete="email" required autofocus></Field>
                <button type="submit" class="btn btn-primary block mt-4" :class="{ 'is-loading': form.processing }" :disabled="form.processing">{{ t('pub.send_link') }}</button>
            </form>
            <div class="auth-links"><Link :href="route('seeker.login')">{{ t('pub.back_sign_in') }}</Link></div>
        </div>
    </PublicLayout>
</template>

<style scoped>
.box { max-width: 440px; margin: 20px auto 0; }
.box h1 { font-size: 22px; margin: 0 0 4px; }
.sub { color: var(--ms-text-muted); font-size: 13px; margin-bottom: 18px; }
</style>
