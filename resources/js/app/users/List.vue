<template>
  <div class="users">
    <admin-page-header title="Users" subtitle="Staff accounts and what each person can do.">
      <el-button :loading="downloading" @click="handleDownload">
        <el-icon><IconDownload /></el-icon>
        Export
      </el-button>
      <el-button v-if="canAddNew && can(['manage user'])" type="primary" @click="handleCreate">
        <el-icon><IconPlus /></el-icon>
        Add user
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
          <el-select v-model="query.role" placeholder="All roles" clearable class="users__role-filter" @change="handleFilter">
            <el-option v-for="role in roles" :key="role.name" :label="uppercaseFirst(role.name)" :value="role.name" />
          </el-select>
        </div>
      </template>

      <el-table v-loading="load_table" :data="list" empty-text="No users found">
        <el-table-column label="User" min-width="260">
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
        <el-table-column label="Phone" min-width="140">
          <template #default="{ row }">{{ row.phone || '—' }}</template>
        </el-table-column>
        <el-table-column label="Role" min-width="200">
          <template #default="{ row }">
            <admin-status-tag v-if="isAdminRow(row)" tone="primary" label="Administrator" kind="generic" />
            <el-select
              v-else
              :model-value="currentRole(row)"
              size="small"
              placeholder="No role"
              class="users__role-select"
              :disabled="!can(['manage user']) || row.id === myId"
              @change="assignUserRole(row, $event)"
            >
              <el-option v-for="role in defaultRoles" :key="role.name" :label="uppercaseFirst(role.name)" :value="role.name" />
            </el-select>
          </template>
        </el-table-column>
        <el-table-column label="" width="190" align="right" fixed="right">
          <template #default="{ row }">
            <div class="row-actions">
              <el-tooltip v-if="can(['manage user'])" content="Edit profile" placement="top">
                <el-button circle size="small" aria-label="Edit profile" @click="$router.push('/administrator/users/edit/' + row.id)">
                  <el-icon><IconEdit /></el-icon>
                </el-button>
              </el-tooltip>
              <el-tooltip v-if="can(['manage permission']) && !isAdminRow(row)" content="Permissions" placement="top">
                <el-button circle size="small" type="primary" plain aria-label="Manage permissions" @click="handleEditPermissions(row.id)">
                  <el-icon><IconKey /></el-icon>
                </el-button>
              </el-tooltip>
              <el-tooltip v-if="can(['manage user']) && row.id !== myId" content="Reset password" placement="top">
                <el-button circle size="small" type="warning" plain aria-label="Reset password" @click="resetUserPassword(row)">
                  <el-icon><IconRefreshRight /></el-icon>
                </el-button>
              </el-tooltip>
              <el-tooltip v-if="can(['manage user']) && !isAdminRow(row) && row.id !== myId" content="Delete user" placement="top">
                <el-button circle size="small" type="danger" plain aria-label="Delete user" @click="handleDelete(row)">
                  <el-icon><IconDelete /></el-icon>
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

    <!-- permissions -->
    <el-dialog v-model="dialogPermissionVisible" :title="'Permissions · ' + currentUser.name" width="780px">
      <div v-if="currentUser.name" v-loading="dialogPermissionLoading" class="perm">
        <div class="perm__col">
          <h4 class="perm__title">Menus</h4>
          <el-tree
            ref="menuPermissions"
            :data="normalizedMenuPermissions"
            :default-checked-keys="permissionKeys(userMenuPermissions)"
            :props="permissionProps"
            show-checkbox
            node-key="id"
            default-expand-all
          />
        </div>
        <div class="perm__col">
          <h4 class="perm__title">Permissions</h4>
          <el-tree
            ref="otherPermissions"
            :data="normalizedOtherPermissions"
            :default-checked-keys="permissionKeys(userOtherPermissions)"
            :props="permissionProps"
            show-checkbox
            node-key="id"
            default-expand-all
          />
        </div>
      </div>
      <template #footer>
        <el-button @click="dialogPermissionVisible = false">Cancel</el-button>
        <el-button type="primary" :loading="dialogPermissionLoading" @click="confirmPermission">Save permissions</el-button>
      </template>
    </el-dialog>

    <!-- create -->
    <el-dialog v-model="dialogFormVisible" title="Add a user" width="520px" @closed="resetNewUser">
      <el-form ref="userForm" v-loading="userCreating" :rules="rules" :model="newUser" label-position="top" @submit.prevent="createUser">
        <el-form-item label="Role" prop="role">
          <el-select v-model="newUser.role" placeholder="Choose a role" style="width: 100%">
            <el-option v-for="role in defaultRoles" :key="role.name" :label="uppercaseFirst(role.name)" :value="role.name" />
          </el-select>
        </el-form-item>
        <el-form-item label="Full name" prop="name">
          <el-input v-model="newUser.name" maxlength="190" />
        </el-form-item>
        <el-form-item label="Email" prop="email">
          <el-input v-model="newUser.email" type="email" autocomplete="off" />
        </el-form-item>
        <el-form-item label="Phone" prop="phone">
          <el-input v-model="newUser.phone" inputmode="tel" maxlength="30" />
        </el-form-item>
        <el-form-item label="Password" prop="password">
          <el-input v-model="newUser.password" show-password autocomplete="new-password" />
          <span class="hint">At least 8 characters.</span>
        </el-form-item>
        <el-form-item label="Confirm password" prop="confirmPassword">
          <el-input v-model="newUser.confirmPassword" show-password autocomplete="new-password" />
        </el-form-item>
      </el-form>
      <template #footer>
        <el-button @click="dialogFormVisible = false">Cancel</el-button>
        <el-button type="primary" :loading="userCreating" @click="createUser">Create user</el-button>
      </template>
    </el-dialog>

    <!-- temporary password after a reset -->
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
import UserResource from '@/api/user';
import Resource from '@/api/resource';
import checkPermission from '@/utils/permission';
import { uppercaseFirst } from '@/filters';
import { useUserStore } from '@/store';

