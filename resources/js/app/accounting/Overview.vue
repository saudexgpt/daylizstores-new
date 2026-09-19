<template>
  <div class="overview">
    <admin-page-header title="Accounting" subtitle="Is the business making a profit? Here is where the money came from and where it went.">
      <period-picker v-model="range" @update:model-value="fetch" />
      <template v-if="canManage">
        <el-button type="primary" @click="add('expense')">
          <el-icon><IconPlus /></el-icon>Add expense
        </el-button>
        <el-button type="success" plain @click="add('income')">
          <el-icon><IconPlus /></el-icon>Add income
        </el-button>
      </template>
    </admin-page-header>

    <div v-loading="loading" class="overview__body">
      <template v-if="data">
        <!-- the answer to the question the page exists for -->
        <section class="verdict" :class="profit >= 0 ? 'verdict--profit' : 'verdict--loss'">
          <div class="verdict__icon" aria-hidden="true">
            <el-icon><component :is="profit >= 0 ? 'IconTrendCharts' : 'IconWarning'" /></el-icon>
          </div>
          <div class="verdict__text">
            <p class="verdict__label">{{ profit >= 0 ? 'Profit' : 'Loss' }} for {{ periodText }}</p>
            <p class="verdict__value">{{ naira(Math.abs(profit)) }}</p>
            <p class="verdict__sub">
              <template v-if="kpis.income > 0">
                {{ profit >= 0 ? 'You kept' : 'You lost' }} {{ Math.abs(kpis.net_margin ?? 0).toFixed(1) }}% of every naira earned.
              </template>
              <template v-else>No income has been booked in this period.</template>
              <span v-if="compareText" :class="['verdict__delta', deltaGood ? 'is-good' : 'is-bad']">{{ compareText }}</span>
            </p>
          </div>
          <div class="verdict__sum">
            <div><span>Income</span><strong>{{ naira(kpis.income) }}</strong></div>
            <div><span>− Costs &amp; expenses</span><strong>{{ naira(kpis.total_expenses) }}</strong></div>
            <div class="verdict__net"><span>= {{ profit >= 0 ? 'Profit' : 'Loss' }}</span><strong>{{ naira(Math.abs(profit)) }}</strong></div>
          </div>
        </section>

        <el-alert
          v-if="kpis.income > 0 && kpis.total_expenses === 0"
          class="overview__warn"
          type="warning"
          show-icon
          :closable="false"
          title="No costs have been recorded for this period"
          description="The figure above is your revenue, not yet your profit. Record what you spent (stock purchases, transport, rent, salaries, ads) and it will update. Cash & bank also needs your opening balance — see Accounting settings."
        />

        <!-- product costing: is profit built on real costs? -->
        <el-alert
          v-if="costing && !costing.enabled && canManage"
          class="overview__warn"
          type="info"
          show-icon
          :closable="false"
          title="Product costing is not switched on"
          description="Until it is, profit only knows the expenses you type in — not what the goods you sold actually cost. Give each product size a cost and switch costing on to see real profit."
        >
          <router-link to="/accounting/cost-setup">Set up product costing</router-link>
        </el-alert>
        <router-link v-else-if="costing && costing.enabled && costCheck" to="/accounting/cost-setup" class="costbar" :class="{ 'is-bad': !costCheck.in_step }">
          <span class="costbar__item"><em>Stock at cost</em><strong>{{ naira(costCheck.stock_value) }}</strong></span>
          <span class="costbar__item"><em>Inventory in the books</em><strong>{{ naira(costCheck.books_value) }}</strong></span>
          <span class="costbar__item"><em>Books and shelf</em><strong>{{ costCheck.in_step ? 'In step' : 'Out of step — check' }}</strong></span>
          <span v-if="costCheck.provisional_units" class="costbar__item"><em>Sold without a delivery on record</em><strong>{{ costCheck.provisional_units }} units</strong></span>
        </router-link>

        <div class="grid-stats">
          <admin-stat-card label="Income" :value="naira(kpis.income)" icon="Money" tone="success" :hint="deltaHint(kpis.income, kpis.income_previous)" />
          <admin-stat-card label="Costs & expenses" :value="naira(kpis.total_expenses)" icon="ShoppingCart" tone="warning" :hint="deltaHint(kpis.total_expenses, kpis.total_expenses_previous)" />
          <admin-stat-card label="Gross profit" :value="naira(kpis.gross_profit)" icon="Coin" tone="info" :hint="kpis.gross_margin !== null ? `${kpis.gross_margin}% margin` : 'Income less cost of sales'" />
          <admin-stat-card label="Cash & bank" :value="naira(position.cash_and_bank)" icon="Wallet" tone="primary" hint="Right now" />
          <admin-stat-card label="Owed to you" :value="naira(position.receivable)" icon="Tickets" tone="info" hint="Receivables" />
          <admin-stat-card label="You owe" :value="naira(position.payable)" icon="Document" tone="danger" hint="Payables" />
        </div>

        <el-row :gutter="20">
          <el-col :xs="24" :lg="15">
            <admin-card title="Last 12 months" subtitle="Income against costs and expenses, month by month">
              <monthly-trend :points="data.trend" />
            </admin-card>
          </el-col>
          <el-col :xs="24" :lg="9">
            <admin-card title="Where the money went" :subtitle="periodText">
              <ul v-if="data.expense_breakdown.length" class="breakdown">
                <li v-for="line in breakdownShown" :key="line.code" class="breakdown__row">
                  <div class="breakdown__head">
                    <span class="breakdown__name">{{ line.name }}</span>
                    <span class="money">{{ naira(line.amount) }}</span>
                  </div>
                  <div class="breakdown__bar"><i :style="{ width: barWidth(line.amount) + '%' }" /></div>
                </li>
                <li v-if="data.expense_breakdown.length > 8" class="breakdown__more">+ {{ data.expense_breakdown.length - 8 }} smaller categories — see Reports → Expenses by category</li>
              </ul>
              <admin-empty v-else title="No expenses in this period" description="Costs you record will show up here, biggest first." icon="PieChart" />
            </admin-card>
          </el-col>
        </el-row>

        <admin-card v-if="position.accounts.length" title="Cash and bank accounts" subtitle="Balances as of today">
          <div class="accounts">
            <div v-for="a in position.accounts" :key="a.code" class="accounts__item">
              <span class="accounts__name">{{ a.name }}</span>
              <span class="mono text-muted">{{ a.code }}</span>
              <strong class="money" :class="{ 'is-neg': a.balance < 0 }">{{ naira(a.balance) }}</strong>
            </div>
          </div>
        </admin-card>

        <p class="overview__footnote">
          Website sales are booked into the ledger automatically every hour (revenue = paid, non-cancelled orders, less delivery).
          <router-link to="/accounting/settings" v-if="canManage">Accounting settings</router-link>
        </p>
      </template>
    </div>

    <transaction-dialog v-model="dialog.open" :accounts="accounts" :type="dialog.type" @saved="fetch" />
  </div>
