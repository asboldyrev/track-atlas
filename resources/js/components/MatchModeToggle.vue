<script setup>
// Сегментированный переключатель режима группы: «ИЛИ» (по умолчанию) и «И».
// Нативная радиогруппа — стрелки переключают режим, выбранный доступен
// скринридеру как checked.
const props = defineProps({
  modelValue: { type: String, default: 'or' },
  label: { type: String, required: true },
  disabled: { type: Boolean, default: false },
})
const emit = defineEmits(['update:modelValue'])

const options = [
  { value: 'or', text: 'ИЛИ' },
  { value: 'and', text: 'И' },
]

function select(value) {
  if (!props.disabled && value !== props.modelValue) emit('update:modelValue', value)
}
</script>

<template>
  <div
    role="radiogroup"
    :aria-label="label"
    class="inline-flex items-center rounded-lg bg-secondary p-0.5"
    :class="disabled ? 'opacity-50' : ''"
  >
    <label
      v-for="option in options"
      :key="option.value"
      class="relative inline-flex h-6 min-w-11 items-center justify-center rounded-md px-2 text-xs font-medium leading-4 text-muted-foreground transition-colors has-[:checked]:bg-card has-[:checked]:text-foreground has-[:checked]:shadow-sm"
      :class="disabled ? 'cursor-not-allowed' : 'cursor-pointer hover:text-foreground'"
    >
      <input
        type="radio"
        class="peer sr-only"
        :name="label"
        :value="option.value"
        :checked="modelValue === option.value"
        :disabled="disabled"
        @change="select(option.value)"
      >
      <span
        class="rounded peer-focus-visible:outline peer-focus-visible:outline-2 peer-focus-visible:outline-offset-[3px] peer-focus-visible:outline-ring"
      >{{ option.text }}</span>
    </label>
  </div>
</template>
