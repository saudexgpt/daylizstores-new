<template>
  <div class="orders">
    <!-- list -->
    <template v-if="view === 'list'">
      <admin-page-header title="Orders" subtitle="Review orders, confirm payment and mark them delivered." />

      <admin-card flush>
        <template #toolbar>
          <div class="admin-toolbar">
            <el-input
              v-model="searchQuery"
              class="grow"
              placeholder="Search by order number, customer name, email or phone"
              clearable
              @keyup.enter="runSearch"
              @clear="clearSearch"
            >
              <template #prefix>
                <el-icon><IconSearch /></el-icon>
              </template>
            </el-input>
            <el-button type="primary" :disabled="!searchQuery.trim()" @click="runSearch">Search</el-button>
          </div>
          <el-radio-group v-model="status" class="orders__filters" @change="onStatusChange">
            <el-radio-button v-for="option in statusOptions" :key="option.value" :value="option.value">
              {{ option.label }}
            </el-radio-button>
          </el-radio-group>
        </template>

        <el-table v-loading="loading" :data="orders" empty-text="No orders match" class="orders__table" @row-click="openOrder">
          <el-table-column label="Order" min-width="170">
            <template #default="{ row }">
              <div class="cell-title mono">{{ row.order_number }}</div>
              <div class="cell-sub">{{ moment(row.created_at).format('D MMM YYYY, h:mm a') }}</div>
            </template>
          </el-table-column>
          <el-table-column label="Customer" min-width="190">
            <template #default="{ row }">
              <div class="cell-title">{{ row.customer ? row.customer.name : '—' }}</div>
              <div class="cell-sub">{{ row.customer ? row.customer.phone : '' }}</div>
            </template>
          </el-table-column>
          <el-table-column label="Amount" min-width="130" align="right">
            <template #default="{ row }">
              <span class="money">{{ currency }}{{ formatNumber(row.total, 2) }}</span>
            </template>
          </el-table-column>
          <el-table-column label="Payment" min-width="130">
            <template #default="{ row }">
              <admin-status-tag :status="row.payment_status" kind="payment" />
            </template>
          </el-table-column>
          <el-table-column label="Status" min-width="150">
            <template #default="{ row }">
              <admin-status-tag :status="row.order_status" kind="order" />
              <div v-if="row.order_status === 'Delivered'" class="cell-sub">
                {{ moment(row.updated_at).format('D MMM YYYY') }}
              </div>
            </template>
          </el-table-column>
          <el-table-column label="" width="150" align="right" fixed="right">
            <template #default="{ row }">
              <div class="row-actions">
                <el-button
                  v-if="row.order_status === 'Cancelled' && row.bulk_order_cancellation === 1 && canReverse"
                  size="small"
                  type="warning"
                  plain
                  @click.stop="undoCancellation(row)"
                >
                  <el-icon><IconRefreshRight /></el-icon>
                  Restore
                </el-button>
                <el-tooltip content="Open order" placement="top">
                  <el-button circle size="small" aria-label="Open order" @click.stop="openOrder(row)">
                    <el-icon><IconView /></el-icon>
                  </el-button>
                </el-tooltip>
              </div>
            </template>
          </el-table-column>
        </el-table>

        <div v-if="!searching && total > 0" class="pager">
          <el-pagination
            v-model:current-page="page"
            v-model:page-size="limit"
            :page-sizes="[10, 20, 50, 100]"
            :total="total"
            layout="total, sizes, prev, pager, next"
            background
            @current-change="getOrders"
            @size-change="onSizeChange"
          />
        </div>
        <div v-else-if="searching" class="pager pager--note">
          <span class="text-muted">Showing up to 100 matches.</span>
          <el-button link type="primary" @click="clearSearch">Clear search</el-button>
        </div>
      </admin-card>
    </template>

    <!-- one order -->
    <template v-else>
      <admin-page-header :title="`Order ${order.order_number || ''}`" subtitle="Order details">
        <el-button class="no-print" @click="closeOrder">
          <el-icon><IconArrowLeft /></el-icon>
          Back to orders
        </el-button>
        <el-button class="no-print" type="primary" @click="print">
          <el-icon><IconPrinter /></el-icon>
          Print
        </el-button>
      </admin-page-header>
      <order-details :order="order" :can-update="true" @updated="onOrderUpdated" />
    </template>
  </div>
</template>

<script>
import moment from 'moment';
import { formatNumber } from '@/utils/index';
import checkPermission from '@/utils/permission';
import OrderDetails from './Details';
import Resource from '@/api/resource';

const ordersResource = new Resource('order/general');
const searchResource = new Resource('order/general/search-order');
const reverseResource = new Resource('order/general/reverse-cancellation');

