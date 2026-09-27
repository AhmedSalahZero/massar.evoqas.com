<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — Coming Soon (planned module)
//  Location: resources/js/Pages/ComingSoon.vue
//  Route: any route pointed at ComingSoonController (routes/web.php)
//
//  One shared page for every module in the Scope of Work that is not
//  built yet. `module` picks the title, icon and "what it will do"
//  list from `modules` in resources/js/lang/translations.js. Uses the
//  admin frame for platform modules, the workspace frame otherwise.
// ══════════════════════════════════════════════════════════════════

import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { modules } from '@/lang/translations';
import { usePermissions } from '@/composables/usePermissions';
import { useTranslations } from '@/composables/useTranslations';

const props = defineProps({ module: { type: String, required: true } });

const { t, locale } = useTranslations();
const { isSuperAdmin } = usePermissions();

const Layout = computed(() => (isSuperAdmin.value ? AdminLayout : AppLayout));
const info = computed(() => modules[props.module] ?? { icon: 'info', en: { title: props.module, items: [] }, ar: { title: props.module, items: [] } });
const text = computed(() => info.value[locale.value] ?? info.value.en);
</script>

<template>
    <component :is="Layout" :title="text.title">
        <div class="page-head">
            <div>
                <div class="page-eyebrow">{{ t('soon.badge') }}</div>
                <h1 class="page-title">{{ text.title }}</h1>
            </div>
        </div>

        <div class="ph-box">
            <div class="ic"><AppIcon :name="info.icon" :size="24" /></div>
            <h3>{{ text.title }}</h3>
            <p>{{ t('soon.status') }}</p>

            <div class="panel text-start mt-6" style="max-width:560px;margin-inline:auto">
                <div class="section-label" style="margin-top:0">{{ t('soon.what') }}</div>
                <ul class="checks" style="list-style:none;padding:0;margin:0">
                    <li v-for="(item, i) in text.items" :key="i" class="checkline">
                        <span class="c-teal"><AppIcon name="check-circle" :size="16" /></span><span>{{ item }}</span>
                    </li>
                </ul>
            </div>

            <Link :href="route(isSuperAdmin ? 'admin.dashboard' : 'app.dashboard')" class="btn btn-line mt-6">
                <AppIcon name="arrow-left" :size="14" />{{ t('soon.back') }}
            </Link>
        </div>
    </component>
</template>
