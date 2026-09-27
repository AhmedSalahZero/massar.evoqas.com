<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — confirm your email with the 6-digit code (Step 10)
//  Location: resources/js/Pages/Public/Verify.vue
//  Route: GET/POST /join/verify (seeker.verify) — Public\SeekerAuthController
//  The profile is shown to partners only after this.
// ══════════════════════════════════════════════════════════════════

import { router, useForm } from '@inertiajs/vue3';
import PublicLayout from '@/Layouts/PublicLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import Field from '@/Components/Field.vue';
import { useTranslations } from '@/composables/useTranslations';

defineProps({ email: { type: String, required: true } });
const { t } = useTranslations();
const form = useForm({ code: '' });
const submit = () => form.post(route('seeker.verify.store'));
const resend = () => router.post(route('seeker.verify.resend'), {}, { preserveScroll: true });
</script>

<template>
    <PublicLayout :title="t('pub.verify_title')" narrow>
        <div class="panel box">
            <div class="big-ic"><AppIcon name="mail" :size="26" /></div>
            <h1>{{ t('pub.verify_title') }}</h1>
            <p class="mute">{{ t('pub.verify_sub') }} <b dir="ltr">{{ email }}</b></p>
            <form @submit.prevent="submit">
                <Field :label="t('pub.code')" :error="form.errors.code">
                    <input v-model="form.code" type="text" inputmode="numeric" autocomplete="one-time-code" maxlength="8" dir="ltr" class="code" required autofocus>
                </Field>
                <button type="submit" class="btn btn-primary block mt-4" :class="{ 'is-loading': form.processing }" :disabled="form.processing">{{ t('pub.confirm') }}</button>
            </form>
            <p class="small mute mt-3">{{ t('pub.no_code') }} <button type="button" class="linkbtn" @click="resend">{{ t('pub.resend') }}</button></p>
        </div>
    </PublicLayout>
</template>

<style scoped>
.box { max-width: 440px; margin: 20px auto 0; text-align: center; }
.box h1 { font-size: 22px; margin: 10px 0 4px; }
.box :deep(.fld) { text-align: start; }
.big-ic { width: 56px; height: 56px; border-radius: 16px; margin: 0 auto; display: grid; place-items: center; background: var(--ms-teal-dim); color: var(--ms-teal); }
.code { font-size: 22px; letter-spacing: .35em; text-align: center; }
.linkbtn { background: none; border: 0; padding: 0; color: var(--ms-navy); cursor: pointer; font-size: inherit; }
</style>
