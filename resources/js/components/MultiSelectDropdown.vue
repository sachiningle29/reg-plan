<template>
  <div class="ms-dropdown" v-click-outside="close">
    <button type="button" class="form-select text-start ms-toggle" @click="open = !open">
      {{ summaryText }}
    </button>
    <div v-if="open" class="ms-panel shadow-sm">
      <div class="ms-panel-header">
        <button type="button" class="btn btn-sm btn-link p-0" @click="$emit('update:modelValue', [])">
          Clear all
        </button>
      </div>
      <label v-for="opt in options" :key="opt" class="ms-option">
        <input
          type="checkbox"
          :checked="modelValue.includes(opt)"
          @change="toggle(opt)"
        />
        {{ opt }}
      </label>
      <div v-if="!options.length" class="text-muted small px-3 py-2">No options</div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
  modelValue: { type: Array, default: () => [] },
  options: { type: Array, default: () => [] },
  placeholder: { type: String, default: 'All' },
});
const emit = defineEmits(['update:modelValue']);

const open = ref(false);

function toggle(opt) {
  const next = props.modelValue.includes(opt)
    ? props.modelValue.filter((o) => o !== opt)
    : [...props.modelValue, opt];
  emit('update:modelValue', next);
}

function close() { open.value = false; }
defineExpose({ close });

const summaryText = computed(() => {
  if (!props.modelValue.length) return props.placeholder;
  if (props.modelValue.length === 1) return props.modelValue[0];
  return `${props.modelValue.length} selected`;
});

const vClickOutside = {
  mounted(el, binding) {
    el._clickOutside = (e) => { if (!el.contains(e.target)) binding.value(); };
    document.addEventListener('click', el._clickOutside);
  },
  unmounted(el) { document.removeEventListener('click', el._clickOutside); },
};
</script>

<style scoped>
.ms-dropdown { position: relative; }
.ms-toggle { cursor: pointer; }
.ms-panel {
  position: absolute; top: calc(100% + 4px); left: 0; z-index: 20;
  background: #fff; border: 1px solid #e2e8f0; border-radius: 0.5rem;
  min-width: 100%; max-height: 260px; overflow-y: auto; padding: 0.4rem 0;
}
.ms-panel-header { display: flex; justify-content: flex-end; padding: 0.25rem 0.75rem 0.5rem; border-bottom: 1px solid #f1f5f9; margin-bottom: 0.25rem; }
.ms-option { display: flex; align-items: center; gap: 0.5rem; padding: 0.4rem 0.75rem; font-size: 0.85rem; cursor: pointer; margin: 0; }
.ms-option:hover { background: #f8fafc; }
</style>