const userResource = new UserResource();
const permissionResource = new Resource('permissions');
const resetUserPasswordResource = new Resource('users/reset-password');
const deleteUserResource = new Resource('users');
const assignRoleResource = new Resource('users/assign-role');
const necessaryParams = new Resource('fetch-necessary-params');

// the coarse "staff"/"customer" marker is returned alongside the real role names
const MARKER_ROLES = ['staff', 'customer'];

export default {
  name: 'UserList',
  props: {
    canAddNew: {
      type: Boolean,
      default: true,
    },
  },
  data() {
    const validateConfirmPassword = (rule, value, callback) => {
      if (value !== this.newUser.password) {
        callback(new Error('The passwords do not match'));
      } else {
        callback();
      }
    };
    return {
      list: [],
      total: 0,
      load_table: false,
      downloading: false,
      userCreating: false,
      query: { page: 1, limit: 10, keyword: '', role: '' },
      filterTimer: null,
      roles: [],
      defaultRoles: [],
      newUser: {},
      dialogFormVisible: false,
      dialogPermissionVisible: false,
      dialogPermissionLoading: false,
      currentUserId: 0,
      currentUser: { name: '', permissions: { role: [], user: [] }},
      passwordDialog: { visible: false, name: '', password: '' },
      rules: {
        role: [{ required: true, message: 'Choose a role', trigger: 'change' }],
        name: [{ required: true, message: 'Enter a name', trigger: 'blur' }],
        phone: [{ required: true, message: 'Enter a phone number', trigger: 'blur' }],
        email: [
          { required: true, message: 'Enter an email address', trigger: 'blur' },
          { type: 'email', message: 'Enter a valid email address', trigger: ['blur', 'change'] },
        ],
        password: [
          { required: true, message: 'Enter a password', trigger: 'blur' },
          { min: 8, message: 'Use at least 8 characters', trigger: 'blur' },
        ],
        confirmPassword: [{ validator: validateConfirmPassword, trigger: 'blur' }],
      },
      permissionProps: { children: 'children', label: 'name', disabled: 'disabled' },
      menuPermissions: [],
      otherPermissions: [],
    };
  },
  computed: {
    myId() {
      return useUserStore().userId;
    },
    normalizedMenuPermissions() {
      return this.permissionGroups(this.menuPermissions, 'menu', 'Inherited from role', 'Extra menus');
    },
    normalizedOtherPermissions() {
      return this.permissionGroups(this.otherPermissions, 'other', 'Inherited from role', 'Extra permissions');
    },
    userMenuPermissions() {
      return this.classifyPermissions(this.userPermissions).menu;
    },
    userOtherPermissions() {
      return this.classifyPermissions(this.userPermissions).other;
    },
    userPermissions() {
      return this.currentUser.permissions.role.concat(this.currentUser.permissions.user);
    },
  },
  created() {
    this.getList();
    this.fetchNecessaryParams();
    this.resetNewUser();
    if (checkPermission(['manage permission'])) {
      this.getPermissions();
    }
  },
  beforeUnmount() {
    clearTimeout(this.filterTimer);
  },
  methods: {
    uppercaseFirst,
    can(permissions) {
      return checkPermission(permissions);
    },
    initials(name) {
      const words = String(name || '').trim().split(/\s+/).filter(Boolean);
      return words.length ? (words[0][0] + (words.length > 1 ? words[words.length - 1][0] : '')).toUpperCase() : '?';
    },
    isAdminRow(row) {
      return (row.roles || []).includes('admin');
    },
    currentRole(row) {
      return (row.roles || []).find(role => !MARKER_ROLES.includes(role)) || '';
    },
    async getPermissions() {
      const { data } = await permissionResource.list({});
      const { menu, other } = this.classifyPermissions(data);
      this.menuPermissions = menu;
      this.otherPermissions = other;
    },
    fetchNecessaryParams() {
      necessaryParams.list().then((response) => {
        const roles = (response.params && response.params.all_roles) || [];
        this.roles = roles;
        this.defaultRoles = roles;
      });
    },
    getList() {
      this.load_table = true;
      userResource
        .list(this.query)
        .then((response) => {
          this.list = response.data;
          this.total = response.meta.total;
        })
        .catch(() => {})
        .finally(() => {
          this.load_table = false;
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
    handleCreate() {
      this.resetNewUser();
      this.dialogFormVisible = true;
    },
    resetNewUser() {
      this.newUser = { name: '', email: '', phone: '', password: '', confirmPassword: '', role: '' };
      if (this.$refs.userForm) {
        this.$refs.userForm.clearValidate();
      }
    },
    createUser() {
      this.$refs.userForm.validate((valid) => {
        if (!valid) {
          return;
        }
        this.userCreating = true;
        userResource
          .store({ ...this.newUser, roles: [this.newUser.role] })
          .then(() => {
            this.$message({ message: `${this.newUser.name} was added`, type: 'success' });
            this.dialogFormVisible = false;
            this.handleFilter();
          })
          .catch(() => {
            // the shared axios interceptor shows the reason (e.g. the email is taken)
          })
          .finally(() => {
            this.userCreating = false;
          });
      });
    },
    resetUserPassword(row) {
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
    handleDelete(row) {
      this.$confirm(`${row.name}'s account will be deleted. This cannot be undone.`, 'Delete user?', {
        confirmButtonText: 'Delete',
        cancelButtonText: 'Keep',
        type: 'warning',
      })
        .then(() => deleteUserResource.destroy(row.id))
        .then(() => {
          this.$message({ message: 'User deleted', type: 'success' });
          this.getList();
        })
        .catch(() => {});
    },
    assignUserRole(row, role) {
      this.$confirm(`${row.name} will be given the role “${role}”.`, 'Change role?', {
        confirmButtonText: 'Change role',
        cancelButtonText: 'Cancel',
        type: 'warning',
      })
        .then(() => assignRoleResource.update(row.id, { role }))
        .then((response) => {
          // reactive update (this used to poke the DOM: getElementById(id).innerHTML = ...)
          row.roles = response.data.roles;
          this.$message({ message: 'Role updated', type: 'success' });
        })
        .catch(() => {});
    },
    async handleDownload() {
      // The API caps a page at 100, so fetch page by page.
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
        const columns = ['name', 'email', 'phone', 'role'];
        excel.export_json_to_excel({
          header: columns,
          data: all.map(user => [user.name, user.email, user.phone, this.currentRole(user) || (user.roles || []).join(', ')]),
          filename: 'user-list',
        });
      } catch (error) {
        // the interceptor already showed any request error
      } finally {
        this.downloading = false;
      }
    },
    async handleEditPermissions(id) {
      this.currentUserId = id;
      this.dialogPermissionLoading = true;
      this.dialogPermissionVisible = true;
      const found = this.list.find((user) => user.id === id);
      const { data } = await userResource.permissions(id);
      this.currentUser = { id: found.id, name: found.name, permissions: data };
      this.dialogPermissionLoading = false;
      this.$nextTick(() => {
        this.$refs.menuPermissions.setCheckedKeys(this.permissionKeys(this.userMenuPermissions));
        this.$refs.otherPermissions.setCheckedKeys(this.permissionKeys(this.userOtherPermissions));
      });
    },
    permissionKeys(permissions) {
      return permissions.map((permission) => permission.id);
    },
    // the tree: what the role already grants (locked), then what can be added for this user
    permissionGroups(available, kind, inheritedLabel, extraLabel) {
      const inherited = this.classifyPermissions(
        this.currentUser.permissions.role.map(p => ({ id: p.id, name: p.name, disabled: true })),
      )[kind];
      const extra = available.filter(
        (permission) => !this.currentUser.permissions.role.find((p) => p.id === permission.id),
      );
      return [
        { id: -1, name: inheritedLabel, disabled: true, children: inherited },
        { id: 0, name: extraLabel, children: extra, disabled: extra.length === 0 },
      ];
    },
    classifyPermissions(permissions) {
      const all = [];
      const menu = [];
      const other = [];
      permissions.forEach((permission) => {
        all.push(permission);
        if (permission.name.startsWith('view menu')) {
          menu.push(this.normalizeMenuPermission(permission));
        } else {
          other.push(this.normalizePermission(permission));
        }
      });
      return { all, menu, other };
    },
    normalizeMenuPermission(permission) {
      return {
        id: permission.id,
        name: uppercaseFirst(permission.name.substring(10)),
        disabled: permission.disabled || false,
      };
    },
    normalizePermission(permission) {
      return {
        id: permission.id,
        name: uppercaseFirst(permission.name),
        disabled: permission.disabled || permission.name === 'manage permission',
      };
    },
    confirmPermission() {
      const checked = this.$refs.menuPermissions.getCheckedKeys().concat(this.$refs.otherPermissions.getCheckedKeys());
      this.dialogPermissionLoading = true;
      userResource
        .updatePermission(this.currentUserId, { permissions: checked })
        .then(() => {
          this.$message({ message: 'Permissions saved', type: 'success' });
          this.dialogPermissionVisible = false;
        })
        .catch(() => {})
        .finally(() => {
          this.dialogPermissionLoading = false;
        });
    },
  },
};
</script>

<style lang="scss" scoped>
.users {
  &__role-filter { width: 200px; }
  &__role-select { width: 170px; }
}

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

.perm {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 20px;
  min-height: 160px;

  &__title {
    margin: 0 0 10px;
    font-size: 14px;
    font-weight: 700;
  }
}

.hint {
  display: block;
  width: 100%;
  margin-top: 4px;
  font-size: 12px;
  color: var(--admin-muted);
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

@media (max-width: 720px) {
  .perm { grid-template-columns: minmax(0, 1fr); }
  .users__role-filter { width: 100%; }
}
</style>
