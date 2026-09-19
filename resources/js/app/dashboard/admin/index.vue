<template>
  <div class="dashboard">
    <admin-page-header :title="`${greeting}, ${firstName}`" subtitle="Here's how the store is doing.">
      <el-button :loading="loading" @click="load(true)">
        <el-icon><IconRefresh /></el-icon>
        Refresh
      </el-button>
      <el-button v-if="canManageProducts" type="primary" @click="$router.push('/food-menu/manage-items')">
        <el-icon><IconPlus /></el-icon>
        Add product
      </el-button>
    </admin-page-header>

    <div class="grid-stats">
      <admin-stat-card
        label="Revenue · last 30 days"
        :value="compactMoney(summary.revenue_30_days)"
        :hint="`${money(summary.revenue_total)} all time`"
        icon="Money"
        tone="success"
        :loading="loading && !loaded"
      />
      <admin-stat-card
        label="Pending orders"
        :value="count(summary.pending_orders)"
        :hint="`${count(summary.awaiting_payment)} awaiting payment`"
        icon="ShoppingBag"
        tone="warning"
        :loading="loading && !loaded"
      />
      <admin-stat-card label="On transit" :value="count(summary.transit_orders)" icon="Van" tone="info" :loading="loading && !loaded" />
      <admin-stat-card label="Delivered" :value="count(summary.delivered_orders)" icon="CircleCheck" tone="success" :loading="loading && !loaded" />
      <admin-stat-card label="Cancelled" :value="count(summary.cancelled_orders)" icon="CircleClose" tone="danger" :loading="loading && !loaded" />
      <admin-stat-card label="Products" :value="count(summary.products)" icon="Goods" tone="primary" :loading="loading && !loaded" />
    </div>

    <div class="dashboard__split">
      <admin-card title="Orders & revenue" subtitle="Last 14 days">
        <trend-chart :points="trend" :currency="currency" />
      </admin-card>

      <admin-card title="Low stock" subtitle="Products running out">
        <template #actions>
          <el-button v-if="canManageProducts" link type="primary" @click="$router.push('/food-menu/manage-items')">
            Manage
          </el-button>
        </template>
        <low-stock :items="lowStock" />
      </admin-card>
    </div>

    <admin-card v-if="canViewOrders" title="Recent orders" flush>
      <template #actions>
        <el-button link type="primary" @click="$router.push('/orders/view-orders')">
          View all orders
          <el-icon class="el-icon--right"><IconArrowRight /></el-icon>
        </el-button>
      </template>
      <recent-orders :orders="recentOrders" :loading="loading && !loaded" :currency="currency" @open="openOrder" />
    </admin-card>
  </div>
</template>

<script>
import { mapState } from 'pinia';
import { useAppStore, useUserStore } from '@/store';
import checkPermission from '@/utils/permission';
import { formatNumber } from '@/utils/index';
import Resource from '@/api/resource';
import TrendChart from './components/TrendChart';
import LowStock from './components/LowStock';
import RecentOrders from './components/RecentOrders';

const dashboardResource = new Resource('dashboard/admin');
const ordersResource = new Resource('order/general');

export default {
  name: 'AdminDashboard',
  components: { TrendChart, LowStock, RecentOrders },
  data() {
    return {
      loading: false,
      loaded: false,
      summary: {},
      trend: [],
      lowStock: [],
      recentOrders: [],
    };
  },
  computed: {
    ...mapState(useUserStore, ['name']),
    currency() {
      return (useAppStore().params && useAppStore().params.currency) || '₦';
    },
    firstName() {
      return String(this.name || '').trim().split(/\s+/)[0] || 'there';
    },
    greeting() {
      const hour = new Date().getHours();
      return hour < 12 ? 'Good morning' : hour < 17 ? 'Good afternoon' : 'Good evening';
    },
    canManageProducts() {
      return checkPermission(['create menu']);
    },
    canViewOrders() {
      return checkPermission(['view order']);
    },
  },
  created() {
    this.load();
  },
  methods: {
    count(value) {
      return value === undefined || value === null ? '—' : formatNumber(value, 0);
    },
    money(value) {
      return this.currency + formatNumber(value || 0, 0);
    },
    // ₦215.1M rather than ₦215,056,740 — keeps the card from overflowing
    compactMoney(value) {
      const n = Number(value || 0);
      if (n >= 1e9) {
        return this.currency + (n / 1e9).toFixed(2) + 'B';
      }
      if (n >= 1e6) {
        return this.currency + (n / 1e6).toFixed(2) + 'M';
      }
      return this.money(n);
    },
    // `fresh` = the Refresh button: skip the server's one-minute cache
    load(fresh = false) {
      this.loading = true;
      const requests = [dashboardResource.list(fresh === true ? { fresh: 1 } : undefined)];
      if (this.canViewOrders) {
        requests.push(ordersResource.list({ limit: 8, page: 1 }));
      }
      Promise.all(requests)
        .then(([dashboard, orders]) => {
          this.summary = dashboard.data_summary || {};
          this.trend = dashboard.trend || [];
          this.lowStock = dashboard.low_stock || [];
          this.recentOrders = orders && orders.orders ? orders.orders.data : [];
          this.loaded = true;
        })
        .catch(() => {
          // the shared axios interceptor has already shown the error
        })
        .finally(() => {
          this.loading = false;
        });
    },
    openOrder(order) {
      this.$router.push({ path: '/orders/view-orders', query: { order: order.order_number }});
    },
  },
};
</script>

<style lang="scss" scoped>
.dashboard {
  &__split {
    display: grid;
    grid-template-columns: minmax(0, 2fr) minmax(0, 1fr);
    gap: 20px;
    align-items: start;
    margin-bottom: 0;

    // the cards carry their own bottom margin
    > * { margin-bottom: 20px; }
  }
}

@media (max-width: 1100px) {
  .dashboard__split {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
