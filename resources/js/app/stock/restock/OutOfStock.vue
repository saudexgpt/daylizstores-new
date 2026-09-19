<template>
  <div class="restock">
    <!-- restocking a product reuses the normal "add stock" screen -->
    <stock-item
      v-if="stocking"
      :item="stocking"
      @saved="onRestocked"
      @cancel="stocking = null"
    />

    <template v-else>
      <admin-page-header title="Out of stock" subtitle="Products that have run out or are running low — with how fast they sell and how many to reorder.">
        <el-button :loading="exporting" :disabled="!total" @click="exportCsv">
          <el-icon><IconDownload /></el-icon>
          Download CSV
        </el-button>
      </admin-page-header>

      <div class="grid-stats restock__stats">
        <admin-stat-card label="Out of stock" :value="counts.out" icon="Box" tone="danger" :loading="loading && !loaded" hint="Nothing left to sell" />
        <admin-stat-card label="Oversold" :value="counts.oversold" icon="Warning" tone="warning" :loading="loading && !loaded" hint="More sold than was ever stocked" />
        <admin-stat-card label="Running low" :value="counts.low" icon="Clock" tone="info" :loading="loading && !loaded" :hint="`${query.threshold} or fewer left`" />
        <admin-stat-card label="Listed below" :value="counts.total" icon="List" tone="primary" :loading="loading && !loaded" hint="Matching your filters" />
      </div>

      <admin-card flush>
        <template #toolbar>
          <div class="admin-toolbar">
            <el-radio-group v-model="query.stock" @change="search">
              <el-radio-button value="out">Out of stock</el-radio-button>
              <el-radio-button value="low">Low stock</el-radio-button>
              <el-radio-button value="both">Both</el-radio-button>
            </el-radio-group>
            <el-select v-model="query.category_id" clearable filterable placeholder="All categories" class="restock__category" @change="search">
              <el-option v-for="c in categories" :key="c.id" :label="c.name" :value="c.id" />
            </el-select>
            <el-input
              v-model="query.q"
              class="grow"
              placeholder="Search products by name"
              clearable
              @keyup.enter="search"
              @clear="search"
            >
              <template #prefix>
                <el-icon><IconSearch /></el-icon>
              </template>
            </el-input>
            <el-radio-group v-model="query.level" @change="search">
              <el-radio-button value="product">Products</el-radio-button>
              <el-radio-button value="variant">Sizes &amp; colours</el-radio-button>
            </el-radio-group>
          </div>
          <p class="restock__rules">
            <template v-if="query.stock !== 'out'">
              “Low” means
              <el-input-number v-model="query.threshold" :min="1" :max="100000" size="small" controls-position="right" @change="search" />
              or fewer.
            </template>
            Suggest enough to cover
            <el-input-number v-model="query.cover" :min="1" :max="365" size="small" controls-position="right" @change="search" />
            days of sales, based on the last
            <el-input-number v-model="query.window" :min="7" :max="730" size="small" controls-position="right" @change="search" />
            days.
            <el-checkbox v-model="includeDisabled" class="restock__disabled" @change="search">Include disabled products</el-checkbox>
          </p>
        </template>

        <el-table v-loading="loading" :data="rows" empty-text=" ">
          <el-table-column label="Product" min-width="240">
            <template #default="{ row }">
              <div class="cell-title">{{ row.name }}</div>
              <div class="cell-sub">
                <template v-if="row.variant">{{ row.variant }}</template>
                <template v-else-if="row.variants > 1">{{ row.variants }} sizes / colours, all out</template>
                <admin-status-tag v-if="!row.enabled" kind="enabled" :status="0" class="restock__disabled-tag" />
              </div>
            </template>
          </el-table-column>
          <el-table-column label="Category" prop="category" min-width="140">
            <template #default="{ row }">{{ row.category || '—' }}</template>
          </el-table-column>
          <el-table-column label="Status" min-width="150">
            <template #default="{ row }">
              <admin-status-tag :status="row.status" kind="generic" :tone="tones[row.status]" :label="labels[row.status]" />
            </template>
          </el-table-column>
          <el-table-column label="Available" align="right" min-width="100">
            <template #default="{ row }">
              <strong :class="{ 'restock__neg': row.balance < 0 }">{{ row.balance }}</strong>
            </template>
          </el-table-column>
          <el-table-column align="right" min-width="170">
            <template #header>Sold in {{ query.window }} days</template>
            <template #default="{ row }">{{ row.sold_recent }}</template>
          </el-table-column>
          <el-table-column align="right" min-width="190">
            <template #header>
              <el-tooltip :content="`Units to order to cover ${query.cover} days at the recent sales rate`" placement="top">
                <span class="restock__hint">Suggested order</span>
              </el-tooltip>
            </template>
            <template #default="{ row }">
              <strong v-if="row.suggested_qty !== null" class="restock__suggest">{{ row.suggested_qty }}</strong>
              <span v-else class="text-muted">No recent sales</span>
            </template>
          </el-table-column>
          <el-table-column v-if="hasCosts" label="Est. cost to reorder" align="right" min-width="160">
            <template #default="{ row }">
              <span v-if="row.order_cost !== null && row.order_cost !== undefined" class="money">{{ naira(row.order_cost) }}</span>
              <span v-else class="text-muted">—</span>
            </template>
          </el-table-column>
          <el-table-column label="Last sold" min-width="120">
            <template #default="{ row }">{{ row.last_sold ? fromNow(row.last_sold) : 'Never' }}</template>
          </el-table-column>
          <el-table-column label="Price" align="right" min-width="120">
            <template #default="{ row }">
              <span v-if="row.price !== null" class="money">{{ naira(row.price) }}</span>
              <span v-else class="text-muted">—</span>
            </template>
          </el-table-column>
          <el-table-column label="" align="right" width="120" fixed="right">
            <template #default="{ row }">
              <el-button type="primary" plain size="small" :loading="opening === row.item_id" @click="restock(row)">
                <el-icon><IconUpload /></el-icon>
                Restock
              </el-button>
            </template>
          </el-table-column>

          <template #empty>
            <admin-empty
              v-if="loaded"
              :title="query.stock === 'out' ? 'Nothing is out of stock' : 'Nothing needs restocking'"
              description="Every product matching these filters has stock available."
              icon="CircleCheck"
            />
          </template>
        </el-table>

        <div v-if="total > query.per_page" class="pager">
          <el-pagination
            v-model:current-page="query.page"
            v-model:page-size="query.per_page"
            :total="total"
            :page-sizes="[25, 50, 100]"
            layout="total, sizes, prev, pager, next"
            background
            @current-change="fetch"
            @size-change="search"
          />
        </div>
      </admin-card>
    </template>
  </div>
