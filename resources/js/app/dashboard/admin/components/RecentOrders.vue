<template>
  <el-table v-loading="loading" :data="orders" class="recent-orders" empty-text="No orders yet" @row-click="open">
    <el-table-column label="Order" min-width="150">
      <template #default="{ row }">
        <div class="cell-title mono">{{ row.order_number }}</div>
        <div class="cell-sub">{{ moment(row.created_at).format('D MMM YYYY, h:mm a') }}</div>
      </template>
    </el-table-column>
    <el-table-column label="Customer" min-width="160">
      <template #default="{ row }">
        <div class="cell-title">{{ row.customer ? row.customer.name : '—' }}</div>
        <div class="cell-sub">{{ row.customer ? row.customer.phone : '' }}</div>
      </template>
    </el-table-column>
    <el-table-column label="Amount" min-width="120" align="right">
      <template #default="{ row }">
        <span class="money">{{ currency }}{{ formatNumber(row.total, 2) }}</span>
      </template>
    </el-table-column>
    <el-table-column label="Payment" min-width="120">
      <template #default="{ row }">
        <admin-status-tag :status="row.payment_status" kind="payment" />
      </template>
    </el-table-column>
    <el-table-column label="Status" min-width="130">
      <template #default="{ row }">
        <admin-status-tag :status="row.order_status" kind="order" />
      </template>
    </el-table-column>
    <el-table-column width="70" align="right">
      <template #default="{ row }">
        <el-tooltip content="Open order" placement="top">
          <el-button circle size="small" aria-label="Open order" @click.stop="open(row)">
            <el-icon><IconView /></el-icon>
          </el-button>
        </el-tooltip>
      </template>
    </el-table-column>
  </el-table>
</template>

<script setup>
import moment from 'moment';
import { formatNumber } from '@/utils/index';

defineProps({
  orders: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
  currency: { type: String, default: '₦' },
});
const emit = defineEmits(['open']);

const open = (order) => emit('open', order);
</script>

<style lang="scss" scoped>
.recent-orders :deep(.el-table__row) {
  cursor: pointer;
}
</style>
