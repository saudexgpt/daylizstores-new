<template>
  <div v-if="meta" class="rr">
    <p v-if="meta.note" class="rr__note no-print">
      <el-icon><IconInfoFilled /></el-icon>{{ meta.note }}
    </p>

    <!-- filters: drawn from the report's own spec, so every report gets the right controls -->
    <admin-card class="no-print" title="Filters">
      <form class="rr__filters" @submit.prevent="apply">
        <div
          v-for="spec in meta.filters"
          :key="spec.key"
          class="rr__field"
          :class="{ 'rr__field--wide': spec.type === 'date_range' }"
        >
          <label class="rr__label">{{ spec.label }}</label>

          <period-picker
            v-if="spec.type === 'date_range'"
            :model-value="[values.from, values.to]"
            @update:model-value="setRange"
          />
          <el-date-picker
            v-else-if="spec.type === 'date'"
            v-model="values[spec.key]"
            type="date"
            value-format="YYYY-MM-DD"
            format="D MMM YYYY"
            :clearable="false"
          />
          <el-select
            v-else-if="spec.type === 'select'"
            v-model="values[spec.key]"
            :clearable="spec.clearable"
            :filterable="spec.searchable"
            :placeholder="spec.clearable ? 'All' : 'Choose'"
          >
            <el-option v-for="o in spec.options" :key="o.value" :label="o.label" :value="o.value" />
          </el-select>
          <el-input
            v-else-if="spec.type === 'text'"
            v-model="values[spec.key]"
            :placeholder="spec.placeholder"
            clearable
          />
          <el-input-number
            v-else-if="spec.type === 'money'"
            v-model="values[spec.key]"
            :min="0"
            :precision="2"
            :controls="false"
            placeholder="Any"
            class="rr__number"
          />
          <el-input-number
            v-else-if="spec.type === 'number'"
            v-model="values[spec.key]"
            :min="spec.min"
            :max="spec.max"
            :step="1"
            :precision="0"
            class="rr__number"
          />
        </div>

        <div class="rr__actions">
          <el-button type="primary" native-type="submit" :loading="loading">Run report</el-button>
          <el-button @click="reset">Reset</el-button>
        </div>
      </form>
    </admin-card>

    <!-- printed reports carry their own heading, since the filters are hidden -->
    <div class="rr__print-head">
      <h2>{{ meta.title }}</h2>
      <p>{{ appliedSummary }}</p>
      <p>Generated {{ generatedText }}</p>
    </div>

    <admin-card flush>
      <template #title>
        {{ meta.title }}
        <span v-if="result" class="rr__count">{{ pagination.total.toLocaleString() }} {{ pagination.total === 1 ? 'row' : 'rows' }}</span>
      </template>
      <template #actions>
        <span class="no-print rr__buttons">
          <el-button :disabled="!canExport" :loading="downloading === 'csv'" @click="download('csv')">
            <el-icon><IconDownload /></el-icon>CSV
          </el-button>
          <el-button :disabled="!canExport" :loading="downloading === 'excel'" @click="download('excel')">
            <el-icon><IconDownload /></el-icon>Excel
          </el-button>
          <el-button :disabled="!result" @click="print">
            <el-icon><IconPrinter /></el-icon>Print
          </el-button>
        </span>
      </template>

      <div v-if="result && result.summary.length" class="rr__summary">
        <div v-for="s in result.summary" :key="s.label" class="rr__metric" :class="s.tone ? `is-${s.tone}` : ''">
          <span class="rr__metric-label">{{ s.label }}</span>
          <strong class="rr__metric-value">{{ summaryText(s) }}</strong>
        </div>
      </div>

      <div v-loading="loading" class="rr__table">
        <report-table
          v-if="result"
          :columns="result.columns"
          :rows="result.rows"
          :footer="result.footer"
        />
        <admin-empty v-else-if="!loading" title="Run the report" description="Choose your filters and press Run report." icon="DataAnalysis" />
        <div v-else style="min-height: 160px" />
      </div>

      <div v-if="result && pagination.total > pagination.per_page" class="pager no-print">
        <el-pagination
          v-model:current-page="page"
          v-model:page-size="perPage"
          :total="pagination.total"
          :page-sizes="[25, 50, 100, 200]"
          layout="total, sizes, prev, pager, next"
          background
          @current-change="fetch"
          @size-change="onSize"
        />
      </div>
      <p v-if="result && pagination.total > result.rows.length" class="rr__partial">
        Showing {{ result.rows.length }} of {{ pagination.total.toLocaleString() }} rows on this page — download the CSV or Excel file for all of them.
      </p>
    </admin-card>
  </div>
</template>

<script>
import PeriodPicker from '@/components/admin/PeriodPicker.vue';
import ReportTable from '@/components/admin/ReportTable.vue';
import { downloadReportCsv, downloadReportExcel, runReport } from '@/api/reports';
import { dateText, dateTimeText, formatCell, naira } from '@/utils/reportFormat';

/**
 * Runs any report from the catalogue. It draws the filter controls from the report's spec,
 * fetches a page of rows, shows the headline figures, and exports exactly what was run
 * (the filters as last applied — not whatever is half-typed in the form).
 */
