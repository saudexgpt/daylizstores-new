<template>
  <el-table
    :data="tableRows"
    :row-class-name="rowClass"
    :show-header="true"
    class="report-table"
    empty-text=" "
  >
    <el-table-column
      v-for="(col, index) in shown"
      :key="col.key"
      :label="col.label"
      :prop="col.key"
      :align="col.align === 'right' ? 'right' : 'left'"
      :min-width="minWidth(col, index)"
      :fixed="index === 0 && isWide ? 'left' : false"
    >
      <template #default="{ row }">
        <template v-if="col.type === 'status' && row._kind !== 'footer' && cellValue(row, col) !== ''">
          <admin-status-tag :status="String(row[col.key])" :tone="toneFor(row[col.key])" kind="generic" />
        </template>
        <span v-else-if="col.type === 'money'" class="money" :class="{ 'is-negative': Number(row[col.key]) < 0 }">{{ cellValue(row, col) }}</span>
        <span v-else-if="col.type === 'int' || col.type === 'percent'" class="num">{{ cellValue(row, col) }}</span>
        <span v-else :class="labelClass(row, index)">{{ cellValue(row, col) }}</span>
      </template>
    </el-table-column>

    <template #empty>
      <admin-empty title="Nothing to show" :description="emptyText" icon="Files" />
    </template>
  </el-table>
</template>

<script setup>
import { computed } from 'vue';
import { formatCell, visibleColumns } from '@/utils/reportFormat';

const props = defineProps({
  columns: { type: Array, default: () => [] },
  rows: { type: Array, default: () => [] },
  footer: { type: Object, default: null },
  emptyText: { type: String, default: 'No records match these filters. Try a wider period or fewer filters.' },
});

const shown = computed(() => visibleColumns(props.columns, props.rows, props.footer));
const tableRows = computed(() => (props.footer ? [...props.rows, { ...props.footer, _kind: 'footer' }] : props.rows));
// a first column that stays put while a wide table scrolls sideways — only worth doing when it would
const isWide = computed(() => shown.value.length > 6);

const cellValue = (row, col) => formatCell(row[col.key], col.type);

const minWidth = (col, index) => {
  if (col.type === 'money') return 140;
  if (col.type === 'int') return 120;
  if (col.type === 'percent') return 150;
  if (col.type === 'date') return 120;
  if (col.type === 'datetime') return 160;
  if (col.type === 'status') return 150;
  return index === 0 ? 200 : 150;
};

// statement-style rows (profit & loss, balance sheet, ledger) carry a `_kind` that styles them
const rowClass = ({ row }) => (row._kind ? `is-${row._kind}` : '');
const labelClass = (row, index) => ({ 'is-indented': index === 0 && row._kind === 'line' });

const TONES = {
  success: ['delivered', 'paid', 'posted', 'in stock', 'active'],
  warning: ['pending', 'low'],
  info: ['on transit'],
  danger: ['cancelled', 'void', 'out of stock', 'oversold'],
};
const toneFor = (value) => {
  const v = String(value).toLowerCase();
  return Object.keys(TONES).find(tone => TONES[tone].includes(v)) || 'neutral';
};
</script>

<style lang="scss" scoped>
.report-table {
  width: 100%;

  .num,
  .money {
    font-variant-numeric: tabular-nums;
  }

  .money.is-negative {
    color: var(--admin-danger);
  }

  .is-indented {
    padding-left: 18px;
  }

  :deep(.el-table__row) {
    &.is-header td {
      background: var(--admin-surface-soft);
      font-weight: 700;
      color: var(--admin-text);
    }

    &.is-subtotal td {
      font-weight: 650;
      border-top: 1px solid var(--admin-border);
    }

    &.is-total td {
      font-weight: 700;
      font-size: 15px;
      background: var(--admin-primary-soft);
      border-top: 2px solid var(--admin-border);
    }

    &.is-opening td,
    &.is-closing td {
      font-style: italic;
      font-weight: 600;
      background: var(--admin-surface-soft);
    }

    &.is-footer td {
      font-weight: 700;
      background: var(--admin-surface-soft);
      border-top: 2px solid var(--admin-border);
    }
  }
}

@media print {
  .report-table :deep(.el-table__body-wrapper) {
    overflow: visible;
  }
}
</style>
