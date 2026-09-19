<template>
  <admin-card v-if="user" title="Contact">
    <ul class="bio">
      <li class="bio__row">
        <el-icon class="bio__icon"><IconIphone /></el-icon>
        <div>
          <span class="bio__label">Phone</span>
          <span class="bio__value">{{ user.phone || '—' }}</span>
        </div>
      </li>
      <li class="bio__row">
        <el-icon class="bio__icon"><IconMessage /></el-icon>
        <div>
          <span class="bio__label">Email</span>
          <span class="bio__value">{{ user.email || '—' }}</span>
        </div>
      </li>
      <li class="bio__row">
        <el-icon class="bio__icon"><IconLocation /></el-icon>
        <div>
          <span class="bio__label">Address</span>
          <span class="bio__value">{{ user.address || '—' }}</span>
        </div>
      </li>
    </ul>
  </admin-card>
</template>

<script>
export default {
  props: {
    user: {
      type: Object,
      default: () => ({}),
    },
  },
};
</script>

<style lang="scss" scoped>
.bio {
  margin: 0;
  padding: 0;
  list-style: none;

  &__row {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    padding: 12px 0;
    border-bottom: 1px solid var(--admin-border);

    &:first-child { padding-top: 0; }
    &:last-child { padding-bottom: 0; border-bottom: 0; }
  }

  &__icon {
    flex: none;
    display: grid;
    place-items: center;
    width: 34px;
    height: 34px;
    border-radius: 10px;
    background: var(--admin-primary-soft);
    color: var(--admin-primary);
    font-size: 17px;
  }

  &__label {
    display: block;
    font-size: 12px;
    font-weight: 600;
    color: var(--admin-muted);
  }

  &__value {
    display: block;
    margin-top: 2px;
    font-weight: 500;
    overflow-wrap: anywhere;
  }
}
</style>
