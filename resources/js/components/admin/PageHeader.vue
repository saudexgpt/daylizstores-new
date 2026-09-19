<template>
  <header class="admin-page-header">
    <div class="admin-page-header__text">
      <h1 class="admin-page-header__title">
        <slot name="title">{{ title }}</slot>
      </h1>
      <p v-if="subtitle || $slots.subtitle" class="admin-page-header__subtitle">
        <slot name="subtitle">{{ subtitle }}</slot>
      </p>
    </div>
    <div v-if="$slots.default" class="admin-page-header__actions">
      <slot />
    </div>
  </header>
</template>

<script setup>
defineProps({
  title: { type: String, default: '' },
  subtitle: { type: String, default: '' },
});
</script>

<style lang="scss" scoped>
.admin-page-header {
  display: flex;
  align-items: flex-end;
  justify-content: space-between;
  flex-wrap: wrap;
  gap: 12px 24px;
  margin-bottom: 22px;

  &__title {
    margin: 0;
    font-size: 24px;
    font-weight: 700;
    letter-spacing: -0.01em;
    line-height: 1.2;
    color: var(--admin-text);
  }

  &__subtitle {
    margin: 6px 0 0;
    font-size: 14px;
    line-height: 1.5;
    color: var(--admin-muted);
  }

  &__actions {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
  }
}
</style>
