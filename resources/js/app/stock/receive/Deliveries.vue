<template>
  <div class="deliveries">
    <admin-page-header title="Deliveries" subtitle="Every supplier delivery recorded, with its invoice and landed cost.">
      <router-link to="/food-menu/receive-stock">
        <el-button type="primary"><el-icon><IconPlus /></el-icon>Receive stock</el-button>
      </router-link>
    </admin-page-header>

    <admin-card flush>
      <template #toolbar>
        <div class="admin-toolbar">
          <period-picker v-model="range" no-future @update:model-value="search" />
          <el-input v-model="query.q" class="grow" placeholder="Search supplier, invoice or reference" clearable @keyup.enter="search" @clear="search">
            <template #prefix><el-icon><IconSearch /></el-icon></template>
          </el-input>
          <el-select v-model="query.status" clearable placeholder="Any status" class="deliveries__status" @change="search">
            <el-option label="Recorded" value="posted" />
            <el-option label="Voided" value="void" />
          </el-select>
        </div>
      </template>

      <el-table v-loading="loading" :data="rows" empty-text=" ">
        <el-table-column label="Date" width="120">
          <template #default="{ row }">{{ dateText(row.received_on) }}</template>
        </el-table-column>
        <el-table-column label="Supplier" min-width="220">
          <template #default="{ row }">
            <div class="cell-title">{{ row.supplier }}</div>
            <div class="cell-sub"><span class="mono">{{ row.reference }}</span><template v-if="row.invoice_number"> · invoice {{ row.invoice_number }}</template></div>
          </template>
        </el-table-column>
        <el-table-column label="Goods" align="right" min-width="130">
          <template #default="{ row }"><span class="money">{{ naira(row.items_total) }}</span></template>
        </el-table-column>
        <el-table-column label="Extra costs" align="right" min-width="120">
          <template #default="{ row }"><span class="money">{{ row.extra_costs ? naira(row.extra_costs) : '—' }}</span></template>
        </el-table-column>
        <el-table-column label="Landed total" align="right" min-width="140">
          <template #default="{ row }"><span class="money" :class="{ 'is-void': row.status === 'void' }">{{ naira(row.landed_total) }}</span></template>
        </el-table-column>
        <el-table-column label="Payment" width="130">
          <template #default="{ row }">
            <admin-status-tag kind="generic" :tone="row.paid ? 'success' : 'warning'" :label="row.paid ? 'Paid' : 'Owed'" />
          </template>
        </el-table-column>
        <el-table-column label="Status" width="120">
          <template #default="{ row }">
            <admin-status-tag kind="generic" :tone="row.status === 'void' ? 'danger' : 'success'" :label="row.status === 'void' ? 'Voided' : 'Recorded'" />
          </template>
        </el-table-column>
        <el-table-column label="" width="130" align="right" fixed="right">
          <template #default="{ row }">
            <span class="row-actions">
              <el-tooltip content="View" placement="top">
                <el-button circle size="small" aria-label="View" @click="view(row)"><el-icon><IconView /></el-icon></el-button>
              </el-tooltip>
              <el-tooltip v-if="canVoid && row.status === 'posted'" content="Void" placement="top">
                <el-button circle size="small" type="danger" plain aria-label="Void" @click="voidRow(row)"><el-icon><IconDelete /></el-icon></el-button>
              </el-tooltip>
            </span>
          </template>
        </el-table-column>
        <template #empty>
          <admin-empty v-if="loaded" title="No deliveries in this period" description="Deliveries you record with Receive stock appear here." icon="Box" />
        </template>
      </el-table>

      <div v-if="total > query.per_page" class="pager">
        <el-pagination v-model:current-page="query.page" :page-size="query.per_page" :total="total" layout="total, prev, pager, next" background @current-change="fetch" />
      </div>
    </admin-card>

    <el-drawer v-model="detail.open" :title="detail.receipt ? detail.receipt.reference : ''" size="620px" append-to-body>
      <div v-if="detail.receipt" v-loading="detail.loading">
        <p class="deliveries__summary">
          <strong>{{ detail.receipt.supplier }}</strong> · {{ dateText(detail.receipt.received_on) }}
          <template v-if="detail.receipt.invoice_number"> · invoice {{ detail.receipt.invoice_number }}</template>
        </p>
        <el-alert v-if="detail.receipt.status === 'void'" type="error" :closable="false" show-icon title="This delivery was voided" :description="detail.receipt.void_reason || ''" />
        <p v-if="detail.receipt.extra_costs" class="text-muted">
          {{ naira(detail.receipt.extra_costs) }} of extra costs{{ detail.receipt.extra_costs_note ? ' (' + detail.receipt.extra_costs_note + ')' : '' }} were added to the goods.
        </p>
        <el-table :data="detail.receipt.lines || []" size="small">
          <el-table-column label="Product" min-width="180">
            <template #default="{ row }">
              <div class="cell-title">{{ row.product }}</div>
              <div class="cell-sub">{{ [row.size ? 'Size ' + row.size : '', row.color].filter(Boolean).join(' · ') || 'No size or colour' }}</div>
            </template>
          </el-table-column>
          <el-table-column label="Qty" prop="quantity" align="right" width="70" />
          <el-table-column label="Unit cost" align="right" width="110">
            <template #default="{ row }">{{ money(row.unit_cost) }}</template>
          </el-table-column>
          <el-table-column label="Landed" align="right" width="110">
            <template #default="{ row }">{{ money(row.landed_unit_cost) }}</template>
          </el-table-column>
        </el-table>
        <p class="deliveries__total">Landed total <strong>{{ naira(detail.receipt.landed_total) }}</strong></p>
      </div>
    </el-drawer>
  </div>
