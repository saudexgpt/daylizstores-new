<template>
  <div class="products">
    <!-- add / edit -->
    <product-form
      v-if="view === 'form'"
      :categories="categories"
      :item="editing"
      @saved="onSaved"
      @cancel="backToList"
    />

    <!-- stock up -->
    <stock-item
      v-else-if="view === 'stock'"
      :item="editing"
      @saved="onSaved"
      @cancel="backToList"
    />

    <!-- list -->
    <template v-else>
      <admin-page-header title="Products" subtitle="Manage your catalogue, prices and stock.">
        <el-button v-if="canCreateNewProduct" type="primary" @click="startAdd">
          <el-icon><IconPlus /></el-icon>
          Add product
        </el-button>
      </admin-page-header>

      <admin-card flush>
        <template #toolbar>
          <div class="admin-toolbar">
            <el-input
              v-model="query.item_name"
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
            <el-select
              v-model="query.category_id"
              placeholder="All categories"
              clearable
              filterable
              class="products__category"
              @change="search"
            >
              <el-option v-for="category in categories" :key="category.id" :value="category.id" :label="category.name" />
            </el-select>
            <el-button type="primary" @click="search">Search</el-button>
          </div>
        </template>

        <el-table v-loading="loading" :data="items" empty-text="No products found">
          <el-table-column label="Product" min-width="280">
            <template #default="{ row }">
              <div class="product-cell">
                <img :src="thumb(row)" alt="" class="product-cell__img" @error="onImageError">
                <div class="product-cell__text">
                  <div class="cell-title">{{ row.name }}</div>
                  <div class="cell-sub">{{ row.category ? row.category.name : 'No category' }}</div>
                </div>
              </div>
            </template>
          </el-table-column>
          <el-table-column label="Price" min-width="120" align="right">
            <template #default="{ row }">
              <span class="money">{{ row.price ? currency + Number(row.price.amount).toLocaleString() : '—' }}</span>
            </template>
          </el-table-column>
          <el-table-column label="In stock" min-width="130">
            <template #default="{ row }">
              <admin-status-tag :tone="stockTone(row)" :label="stockLabel(row)" kind="generic" />
            </template>
          </el-table-column>
          <el-table-column label="Status" min-width="110">
            <template #default="{ row }">
              <admin-status-tag :status="Number(row.enabled)" kind="enabled" />
            </template>
          </el-table-column>
          <el-table-column label="" width="150" align="right" fixed="right">
            <template #default="{ row }">
              <div class="row-actions">
                <el-tooltip content="Edit product" placement="top">
                  <el-button circle size="small" aria-label="Edit product" @click="startEdit(row)">
                    <el-icon><IconEdit /></el-icon>
                  </el-button>
                </el-tooltip>
                <el-tooltip content="Add stock" placement="top">
                  <el-button circle size="small" type="success" plain aria-label="Add stock" @click="startStock(row)">
                    <el-icon><IconBox /></el-icon>
                  </el-button>
                </el-tooltip>
                <el-tooltip :content="Number(row.enabled) === 1 ? 'Disable (hide from store)' : 'Enable (show in store)'" placement="top">
                  <el-button
                    circle
                    size="small"
                    :type="Number(row.enabled) === 1 ? 'danger' : 'primary'"
                    plain
                    :aria-label="Number(row.enabled) === 1 ? 'Disable product' : 'Enable product'"
                    @click="toggleStatus(row)"
                  >
                    <el-icon>
                      <IconRemove v-if="Number(row.enabled) === 1" />
                      <IconCircleCheck v-else />
                    </el-icon>
                  </el-button>
                </el-tooltip>
              </div>
            </template>
          </el-table-column>
        </el-table>

        <div v-if="total > 0" class="pager">
          <el-pagination
            v-model:current-page="query.page"
            v-model:page-size="query.limit"
            :page-sizes="[10, 20, 50, 100]"
            :total="total"
            layout="total, sizes, prev, pager, next"
            background
            @current-change="fetchProducts"
            @size-change="onSizeChange"
          />
        </div>
      </admin-card>
    </template>
  </div>
</template>

<script>
import ProductForm from './partials/ProductForm';
import StockItem from './partials/StockItem';
import { costingLive } from '@/api/costing';
import Resource from '@/api/resource';
import { onImageError } from '@/utils/index';

const categoriesResource = new Resource('stock/item-category');
const productsResource = new Resource('stock/general-items');
const toggleStatusResource = new Resource('stock/general-items/toggle-status');