export default {
  name: 'ReportRunner',
  components: { PeriodPicker, ReportTable },
  props: {
    // one entry of GET /reports/catalog
    meta: { type: Object, default: null },
  },
  data() {
    return {
      values: {},
      applied: {},
      result: null,
      loading: false,
      downloading: '',
      page: 1,
      perPage: 50,
      token: 0, // so a slow answer for a report you have already left cannot overwrite the current one
    };
  },
  computed: {
    pagination() {
      return this.result ? this.result.pagination : { total: 0, per_page: this.perPage };
    },
    canExport() {
      return !!this.result && this.result.pagination.total > 0 && !this.loading;
    },
    generatedText() {
      return this.result ? dateTimeText(this.result.generated_at.replace('T', ' ').slice(0, 16)) : '';
    },
    appliedSummary() {
      if (!this.meta) {
        return '';
      }
      const parts = [];
      this.meta.filters.forEach(spec => {
        if (spec.type === 'date_range') {
          parts.push(`${dateText(this.applied.from)} to ${dateText(this.applied.to)}`);
          return;
        }
        const value = this.applied[spec.key];
        if (value === undefined || value === null || value === '') {
          return;
        }
        if (spec.type === 'select') {
          const option = spec.options.find(o => String(o.value) === String(value));
          parts.push(`${spec.label}: ${option ? option.label : value}`);
        } else if (spec.type === 'date') {
          parts.push(`${spec.label} ${dateText(value)}`);
        } else {
          parts.push(`${spec.label}: ${value}`);
        }
      });
      return parts.join('  ·  ');
    },
  },
  watch: {
    'meta.key': {
      immediate: true,
      handler() {
        this.reset();
      },
    },
  },
  methods: {
    defaults() {
      const values = {};
      (this.meta ? this.meta.filters : []).forEach(spec => {
        if (spec.type === 'date_range') {
          values.from = spec.default.from;
          values.to = spec.default.to;
        } else {
          values[spec.key] = spec.default === undefined ? null : spec.default;
        }
      });
      return values;
    },
    reset() {
      this.values = this.defaults();
      this.result = null;
      this.page = 1;
      if (this.meta) {
        this.apply();
      }
    },
    setRange(range) {
      [this.values.from, this.values.to] = range || [null, null];
    },
    // only what is actually set goes to the server
    cleanValues() {
      const out = {};
      Object.keys(this.values).forEach(k => {
        const v = this.values[k];
        if (v !== null && v !== undefined && v !== '') {
          out[k] = v;
        }
      });
      return out;
    },
    apply() {
      this.applied = this.cleanValues();
      this.page = 1;
      this.fetch();
    },
    onSize() {
      this.page = 1;
      this.fetch();
    },
    fetch() {
      const key = this.meta.key;
      const token = ++this.token;
      this.loading = true;
      return runReport(key, { ...this.applied, page: this.page, per_page: this.perPage })
        .then(result => {
          if (token === this.token) {
            this.result = result;
          }
        })
        .catch(() => {
          // the request helper has already shown the reason; keep the last good result on screen
        })
        .finally(() => {
          if (token === this.token) {
            this.loading = false;
          }
        });
    },
    summaryText(s) {
      return s.type === 'money' ? naira(s.value) : (formatCell(s.value, s.type) || (s.value ?? '—'));
    },
    download(kind) {
      this.downloading = kind;
      const job = kind === 'csv' ? downloadReportCsv(this.meta.key, this.applied) : downloadReportExcel(this.meta.key, this.applied);
      return Promise.resolve(job)
        .catch(() => {})
        .finally(() => {
          this.downloading = '';
        });
    },
    print() {
      window.print();
    },
  },
};
</script>

<style lang="scss" scoped>
.rr {
  &__note {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    margin: 0 0 16px;
    padding: 10px 14px;
    border-radius: 10px;
    background: var(--admin-info-soft);
    color: var(--admin-info);
    font-size: 13px;
    line-height: 1.5;

    .el-icon {
      margin-top: 2px;
      flex: none;
    }
  }

  &__filters {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(210px, 1fr));
    gap: 14px 18px;
    align-items: start;
  }

  &__field {
    display: flex;
    flex-direction: column;
    gap: 6px;
    min-width: 0;

    &--wide {
      grid-column: span 2;
    }

    :deep(.el-select),
    :deep(.el-date-editor),
    :deep(.el-input-number) {
      width: 100%;
    }
  }

  &__label {
    font-size: 12px;
    font-weight: 600;
    color: var(--admin-muted);
  }

  &__actions {
    display: flex;
    gap: 8px;
    grid-column: 1 / -1;
  }

  &__count {
    margin-left: 8px;
    font-size: 13px;
    font-weight: 500;
    color: var(--admin-muted);
  }

  &__buttons {
    display: inline-flex;
    gap: 8px;
    flex-wrap: wrap;
  }

  &__summary {
    display: flex;
    flex-wrap: wrap;
    border-bottom: 1px solid var(--admin-border);
  }

  &__metric {
    flex: 1 1 200px;
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 14px 22px;
    background: var(--admin-surface);
    min-width: 0;
    box-shadow: 1px 0 0 var(--admin-border), 0 1px 0 var(--admin-border);

    &.is-good .rr__metric-value {
      color: var(--admin-success);
    }

    &.is-bad .rr__metric-value {
      color: var(--admin-danger);
    }
  }

  &__metric-label {
    font-size: 12px;
    font-weight: 500;
    color: var(--admin-muted);
  }

  &__metric-value {
    font-size: 18px;
    font-weight: 700;
    letter-spacing: -0.01em;
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
  }

  &__table {
    min-height: 120px;
  }

  &__partial {
    margin: 0;
    padding: 12px 22px;
    font-size: 13px;
    color: var(--admin-muted);
    border-top: 1px solid var(--admin-border);
  }

  &__print-head {
    display: none;
  }
}

@media (max-width: 640px) {
  .rr__field--wide {
    grid-column: 1 / -1;
  }

  .rr__metric {
    padding: 12px 14px;
  }
}

@media print {
  .rr__print-head {
    display: block;
    margin-bottom: 14px;

    h2 {
      margin: 0 0 4px;
      font-size: 20px;
    }

    p {
      margin: 0;
      font-size: 12px;
      color: #555;
    }
  }
}
</style>
