<template>
  <el-popover
    v-model:visible="open"
    placement="bottom-end"
    trigger="click"
    :width="360"
    :show-arrow="false"
    popper-class="notification-popper"
  >
    <template #reference>
      <button type="button" class="bell" aria-label="Notifications">
        <el-icon><IconBell /></el-icon>
        <span v-if="count > 0" class="bell__badge">{{ count > 99 ? '99+' : count }}</span>
      </button>
    </template>

    <div class="panel">
      <header class="panel__header">
        <h3 class="panel__title">Notifications</h3>
        <span v-if="count > 0" class="panel__count">{{ count }} new</span>
      </header>

      <ul v-if="recent.length" class="panel__list">
        <li v-for="(notification, index) in recent" :key="notification.id || index" class="panel__item">
          <span class="panel__dot" aria-hidden="true" />
          <div class="panel__text">
            <p class="panel__item-title">{{ titleOf(notification) }}</p>
            <p v-if="descriptionOf(notification)" class="panel__item-desc">{{ descriptionOf(notification) }}</p>
            <time class="panel__time">{{ timeOf(notification) }}</time>
          </div>
        </li>
      </ul>
      <admin-empty v-else icon="Bell" title="You're all caught up" description="New activity will show up here." />

      <footer class="panel__footer">
        <el-button type="primary" link @click="viewAll">
          View all notifications
          <el-icon class="el-icon--right"><IconArrowRight /></el-icon>
        </el-button>
      </footer>
    </div>
  </el-popover>
</template>

<script>
import moment from 'moment';
import { useUserStore } from '@/store';

const PREVIEW = 6;

export default {
  data() {
    return {
      open: false,
    };
  },
  computed: {
    // the store guarantees an array, but guard anyway — this component renders on every admin page
    notifications() {
      const list = useUserStore().notifications;
      return Array.isArray(list) ? list : [];
    },
    count() {
      return this.notifications.length;
    },
    recent() {
      return this.notifications.slice(0, PREVIEW);
    },
  },
  methods: {
    titleOf(notification) {
      return (notification.data && notification.data.title) || notification.title || 'Notification';
    },
    descriptionOf(notification) {
      return (notification.data && notification.data.description) || notification.description || '';
    },
    timeOf(notification) {
      return notification.created_at ? moment(notification.created_at).fromNow() : '';
    },
    viewAll() {
      this.open = false;
      this.$router.push('/notifications');
    },
  },
};
</script>

<style lang="scss" scoped>
.bell {
  position: relative;
  display: grid;
  place-items: center;
  width: 40px;
  height: 40px;
  border: 0;
  border-radius: 10px;
  background: transparent;
  color: var(--admin-muted);
  font-size: 20px;
  cursor: pointer;
  transition: background-color 0.15s ease, color 0.15s ease;

  &:hover {
    background: var(--admin-primary-soft);
    color: var(--admin-primary);
  }

  // sits on the bell's upper-right corner, clear of the icon itself
  &__badge {
    position: absolute;
    top: -2px;
    right: -4px;
    min-width: 16px;
    height: 16px;
    padding: 0 4px;
    border-radius: 999px;
    background: var(--admin-accent);
    border: 2px solid #fff;
    box-sizing: content-box;
    color: #fff;
    font-size: 10px;
    font-weight: 700;
    line-height: 16px;
    text-align: center;
  }
}

.panel {
  margin: -12px;

  &__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 16px 18px 12px;
    border-bottom: 1px solid var(--admin-border);
  }

  &__title {
    margin: 0;
    font-size: 15px;
    font-weight: 700;
  }

  &__count {
    padding: 2px 9px;
    border-radius: 999px;
    background: var(--admin-danger-soft);
    color: var(--admin-danger);
    font-size: 12px;
    font-weight: 700;
  }

  &__list {
    margin: 0;
    padding: 0;
    max-height: 360px;
    overflow-y: auto;
    list-style: none;
  }

  &__item {
    display: flex;
    gap: 12px;
    padding: 13px 18px;
    border-bottom: 1px solid var(--admin-border);

    &:last-child {
      border-bottom: 0;
    }

    &:hover {
      background: var(--admin-surface-soft);
    }
  }

  &__dot {
    flex: none;
    width: 8px;
    height: 8px;
    margin-top: 6px;
    border-radius: 50%;
    background: var(--admin-primary);
  }

  &__text {
    min-width: 0;
  }

  &__item-title {
    margin: 0;
    font-size: 13.5px;
    font-weight: 650;
    color: var(--admin-text);
  }

  &__item-desc {
    margin: 3px 0 0;
    font-size: 13px;
    line-height: 1.45;
    color: var(--admin-muted);
    overflow-wrap: anywhere;
  }

  &__time {
    display: block;
    margin-top: 5px;
    font-size: 12px;
    color: var(--admin-muted);
  }

  &__footer {
    display: flex;
    justify-content: center;
    padding: 10px;
    border-top: 1px solid var(--admin-border);
  }
}
</style>
