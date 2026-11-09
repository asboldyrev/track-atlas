<script setup>
import { computed } from 'vue'
import { cn } from '@/lib/utils'

// Единый нативный select дизайн-системы: те же границы, фон и кольцо фокуса,
// что у Input/Button. Раньше каждый select собирал классы сам — из-за этого
// фокус выглядел как системный чёрный outline, а не как ring-ring.
const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  class: { type: [String, Array, Object], default: '' },
})
const emit = defineEmits(['update:modelValue'])
const classes = computed(() => cn(
  'h-9 rounded-md border border-input bg-background px-2 text-sm text-foreground shadow-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50',
  props.class,
))
</script>

<template>
  <select :value="modelValue" :class="classes" @change="emit('update:modelValue', $event.target.value)">
    <slot />
  </select>
</template>
