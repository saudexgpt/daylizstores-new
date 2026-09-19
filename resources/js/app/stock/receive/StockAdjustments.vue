<template>
  <div class="adjust">
    <admin-page-header title="Stock adjustments" subtitle="Damaged, lost or found stock, and count differences — the only other way stock leaves the shelf besides a sale.">
      <el-button type="primary" @click="openNew"><el-icon><IconPlus /></el-icon>New adjustment</el-button>
    </admin-page-header>

    <admin-card flush>
      <template #toolbar>
        <div class="admin-toolbar">
          <el-input v-model="query.q" class="grow" placeholder="Search product or reference" clearable @keyup.enter="search" @clear="search">
            <template #prefix><el-icon><IconSearch /></el-icon></template>
          </el-input>
        </div>
      </template>

      <el-table v-loading="loading" :data="rows" empty-text=" ">
        <el-table-column label="Date" width="120">
          <template #default="{ row }">{{ dateText(row.date) }}</template>
        </el-table-column>
        <el-table-column label="Product" min-width="240">
          <template #default="{ row }">
            <div class="cell-title">{{ row.product }}</div>
            <div class="cell-sub"><span class="mono">{{ row.reference }}</span> · {{ [row.size ? 'Size ' + row.size : '', row.color].filter(Boolean).join(' · ') || 'No size or colour' }}</div>
          </template>
        </el-table-column>
        <el-table-column label="Change" align="right" width="110">
          <template #default="{ row }">
            <strong :class="row.quantity < 0 ? 'is-loss' : 'is-gain'">{{ row.quantity > 0 ? '+' : '' }}{{ row.quantity }}</strong>
          </template>
        </el-table-column>
        <el-table-column label="Reason" min-width="170">
          <template #default="{ row }">
            <admin-status-tag kind="generic" :tone="row.quantity < 0 ? 'danger' : 'success'" :label="reasons[row.reason]" />
            <div v-if="row.note" class="cell-sub">{{ row.note }}</div>
          </template>
        </el-table-column>
        <el-table-column v-if="showCost" label="Cost" align="right" min-width="120">
          <template #default="{ row }"><span class="money">{{ row.cost !== null ? naira(row.cost) : '' }}</span></template>
        </el-table-column>
        <el-table-column label="By" prop="by" min-width="130" />
        <template #empty>
          <admin-empty v-if="loaded" title="No adjustments yet" description="Record damaged or missing stock here, so the shelf, the books and the profit all stay true." icon="Box" />
        </template>
      </el-table>

      <div v-if="total > query.per_page" class="pager">
        <el-pagination v-model:current-page="query.page" :page-size="query.per_page" :total="total" layout="total, prev, pager, next" background @current-change="fetch" />
      </div>
    </admin-card>

    <el-dialog v-model="dialog.open" title="Adjust stock" width="560px" append-to-body destroy-on-close>
      <el-form label-position="top" :model="form" @submit.prevent>
        <el-form-item label="Product" required>
          <el-select v-model="form.product" filterable remote reserve-keyword value-key="id" placeholder="Search a product" :remote-method="searchProducts" :loading="searching" @change="onProduct">
            <el-option v-for="p in results" :key="p.id" :label="p.name" :value="p" />
          </el-select>
        </el-form-item>
        <el-form-item v-if="form.product" label="Which one" required>
          <el-select v-model="form.item_stock_id" placeholder="Choose the size / colour">
            <el-option v-for="s in form.product.stocks" :key="s.id" :label="stockLabel(s)" :value="s.id" />
          </el-select>
        </el-form-item>
        <div class="adjust__row">
          <el-form-item label="What happened" required>
            <el-radio-group v-model="form.direction">
              <el-radio-button value="out">Stock lost</el-radio-button>
              <el-radio-button value="in">Stock found</el-radio-button>
            </el-radio-group>
          </el-form-item>
          <el-form-item label="Units" required>
            <el-input-number v-model="form.units" :min="1" :max="1000000" controls-position="right" />
          </el-form-item>
        </div>
        <div class="adjust__row">
          <el-form-item label="Reason" required>
            <el-select v-model="form.reason">
              <el-option label="Damaged" value="damage" />
              <el-option label="Lost / stolen" value="loss" />
              <el-option label="Count difference" value="count_difference" />
              <el-option label="Other" value="other" />
            </el-select>
          </el-form-item>
          <el-form-item label="Date" required>
            <el-date-picker v-model="form.adjusted_on" type="date" value-format="YYYY-MM-DD" format="D MMM YYYY" :clearable="false" :disabled-date="isFuture" />
          </el-form-item>
        </div>
        <el-form-item v-if="form.direction === 'in' && live" label="What each unit found is worth (₦)" required>
          <el-input-number v-model="form.unit_cost" :min="0" :precision="2" :controls="false" placeholder="Unit cost" />
        </el-form-item>
        <el-form-item label="Note">
          <el-input v-model="form.note" maxlength="255" placeholder="What happened?" />
        </el-form-item>
        <p v-if="form.direction === 'out' && live" class="adjust__help">Stock lost is written off at its FIFO cost and booked to “Stock Loss &amp; Damages”.</p>
      </el-form>
      <template #footer>
        <el-button @click="dialog.open = false">Cancel</el-button>
        <el-button type="primary" :loading="saving" @click="save">Save adjustment</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script>
