<template>
  <div class="base-card" :class="{ 'base-card--hover': hover }">
    <slot />
  </div>
</template>

<script setup>
defineProps({
  hover: {
    type: Boolean,
    default: false,
  },
});
</script>

<style lang="scss" scoped>
.base-card {
  background: var(--color-surface);
  border: 1px solid var(--color-border);
  border-radius: var(--radius-sm);
  box-shadow: var(--shadow-subtle);

  &--hover {
    transition: box-shadow 0.25s ease, transform 0.25s ease;

    &:hover {
      box-shadow: var(--shadow-hover);
      transform: translateY(-2px);
    }
  }
}
</style>