</template>

<script>
import moment from 'moment';
import PeriodPicker from '@/components/admin/PeriodPicker.vue';
import MonthlyTrend from './components/MonthlyTrend.vue';
import TransactionDialog from './components/TransactionDialog.vue';
import { fetchAccounts, fetchOverview } from '@/api/accounting';
import { costingState, reconcile } from '@/api/costing';
import { dateText, naira } from '@/utils/reportFormat';
import { can } from '@/utils/permission';

export default {
  name: 'AccountingOverview',
  components: { PeriodPicker, MonthlyTrend, TransactionDialog },
  data() {
    return {
      data: null,
      loading: false,
      accounts: [],
      range: [moment().startOf('month').format('YYYY-MM-DD'), moment().format('YYYY-MM-DD')],
      dialog: { open: false, type: 'expense' },
      costing: null,
      costCheck: null,
    };
  },
  computed: {
    canManage() {
      return can('manage accounting');
    },
    kpis() {
      return this.data.kpis;
    },
    position() {
      return this.data.position;
    },
    profit() {
      return this.kpis.net_profit;
    },
    periodText() {
      return `${dateText(this.range[0])} – ${dateText(this.range[1])}`;
    },
    breakdownShown() {
      return this.data.expense_breakdown.slice(0, 8);
    },
    // vs the previous period of the same length
    compareText() {
      const prev = this.kpis.net_profit_previous;
      if (prev === null || prev === undefined) {
        return '';
      }
      const diff = this.profit - prev;
      if (Math.abs(diff) < 0.005) {
        return 'Same as the previous period.';
      }
      return `${diff > 0 ? '▲' : '▼'} ${naira(Math.abs(diff))} ${diff > 0 ? 'better' : 'worse'} than the previous period.`;
    },
    deltaGood() {
      const prev = this.kpis.net_profit_previous;
      return prev !== null && this.profit >= prev;
    },
  },
  created() {
    fetchAccounts().then(a => {
      this.accounts = a;
    }).catch(() => {});
    this.fetch();
    // costing status is only for people who manage the books or may see costs
    if (can('manage accounting') || can('view cost')) {
      costingState().then(state => {
        this.costing = state;
        return state.enabled ? reconcile().then(r => {
          this.costCheck = r;
        }) : null;
      }).catch(() => {});
    }
  },
  methods: {
    naira,
    fetch() {
      this.loading = true;
      return fetchOverview({ from: this.range[0], to: this.range[1] })
        .then(r => {
          this.data = r;
        })
        .catch(() => {})
        .finally(() => {
          this.loading = false;
        });
    },
    add(type) {
      this.dialog = { open: true, type };
    },
    barWidth(amount) {
      const top = this.data.expense_breakdown[0].amount || 1;
      return Math.max(2, (amount / top) * 100);
    },
    // "▲ 12.5% on the previous period"
    deltaHint(current, previous) {
      if (previous === null || previous === undefined) {
        return '';
      }
      if (!previous) {
        return current ? 'Nothing in the previous period' : '';
      }
      const pct = ((current - previous) / Math.abs(previous)) * 100;
      if (Math.abs(pct) < 0.05) {
        return 'Same as the previous period';
      }
      return `${pct > 0 ? '▲' : '▼'} ${Math.abs(pct).toFixed(1)}% on the previous period`;
    },
  },
};
</script>

