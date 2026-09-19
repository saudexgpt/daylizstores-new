<template>
  <admin-card v-if="user.name" class="user-card">
    <div class="user-card__head">
      <span class="user-card__avatar">{{ initials }}</span>
      <h2 class="user-card__name">{{ user.name }}</h2>
      <div class="user-card__roles">
        <admin-status-tag v-for="role in roleLabels" :key="role" tone="primary" :label="role" kind="generic" />
      </div>
    </div>
  </admin-card>
</template>

<script>
import { uppercaseFirst } from '@/filters';

// "staff" / "customer" are coarse markers that come back alongside the real role names
const MARKER_ROLES = ['staff'];

export default {
  props: {
    user: {
      type: Object,
      default: () => ({ name: '', email: '', avatar: '', roles: [] }),
    },
  },
  computed: {
    initials() {
      const words = String(this.user.name || '').trim().split(/\s+/).filter(Boolean);
      return words.length ? (words[0][0] + (words.length > 1 ? words[words.length - 1][0] : '')).toUpperCase() : '?';
    },
    roleLabels() {
      const roles = (this.user.roles || []).filter(role => !MARKER_ROLES.includes(role));
      return (roles.length ? roles : (this.user.roles || [])).map(uppercaseFirst);
    },
  },
};
</script>

<style lang="scss" scoped>
.user-card {
  &__head {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    text-align: center;
  }

  &__avatar {
    display: grid;
    place-items: center;
    width: 84px;
    height: 84px;
    border-radius: 50%;
    background: linear-gradient(135deg, #2b45d6, var(--admin-primary));
    color: #fff;
    font-size: 28px;
    font-weight: 700;
    letter-spacing: 0.02em;
    box-shadow: 0 8px 20px rgba(25, 46, 167, 0.28);
  }

  &__name {
    margin: 4px 0 0;
    font-size: 20px;
    font-weight: 700;
    overflow-wrap: anywhere;
  }

  &__roles {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 6px;
  }
}
</style>
