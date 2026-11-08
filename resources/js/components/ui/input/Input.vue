<script setup>
import { computed } from 'vue'
import { cn } from '@/lib/utils'
const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  type: { type: String, default: 'text' },
  placeholder: { type: String, default: '' },
  class: { type: [String, Array, Object], default: '' },
})
const emit = defineEmits(['update:modelValue'])
const classes = computed(() => cn('flex h-9 w-full rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',props.class))
</script>

<template>
  <input :type="type" :value="modelValue" :placeholder="placeholder" :class="classes" @input="emit('update:modelValue', $event.target.value)" />
</template>
