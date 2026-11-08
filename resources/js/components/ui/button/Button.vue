<script setup>
import { computed } from 'vue'
import { cva } from 'class-variance-authority'
import { cn } from '@/lib/utils'

const props = defineProps({
  variant: { type: String, default: 'default' },
  size: { type: String, default: 'default' },
  class: { type: [String, Array, Object], default: '' },
  type: { type: String, default: 'button' },
  disabled: { type: Boolean, default: false },
})

const styles = cva(
  'inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:pointer-events-none disabled:opacity-50 cursor-pointer',
  { variants: {
    variant: {
      default: 'bg-primary text-primary-foreground hover:bg-primary/90',
      secondary: 'bg-secondary text-secondary-foreground hover:bg-secondary/80',
      outline: 'border border-input bg-background hover:bg-accent hover:text-accent-foreground',
      ghost: 'hover:bg-accent hover:text-accent-foreground',
      destructive: 'bg-destructive text-white hover:bg-destructive/90',
    },
    size: {
      default: 'h-9 px-4 py-2',
      sm: 'h-8 rounded-md px-3',
      lg: 'h-10 rounded-md px-6',
      icon: 'h-9 w-9',
    },
  }, defaultVariants: {variant:'default',size:'default'}}
)
const classes = computed(() => cn(styles({variant:props.variant,size:props.size}),props.class))
</script>

<template>
  <button :type="type" :disabled="disabled" :class="classes"><slot /></button>
</template>
