<template>
  <div class="report-centre">
    <admin-page-header title="Reports" subtitle="Every report in one place — filter by date and more, then view, print or download." />

    <div v-if="loading" class="rc-loading"><el-skeleton :rows="6" animated /></div>

    <admin-card v-else-if="!catalog.length">
      <admin-empty title="No reports available" description="Your account does not have access to any reports. Ask an administrator for the “view reports” or “view accounting” permission." icon="DataAnalysis" />
    </admin-card>

    <div v-else class="rc-layout">
      <nav class="rc-nav no-print" aria-label="Reports">
        <section v-for="group in grouped" :key="group.name" class="rc-group">
          <h3 class="rc-group__title">
            <el-icon><component :is="'Icon' + group.icon" /></el-icon>{{ group.name }}
          </h3>
          <button
            v-for="report in group.reports"
            :key="report.key"
            type="button"
            class="rc-item"
            :class="{ 'is-active': current && current.key === report.key }"
            @click="select(report.key)"
          >
            <span class="rc-item__title">{{ report.title }}</span>
            <span class="rc-item__desc">{{ report.description }}</span>
          </button>
        </section>
      </nav>

      <div class="rc-main">
        <!-- phones: a dropdown replaces the side list -->
        <el-select
          v-if="current"
          :model-value="current.key"
          class="rc-picker no-print"
          @update:model-value="select"
        >
          <el-option-group v-for="group in grouped" :key="group.name" :label="group.name">
            <el-option v-for="r in group.reports" :key="r.key" :label="r.title" :value="r.key" />
          </el-option-group>
        </el-select>

        <report-runner :meta="current" />
      </div>
    </div>
  </div>
</template>

<script>
import ReportRunner from '@/components/admin/ReportRunner.vue';
import { fetchCatalog } from '@/api/reports';

const GROUP_ICONS = { Sales: 'ShoppingBag', Inventory: 'Box', Customers: 'User', Finance: 'Wallet' };

export default {
  name: 'ReportCentre',
  components: { ReportRunner },
  data() {
    return { catalog: [], loading: true };
  },
  computed: {
    // groups in the order the server lists them, each with its reports
    grouped() {
      const groups = [];
      this.catalog.forEach(r => {
        let g = groups.find(x => x.name === r.group);
        if (!g) {
          g = { name: r.group, icon: GROUP_ICONS[r.group] || 'Document', reports: [] };
          groups.push(g);
        }
        g.reports.push(r);
      });
      return groups;
    },
    current() {
      return this.catalog.find(r => r.key === this.$route.query.report) || this.catalog[0] || null;
    },
  },
  created() {
    fetchCatalog()
      .then(data => {
        this.catalog = data.reports;
      })
      .catch(() => {})
      .finally(() => {
        this.loading = false;
      });
  },
  methods: {
    // the chosen report lives in the URL, so it can be bookmarked or shared
    select(key) {
      this.$router.replace({ query: { ...this.$route.query, report: key } });
    },
  },
};
</script>

<style lang="scss" scoped>
.rc-layout {
  display: grid;
  grid-template-columns: 290px minmax(0, 1fr);
  gap: 24px;
  align-items: start;
}

.rc-nav {
  position: sticky;
  top: 16px;
  max-height: calc(100vh - 110px);
  overflow-y: auto;
  padding: 10px;
  background: var(--admin-surface);
  border: 1px solid var(--admin-border);
  border-radius: var(--admin-radius);
  box-shadow: var(--admin-shadow);
}

.rc-group {
  & + & {
    margin-top: 6px;
  }

  &__title {
    display: flex;
    align-items: center;
    gap: 8px;
    margin: 0;
    padding: 10px 10px 6px;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--admin-muted);
  }
}

.rc-item {
  display: block;
  width: 100%;
  padding: 9px 10px;
  border: 0;
  border-radius: 9px;
  background: transparent;
  text-align: left;
  cursor: pointer;
  transition: background 0.15s;

  &:hover {
    background: var(--admin-surface-soft);
  }

  &:focus-visible {
    outline: 2px solid var(--admin-primary);
    outline-offset: -2px;
  }

  &.is-active {
    background: var(--admin-primary-soft);

    .rc-item__title {
      color: var(--admin-primary);
    }
  }

  &__title {
    display: block;
    font-size: 14px;
    font-weight: 600;
    color: var(--admin-text);
  }

  &__desc {
    display: block;
    margin-top: 2px;
    font-size: 12px;
    line-height: 1.4;
    color: var(--admin-muted);
  }
}

.rc-main {
  min-width: 0;
}

.rc-picker {
  display: none;
  width: 100%;
  margin-bottom: 16px;
}

@media (max-width: 960px) {
  .rc-layout {
    grid-template-columns: minmax(0, 1fr);
  }

  .rc-nav {
    display: none;
  }

  .rc-picker {
    display: block;
  }
}

@media print {
  .rc-layout {
    display: block;
  }
}
</style>
