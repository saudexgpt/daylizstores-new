<template>
  <div class="statements">
    <admin-page-header title="Financial statements" subtitle="The four statements an accountant, a bank or a tax office will ask for — live, filterable and downloadable." />

    <div v-if="loading" class="statements__loading"><el-skeleton :rows="5" animated /></div>

    <admin-card v-else-if="!tabs.length">
      <admin-empty title="Statements unavailable" description="They could not be loaded. Try refreshing the page." icon="Notebook" />
    </admin-card>

    <template v-else>
      <el-tabs v-model="active" class="no-print">
        <el-tab-pane v-for="t in tabs" :key="t.key" :label="t.short" :name="t.key" />
      </el-tabs>
      <report-runner v-if="current" :meta="current" />
    </template>
  </div>
</template>

<script>
import ReportRunner from '@/components/admin/ReportRunner.vue';
import { fetchCatalog } from '@/api/reports';

// the statements, in the order an accountant reads them
const ORDER = [
  ['profit-loss', 'Profit & loss'],
  ['balance-sheet', 'Balance sheet'],
  ['trial-balance', 'Trial balance'],
  ['general-ledger', 'General ledger'],
];

export default {
  name: 'AccountingStatements',
  components: { ReportRunner },
  data() {
    return { tabs: [], active: 'profit-loss', loading: true };
  },
  computed: {
    current() {
      const tab = this.tabs.find(t => t.key === this.active);
      return tab ? tab.meta : null;
    },
  },
  created() {
    fetchCatalog()
      .then(({ reports }) => {
        this.tabs = ORDER
          .map(([key, short]) => ({ key, short, meta: reports.find(r => r.key === key) }))
          .filter(t => t.meta);
        if (this.tabs.length && !this.tabs.some(t => t.key === this.active)) {
          this.active = this.tabs[0].key;
        }
      })
      .catch(() => {})
      .finally(() => {
        this.loading = false;
      });
  },
};
</script>
