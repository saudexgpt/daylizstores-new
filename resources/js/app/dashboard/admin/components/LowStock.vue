<template>
  <div class="low-stock">
    <ul v-if="items.length" class="low-stock__list">
      <li v-for="item in items.slice(0, limit)" :key="item.item_id" class="low-stock__row">
        <router-link :to="{ path: '/food-menu/manage-items', query: { q: item.name } }" class="low-stock__name" :title="item.name">
          {{ item.name }}
        </router-link>
        <span class="low-stock__badge" :class="`low-stock__badge--${levelOf(item)}`">
          {{ label(item) }}
        </span>
      </li>
    </ul>
    <admin-empty
      v-else
      icon="CircleCheck"
      title="Stock levels look healthy"
      description="Products with 10 or fewer units left will be listed here."
    />
  </div>
</template>

<script setup>
defineProps({
  items: { type: Array, default: () => [] },
  // the API sends the worst 10; the dashboard shows the worst few
  limit: { type: Number, default: 6 },
});

// negative balance = more has been sold/reserved than was ever stocked
const levelOf = (item) => (item.total_balance < 0 ? 'danger' : item.total_balance <= 3 ? 'warning' : 'neutral');
const label = (item) => (item.total_balance < 0 ? `Oversold by ${Math.abs(item.total_balance)}` : `${item.total_balance} left`);
</script>

<style lang="scss" scoped>
.low-stock {
  &__list {
    margin: 0;
    padding: 0;
    list-style: none;
  }

  &__row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 12px 0;
    border-bottom: 1px solid var(--admin-border);

    &:first-child { padding-top: 0; }
    &:last-child { padding-bottom: 0; border-bottom: 0; }
  }

  &__name {
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 14px;
    font-weight: 600;
    color: var(--admin-text);
    transition: color 0.15s ease;

    &:hover { color: var(--admin-primary); }
  }

  &__badge {
    flex: none;
    padding: 3px 10px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
    white-space: nowrap;

    &--danger { background: var(--admin-danger-soft); color: var(--admin-danger); }
    &--warning { background: var(--admin-warning-soft); color: var(--admin-warning); }
    &--neutral { background: #eef0f6; color: var(--admin-muted); }
  }
}
</style>