const STATUS_OPTIONS = [
  { value: '', label: 'All' },
  { value: 'Pending', label: 'Pending' },
  { value: 'CARP', label: 'Restored' }, // "Cancellation Reversed to Pending"
  { value: 'On Transit', label: 'On transit' },
  { value: 'Delivered', label: 'Delivered' },
  { value: 'Cancelled', label: 'Cancelled' },
];

export default {
  name: 'View', // matches the route name — <keep-alive> uses it
  components: { OrderDetails },
  data() {
    return {
      currency: '₦',
      statusOptions: STATUS_OPTIONS,
      view: 'list',
      orders: [],
      order: {},
      status: 'Pending',
      page: 1,
      limit: 10,
      total: 0,
      loading: false,
      searchQuery: '',
      searching: false,
    };
  },
  computed: {
    canReverse() {
      return checkPermission(['approve order', 'cancel order']);
    },
  },
  watch: {
    // the dashboard links here as /orders/view-orders?order=DS12345
    '$route.query.order'(orderNumber) {
      if (orderNumber) {
        this.openByNumber(orderNumber);
      }
    },
  },
  created() {
    const { order: orderNumber, q } = this.$route.query;
    if (orderNumber) {
      this.openByNumber(orderNumber);
    } else if (q) {
      // the customers screen links here as ?q=<email> — show all of that customer's orders
      this.searchQuery = String(q);
      this.status = '';
      this.runSearch();
    } else {
      this.getOrders();
    }
  },
  methods: {
    moment,
    formatNumber,
    getOrders() {
      this.loading = true;
      this.searching = false;
      ordersResource.list({ page: this.page, limit: this.limit, status: this.status })
        .then(response => {
          this.orders = response.orders.data;
          this.total = response.orders.total;
        })
        .catch(() => {
          // the shared axios interceptor has already shown the error
        })
        .finally(() => {
          this.loading = false;
        });
    },
    onStatusChange() {
      this.page = 1;
      this.searchQuery.trim() ? this.runSearch() : this.getOrders();
    },
    onSizeChange() {
      this.page = 1;
      this.getOrders();
    },
    runSearch() {
      const query = this.searchQuery.trim();
      if (!query) {
        return;
      }
      this.loading = true;
      this.searching = true;
      searchResource.list({ search_query: query, status: this.status })
        .then(response => {
          this.orders = response.orders;
        })
        .catch(() => {})
        .finally(() => {
          this.loading = false;
        });
    },
    clearSearch() {
      this.searchQuery = '';
      this.page = 1;
      this.getOrders();
    },
    openOrder(order) {
      this.order = order;
      this.view = 'details';
      window.scrollTo({ top: 0 });
    },
    // open one order straight from its number (no status filter — it may be any status)
    openByNumber(orderNumber) {
      this.loading = true;
      searchResource.list({ search_query: orderNumber })
        .then(response => {
          const match = (response.orders || []).find(o => o.order_number === orderNumber) || (response.orders || [])[0];
          if (match) {
            this.openOrder(match);
          } else {
            this.$message({ message: `Order ${orderNumber} was not found`, type: 'warning' });
            this.getOrders();
          }
        })
        .catch(() => this.getOrders())
        .finally(() => {
          this.loading = false;
        });
    },
    closeOrder() {
      this.view = 'list';
      // drop ?order= so a refresh doesn't reopen it
      if (this.$route.query.order) {
        this.$router.replace({ path: this.$route.path });
      }
      this.searching ? this.runSearch() : this.getOrders();
    },
    // keep the open order (and the list behind it) in step with a status change made in the details
    onOrderUpdated({ id, order_status, payment_status }) {
      this.order = { ...this.order, order_status, payment_status };
      this.orders = this.orders.map(o => (o.id === id ? { ...o, order_status, payment_status } : o));
    },
    undoCancellation(order) {
      this.$confirm(
        `Restore ${order.order_number}? Its stock is reserved again where available and it goes back to pending.`,
        'Restore this order?',
        { confirmButtonText: 'Yes, restore', cancelButtonText: 'Keep cancelled', type: 'warning' },
      )
        .then(() => reverseResource.update(order.id, { status: 'carp' }))
        .then(() => {
          this.$message({ message: 'Order restored', type: 'success' });
          this.searching ? this.runSearch() : this.getOrders();
        })
        .catch(() => {
          // cancelled the dialog, or the interceptor already showed the error
        });
    },
    print() {
      window.print();
    },
  },
};
</script>

<style lang="scss" scoped>
.orders {
  &__filters {
    margin-top: 14px;
    flex-wrap: wrap;
  }

  &__table :deep(.el-table__row) {
    cursor: pointer;
  }

  .pager--note {
    align-items: center;
    justify-content: space-between;
  }
}
</style>
