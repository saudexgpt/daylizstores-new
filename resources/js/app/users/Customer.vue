<template>
  <div class="customers">
    <admin-page-header title="Customers" subtitle="Everyone who has shopped with you.">
      <el-button :loading="downloading" @click="handleDownload">
        <el-icon><IconDownload /></el-icon>
        Export
      </el-button>
    </admin-page-header>

    <admin-card flush>
      <template #toolbar>
        <div class="admin-toolbar">
          <el-input
            v-model="query.keyword"
            class="grow"
            placeholder="Search by name, email or phone"
            clearable
            @input="debouncedFilter"
            @clear="handleFilter"
          >
            <template #prefix>
              <el-icon><IconSearch /></el-icon>
            </template>
          </el-input>
        </div>
      </template>

      <el-table v-loading="loading" :data="list" empty-text="No customers found">
        <el-table-column label="Customer" min-width="260">
          <template #default="{ row }">
            <div class="person">
              <span class="person__avatar">{{ initials(row.name) }}</span>
              <div class="person__text">
                <div class="cell-title">{{ row.name }}</div>
                <div class="cell-sub">{{ row.email }}</div>
              </div>
            </div>
          </template>
        </el-table-column>
        <el-table-column label="Phone" min-width="150">
          <template #default="{ row }">{{ row.phone || '—' }}</template>
        </el-table-column>
        <el-table-column label="Orders" min-width="110" align="right">
          <template #default="{ row }">
            <admin-status-tag :tone="row.orders_count ? 'info' : 'neutral'" :label="String(row.orders_count || 0)" kind="generic" />
          </template>
        </el-table-column>
        <el-table-column label="Joined" min-width="130">
          <template #default="{ row }">
            <span class="text-muted">{{ row.joined_at ? moment(row.joined_at).format('D MMM YYYY') : '—' }}</span>
          </template>
        </el-table-column>
        <el-table-column label="" width="150" align="right" fixed="right">
          <template #default="{ row }">
            <div class="row-actions">
              <el-tooltip v-if="canViewOrders" content="View their orders" placement="top">
                <el-button circle size="small" aria-label="View orders" @click="viewOrders(row)">
                  <el-icon><IconShoppingBag /></el-icon>
                </el-button>
              </el-tooltip>
              <el-tooltip content="Edit profile" placement="top">
                <el-button circle size="small" aria-label="Edit profile" @click="$router.push('/administrator/users/edit/' + row.id)">
                  <el-icon><IconEdit /></el-icon>
                </el-button>
              </el-tooltip>
              <el-tooltip content="Reset password" placement="top">
                <el-button circle size="small" type="warning" plain aria-label="Reset password" @click="resetPassword(row)">
                  <el-icon><IconRefreshRight /></el-icon>
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
          @current-change="getList"
          @size-change="handleFilter"
        />
      </div>
    </admin-card>

    <el-dialog v-model="passwordDialog.visible" title="Password reset" width="440px">
      <p class="pw-note">
        <strong>{{ passwordDialog.name }}</strong> can now sign in with this temporary password and will be asked to choose a new one.
        It is shown only once.
      </p>
      <div class="pw-box">
        <code>{{ passwordDialog.password }}</code>
        <el-button size="small" @click="copyPassword">
          <el-icon><IconCopyDocument /></el-icon>
          Copy
        </el-button>
      </div>
      <template #footer>
        <el-button type="primary" @click="passwordDialog.visible = false">Done</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script>
import moment from 'moment';
import Resource from '@/api/resource';
import UserResource from '@/api/user';
import checkPermission from '@/utils/permission';

const userResource = new UserResource();
const resetUserPasswordResource = new Resource('users/reset-password');

export default {
  name: 'CustomerList',
  data() {
    return {
      list: [],
      total: 0,
      loading: false,
      downloading: false,
      query: { page: 1, limit: 10, keyword: '', role: 'customer' },
      filterTimer: null,
      passwordDialog: { visible: false, name: '', password: '' },
    };
  },
  computed: {
    canViewOrders() {
      return checkPermission(['view order']);
    },
  },
  created() {
    this.getList();
  },
  beforeUnmount() {
    clearTimeout(this.filterTimer);
  },
  methods: {
    moment,
    initials(name) {
      const words = String(name || '').trim().split(/\s+/).filter(Boolean);
      return words.length ? (words[0][0] + (words.length > 1 ? words[words.length - 1][0] : '')).toUpperCase() : '?';
    },
    getList() {
      this.loading = true;
      userResource
        .list(this.query)
        .then((response) => {
          this.list = response.data;
          this.total = response.meta.total;
        })
        .catch(() => {})
        .finally(() => {
          this.loading = false;
        });
    },
    handleFilter() {
      this.query.page = 1;
      this.getList();
    },
    debouncedFilter() {
      clearTimeout(this.filterTimer);
      this.filterTimer = setTimeout(this.handleFilter, 350);
    },
    // the orders screen searches by email, name, phone or order number
    viewOrders(customer) {
      this.$router.push({ path: '/orders/view-orders', query: { q: customer.email }});
    },
    resetPassword(row) {
      this.$confirm(`This signs ${row.name} out everywhere and replaces their password with a temporary one.`, 'Reset password?', {
        confirmButtonText: 'Reset password',
        cancelButtonText: 'Cancel',
        type: 'warning',
      })
        .then(() => resetUserPasswordResource.update(row.id))
        .then((response) => {
          this.passwordDialog = { visible: true, name: row.name, password: response.new_password };
        })
        .catch(() => {
          // dialog dismissed, or the interceptor already showed the reason
        });
    },
    copyPassword() {
      if (navigator.clipboard) {
        navigator.clipboard.writeText(this.passwordDialog.password)
          .then(() => this.$message({ message: 'Copied', type: 'success' }))
          .catch(() => this.$message({ message: 'Select the password and copy it manually', type: 'warning' }));
      }
    },
    // The API caps a page at 100, so fetch page by page.
    async handleDownload() {
      this.downloading = true;
      try {
        const all = [];
        let page = 1;
        let lastPage = 1;
        do {
          const response = await userResource.list({ ...this.query, page, limit: 100 });
          all.push(...response.data);
          lastPage = response.meta.last_page;
          page++;
        } while (page <= lastPage);
        const excel = await import('@/vendor/Export2Excel');
        excel.export_json_to_excel({
          header: ['name', 'email', 'phone', 'orders', 'joined'],
          data: all.map(c => [c.name, c.email, c.phone, c.orders_count || 0, c.joined_at || '']),
          filename: 'customer-list',
        });
      } catch (error) {
        // the interceptor already showed any request error
      } finally {
        this.downloading = false;
      }
    },
  },
};
</script>

<style lang="scss" scoped>
.person {
  display: flex;
  align-items: center;
  gap: 12px;

  &__avatar {
    flex: none;
    display: grid;
    place-items: center;
    width: 38px;
    height: 38px;
    border-radius: 50%;
    background: var(--admin-primary-soft);
    color: var(--admin-primary);
    font-size: 13px;
    font-weight: 700;
  }

  &__text { min-width: 0; }
}

.pw-note {
  margin: 0 0 14px;
  line-height: 1.55;
  color: var(--admin-muted);

  strong { color: var(--admin-text); }
}

.pw-box {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 14px 16px;
  border: 1px dashed var(--admin-border-strong);
  border-radius: 12px;
  background: var(--admin-surface-soft);

  code {
    font-family: 'JetBrains Mono', Consolas, monospace;
    font-size: 18px;
    font-weight: 700;
    letter-spacing: 0.06em;
    user-select: all;
  }
}
</style>
