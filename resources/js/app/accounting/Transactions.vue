<template>
  <div class="txns">
    <admin-page-header title="Income & expenses" subtitle="Every naira in and out of the business. Website sales are booked automatically each day.">
      <template v-if="canManage">
        <el-button type="primary" @click="add('expense')">
          <el-icon><IconPlus /></el-icon>Add expense
        </el-button>
        <el-button type="success" plain @click="add('income')">
          <el-icon><IconPlus /></el-icon>Add income
        </el-button>
        <el-dropdown trigger="click" @command="add">
          <el-button>More<el-icon class="el-icon--right"><IconArrowDown /></el-icon></el-button>
          <template #dropdown>
            <el-dropdown-menu>
              <el-dropdown-item command="transfer">Transfer between accounts</el-dropdown-item>
              <el-dropdown-item command="journal">Journal entry</el-dropdown-item>
            </el-dropdown-menu>
          </template>
        </el-dropdown>
      </template>
    </admin-page-header>

    <div class="txns__totals">
      <div class="txns__total">
        <span>Income</span>
        <strong class="is-good">{{ naira(summary.income) }}</strong>
      </div>
      <div class="txns__total">
        <span>Expenses</span>
        <strong>{{ naira(summary.expense) }}</strong>
      </div>
      <div class="txns__total txns__total--net">
        <span>{{ summary.net >= 0 ? 'Profit' : 'Loss' }}</span>
        <strong :class="summary.net >= 0 ? 'is-good' : 'is-bad'">{{ naira(Math.abs(summary.net)) }}</strong>
      </div>
      <p class="txns__totals-note">For the transactions shown below. Voided entries are not counted.</p>
    </div>

    <admin-card flush>
      <template #toolbar>
        <div class="admin-toolbar">
          <period-picker v-model="range" no-future @update:model-value="search" />
          <el-select v-model="query.type" clearable placeholder="All types" class="txns__type" @change="search">
            <el-option label="Income" value="income" />
            <el-option label="Expense" value="expense" />
            <el-option label="Transfer" value="transfer" />
            <el-option label="Journal" value="journal" />
          </el-select>
          <el-select v-model="query.account_id" clearable filterable placeholder="Any account" class="txns__account" @change="search">
            <el-option v-for="a in accounts" :key="a.id" :label="`${a.code} · ${a.name}`" :value="a.id" />
          </el-select>
          <el-input v-model="query.q" class="grow" placeholder="Search description, payee or reference" clearable @keyup.enter="search" @clear="search">
            <template #prefix>
              <el-icon><IconSearch /></el-icon>
            </template>
          </el-input>
        </div>
        <div class="admin-toolbar txns__more">
          <el-input-number v-model="query.min_amount" :min="0" :controls="false" :precision="2" placeholder="Min ₦" @change="search" />
          <el-input-number v-model="query.max_amount" :min="0" :controls="false" :precision="2" placeholder="Max ₦" @change="search" />
          <el-select v-model="query.status" clearable placeholder="Posted and voided" class="txns__status" @change="search">
            <el-option label="Posted only" value="posted" />
            <el-option label="Voided only" value="void" />
          </el-select>
          <el-radio-group v-model="query.source" @change="search">
            <el-radio-button value="manual">Recorded by hand</el-radio-button>
            <el-radio-button value="sales">Daily sales</el-radio-button>
            <el-radio-button value="stock">Stock</el-radio-button>
            <el-radio-button value="all">Everything</el-radio-button>
          </el-radio-group>
        </div>
      </template>

      <el-table v-loading="loading" :data="rows" empty-text=" " :row-class-name="rowClass">
        <el-table-column label="Date" width="120">
          <template #default="{ row }">{{ dateText(row.date) }}</template>
        </el-table-column>
        <el-table-column label="Description" min-width="260">
          <template #default="{ row }">
            <div class="cell-title">{{ row.description }}</div>
            <div class="cell-sub">
              <span class="mono">{{ row.reference }}</span>
              <template v-if="row.party"> · {{ row.party }}</template>
              <template v-if="row.payment_reference"> · ref {{ row.payment_reference }}</template>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="Type" width="120">
          <template #default="{ row }">
            <admin-status-tag v-if="row.status === 'void'" tone="danger" kind="generic" label="Void" />
            <admin-status-tag v-else :tone="typeTone[row.type]" kind="generic" :label="typeLabel[row.type]" />
          </template>
        </el-table-column>
        <el-table-column label="Category / account" min-width="220">
          <template #default="{ row }">
            <template v-if="row.type === 'transfer'">
              <div class="cell-title">{{ row.from ? row.from.name : '' }} → {{ row.to ? row.to.name : '' }}</div>
            </template>
            <template v-else-if="row.category">
              <div class="cell-title">{{ row.category.name }}</div>
              <div v-if="row.account" class="cell-sub">{{ row.type === 'expense' ? 'Paid from' : 'Into' }} {{ row.account.name }}</div>
            </template>
            <span v-else class="text-muted">Multiple accounts</span>
          </template>
        </el-table-column>
        <el-table-column label="Amount" align="right" min-width="140">
          <template #default="{ row }">
            <span class="money" :class="amountClass(row)">{{ sign(row) }}{{ naira(row.amount) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="" width="150" align="right" fixed="right">
          <template #default="{ row }">
            <span class="row-actions">
              <el-tooltip content="View" placement="top">
                <el-button circle size="small" aria-label="View" @click="view(row)"><el-icon><IconView /></el-icon></el-button>
              </el-tooltip>
              <template v-if="canManage && row.editable">
                <el-tooltip content="Correct" placement="top">
                  <el-button circle size="small" type="primary" plain aria-label="Correct" @click="edit(row)"><el-icon><IconEdit /></el-icon></el-button>
                </el-tooltip>
                <el-tooltip content="Void" placement="top">
                  <el-button circle size="small" type="danger" plain aria-label="Void" @click="voidRow(row)"><el-icon><IconDelete /></el-icon></el-button>
                </el-tooltip>
              </template>
            </span>
          </template>
        </el-table-column>

        <template #empty>
          <admin-empty
            v-if="loaded"
            title="No transactions in this period"
            :description="canManage ? 'Record an expense or some income, or widen the dates and filters.' : 'Try a wider period or fewer filters.'"
            icon="Notebook"
          />
        </template>
      </el-table>

      <div v-if="total > query.per_page" class="pager">
        <el-pagination
          v-model:current-page="query.page"
          :page-size="query.per_page"
          :total="total"
          layout="total, prev, pager, next"
          background
          @current-change="fetch"
        />
      </div>
    </admin-card>

    <transaction-dialog v-model="dialog.open" :accounts="accounts" :type="dialog.type" :transaction="dialog.transaction" @saved="fetch" />

    <!-- one transaction in full -->
    <el-drawer v-model="detail.open" :title="detail.txn ? detail.txn.reference : ''" size="520px" append-to-body>
      <div v-if="detail.txn" v-loading="detail.loading" class="detail">
        <div class="detail__head">
          <admin-status-tag v-if="detail.txn.status === 'void'" tone="danger" kind="generic" label="Void" />
          <admin-status-tag v-else :tone="typeTone[detail.txn.type]" kind="generic" :label="typeLabel[detail.txn.type]" />
          <span class="money detail__amount">{{ naira(detail.txn.amount) }}</span>
        </div>
        <p class="detail__desc">{{ detail.txn.description }}</p>

        <dl class="detail__meta">
          <div><dt>Date</dt><dd>{{ dateText(detail.txn.date) }}</dd></div>
          <div v-if="detail.txn.party"><dt>Party</dt><dd>{{ detail.txn.party }}</dd></div>
          <div v-if="detail.txn.payment_reference"><dt>Reference no.</dt><dd>{{ detail.txn.payment_reference }}</dd></div>
          <div v-if="detail.txn.created_by"><dt>Recorded by</dt><dd>{{ detail.txn.created_by }}, {{ dateTimeText(detail.txn.created_at) }}</dd></div>
        </dl>

        <el-alert
          v-if="detail.txn.status === 'void'"
          type="error"
          :closable="false"
          show-icon
          title="This transaction was voided"
          :description="`${detail.txn.void_reason || ''} ${detail.txn.voided_at ? '(' + dateTimeText(detail.txn.voided_at) + ')' : ''}`"
        />
        <p v-if="detail.txn.replaced_by_entry_id" class="text-muted">A corrected version was posted in its place.</p>
        <p v-if="detail.txn.replaces_entry_id" class="text-muted">This corrects an earlier entry, which was voided.</p>

        <h4 class="detail__title">Double-entry lines</h4>
        <el-table :data="detail.txn.lines || []" size="small">
          <el-table-column label="Account" min-width="180">
            <template #default="{ row }"><span class="mono">{{ row.code }}</span> {{ row.name }}</template>
          </el-table-column>
          <el-table-column label="Debit" align="right" width="110">
            <template #default="{ row }">{{ row.debit ? money(row.debit) : '' }}</template>
          </el-table-column>
          <el-table-column label="Credit" align="right" width="110">
            <template #default="{ row }">{{ row.credit ? money(row.credit) : '' }}</template>
          </el-table-column>
        </el-table>
      </div>
    </el-drawer>
  </div>
</template>

<script>
import moment from 'moment';
import { ElMessageBox } from 'element-plus';
import PeriodPicker from '@/components/admin/PeriodPicker.vue';
import TransactionDialog from './components/TransactionDialog.vue';
import { fetchAccounts, listTransactions, showTransaction, voidTransaction } from '@/api/accounting';
import { dateText, dateTimeText, money, naira } from '@/utils/reportFormat';
import { can } from '@/utils/permission';

export default {
  name: 'AccountingTransactions',
  components: { PeriodPicker, TransactionDialog },
  data() {
    return {
      rows: [],
      total: 0,
      summary: { income: 0, expense: 0, net: 0 },
      accounts: [],
      loading: false,
      loaded: false,
      range: [moment().startOf('month').format('YYYY-MM-DD'), moment().format('YYYY-MM-DD')],
      query: { type: '', account_id: null, q: '', min_amount: undefined, max_amount: undefined, status: '', source: 'manual', page: 1, per_page: 25 },
      dialog: { open: false, type: 'expense', transaction: null },
      detail: { open: false, loading: false, txn: null },
      typeLabel: { income: 'Income', expense: 'Expense', transfer: 'Transfer', journal: 'Journal', opening: 'Opening' },
      typeTone: { income: 'success', expense: 'warning', transfer: 'info', journal: 'neutral', opening: 'neutral' },
    };
  },
  computed: {
    canManage() {
      return can('manage accounting');
    },
  },
  created() {
    fetchAccounts().then(a => {
      this.accounts = a;
    }).catch(() => {});
    this.fetch();
  },
  methods: {
    naira,
    money,
    dateText,
    dateTimeText,
    search() {
      this.query.page = 1;
      this.fetch();
    },
    params() {
      const p = { ...this.query, from: this.range[0], to: this.range[1] };
      Object.keys(p).forEach(k => (p[k] === '' || p[k] === null || p[k] === undefined) && delete p[k]);
      return p;
    },
    fetch() {
      this.loading = true;
      return listTransactions(this.params())
        .then(r => {
          this.rows = r.transactions;
          this.total = r.pagination.total;
          this.summary = r.summary;
        })
        .catch(() => {})
        .finally(() => {
          this.loading = false;
          this.loaded = true;
        });
    },
    rowClass: ({ row }) => (row.status === 'void' ? 'is-void' : ''),
    sign: (row) => (row.status === 'void' || ['transfer', 'journal', 'opening'].includes(row.type) ? '' : (row.type === 'expense' ? '−' : '+')),
    amountClass: (row) => ({ 'is-good': row.type === 'income' && row.status !== 'void', 'is-void': row.status === 'void' }),
    add(type) {
      this.dialog = { open: true, type, transaction: null };
    },
    edit(row) {
      // the list row has no lines (a journal needs them), so fetch the full entry first
      showTransaction(row.id).then(r => {
        this.dialog = { open: true, type: r.transaction.type, transaction: r.transaction };
      }).catch(() => {});
    },
    view(row) {
      this.detail = { open: true, loading: true, txn: row };
      showTransaction(row.id)
        .then(r => {
          this.detail.txn = r.transaction;
        })
        .catch(() => {})
        .finally(() => {
          this.detail.loading = false;
        });
    },
    voidRow(row) {
      ElMessageBox.prompt(
        `${row.reference} — ${row.description}. The entry stays on record, marked void, with a reversing entry so the books stay balanced. Why is it being voided?`,
        'Void this transaction?',
        {
          confirmButtonText: 'Void it',
          cancelButtonText: 'Keep it',
          type: 'warning',
          inputPlaceholder: 'Reason (e.g. entered twice)',
          inputValidator: v => (v && v.trim().length >= 3) || 'Give a reason of at least 3 characters',
        },
      ).then(({ value }) => voidTransaction(row.id, value.trim()))
        .then(() => {
          this.$message({ message: 'Transaction voided', type: 'success' });
          this.fetch();
        })
        .catch(() => {});
    },
  },
};
</script>

<style lang="scss" scoped>
.txns {
  &__totals {
    display: flex;
    flex-wrap: wrap;
    align-items: stretch;
    gap: 14px;
    margin-bottom: 18px;
  }

  &__total {
    display: flex;
    flex-direction: column;
    gap: 2px;
    min-width: 170px;
    padding: 12px 18px;
    background: var(--admin-surface);
    border: 1px solid var(--admin-border);
    border-radius: var(--admin-radius);

    span {
      font-size: 12px;
      font-weight: 500;
      color: var(--admin-muted);
    }

    strong {
      font-size: 20px;
      font-variant-numeric: tabular-nums;
    }

    &--net {
      background: var(--admin-surface-soft);
    }
  }

  &__totals-note {
    align-self: center;
    margin: 0;
    font-size: 12px;
    color: var(--admin-muted);
  }

  &__type {
    width: 140px;
  }

  &__account {
    width: 230px;
  }

  &__status {
    width: 180px;
  }

  &__more {
    margin-top: 10px;

    :deep(.el-input-number) {
      width: 130px;
    }
  }

  .is-good {
    color: var(--admin-success);
  }

  .is-bad {
    color: var(--admin-danger);
  }

  .is-void {
    text-decoration: line-through;
    color: var(--admin-muted);
  }

  :deep(.el-table__row.is-void td) {
    opacity: 0.65;
  }
}

.detail {
  &__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
  }

  &__amount {
    font-size: 26px;
  }

  &__desc {
    margin: 10px 0 16px;
    font-size: 15px;
    font-weight: 600;
  }

  &__meta {
    margin: 0 0 16px;

    div {
      display: flex;
      gap: 12px;
      padding: 7px 0;
      border-bottom: 1px solid var(--admin-border);
      font-size: 13px;
    }

    dt {
      flex: 0 0 110px;
      color: var(--admin-muted);
    }

    dd {
      margin: 0;
      min-width: 0;
      overflow-wrap: anywhere;
    }
  }

  &__title {
    margin: 22px 0 8px;
    font-size: 14px;
  }
}
</style>
