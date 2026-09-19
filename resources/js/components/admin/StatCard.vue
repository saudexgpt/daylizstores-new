<template>
  <article class="stat-card" :class="`stat-card--${tone}`">
    <div class="stat-card__icon" aria-hidden="true">
      <el-icon><component :is="iconComponent" /></el-icon>
    </div>
    <div class="stat-card__body">
      <p class="stat-card__label">{{ label }}</p>
      <el-skeleton v-if="loading" animated :rows="0" class="stat-card__skeleton">
        <template #template><el-skeleton-item variant="h1" style="width: 90px; height: 30px;" /></template>
      </el-skeleton>
      <p v-else class="stat-card__value">{{ value }}</p>
      <p v-if="hint" class="stat-card__hint">{{ hint }}</p>
    </div>
  </article>
</template>

<script setup>
import { computed } from 'vue';
import { resolveIconName } from '@/utils/icons';

const props = defineProps({
  label: { type: String, required: true },
  value: { type: [String, Number], default: '' },
  hint: { type: String, default: '' },
  // an icon name from utils/icons.js, e.g. 'ShoppingBag'
  icon: { type: String, default: 'DataBoard' },
  tone: { type: String, default: 'primary' }, // primary | success | warning | danger | info
  loading: { type: Boolean, default: false },
});

const iconComponent = computed(() => resolveIconName(props.icon) || 'IconDataBoard');
</script>

<style lang="scss" scoped>
.stat-card {
  --tone: var(--admin-primary);
  --tone-soft: var(--admin-primary-soft);

  display: flex;
  align-items: flex-start;
  gap: 16px;
  height: 100%;
  padding: 20px 22px;
  background: var(--admin-surface);
  border: 1px solid var(--admin-border);
  border-radius: var(--admin-radius);
  box-shadow: var(--admin-shadow);

  &--success { --tone: var(--admin-success); --tone-soft: var(--admin-success-soft); }
  &--warning { --tone: var(--admin-warning); --tone-soft: var(--admin-warning-soft); }
  &--danger { --tone: var(--admin-danger); --tone-soft: var(--admin-danger-soft); }
  &--info { --tone: var(--admin-info); --tone-soft: var(--admin-info-soft); }

  &__icon {
    flex: none;
    display: grid;
    place-items: center;
    width: 46px;
    height: 46px;
    border-radius: 12px;
    background: var(--tone-soft);
    color: var(--tone);
    font-size: 22px;
  }

  &__body {
    min-width: 0;
  }

  &__label {
    margin: 0;
    font-size: 13px;
    font-weight: 500;
    color: var(--admin-muted);
  }

  &__value {
    margin: 4px 0 0;
    font-size: 28px;
    font-weight: 700;
    letter-spacing: -0.02em;
    line-height: 1.15;
    color: var(--admin-text);
    font-variant-numeric: tabular-nums;
    overflow-wrap: anywhere;
  }

  &__skeleton {
    margin-top: 6px;
  }

  &__hint {
    margin: 6px 0 0;
    font-size: 12px;
    color: var(--admin-muted);
  }
}
</style>
