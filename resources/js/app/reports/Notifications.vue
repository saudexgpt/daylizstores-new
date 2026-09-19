<template>
  <div class="notifications">
    <admin-page-header title="Notifications" subtitle="What has happened since you last looked." />

    <admin-card>
      <el-timeline v-if="items.length" v-loading="loading" class="notifications__timeline">
        <el-timeline-item
          v-for="(item, index) in items"
          :key="item.id || index"
          :timestamp="moment(item.created_at).fromNow()"
          placement="top"
          color="var(--admin-primary)"
        >
          <div class="notifications__item">
            <strong>{{ item.data.title }}</strong>
            <p>{{ item.data.description }}</p>
          </div>
        </el-timeline-item>
      </el-timeline>
      <admin-empty
        v-else
        icon="Bell"
        title="You're all caught up"
        description="Earlier activity is always available in the audit trail."
      />
    </admin-card>
  </div>
</template>

<script>
import moment from 'moment';
import Resource from '@/api/resource';
import { useUserStore } from '@/store';

const markNotificationAsRead = new Resource('reports/notification/mark-as-read');

export default {
  name: 'Notifications',
  data() {
    return {
      // a snapshot: opening this page marks everything read, and the bell is cleared behind it
      items: [],
      loading: false,
    };
  },
  created() {
    this.items = [...(useUserStore().notifications || [])];
    if (this.items.length) {
      this.markAsRead();
    }
  },
  methods: {
    moment,
    markAsRead() {
      this.loading = true;
      markNotificationAsRead.list()
        .then(() => {
          useUserStore().setNotifications([]);
        })
        .catch(() => {})
        .finally(() => {
          this.loading = false;
        });
    },
  },
};
</script>

<style lang="scss" scoped>
.notifications {
  &__timeline {
    padding: 4px 4px 0;
    max-width: 760px;
  }

  &__item {
    padding: 12px 16px;
    border: 1px solid var(--admin-border);
    border-radius: 12px;
    background: var(--admin-surface-soft);

    strong { font-size: 14px; }
    p { margin: 4px 0 0; color: var(--admin-muted); line-height: 1.5; overflow-wrap: anywhere; }
  }
}
</style>
