<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — Pagination
//  Location: resources/js/Components/Pagination.vue
//
//  Renders a Laravel paginator (->paginate()) as the Massar
//  .table-foot: "Showing 1–20 of 248" plus page links. Pass the whole
//  paginator prop:  <Pagination :paginator="companies" />
//  Links keep the current filters (the controller uses withQueryString).
// ══════════════════════════════════════════════════════════════════

import { Link } from '@inertiajs/vue3';
import { useTranslations } from '@/composables/useTranslations';

defineProps({ paginator: { type: Object, required: true } });
const { t } = useTranslations();

function label(raw) {
    if (raw.includes('Previous') || raw.includes('&laquo;')) return '‹';
    if (raw.includes('Next') || raw.includes('&raquo;')) return '›';
    return raw;
}
</script>

<template>
    <div v-if="paginator.total > 0" class="table-foot">
        <span>{{ t('common.showing', { from: paginator.from ?? 0, to: paginator.to ?? 0, total: paginator.total }) }}</span>
        <nav v-if="paginator.last_page > 1" class="pagination">
            <template v-for="(link, i) in paginator.links" :key="i">
                <Link v-if="link.url" :href="link.url" preserve-scroll :class="{ active: link.active }"
                      :aria-current="link.active ? 'page' : null">{{ label(link.label) }}</Link>
                <span v-else class="disabled">{{ label(link.label) }}</span>
            </template>
        </nav>
    </div>
</template>
