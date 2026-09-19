<template>
  <section class="admin-card" :class="{ 'admin-card--flush': flush }">
    <header v-if="title || $slots.title || $slots.actions" class="admin-card__header">
      <div class="admin-card__heading">
        <h2 class="admin-card__title">
          <slot name="title">{{ title }}</slot>
        </h2>
        <p v-if="subtitle" class="admin-card__subtitle">{{ subtitle }}</p>
      </div>
      <div v-if="$slots.actions" class="admin-card__actions">
        <slot name="actions" />
      </div>
    </header>
    <div v-if="$slots.toolbar" class="admin-card__toolbar">
      <slot name="toolbar" />
    </div>
    <div class="admin-card__body">
      <slot />
    </div>
    <footer v-if="$slots.footer" class="admin-card__footer">
      <slot name="footer" />
    </footer>
  </section>
</template>

<script setup>
defineProps({
  title: { type: String, default: '' },
  subtitle: { type: String, default: '' },
  // no body padding — for tables that should run edge to edge
  flush: { type: Boolean, default: false },
});
</script>

<style lang="scss" scoped>
.admin-card {
  background: var(--admin-surface);
  border: 1px solid var(--admin-border);
  border-radius: var(--admin-radius);
  box-shadow: var(--admin-shadow);
  margin-bottom: 20px;

  &__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 18px 22px;
    border-bottom: 1px solid var(--admin-border);
  }

  &__title {
    margin: 0;
    font-size: 16px;
    font-weight: 650;
    color: var(--admin-text);
  }

  &__subtitle {
    margin: 4px 0 0;
    font-size: 13px;
    color: var(--admin-muted);
  }

  &__actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
  }

  &__toolbar {
    padding: 16px 22px;
    border-bottom: 1px solid var(--admin-border);
    background: var(--admin-surface-soft);
  }

  &__body {
    padding: 22px;
  }

  &--flush &__body {
    padding: 0;
  }

  &__footer {
    padding: 14px 22px;
    border-top: 1px solid var(--admin-border);
  }
}

@media (max-width: 560px) {
  .admin-card__header,
  .admin-card__toolbar,
  .admin-card__body,
  .admin-card__footer {
    padding-left: 14px;
    padding-right: 14px;
  }
}
</style>
