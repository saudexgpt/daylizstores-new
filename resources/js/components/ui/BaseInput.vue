<template>
  <div class="base-input" :class="{ 'base-input--error': error }">
    <input
      :id="inputId"
      class="base-input__field"
      :type="type"
      :value="modelValue"
      placeholder=" "
      :aria-invalid="!!error"
      :aria-describedby="error ? `${inputId}-error` : undefined"
      v-bind="$attrs"
      @input="$emit('update:modelValue', $event.target.value)"
    >
    <label :for="inputId" class="base-input__label">{{ label }}</label>
    <p v-if="error" :id="`${inputId}-error`" class="base-input__error">{{ error }}</p>
  </div>
</template>

<script setup>
import { computed, useId } from 'vue';

defineProps({
  modelValue: {
    type: [String, Number],
    default: '',
  },
  label: {
    type: String,
    required: true,
  },
  type: {
    type: String,
    default: 'text',
  },
  error: {
    type: String,
    default: '',
  },
});

defineEmits(['update:modelValue']);

const inputId = computed(() => `base-input-${useId()}`);
</script>

<style lang="scss" scoped>
.base-input {
  position: relative;
  padding-top: 18px;

  &__field {
    width: 100%;
    font-family: var(--font-sans);
    font-size: 15px;
    color: var(--color-text);
    background: transparent;
    border: none;
    border-bottom: 1px solid var(--color-border);
    padding: 8px 0;
    outline: none;
    transition: border-color 0.2s ease;

    &:focus {
      border-color: var(--color-accent);
    }

    &:focus-visible {
      outline: none;
    }
  }

  &__label {
    position: absolute;
    left: 0;
    top: 26px;
    font-family: var(--font-sans);
    font-size: 15px;
    color: var(--color-text-muted);
    pointer-events: none;
    transition: top 0.2s ease, font-size 0.2s ease, color 0.2s ease;
  }

  &__field:focus + &__label,
  &__field:not(:placeholder-shown) + &__label {
    top: 0;
    font-size: 11px;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    color: var(--color-accent);
  }

  &__error {
    font-family: var(--font-sans);
    font-size: 12px;
    color: #b3261e;
    margin: 6px 0 0;
  }

  &--error &__field {
    border-color: #b3261e;
  }
}
</style>
