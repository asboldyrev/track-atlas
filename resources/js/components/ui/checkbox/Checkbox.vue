<script setup>
import { CheckboxIndicator, CheckboxRoot } from 'reka-ui'
import { Check } from '@lucide/vue'
import { cn } from '@/lib/utils'
defineProps({
  modelValue: { type: Boolean, default: false },
  disabled: { type: Boolean, default: false },
  class: { type: [String, Array, Object], default: '' },
})
const emit = defineEmits(['update:modelValue'])
</script>

<template>
  <CheckboxRoot
    :model-value="modelValue"
    :disabled="disabled"
    :class="cn('peer inline-flex size-4 shrink-0 items-center justify-center rounded border border-primary shadow-sm outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-50 data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground', $props.class)"
    @update:model-value="emit('update:modelValue', $event === true)"
  >
    <CheckboxIndicator><Check :size="12" :stroke-width="3" /></CheckboxIndicator>
  </CheckboxRoot>
</template>