</template>

<script>
import moment from 'moment';
import request from '@/utils/request';
import { saveBlob } from '@/api/reports';
import { naira } from '@/utils/reportFormat';
import StockItem from '@/app/stock/item/partials/StockItem';
import { costingLive } from '@/api/costing';

export default {
  name: 'OutOfStock',
  components: { StockItem },
  data() {
    return {
      rows: [],
      total: 0,
      counts: { total: 0, out: 0, oversold: 0, low: 0 },
      categories: [],
      loading: false,
      loaded: false,
      exporting: false,
      opening: null,
      stocking: null,
      includeDisabled: false,
      query: { stock: 'out', threshold: 10, level: 'product', category_id: '', q: '', window: 90, cover: 30, sort: 'name', page: 1, per_page: 25 },
      tones: { out: 'danger', oversold: 'danger', low: 'warning' },
      hasCosts: false,
      labels: { out: 'Out of stock', oversold: 'Oversold', low: 'Low' },
    };
  },
  created() {
    // the dashboard links here as ?q=<name> too
    if (this.$route.query.q) {
      this.query.q = String(this.$route.query.q);
    }
    request({ url: '/stock/item-category', method: 'get' })
      .then(r => {
        this.categories = (r.categories || []).map(({ id, name }) => ({ id, name }));
      })
      .catch(() => {});
    this.fetch();
  },
  methods: {
    naira,
    fromNow: (value) => moment(value).fromNow(),
    // what the server is asked, without empty filters
    params() {
      const { page, per_page: perPage, ...rest } = this.query;
      const out = { ...rest, enabled: this.includeDisabled ? 'all' : 'active', page, per_page: perPage };
      Object.keys(out).forEach(k => (out[k] === '' || out[k] === null || out[k] === undefined) && delete out[k]);
      return out;
    },
    search() {
      this.query.page = 1;
      this.fetch();
    },
    fetch() {
      this.loading = true;
      return request({ url: '/stock/restock', method: 'get', params: this.params() })
        .then(r => {
          this.rows = r.rows;
          this.hasCosts = r.rows.some(row => Object.prototype.hasOwnProperty.call(row, 'order_cost'));
          this.total = r.pagination.total;
          this.counts = r.summary;
        })
        .catch(() => {})
        .finally(() => {
          this.loading = false;
          this.loaded = true;
        });
    },
    exportCsv() {
      this.exporting = true;
      const { page, per_page: perPage, ...filters } = this.params();
      return request({ url: '/stock/restock', method: 'get', params: { ...filters, format: 'csv' }, responseType: 'blob' })
        .then(blob => saveBlob(blob, `restock-list_${moment().format('YYYY-MM-DD')}.csv`))
        .catch(() => {})
        .finally(() => {
          this.exporting = false;
        });
    },
    // the "add stock" screen needs the product with its current stock lines and size prices
    restock(row) {
      // once product costing is live, restocking goes through Receive stock (it needs the cost)
      return costingLive().then(live => (live
        ? this.$router.push({ path: '/food-menu/receive-stock', query: { item: row.item_id, name: row.name } })
        : this.openStockUp(row)));
    },
    openStockUp(row) {
      this.opening = row.item_id;
      return request({ url: `/item-show/${row.item_id}`, method: 'get' })
        .then(r => {
          if (r.item) {
            this.stocking = r.item;
          }
        })
        .catch(() => {})
        .finally(() => {
          this.opening = null;
        });
    },
    onRestocked() {
      this.stocking = null;
      this.fetch();
    },
  },
};
</script>

<style lang="scss" scoped>
.restock {
  // four cards fit one row on a wide screen (the shared grid is three across)
  @media (min-width: 1100px) {
    .restock__stats { grid-template-columns: repeat(4, minmax(0, 1fr)); }
  }

  &__category {
    width: 190px;
  }

  &__rules {
    margin: 12px 0 0;
    font-size: 13px;
    line-height: 2.2;
    color: var(--admin-muted);

    :deep(.el-input-number) {
      width: 96px;
      margin: 0 4px;
    }
  }

  &__disabled {
    margin-left: 12px;
  }

  &__disabled-tag {
    margin-left: 6px;
  }

  &__neg {
    color: var(--admin-danger);
  }

  &__suggest {
    color: var(--admin-primary);
  }

  &__hint {
    border-bottom: 1px dotted currentColor;
    cursor: help;
  }
}
</style>
