<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — Beneficiaries list (partner workspace)
//  Location: resources/js/Pages/App/Beneficiaries/Index.vue
//  Route: GET /app/beneficiaries (app.beneficiaries.index) → App\BeneficiaryController@index
//  Permission: beneficiaries.view
//
//  Only this partner's beneficiaries (the server never sends others).
//  Search by name (Arabic or English, any spelling of alef/hamza),
//  mobile, email or number; filter by governorate, gender, education,
//  occupation major group (named in the top-bar standard) and minimum
//  experience. The occupation column follows the Standard switch.
// ══════════════════════════════════════════════════════════════════

import { computed, reactive } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Pagination from '@/Components/Pagination.vue';
import { usePermissions } from '@/composables/usePermissions';
import { usePreferences } from '@/composables/usePreferences';
import { useTranslations } from '@/composables/useTranslations';
import { formatDate } from '@/Utils/date';
import { STANDARD_LABELS } from '@/Components/Backbone/occ';
import { experience, names, occIn } from '@/Components/Beneficiaries/ben';

const props = defineProps({
    list: { type: Object, required: true },
    filters: { type: Object, required: true },
    total: { type: Number, required: true },
    options: { type: Object, required: true },
    majors: { type: Array, default: () => [] },
});

const { t, locale } = useTranslations();
const { can } = usePermissions();
const { prefs } = usePreferences();

const f = reactive({ ...props.filters });
const filtered = computed(() => ['q', 'governorate', 'gender', 'education', 'major'].some((k) => props.filters[k]) || props.filters.min_years > 0);

function load() {
    const params = Object.fromEntries(Object.entries(f).filter(([, v]) => v !== '' && v !== 0 && v !== '0'));
    router.get(route('app.beneficiaries.index'), params, { preserveState: true, preserveScroll: true, replace: true });
}
function reset() {
    Object.assign(f, { q: '', governorate: '', gender: '', education: '', major: '', min_years: 0 });
    load();
}

const majorName = (m) => {
    if (locale.value !== 'ar') return m.title_en;
    return prefs.standard === 'enoc' && m.enoc_title_ar ? m.enoc_title_ar : (m.title_ar || m.title_en);
};
const open = (b) => router.visit(route('app.beneficiaries.show', b.number));
</script>

<template>
    <AppLayout :title="t('ben.title')">
        <div class="page-head">
            <div>
                <h1 class="page-title">{{ t('ben.title') }}</h1>
                <div class="page-sub">{{ t('ben.sub', { n: total }) }}</div>
            </div>
            <div class="page-actions">
                <Link v-if="can('beneficiaries.create')" :href="route('app.beneficiaries.create')" class="btn btn-primary"><AppIcon name="user-plus" :size="15" />{{ t('ben.register') }}</Link>
            </div>
        </div>

        <EmptyState v-if="!total" icon="users" :title="t('ben.empty_title')" :text="t('ben.empty_text')">
            <Link v-if="can('beneficiaries.create')" :href="route('app.beneficiaries.create')" class="btn btn-primary"><AppIcon name="user-plus" :size="15" />{{ t('ben.register_first') }}</Link>
        </EmptyState>

        <template v-else>
            <form class="filter-bar" @submit.prevent="load">
                <div class="search grow">
                    <AppIcon name="search" :size="16" />
                    <input v-model="f.q" type="search" :placeholder="t('ben.search_ph')" :aria-label="t('common.search')">
                    <button type="submit" class="btn btn-teal sm">{{ t('common.search') }}</button>
                </div>
            </form>
            <div class="filter-bar">
                <select v-model="f.governorate" class="inp fsel" :aria-label="t('ben.f_governorate')" @change="load">
                    <option value="">{{ t('ben.all_governorates') }}</option>
                    <option v-for="g in options.governorates" :key="g" :value="g">{{ t(`gov.${g}`) }}</option>
                </select>
                <select v-model="f.gender" class="inp fsel" :aria-label="t('ben.f_gender')" @change="load">
                    <option value="">{{ t('ben.all_genders') }}</option>
                    <option v-for="g in options.genders" :key="g" :value="g">{{ t(`ben.gender_${g}`) }}</option>
                </select>
                <select v-model="f.education" class="inp fsel" :aria-label="t('ben.f_education_level')" @change="load">
                    <option value="">{{ t('ben.all_education') }}</option>
                    <option v-for="e in options.education_levels" :key="e" :value="e">{{ t(`ben.edu_${e}`) }}</option>
                </select>
                <select v-model="f.major" class="inp fsel wide" :aria-label="t('ben.f_occupation')" @change="load">
                    <option value="">{{ t('ben.all_occupations', { std: STANDARD_LABELS[prefs.standard] }) }}</option>
                    <option v-for="m in majors" :key="m.code" :value="m.code">{{ m.code }} · {{ majorName(m) }}</option>
                    <option value="none">{{ t('ben.no_occupation_yet') }}</option>
                </select>
                <select v-model.number="f.min_years" class="inp fsel" :aria-label="t('ben.f_min_years')" @change="load">
                    <option :value="0">{{ t('ben.any_experience') }}</option>
                    <option v-for="y in [1, 2, 3, 5, 10]" :key="y" :value="y">{{ t('ben.min_years', { n: y }) }}</option>
                </select>
                <button v-if="filtered" type="button" class="btn btn-ghost sm" @click="reset"><AppIcon name="x" :size="14" />{{ t('ben.clear_filters') }}</button>
            </div>

            <EmptyState v-if="!list.data.length" icon="search" :text="t('ben.no_results')" />
            <div v-else class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>{{ t('ben.col_beneficiary') }}</th>
                            <th>{{ t('ben.col_occupation', { std: STANDARD_LABELS[prefs.standard] }) }}</th>
                            <th>{{ t('ben.col_experience') }}</th>
                            <th class="hide-mobile">{{ t('ben.col_registered') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="b in list.data" :key="b.number" class="click" @click="open(b)">
                            <td>
                                <div class="name-cell">
                                    <span class="avatar">{{ b.initials }}</span>
                                    <div>
                                        <b>{{ names(b, locale)[0] }}</b>
                                        <div class="meta">
                                            <bdi dir="ltr">#{{ b.number }}</bdi>
                                            <span v-if="names(b, locale)[1]"> · {{ names(b, locale)[1] }}</span>
                                            <span> · {{ t(`gov.${b.governorate}`) }}</span>
                                            <span v-if="b.age !== null"> · {{ t('ben.age', { n: b.age }) }}</span>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <template v-if="b.occupation">
                                    <span class="tag teal code">{{ occIn(b.occupation, prefs.standard, locale).code }}</span>
                                    <span class="small occ-t">{{ occIn(b.occupation, prefs.standard, locale).title }}</span>
                                </template>
                                <span v-else class="small mute">{{ t('ben.no_occupation_yet') }}</span>
                            </td>
                            <td class="small">{{ experience(b.experience, t, locale) }}</td>
                            <td class="small mute hide-mobile">{{ formatDate(b.created_at, locale) }}</td>
                            <td class="actions">
                                <Link :href="route('app.beneficiaries.show', b.number)" class="btn btn-ghost sm" @click.stop>{{ t('ben.open') }}</Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
                <Pagination :paginator="list" />
            </div>
        </template>
    </AppLayout>
</template>

<style scoped>
.fsel { width: auto; min-width: 150px; max-width: 220px; }
.fsel.wide { max-width: 320px; }
tr.click { cursor: pointer; }
.occ-t { margin-inline-start: 6px; }
</style>