export default {
  name: 'ManageItem',
  components: { ProductForm, StockItem },
  props: {
    canCreateNewProduct: {
      type: Boolean,
      default: true,
    },
  },
  data() {
    return {
      currency: '₦',
      view: 'list', // list | form | stock
      categories: [],
      items: [],
      editing: null,
      loading: false,
      total: 0,
      query: {
        page: 1,
        limit: 10,
        category_id: '',
        item_name: '',
        sort: 'newest',
      },
    };
  },
  created() {
    // the dashboard's low-stock list links here as ?q=<product name>
    if (this.$route.query.q) {
      this.query.item_name = String(this.$route.query.q);
    }
    this.fetchCategories();
    this.fetchProducts();
  },
  methods: {
    onImageError,
    fetchCategories() {
      categoriesResource.list()
        .then(response => {
          // only id + name are needed here (the endpoint also embeds every product)
          this.categories = (response.categories || []).map(({ id, name }) => ({ id, name }));
        })
        .catch(() => {});
    },
    fetchProducts() {
      this.loading = true;
      const query = { ...this.query, category_id: this.query.category_id || '' };
      productsResource.list(query)
        .then(response => {
          this.items = response.items.data;
          this.total = response.items.total;
        })
        .catch(() => {
          // the shared axios interceptor has already shown the error
        })
        .finally(() => {
          this.loading = false;
        });
    },
    search() {
      this.query.page = 1;
      this.fetchProducts();
    },
    onSizeChange() {
      this.query.page = 1;
      this.fetchProducts();
    },
    thumb(row) {
      const media = row.media && row.media[0];
      return (media && (media.thumbnail || media.link)) || row.picture || '/images/no-image.jpeg';
    },
    balance(row) {
      return (row.item_stocks || []).reduce((sum, stock) => sum + (stock.quantity_stocked - stock.reserved - stock.sold), 0);
    },
    stockLabel(row) {
      const balance = this.balance(row);
      if (balance < 0) {
        return `Oversold by ${Math.abs(balance)}`;
      }
      return balance === 0 ? 'Out of stock' : `${balance} in stock`;
    },
    stockTone(row) {
      const balance = this.balance(row);
      return balance <= 0 ? 'danger' : balance <= 10 ? 'warning' : 'success';
    },
    startAdd() {
      this.editing = null;
      this.view = 'form';
    },
    startEdit(row) {
      this.editing = row;
      this.view = 'form';
    },
    startStock(row) {
      // once product costing is live, stock must arrive with its cost
      costingLive().then(live => {
        if (live) {
          this.$router.push({ path: '/food-menu/receive-stock', query: { item: row.id, name: row.name } });
        } else {
          this.editing = row;
          this.view = 'stock';
        }
      });
    },
    backToList() {
      this.view = 'list';
      this.editing = null;
    },
    onSaved() {
      this.backToList();
      this.fetchProducts();
    },
    async toggleStatus(row) {
      const enabling = Number(row.enabled) !== 1;
      const action = enabling ? 'enabled' : 'disabled';
      try {
        await this.$confirm(
          enabling
            ? `"${row.name}" will show in the store again.`
            : `"${row.name}" will be hidden from the store. Existing orders are not affected.`,
          enabling ? 'Enable this product?' : 'Disable this product?',
          { confirmButtonText: enabling ? 'Yes, enable' : 'Yes, disable', cancelButtonText: 'Cancel', type: enabling ? 'info' : 'warning' },
        );
      } catch (cancelled) {
        return;
      }
      this.loading = true;
      toggleStatusResource.update(row.id, { action, value: enabling ? 1 : 0 })
        .then(() => {
          row.enabled = enabling ? 1 : 0;
          this.$message({ message: `Product ${action}`, type: 'success' });
        })
        .catch(() => {})
        .finally(() => {
          this.loading = false;
        });
    },
  },
};
</script>

<style lang="scss" scoped>
.products {
  &__category {
    width: 220px;
  }
}

.product-cell {
  display: flex;
  align-items: center;
  gap: 14px;

  &__img {
    flex: none;
    width: 52px;
    height: 52px;
    border-radius: 10px;
    border: 1px solid var(--admin-border);
    object-fit: cover;
    background: var(--admin-surface-soft);
  }

  &__text {
    min-width: 0;
  }
}

@media (max-width: 560px) {
  .products__category {
    width: 100%;
  }
}
</style>
