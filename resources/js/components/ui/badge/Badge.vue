<script setup>
import { computed } from 'vue'
import { cn } from '@/lib/utils'
const props = defineProps({
  variant: { type: String, default: 'secondary' },
  class: { type: [String, Array, Object], default: '' },
})
const classes = computed(() => cn('inline-flex items-center rounded-md border px-2 py-0.5 text-xs font-medium',
  props.variant === 'outline' ? 'border-border bg-transparent text-foreground' : 'border-transparent bg-secondary text-secondary-foreground',
  props.class))
</script>
<template><span :class="classes"><slot /></span></template>
