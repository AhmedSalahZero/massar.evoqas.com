<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — job seekers' sign-in (Step 10)
//  Location: resources/js/Pages/Public/SignIn.vue
//  Route: GET/POST /sign-in (seeker.login) — Public\SeekerAuthController
//  Separate from the staff sign-in (/login); a note points staff there.
// ══════════════════════════════════════════════════════════════════

import { Link, useForm } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import Field from '@/Components/Field.vue';
import PasswordInput from '@/Components/PasswordInput.vue';
import { useTranslations } from '@/composables/useTranslations';

defineProps({ status: { type: String, default: null } });
const { t } = useTranslations();
const form = useForm({ email: '', password: '', remember: false });
const submit = () => form.post(route('seeker.login'), { onFinish: () => form.reset('password') });
</script>

<template>
    <PublicLayout :title="t('pub.sign_in')" narrow>
        <div class="panel box">
            <h1>{{ t('pub.welcome_back') }}</h1>
            <div class="sub">{{ t('pub.sign_in_sub') }}</div>
            <div v-if="status" class="alert success mb-3"><AppIcon name="check" :size="16" /><span>{{ status }}</span></div>
            <form @submit.prevent="submit">
                <Field :label="t('ben.f_email')" :error="form.errors.email"><input v-model="form.email" type="email" dir="ltr" autocomplete="email" required autofocus></Field>
                <Field :label="t('join.password')" :error="form.errors.password"><PasswordInput v-model="form.password" /></Field>
                <label class="checkline mt-3"><input v-model="form.remember" type="checkbox"> {{ t('pub.remember') }}</label>
                <button type="submit" class="btn btn-primary block mt-4" :class="{ 'is-loading': form.processing }" :disabled="form.processing">{{ t('pub.sign_in') }}</button>
            </form>
            <div class="auth-links">
                <Link :href="route('seeker.password.request')">{{ t('pub.forgot') }}</Link>
                <Link :href="route('seeker.join')">{{ t('pub.new_here') }}</Link>
            </div>
            <div class="divider">{{ t('join.or') }}</div>
            <div class="note"><AppIcon name="lock" :size="16" /><div class="grow">{{ t('pub.staff_q') }} <a :href="route('login')">{{ t('pub.staff_sign_in_short') }}</a></div></div>
        </div>
    </PublicLayout>
</template>

<style scoped>
.box { max-width: 440px; margin: 20px auto 0; }
.box h1 { font-size: 24px; margin: 0 0 4px; }
.sub { color: var(--ms-text-muted); font-size: 13px; margin-bottom: 18px; }
</style>
