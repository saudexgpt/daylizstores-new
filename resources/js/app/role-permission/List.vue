<template>
  <div class="roles">
    <admin-page-header title="Roles & permissions" subtitle="Decide what each role is allowed to do." />

    <admin-card flush>
      <el-table v-loading="loading" :data="list" empty-text="No roles found">
        <el-table-column label="Role" min-width="200">
          <template #default="{ row }">
            <div class="cell-title">{{ uppercaseFirst(row.name) }}</div>
            <div v-if="row.description" class="cell-sub">{{ row.description }}</div>
          </template>
        </el-table-column>
        <el-table-column label="Access" min-width="200">
          <template #default="{ row }">
            <admin-status-tag v-if="row.name === 'admin'" tone="primary" label="Full access" kind="generic" />
            <admin-status-tag
              v-else
              :tone="row.permissions.length ? 'info' : 'neutral'"
              :label="`${row.permissions.length} permission${row.permissions.length === 1 ? '' : 's'}`"
              kind="generic"
            />
          </template>
        </el-table-column>
        <el-table-column v-if="canManage" label="" width="200" align="right">
          <template #default="{ row }">
            <el-tooltip v-if="row.name === 'admin'" content="The administrator role always has every permission" placement="top">
              <span class="text-muted">Locked</span>
            </el-tooltip>
            <el-button v-else size="small" @click="handleEditPermissions(row.id)">
              <el-icon><IconKey /></el-icon>
              Edit permissions
            </el-button>
          </template>
        </el-table-column>
      </el-table>
    </admin-card>

    <el-dialog v-model="dialogVisible" :title="'Permissions · ' + uppercaseFirst(currentRole.name)" width="520px">
      <div v-loading="dialogLoading" class="perm">
        <p class="perm__hint">Tick everything this role should be allowed to do.</p>
        <el-tree
          ref="otherPermissions"
          :data="otherPermissions"
          :default-checked-keys="permissionKeys(roleOtherPermissions)"
          :props="permissionProps"
          show-checkbox
          node-key="id"
        />
      </div>
      <template #footer>
        <el-button @click="dialogVisible = false">Cancel</el-button>
        <el-button type="primary" :loading="dialogLoading" @click="confirmPermission">Save permissions</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script>
import Resource from '@/api/resource';
import RoleResource from '@/api/role';
import checkPermission from '@/utils/permission';
import { uppercaseFirst } from '@/filters';

const roleResource = new RoleResource();
const permissionResource = new Resource('permissions');

export default {
  name: 'RoleList',
  data() {
    return {
      currentRoleId: 0,
      list: [],
      loading: true,
      dialogLoading: false,
      dialogVisible: false,
      otherPermissions: [],
      permissionProps: {
        children: 'children',
        label: 'name',
        disabled: 'disabled',
      },
    };
  },
  computed: {
    canManage() {
      return checkPermission(['manage permission']);
    },
    currentRole() {
      return this.list.find(role => role.id === this.currentRoleId) || { name: '', permissions: [] };
    },
    roleOtherPermissions() {
      return this.classifyPermissions(this.currentRole.permissions).other;
    },
  },
  created() {
    this.getRoles();
    this.getPermissions();
  },
  methods: {
    uppercaseFirst,
    async getRoles() {
      this.loading = true;
      try {
        const { data } = await roleResource.list({});
        this.list = data.map(role => ({
          ...role,
          // only translated for the built-in roles; a missing key must not show as "roles.description.x"
          description: this.$te('roles.description.' + role.name) ? this.$t('roles.description.' + role.name) : '',
        }));
      } catch (error) {
        // the interceptor already showed the error
      } finally {
        this.loading = false;
      }
    },
    async getPermissions() {
      try {
        const { data } = await permissionResource.list({});
        this.otherPermissions = this.classifyPermissions(data).other;
      } catch (error) {
        // the interceptor already showed the error
      }
    },
    classifyPermissions(permissions) {
      const all = [];
      const other = [];
      permissions.forEach(permission => {
        all.push(permission);
        // "view menu ..." permissions belong to the old menu system and are no longer edited here
        if (!permission.name.startsWith('view menu')) {
          other.push(this.normalizePermission(permission));
        }
      });
      return { all, other };
    },
    normalizePermission(permission) {
      return { id: permission.id, name: uppercaseFirst(permission.name), disabled: permission.name === 'manage permission' };
    },
    permissionKeys(permissions) {
      return permissions.map(permission => permission.id);
    },
    handleEditPermissions(id) {
      this.currentRoleId = id;
      this.dialogVisible = true;
      this.$nextTick(() => {
        this.$refs.otherPermissions.setCheckedKeys(this.permissionKeys(this.roleOtherPermissions));
      });
    },
    confirmPermission() {
      const checkedPermissions = this.$refs.otherPermissions.getCheckedKeys();
      this.dialogLoading = true;
      roleResource.update(this.currentRole.id, { permissions: checkedPermissions })
        .then(() => {
          this.$message({ message: 'Permissions saved', type: 'success' });
          this.dialogVisible = false;
          this.getRoles();
        })
        .catch(() => {})
        .finally(() => {
          this.dialogLoading = false;
        });
    },
  },
};
</script>

<style lang="scss" scoped>
.perm {
  min-height: 120px;

  &__hint {
    margin: 0 0 12px;
    color: var(--admin-muted);
    font-size: 13px;
  }
}
</style>