<style lang="scss" scoped>
.verdict {
  --tone: var(--admin-success);
  --tone-soft: var(--admin-success-soft);

  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 20px 32px;
  margin-bottom: 20px;
  padding: 24px 28px;
  background: var(--tone-soft);
  border: 1px solid color-mix(in srgb, var(--tone) 25%, transparent);
  border-radius: var(--admin-radius);

  &--loss {
    --tone: var(--admin-danger);
    --tone-soft: var(--admin-danger-soft);
  }

  &__icon {
    display: grid;
    place-items: center;
    width: 56px;
    height: 56px;
    border-radius: 16px;
    background: var(--tone);
    color: #fff;
    font-size: 28px;
  }

  &__text {
    flex: 1 1 260px;
    min-width: 0;
  }

  &__label {
    margin: 0;
    font-size: 13px;
    font-weight: 600;
    color: var(--tone);
  }

  &__value {
    margin: 2px 0 4px;
    font-size: 38px;
    font-weight: 800;
    letter-spacing: -0.03em;
    line-height: 1.1;
    color: var(--admin-text);
    font-variant-numeric: tabular-nums;
    overflow-wrap: anywhere;
  }

  &__sub {
    margin: 0;
    font-size: 13px;
    line-height: 1.5;
    color: var(--admin-muted);
  }

  &__delta {
    display: block;
    margin-top: 2px;
    font-weight: 600;

    &.is-good { color: var(--admin-success); }
    &.is-bad { color: var(--admin-danger); }
  }

  &__sum {
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 260px;
    padding: 12px 16px;
    background: var(--admin-surface);
    border-radius: 12px;

    div {
      display: flex;
      justify-content: space-between;
      gap: 18px;
      font-size: 13px;
      color: var(--admin-muted);
    }

    strong {
      color: var(--admin-text);
      font-variant-numeric: tabular-nums;
    }
  }

  &__net {
    padding-top: 6px;
    border-top: 1px solid var(--admin-border);
    font-weight: 700;

    strong {
      color: var(--tone);
    }
  }
}

.breakdown {
  margin: 0;
  padding: 0;
  list-style: none;

  &__row {
    margin-bottom: 14px;
  }

  &__head {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 5px;
    font-size: 13px;
  }

  &__name {
    min-width: 0;
    overflow-wrap: anywhere;
  }

  &__bar {
    height: 8px;
    border-radius: 999px;
    background: var(--admin-surface-soft);
    overflow: hidden;

    i {
      display: block;
      height: 100%;
      border-radius: 999px;
      background: var(--admin-warning);
    }
  }

  &__more {
    font-size: 12px;
    color: var(--admin-muted);
  }
}

.accounts {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));
  gap: 12px;

  &__item {
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding: 12px 14px;
    border: 1px solid var(--admin-border);
    border-radius: 12px;

    strong {
      margin-top: 4px;
      font-size: 18px;
      font-variant-numeric: tabular-nums;

      &.is-neg {
        color: var(--admin-danger);
      }
    }
  }

  &__name {
    font-weight: 600;
  }
}

.costbar {
  display: flex;
  flex-wrap: wrap;
  gap: 8px 32px;
  margin-bottom: 20px;
  padding: 12px 20px;
  border: 1px solid var(--admin-border);
  border-radius: var(--admin-radius);
  background: var(--admin-surface);
  color: inherit;
  text-decoration: none;

  &.is-bad {
    border-color: var(--admin-danger);
    background: var(--admin-danger-soft);
  }

  &__item {
    display: flex;
    flex-direction: column;

    em { font-size: 12px; font-style: normal; color: var(--admin-muted); }
    strong { font-size: 15px; font-variant-numeric: tabular-nums; }
  }
}

.overview__warn {
  margin-bottom: 20px;
}

.overview__footnote {
  margin: 4px 0 0;
  font-size: 12px;
  color: var(--admin-muted);
}

@media (max-width: 560px) {
  .verdict {
    padding: 18px;

    &__value {
      font-size: 30px;
    }

    &__sum {
      min-width: 0;
      width: 100%;
    }
  }
}
</style>
