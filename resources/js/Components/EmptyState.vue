<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — EmptyState
//  Location: resources/js/Components/EmptyState.vue
//
//  What a list shows when it has nothing in it — an icon, a line of
//  direction, and (optionally) the action that fills it:
//      <EmptyState icon="building" :text="t('companies.empty')">
//          <button class="btn btn-primary sm">…</button>
//      </EmptyState>
// ══════════════════════════════════════════════════════════════════

import AppIcon from '@/Components/AppIcon.vue';

defineProps({
    icon: { type: String, default: 'inbox' },
    title: { type: String, default: '' },
    text: { type: String, default: '' },
});
</script>

<template>
    <div class="empty">
        <div class="ic"><AppIcon :name="icon" :size="22" /></div>
        <h3 v-if="title">{{ title }}</h3>
        <p>{{ text }}</p>
        <div v-if="$slots.default" style="margin-top:14px"><slot /></div>
    </div>
</template>
