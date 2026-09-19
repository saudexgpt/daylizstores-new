<template>
  <component
    :is="tag"
    class="base-btn"
    :class="[`base-btn--${variant}`, { 'base-btn--pill': pill, 'base-btn--loading': loading }]"
    :to="to"
    :disabled="tag === 'button' ? disabled || loading : undefined"
    :aria-busy="loading || undefined"
    @click="onClick"
  >
    <span class="base-btn__label"><slot /></span>
  </component>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  variant: {
    type: String,
    default: 'primary', // primary (navy) | accent (red) | secondary | ghost
  },
  pill: {
    type: Boolean,
    default: false,
  },
  to: {
    type: [String, Object],
    default: null,
  },
  loading: {
    type: Boolean,
    default: false,
  },
  disabled: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['click']);

const tag = computed(() => (props.to ? 'router-link' : 'button'));

function onClick(event) {
  if (props.disabled || props.loading) {
    event.preventDefault();
    return;
  }
  emit('click', event);
}
</script>

<style lang="scss" scoped>
.base-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  font-family: var(--font-sans);
  font-size: 14px;
  font-weight: 500;
  letter-spacing: 0.02em;
  padding: 13px 28px;
  border: 1px solid transparent;
  border-radius: var(--radius-sm);
  cursor: pointer;
  text-decoration: none;
  transition: background-color 0.2s ease, color 0.2s ease, border-color 0.2s ease, transform 0.15s ease;

  &:hover:not(:disabled) {
    transform: scale(1.02);
  }

  &:disabled,
  &.base-btn--loading {
    cursor: not-allowed;
    opacity: 0.6;
    transform: none;
  }

  &--pill {
    border-radius: var(--radius-pill);
  }

  &--primary {
    background: var(--color-navy);
    color: #fff;
    border-color: var(--color-navy);

    &:hover:not(:disabled) {
      background: var(--color-navy-hover);
      border-color: var(--color-navy-hover);
    }
  }

  &--accent {
    background: var(--color-accent);
    color: #fff;
    border-color: var(--color-accent);

    &:hover:not(:disabled) {
      background: var(--color-accent-hover);
      border-color: var(--color-accent-hover);
    }
  }

  &--secondary {
    background: transparent;
    color: var(--color-navy);
    border-color: var(--color-navy);

    &:hover:not(:disabled) {
      background: var(--color-navy);
      color: #fff;
    }
  }

  &--ghost {
    background: transparent;
    color: var(--color-navy);
    border-color: transparent;
    padding-left: 4px;
    padding-right: 4px;

    &:hover:not(:disabled) {
      color: var(--color-accent);
    }
  }
}
</style>
