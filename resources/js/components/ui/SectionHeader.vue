<template>
  <header class="section-header" :class="`section-header--${align}`">
    <p v-if="eyebrow" class="section-header__eyebrow">{{ eyebrow }}</p>
    <h2 class="section-header__title">{{ title }}</h2>
    <p v-if="subtitle" class="section-header__subtitle">{{ subtitle }}</p>
  </header>
</template>

<script setup>
defineProps({
  eyebrow: {
    type: String,
    default: '',
  },
  title: {
    type: String,
    required: true,
  },
  subtitle: {
    type: String,
    default: '',
  },
  align: {
    type: String,
    default: 'left', // left | center
  },
});
</script>

<style lang="scss" scoped>
.section-header {
  max-width: var(--content-max-text);
  margin-bottom: 48px;

  &--center {
    margin-left: auto;
    margin-right: auto;
    text-align: center;
  }

  &__eyebrow {
    font-family: var(--font-sans);
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    color: var(--color-accent);
    margin: 0 0 12px;
  }

  &__title {
    font-family: var(--font-serif);
    font-weight: 600;
    font-size: clamp(26px, 3.4vw, 36px);
    line-height: 1.15;
    color: var(--color-navy);
    margin: 0;
  }

  &__subtitle {
    font-family: var(--font-sans);
    font-size: 16px;
    line-height: 1.6;
    color: var(--color-text-muted);
    margin: 16px 0 0;
  }
}
</style>
