<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — My Profile page
//  Location: resources/js/Pages/Profile/Index.vue
//  Routes: /app/profile (partner staff) and /admin/profile (super admin)
//          → App\ProfileController
//
//  One page for both areas: it wraps itself in AdminLayout for a
//  super admin and AppLayout for everyone else, and posts to the
//  route names the server passes (updateRoute / passwordRoute).
//
//  Details + preferences (language, theme, occupation standard) in
//  one form; password in its own form. Saving preferences here is the
//  same as using the top-bar switches.
// ══════════════════════════════════════════════════════════════════

import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import Field from '@/Components/Field.vue';
import PasswordInput from '@/Components/PasswordInput.vue';
import { usePermissions } from '@/composables/usePermissions';
import { useTranslations } from '@/composables/useTranslations';

const props = defineProps({
    profile: { type: Object, required: true },
    updateRoute: { type: String, required: true },
    passwordRoute: { type: String, required: true },
});

const { t } = useTranslations();
const { isSuperAdmin } = usePermissions();
const Layout = computed(() => (isSuperAdmin.value ? AdminLayout : AppLayout));

const form = useForm({ ...props.profile, phone: props.profile.phone ?? '', job_title: props.profile.job_title ?? '' });
const pwd = useForm({ current_password: '', password: '', password_confirmation: '' });

function save() {
    form.patch(route(props.updateRoute), { preserveScroll: true });
}
function savePassword() {
    pwd.patch(route(props.passwordRoute), { preserveScroll: true, onSuccess: () => pwd.reset() });
}
</script>

<template>
    <component :is="Layout" :title="t('profile.title')">
        <div class="page-head">
            <div>
                <h1 class="page-title">{{ t('profile.title') }}</h1>
                <div class="page-sub">{{ t('profile.sub') }}</div>
            </div>
        </div>

        <div class="grid-2" style="align-items:start">
            <form class="panel acc acc-navy" @submit.prevent="save" novalidate>
                <h3>{{ t('profile.details') }}</h3>
                <div class="form-grid">
                    <Field :label="t('common.name')" :error="form.errors.name" required><input v-model="form.name" type="text"></Field>
                    <Field :label="t('common.email')" :error="form.errors.email" required><input v-model="form.email" type="email"></Field>
                    <Field :label="t('common.phone')" :error="form.errors.phone" optional><input v-model="form.phone" type="tel"></Field>
                    <Field :label="t('team.job_title')" :error="form.errors.job_title" optional><input v-model="form.job_title" type="text"></Field>
                </div>

                <div class="section-label">{{ t('profile.preferences') }}</div>
                <div class="form-grid">
                    <Field :label="t('profile.language')" :error="form.errors.language">
                        <select v-model="form.language"><option value="en">{{ t('common.english') }}</option><option value="ar">{{ t('common.arabic') }}</option></select>
                    </Field>
                    <Field :label="t('profile.theme')" :error="form.errors.theme">
                        <select v-model="form.theme"><option value="dark">{{ t('common.dark') }}</option><option value="light">{{ t('common.light') }}</option></select>
                    </Field>
                    <Field :label="t('profile.standard')" :error="form.errors.occupation_standard" :hint="t('profile.standard_help')" wide>
                        <select v-model="form.occupation_standard">
                            <option value="enoc">ENOC</option><option value="isco">ISCO-08</option><option value="esco">ESCO</option>
                        </select>
                    </Field>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" :class="{ 'is-loading': form.processing }" :disabled="form.processing">{{ t('common.save_changes') }}</button>
                </div>
            </form>

            <form class="panel acc acc-orange" @submit.prevent="savePassword" novalidate>
                <h3>{{ t('profile.password') }}</h3>
                <p class="small mute">{{ t('profile.password_note') }}</p>
                <Field :label="t('profile.current_password')" :error="pwd.errors.current_password"><PasswordInput v-model="pwd.current_password" /></Field>
                <Field :label="t('auth.new_password')" :error="pwd.errors.password" :hint="t('auth.password_hint')"><PasswordInput v-model="pwd.password" autocomplete="new-password" /></Field>
                <Field :label="t('auth.confirm_new_password')" :error="pwd.errors.password_confirmation"><PasswordInput v-model="pwd.password_confirmation" autocomplete="new-password" /></Field>
                <div class="form-actions">
                    <button type="submit" class="btn btn-orange" :class="{ 'is-loading': pwd.processing }" :disabled="pwd.processing">{{ t('profile.password') }}</button>
                </div>
            </form>
        </div>
    </component>
</template>
