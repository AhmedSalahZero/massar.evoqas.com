<script setup>
// ══════════════════════════════════════════════════════════════════
//  Massar — ConfirmDialog
//  Location: resources/js/Components/ConfirmDialog.vue
//
//  "Are you sure?" before a destructive or disruptive action.
//      <ConfirmDialog :show="!!toSuspend" :message="…" :confirm-label="t('companies.suspend')"
//                     danger @confirm="suspend" @close="toSuspend = null" />
//  typeToConfirm: the person must type this exact text first (used
//  for permanent deletes, so it can never be a slip of the mouse).
// ══════════════════════════════════════════════════════════════════

import { computed, ref, watch } from 'vue';
import Modal from '@/Components/Modal.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { useTranslations } from '@/composables/useTranslations';

const props = defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: '' },
    message: { type: String, default: '' },
    confirmLabel: { type: String, default: '' },
    danger: { type: Boolean, default: false },
    processing: { type: Boolean, default: false },
    typeToConfirm: { type: String, default: '' },
    typeLabel: { type: String, default: '' },
});
const emit = defineEmits(['confirm', 'close']);
const { t } = useTranslations();

const typed = ref('');
watch(() => props.show, () => { typed.value = ''; });
const ready = computed(() => !props.typeToConfirm || typed.value.trim() === props.typeToConfirm.trim());
</script>

<template>
    <Modal :show="show" size="sm" :title="title || t('common.confirm_title')" @close="emit('close')">
        <template #icon>
            <span class="ic" style="width:38px;height:38px;border-radius:10px;display:grid;place-items:center;flex-shrink:0"
                  :style="danger ? 'background:var(--ms-danger-dim);color:var(--ms-danger)' : 'background:var(--ms-orange-dim);color:var(--ms-orange)'">
                <AppIcon name="alert" :size="18" />
            </span>
        </template>
        <p class="mute">{{ message }}</p>
        <label v-if="typeToConfirm" class="fld mt-4">
            <span class="fl">{{ typeLabel }}</span>
            <input v-model="typed" type="text" :placeholder="typeToConfirm" autocomplete="off">
        </label>
        <template #footer>
            <button type="button" class="btn btn-line" @click="emit('close')">{{ t('common.cancel') }}</button>
            <button type="button" class="btn" :class="[danger ? 'btn-danger' : 'btn-orange', { 'is-loading': processing }]"
                    :disabled="!ready || processing" @click="emit('confirm')">
                {{ confirmLabel || t('common.yes') }}
            </button>
        </template>
    </Modal>
</template>
