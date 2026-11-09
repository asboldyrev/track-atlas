<script setup>
// Строка значения фильтра: нативный чекбокс + подпись. Вся строка — <label>,
// поэтому клик по тексту переключает значение; доступное имя элемента — его
// подпись (тип задаётся группой-блоком).
defineProps({
  modelValue: { type: Boolean, default: false },
  label: { type: String, required: true },
  disabled: { type: Boolean, default: false },
})
const emit = defineEmits(['update:modelValue'])
</script>

<template>
  <label
    class="group -ml-1.5 flex min-h-8 items-center gap-2 rounded-md px-1.5 py-px text-[13px] leading-4 hover:bg-accent sm:min-h-[26px]"
    :class="disabled ? 'cursor-not-allowed opacity-60' : 'cursor-pointer'"
  >
    <input
      type="checkbox"
      class="peer sr-only"
      :checked="modelValue"
      :disabled="disabled"
      @change="emit('update:modelValue', $event.target.checked)"
    >
    <span
      class="flex size-4 shrink-0 items-center justify-center rounded border border-primary text-primary-foreground peer-checked:bg-primary peer-focus-visible:ring-2 peer-focus-visible:ring-ring peer-focus-visible:ring-offset-2"
      aria-hidden="true"
    >
      <svg
        class="size-2.5 opacity-0 group-has-[:checked]:opacity-100"
        viewBox="0 0 24 24" fill="none" stroke="currentColor"
        stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"
      ><path d="M20 6 9 17l-5-5" /></svg>
    </span>
    <span class="min-w-0 [overflow-wrap:anywhere] group-has-[:checked]:font-medium">{{ label }}</span>
  </label>
</template>
