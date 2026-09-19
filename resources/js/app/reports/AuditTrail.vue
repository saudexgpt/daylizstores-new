<template>
  <div class="audit">
    <admin-page-header title="Audit trail" subtitle="A record of who changed what, and when." />

    <admin-card flush>
      <template #toolbar>
        <div class="admin-toolbar">
          <el-date-picker
            v-model="dateRange"
            type="daterange"
            range-separator="to"
            start-placeholder="From"
            end-placeholder="To"
            value-format="YYYY-MM-DD"
            format="D MMM YYYY"
            :shortcuts="shortcuts"
            :disabled-date="isFuture"
            class="audit__dates"
            @change="handleDateRangeChange"
          />
          <el-input
            v-model="search"
            class="grow"
            placeholder="Filter this page (action or description)"
            clearable
          >
            <template #prefix>
              <el-icon><IconSearch /></el-icon>
            </template>
          </el-input>
          <span v-if="!dateRange" class="text-muted">Showing this week</span>
        </div>
      </template>

      <el-table v-loading="listLoading" :data="visible" empty-text="No activity in this period">
        <el-table-column label="When" min-width="170">
          <template #default="{ row }">
            <div class="cell-title">{{ moment(row.created_at).fromNow() }}</div>
            <div class="cell-sub">{{ moment(row.created_at).format('D MMM YYYY, h:mm a') }}</div>
          </template>
        </el-table-column>
        <el-table-column label="Action" min-width="200">
          <template #default="{ row }">
            <admin-status-tag :tone="toneFor(row.data.title)" :label="row.data.title" kind="generic" />
          </template>
        </el-table-column>
        <el-table-column label="Details" min-width="420">
          <template #default="{ row }">
            <span class="audit__description">{{ row.data.description }}</span>
          </template>
        </el-table-column>
      </el-table>

      <div v-if="total > 0" class="pager">
        <el-pagination
          v-model:current-page="listQuery.page"
          :page-size="20"
          :total="total"
          layout="total, prev, pager, next"
          background
          @current-change="fetchAuditTrail"
        />
      </div>
    </admin-card>
  </div>
</template>

<script>
import moment from 'moment';
import Resource from '@/api/resource';

const auditTrailResource = new Resource('reports/audit-trails');

export default {
  name: 'AuditTrail',
  data() {
    const day = (n) => moment().subtract(n, 'days').toDate();
    return {
      activity_logs: [],
      total: 0,
      listLoading: false,
      listQuery: { page: 1, from: '', to: '', panel: '' },
      dateRange: null,
      search: '',
      shortcuts: [
        { text: 'Today', value: () => [day(0), day(0)] },
        { text: 'Last 7 days', value: () => [day(6), day(0)] },
        { text: 'Last 30 days', value: () => [day(29), day(0)] },
        { text: 'This month', value: () => [moment().startOf('month').toDate(), day(0)] },
      ],
    };
  },
  computed: {
    // the search box narrows the page already on screen
    visible() {
      const needle = this.search.trim().toLowerCase();
      if (!needle) {
        return this.activity_logs;
      }
      return this.activity_logs.filter(log => `${log.data.title} ${log.data.description}`.toLowerCase().includes(needle));
    },
  },
  created() {
    this.fetchAuditTrail();
  },
  methods: {
    moment,
    isFuture(date) {
      return date.getTime() > Date.now();
    },
    // colour the action so risky ones (deletions, resets) stand out
    toneFor(title) {
      const t = String(title || '').toLowerCase();
      if (/delet|cancel|remov/.test(t)) {
        return 'danger';
      }
      if (/reset|password|role|permission/.test(t)) {
        return 'warning';
      }
      if (/paid|payment|delivered|added|new|created|stock/.test(t)) {
        return 'success';
      }
      return 'info';
    },
    fetchAuditTrail() {
      this.listLoading = true;
      auditTrailResource.list(this.listQuery)
        .then(response => {
          this.activity_logs = response.activity_logs.data;
          this.total = response.activity_logs.total;
        })
        .catch(() => {
          // the shared axios interceptor has already shown the error
        })
        .finally(() => {
          this.listLoading = false;
        });
    },
    handleDateRangeChange(values) {
      this.listQuery.page = 1;
      if (values) {
        [this.listQuery.from, this.listQuery.to] = values;
        this.listQuery.panel = 'custom';
      } else {
        this.listQuery.from = '';
        this.listQuery.to = '';
        this.listQuery.panel = '';
      }
      this.fetchAuditTrail();
    },
  },
};
</script>

<style lang="scss" scoped>
.audit {
  &__dates {
    width: 300px;
    flex: none;
  }

  &__description {
    line-height: 1.5;
    color: var(--admin-text);
  }
}

@media (max-width: 720px) {
  .audit__dates { width: 100%; }
}
</style>
