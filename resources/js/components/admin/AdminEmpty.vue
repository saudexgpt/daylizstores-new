<template>
  <div class="admin-empty">
    <div class="admin-empty__icon" aria-hidden="true">
      <el-icon><component :is="iconComponent" /></el-icon>
    </div>
    <p class="admin-empty__title">{{ title }}</p>
    <p v-if="description" class="admin-empty__description">{{ description }}</p>
    <div v-if="$slots.default" class="admin-empty__actions">
      <slot />
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { resolveIconName } from '@/utils/icons';

const props = defineProps({
  title: { type: String, default: 'Nothing here yet' },
  description: { type: String, default: '' },
  icon: { type: String, default: 'Box' },
});

const iconComponent = computed(() => resolveIconName(props.icon) || 'IconBox');
</script>

<style lang="scss" scoped>
.admin-empty {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 44px 20px;
  text-align: center;

  &__icon {
    display: grid;
    place-items: center;
    width: 64px;
    height: 64px;
    margin-bottom: 14px;
    border-radius: 50%;
    background: var(--admin-primary-soft);
    color: var(--admin-primary);
    font-size: 28px;
  }

  &__title {
    margin: 0;
    font-size: 15px;
    font-weight: 600;
    color: var(--admin-text);
  }

  &__description {
    margin: 6px 0 0;
    max-width: 380px;
    font-size: 13px;
    line-height: 1.5;
    color: var(--admin-muted);
  }

  &__actions {
    margin-top: 16px;
  }
}
</style>
