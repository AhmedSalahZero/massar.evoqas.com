<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — Modal
//  Location: resources/js/Components/Modal.vue
//
//      <Modal :show="open" :title="t('team.add')" size="lg" @close="open = false">
//          …body…
//          <template #footer> …buttons… </template>
//      </Modal>
//
//  size: 'sm' | 'md' (default) | 'lg'.  side: true → opens as a side
//  sheet from the inline-end edge (mirrors in Arabic).
//  Closes on Esc and on a click outside. Focus moves into the dialog
//  when it opens and the page behind stops scrolling.
// ══════════════════════════════════════════════════════════════════

import { nextTick, onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: '' },
    size: { type: String, default: 'md' },
    side: { type: Boolean, default: false },
});
const emit = defineEmits(['close']);
const dialog = ref(null);

function onKey(e) {
    if (e.key === 'Escape') emit('close');
}

watch(() => props.show, async (open) => {
    document.body.style.overflow = open ? 'hidden' : '';
    if (open) {
        document.addEventListener('keydown', onKey);
        await nextTick();
        dialog.value?.querySelector('input, select, textarea, button')?.focus();
    } else {
        document.removeEventListener('keydown', onKey);
    }
});

onBeforeUnmount(() => {
    document.body.style.overflow = '';
    document.removeEventListener('keydown', onKey);
});
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="modal-back" @click.self="emit('close')">
            <div ref="dialog" role="dialog" aria-modal="true" :aria-label="title"
                 :class="side ? 'sheet' : ['modal', size === 'md' ? '' : size]">
                <div class="modal-h">
                    <slot name="icon" />
                    <h3>{{ title }}</h3>
                    <button type="button" class="close" aria-label="×" @click="emit('close')">×</button>
                </div>
                <div class="modal-b"><slot /></div>
                <div v-if="$slots.footer" class="modal-f"><slot name="footer" /></div>
            </div>
        </div>
    </Teleport>
</template>
