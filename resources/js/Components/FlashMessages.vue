<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — FlashMessages
//  Location: resources/js/Components/FlashMessages.vue
//
//  Shows the server's flash messages (->with('success', …) on a
//  redirect) as toasts at the bottom of the screen. Placed once in
//  each layout. success/info disappear after a few seconds;
//  error/warning stay until closed, because they need reading.
// ══════════════════════════════════════════════════════════════════

import { ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';

const page = usePage();
const toasts = ref([]);
let seq = 0;

const ICON = { success: 'check-circle', error: 'x-circle', warning: 'alert', info: 'info' };

function push(type, text) {
    const id = ++seq;
    toasts.value.push({ id, type, text });
    if (type === 'success' || type === 'info') setTimeout(() => dismiss(id), 4000);
}

function dismiss(id) {
    toasts.value = toasts.value.filter((t) => t.id !== id);
}

watch(
    () => page.props.flash,
    (flash) => {
        if (!flash) return;
        for (const type of ['success', 'error', 'warning', 'info']) {
            if (flash[type]) push(type, flash[type]);
        }
    },
    { immediate: true, deep: true },
);
</script>

<template>
    <div class="toast-stack" role="status" aria-live="polite">
        <div v-for="toast in toasts" :key="toast.id"
             class="toast" :class="{ success: toast.type === 'success', error: toast.type === 'error' || toast.type === 'warning' }">
            <AppIcon :name="ICON[toast.type]" :size="16" />
            <span>{{ toast.text }}</span>
            <button type="button" class="btn-ghost" style="border:0;background:none;color:inherit;padding:0 2px;opacity:.8" @click="dismiss(toast.id)" aria-label="×">×</button>
        </div>
    </div>
</template>
