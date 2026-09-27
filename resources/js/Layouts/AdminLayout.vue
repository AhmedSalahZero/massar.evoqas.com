<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — AdminLayout (Massar platform · Super Admin)
//  Location: resources/js/Layouts/AdminLayout.vue
//
//      <AdminLayout :title="t('companies.title')"> …page… </AdminLayout>
//
//  Same frame as the partner workspace, with the platform navigation:
//  dashboard, partner organisations, activity, the occupation
//  backbone, and the planned rule promotion requests.
// ══════════════════════════════════════════════════════════════════

import MassarShell from '@/Layouts/MassarShell.vue';
import { useTranslations } from '@/composables/useTranslations';

defineProps({ title: { type: String, default: '' } });
const { t } = useTranslations();

const nav = [
    { group: 'nav.overview', items: [
        { label: 'nav.dashboard', icon: 'dashboard', route: 'admin.dashboard' },
    ] },
    { group: 'nav.platform_group', items: [
        { label: 'nav.companies', icon: 'building', route: 'admin.companies.index', permission: 'platform.companies' },
        { label: 'nav.activity', icon: 'activity', route: 'admin.activity.index', permission: 'platform.activity' },
        { label: 'nav.backbone', icon: 'layers', route: 'admin.backbone.index', active: 'admin.backbone.*', permission: 'platform.backbone' },
        { label: 'nav.sectors', icon: 'building', route: 'admin.sectors.index', permission: 'platform.backbone' },
        { label: 'nav.rule_requests', icon: 'sparkles', route: 'admin.rule-requests.index', permission: 'platform.rules' },
        { label: 'nav.reports', icon: 'chart', route: 'admin.reports.index', active: 'admin.reports.*', permission: 'reports.view' },
    ] },
    { group: 'nav.settings_group', items: [
        { label: 'nav.profile', icon: 'user', route: 'admin.profile.index' },
    ] },
];
</script>

<template>
    <MassarShell :title="title" :workspace="t('shell.platform')" :nav="nav" home-route="admin.dashboard" profile-route="admin.profile.index">
        <slot />
    </MassarShell>
</template>