import { costingLive, findProducts, listAdjustments, saveAdjustment } from '@/api/costing';
import { dateText, naira } from '@/utils/reportFormat';
import { can } from '@/utils/permission';

const today = () => {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};
const blank = () => ({ product: null, item_stock_id: null, direction: 'out', units: 1, reason: 'damage', adjusted_on: today(), unit_cost: undefined, note: '' });

export default {
  name: 'StockAdjustments',
  data() {
    return {
      rows: [],
      total: 0,
      loading: false,
      loaded: false,
      live: false,
      saving: false,
      searching: false,
      results: [],
      query: { q: '', page: 1, per_page: 25 },
      dialog: { open: false },
      form: blank(),
      reasons: { damage: 'Damaged', loss: 'Lost / stolen', count_difference: 'Count difference', other: 'Other' },
    };
  },
  computed: {
    showCost: () => can('view cost'),
  },
  created() {
    costingLive().then(live => {
      this.live = live;
    });
    this.fetch();
  },
  methods: {
    dateText,
    naira,
    isFuture: (date) => date.getTime() > Date.now(),
    stockLabel: (s) => `${[s.size ? 'Size ' + s.size : '', s.color].filter(Boolean).join(' · ') || 'No size or colour'} — ${s.available} free`,
    search() {
      this.query.page = 1;
      this.fetch();
    },
    fetch() {
      this.loading = true;
      return listAdjustments({ ...this.query, q: this.query.q || undefined })
        .then(r => {
          this.rows = r.adjustments;
          this.total = r.pagination.total;
        })
        .catch(() => {})
        .finally(() => {
          this.loading = false;
          this.loaded = true;
        });
    },
    openNew() {
      this.form = blank();
      this.results = [];
      this.dialog.open = true;
    },
    searchProducts(q) {
      if (!q || q.trim().length < 2) {
        return;
      }
      this.searching = true;
      findProducts(q.trim()).then(r => {
        this.results = r.products;
      }).catch(() => {}).finally(() => {
        this.searching = false;
      });
    },
    onProduct(product) {
      // a product with a single shelf row needs no choosing
      this.form.item_stock_id = product && product.stocks.length === 1 ? product.stocks[0].id : null;
    },
    save() {
      const f = this.form;
      if (!f.item_stock_id) return this.$message({ message: 'Choose the product and which size / colour.', type: 'warning' });
      if (!f.units || f.units < 1) return this.$message({ message: 'Enter how many units.', type: 'warning' });
      if (f.direction === 'in' && this.live && !(f.unit_cost > 0)) return this.$message({ message: 'Enter what each unit found is worth.', type: 'warning' });
      this.saving = true;
      return saveAdjustment({
        item_stock_id: f.item_stock_id, quantity: f.direction === 'out' ? -f.units : f.units, reason: f.reason,
        adjusted_on: f.adjusted_on, note: f.note || null, unit_cost: f.direction === 'in' && f.unit_cost ? f.unit_cost : null,
      })
        .then(r => {
          this.$message({ message: `${r.adjustment.reference} recorded`, type: 'success' });
          this.dialog.open = false;
          this.fetch();
        })
        .catch(() => {})
        .finally(() => {
          this.saving = false;
        });
    },
  },
};
</script>

<style lang="scss" scoped>
.adjust {
  &__row {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0 16px;
  }

  &__help { margin: 0; font-size: 12px; color: var(--admin-muted); }

  .is-loss { color: var(--admin-danger); }
  .is-gain { color: var(--admin-success); }

  :deep(.el-select),
  :deep(.el-date-editor),
  :deep(.el-input-number) { width: 100%; }
}

@media (max-width: 560px) {
  .adjust__row { grid-template-columns: 1fr; }
}
</style>
