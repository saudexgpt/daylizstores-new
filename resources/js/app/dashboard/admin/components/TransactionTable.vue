<template>
  <el-table
    v-loading="loading"
    :data="list"
    style="width: 100%;padding-top: 15px;"
  >
    <el-table-column label="Order #" min-width="200">
      <template #default="scope">
        {{ orderNoFilter(scope.row && scope.row.order_no) }}
      </template>
    </el-table-column>
    <el-table-column label="Price" width="195" align="center">
      <template #default="scope">
        ¥{{ toThousandFilter(scope.row && scope.row.price) }}
      </template>
    </el-table-column>
    <el-table-column label="Status" width="100" align="center">
      <template #default="scope">
        <el-tag :type="statusFilter(scope.row && scope.row.status)">
          {{ scope.row && scope.row.status }}
        </el-tag>
      </template>
    </el-table-column>
  </el-table>
</template>

<script>
import { fetchList } from '@/api/order';
import { toThousandFilter } from '@/filters';

export default {
  data() {
    return {
      list: [{ order_no: '1', price: '2', status: 'pending' }],
      loading: true,
    };
  },
  created() {
    this.fetchData();
  },
  methods: {
    toThousandFilter,
    statusFilter(status) {
      const statusMap = {
        success: 'success',
        pending: 'danger',
      };
      return statusMap[status];
    },
    orderNoFilter(str) {
      return str;
    },
    async fetchData() {
      const { data } = await fetchList();
      this.list = data.items.slice(0, 8);
      this.loading = false;
    },
  },
};
</script>
