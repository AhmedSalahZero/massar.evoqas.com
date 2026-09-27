<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — job seekers: choose a new password (from the emailed link)
//  Location: resources/js/Pages/Public/Reset.vue
//  Route: GET /sign-in/reset/{token}, POST /sign-in/reset — Public\SeekerAuthController
// ══════════════════════════════════════════════════════════════════

import { useForm } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import Field from '@/Components/Field.vue';
import PasswordInput from '@/Components/PasswordInput.vue';
import { useTranslations } from '@/composables/useTranslations';

const props = defineProps({ token: { type: String, required: true }, email: { type: String, default: '' } });
const { t } = useTranslations();
const form = useForm({ token: props.token, email: props.email, password: '', password_confirmation: '' });
const submit = () => form.post(route('seeker.password.store'), { onFinish: () => form.reset('password', 'password_confirmation') });
</script>

<template>
    <PublicLayout :title="t('pub.new_password')" narrow>
        <div class="panel box">
            <h1>{{ t('pub.new_password') }}</h1>
            <form @submit.prevent="submit">
                <Field :label="t('ben.f_email')" :error="form.errors.email"><input v-model="form.email" type="email" dir="ltr" autocomplete="email" required></Field>
                <Field :label="t('join.password')" :error="form.errors.password" :hint="t('join.password_hint')"><PasswordInput v-model="form.password" autocomplete="new-password" /></Field>
                <Field :label="t('join.password_again')"><PasswordInput v-model="form.password_confirmation" autocomplete="new-password" /></Field>
                <button type="submit" class="btn btn-primary block mt-4" :class="{ 'is-loading': form.processing }" :disabled="form.processing">{{ t('pub.save_password') }}</button>
            </form>
        </div>
    </PublicLayout>
</template>

<style scoped>
.box { max-width: 440px; margin: 20px auto 0; }
.box h1 { font-size: 22px; margin: 0 0 14px; }
</style>
