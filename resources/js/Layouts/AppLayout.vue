<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — AppLayout (partner workspace)
//  Location: resources/js/Layouts/AppLayout.vue
//
//      <AppLayout :title="t('team.title')"> …page… </AppLayout>
//
//  The partner navigation, grouped exactly as the Massar mockup:
//  Overview · Beneficiaries · Opportunities · Insights · Settings.
//  Items marked `soon: true` are modules in the scope that are not
//  built yet — they open the shared Coming Soon page. When a module
//  is built, remove its `soon` flag here and point its route to the
//  real controller in routes/web.php.
//  Items with `permission` only show to people who hold that key.
// ══════════════════════════════════════════════════════════════════

import { computed } from 'vue';
import MassarShell from '@/Layouts/MassarShell.vue';
import { usePermissions } from '@/composables/usePermissions';
import { useTranslations } from '@/composables/useTranslations';

defineProps({ title: { type: String, default: '' } });

const { user } = usePermissions();
const { locale } = useTranslations();

const workspace = computed(() => {
    const c = user.value?.company;
    if (!c) return '';
    return locale.value === 'ar' && c.name_ar ? c.name_ar : c.name;
});

const nav = [
    { group: 'nav.overview', items: [
        { label: 'nav.dashboard', icon: 'dashboard', route: 'app.dashboard' },
    ] },
    { group: 'nav.beneficiaries_group', items: [
        { label: 'nav.beneficiaries', icon: 'users', route: 'app.beneficiaries.index', active: 'app.beneficiaries.*', permission: 'beneficiaries.view' },
        { label: 'nav.cv_bank', icon: 'search', route: 'app.cv-bank.index', permission: 'beneficiaries.view' },
        { label: 'nav.pool', icon: 'globe', route: 'app.pool.index', active: 'app.pool.*', permission: 'pool.view' },
        { label: 'nav.intake', icon: 'user-plus', route: 'app.intake.index', permission: 'beneficiaries.create' },
        { label: 'nav.cv_upload', icon: 'upload', route: 'app.cv-upload.index', permission: 'cv.upload' },
        { label: 'nav.review_queue', icon: 'inbox', route: 'app.review-queue.index', active: 'app.review-queue.*', permission: 'cv.review' },
    ] },
    { group: 'nav.opportunities_group', items: [
        { label: 'nav.jobs', icon: 'briefcase', route: 'app.jobs.index', active: 'app.jobs.*', permission: 'opportunities.view' },
        { label: 'nav.training', icon: 'graduation', route: 'app.training.index', active: 'app.training.*', permission: 'opportunities.view' },
        { label: 'nav.matches', icon: 'link', route: 'app.matches.index', active: 'app.matches.*', permission: 'matches.view' },
    ] },
    { group: 'nav.insights_group', items: [
        { label: 'nav.occupations', icon: 'layers', route: 'app.occupations.index', active: 'app.occupations.*', permission: 'occupations.view' },
        { label: 'nav.reports', icon: 'chart', route: 'app.reports.index', active: 'app.reports.*', permission: 'reports.view' },
    ] },
    { group: 'nav.settings_group', items: [
        { label: 'nav.rules', icon: 'book', route: 'app.rules.index', permission: 'rules.view' },
        { label: 'nav.team', icon: 'team', route: 'app.team.index', active: 'app.team.*', permission: 'team.manage' },
        { label: 'nav.profile', icon: 'user', route: 'app.profile.index' },
    ] },
];
</script>

<template>
    <MassarShell :title="title" :workspace="workspace" :nav="nav" home-route="app.dashboard" profile-route="app.profile.index">
        <slot />
    </MassarShell>
</template>