</template>

<script>
import moment from 'moment';
import { ElMessageBox } from 'element-plus';
import PeriodPicker from '@/components/admin/PeriodPicker.vue';
import { listReceipts, showReceipt, voidReceipt } from '@/api/costing';
import { dateText, money, naira } from '@/utils/reportFormat';
import { can } from '@/utils/permission';

export default {
  name: 'StockDeliveries',
  components: { PeriodPicker },
  data() {
    return {
      rows: [],
      total: 0,
      loading: false,
      loaded: false,
      range: [moment().startOf('month').format('YYYY-MM-DD'), moment().format('YYYY-MM-DD')],
      query: { q: '', status: '', page: 1, per_page: 25 },
      detail: { open: false, loading: false, receipt: null },
    };
  },
  computed: {
    canVoid: () => can('manage accounting'),
  },
  created() {
    this.fetch();
  },
  methods: {
    dateText,
    money,
    naira,
    search() {
      this.query.page = 1;
      this.fetch();
    },
    fetch() {
      this.loading = true;
      const params = { ...this.query, from: this.range[0], to: this.range[1] };
      Object.keys(params).forEach(k => (params[k] === '' || params[k] === null) && delete params[k]);
      return listReceipts(params)
        .then(r => {
          this.rows = r.receipts;
          this.total = r.pagination.total;
        })
        .catch(() => {})
        .finally(() => {
          this.loading = false;
          this.loaded = true;
        });
    },
    view(row) {
      this.detail = { open: true, loading: true, receipt: row };
      showReceipt(row.id)
        .then(r => {
          this.detail.receipt = r.receipt;
        })
        .catch(() => {})
        .finally(() => {
          this.detail.loading = false;
        });
    },
    voidRow(row) {
      ElMessageBox.prompt(
        `${row.reference} from ${row.supplier}. This takes the stock back off the shelf and reverses the inventory entry. It only works while none of it has been sold. Why is it being voided?`,
        'Void this delivery?',
        { confirmButtonText: 'Void it', cancelButtonText: 'Keep it', type: 'warning', inputPlaceholder: 'Reason (e.g. entered against the wrong supplier)', inputValidator: v => (v && v.trim().length >= 3) || 'Give a reason of at least 3 characters' },
      ).then(({ value }) => voidReceipt(row.id, value.trim()))
        .then(() => {
          this.$message({ message: 'Delivery voided', type: 'success' });
          this.fetch();
        })
        .catch(() => {});
    },
  },
};
</script>

<style lang="scss" scoped>
.deliveries {
  &__status { width: 160px; }
  &__summary { margin: 0 0 12px; font-size: 15px; }
  &__total { margin: 14px 0 0; text-align: right; font-size: 15px; }
  .is-void { text-decoration: line-through; color: var(--admin-muted); }
}
</style>
