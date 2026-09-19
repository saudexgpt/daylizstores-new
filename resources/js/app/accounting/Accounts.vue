<template>
  <div class="accounts">
    <admin-page-header title="Chart of accounts" subtitle="The categories every transaction is filed under. The standard set is ready to use — add your own where you need more detail.">
      <el-button v-if="canManage" type="primary" @click="openNew">
        <el-icon><IconPlus /></el-icon>Add account
      </el-button>
    </admin-page-header>

    <admin-card flush>
      <template #toolbar>
        <div class="admin-toolbar">
          <el-radio-group v-model="type">
            <el-radio-button value="">All</el-radio-button>
            <el-radio-button v-for="t in types" :key="t.value" :value="t.value">{{ t.label }}</el-radio-button>
          </el-radio-group>
          <el-input v-model="search" class="grow" placeholder="Search by code or name" clearable>
            <template #prefix>
              <el-icon><IconSearch /></el-icon>
            </template>
          </el-input>
          <el-checkbox v-model="showInactive">Show inactive</el-checkbox>
        </div>
      </template>

      <el-table v-loading="loading" :data="visible" empty-text=" ">
        <el-table-column label="Code" width="100">
          <template #default="{ row }"><span class="mono">{{ row.code }}</span></template>
        </el-table-column>
        <el-table-column label="Account" min-width="260">
          <template #default="{ row }">
            <div class="cell-title">{{ row.name }}</div>
            <div v-if="row.description" class="cell-sub">{{ row.description }}</div>
          </template>
        </el-table-column>
        <el-table-column label="Type" width="130">
          <template #default="{ row }">
            <admin-status-tag kind="generic" :tone="typeTone[row.type]" :label="typeLabel[row.type]" />
          </template>
        </el-table-column>
        <el-table-column label="Balance" align="right" min-width="150">
          <template #default="{ row }">
            <span class="money" :class="{ 'is-neg': row.balance < 0 }">{{ naira(row.balance) }}</span>
          </template>
        </el-table-column>
        <el-table-column label="Status" width="130">
          <template #default="{ row }">
            <admin-status-tag kind="enabled" :status="row.is_active ? 1 : 0" />
            <el-tooltip v-if="row.is_system" content="Used by automatic postings — it can be renamed but not removed" placement="top">
              <el-icon class="accounts__lock"><IconLock /></el-icon>
            </el-tooltip>
          </template>
        </el-table-column>
        <el-table-column v-if="canManage" label="" width="150" align="right" fixed="right">
          <template #default="{ row }">
            <span class="row-actions">
              <el-tooltip content="Edit" placement="top">
                <el-button circle size="small" type="primary" plain aria-label="Edit" @click="openEdit(row)"><el-icon><IconEdit /></el-icon></el-button>
              </el-tooltip>
              <el-tooltip v-if="!row.is_system" :content="row.is_active ? 'Deactivate' : 'Reactivate'" placement="top">
                <el-button circle size="small" :aria-label="row.is_active ? 'Deactivate' : 'Reactivate'" @click="toggle(row)">
                  <el-icon><component :is="row.is_active ? 'IconLock' : 'IconUnlock'" /></el-icon>
                </el-button>
              </el-tooltip>
              <el-tooltip v-if="!row.is_system && !row.in_use" content="Delete" placement="top">
                <el-button circle size="small" type="danger" plain aria-label="Delete" @click="remove(row)"><el-icon><IconDelete /></el-icon></el-button>
              </el-tooltip>
            </span>
          </template>
        </el-table-column>
      </el-table>
    </admin-card>

    <el-dialog v-model="dialog.open" :title="dialog.id ? 'Edit account' : 'New account'" width="520px" append-to-body destroy-on-close>
      <el-form label-position="top" :model="form" @submit.prevent="save">
        <div class="accounts__row">
          <el-form-item label="Code" required>
            <el-input v-model="form.code" maxlength="10" placeholder="e.g. 6150" :disabled="dialog.locked" />
          </el-form-item>
          <el-form-item label="Type" required>
            <el-select v-model="form.type" :disabled="dialog.locked || dialog.system" @change="form.subtype = ''">
              <el-option v-for="t in types" :key="t.value" :label="t.label" :value="t.value" />
            </el-select>
          </el-form-item>
        </div>
        <el-form-item label="Name" required>
          <el-input v-model="form.name" maxlength="120" placeholder="e.g. Packaging materials" />
        </el-form-item>
        <el-form-item label="Group (optional)">
          <el-select v-model="form.subtype" clearable placeholder="Used to group the statements">
            <el-option v-for="s in subtypeOptions" :key="s" :label="subtypeLabel(s)" :value="s" />
          </el-select>
        </el-form-item>
        <el-form-item label="Description (optional)">
          <el-input v-model="form.description" maxlength="255" />
        </el-form-item>
        <p v-if="dialog.locked" class="text-muted">This account already has transactions, so its code and type are fixed.</p>
      </el-form>
      <template #footer>
        <el-button @click="dialog.open = false">Cancel</el-button>
        <el-button type="primary" :loading="saving" @click="save">Save</el-button>
      </template>
    </el-dialog>
  </div>
</template>

<script>
import { ElMessageBox } from 'element-plus';
import { deleteAccount, fetchAccounts, saveAccount } from '@/api/accounting';
import { naira } from '@/utils/reportFormat';
import { can } from '@/utils/permission';

const SUBTYPES = {
  asset: ['cash', 'bank', 'clearing', 'inventory', 'receivable', 'prepayment', 'fixed', 'contra'],
  liability: ['payable', 'loan', 'tax', 'accrued'],
  equity: ['capital', 'drawings', 'retained', 'opening'],
  income: ['sales', 'other_income', 'contra'],
  expense: ['cogs', 'operating'],
};
const SUBTYPE_LABELS = {
  cash: 'Cash', bank: 'Bank', clearing: 'Payment clearing', inventory: 'Inventory', receivable: 'Money owed to you', prepayment: 'Prepayments',
  fixed: 'Fixed assets', contra: 'Contra (reduces its group)', payable: 'Money you owe', loan: 'Loans', tax: 'Taxes', accrued: 'Accrued costs',
  capital: 'Owner capital', drawings: 'Owner drawings', retained: 'Retained earnings', opening: 'Opening balances',
  sales: 'Sales', other_income: 'Other income', cogs: 'Cost of sales', operating: 'Operating expenses',
};

const blank = () => ({ code: '', name: '', type: 'expense', subtype: '', description: '' });

export default {
  name: 'AccountingAccounts',
  data() {
    return {
      accounts: [],
      loading: false,
      saving: false,
      type: '',
      search: '',
      showInactive: false,
      dialog: { open: false, id: null, locked: false, system: false },
      form: blank(),
      types: [
        { value: 'asset', label: 'Assets' }, { value: 'liability', label: 'Liabilities' }, { value: 'equity', label: 'Equity' },
        { value: 'income', label: 'Income' }, { value: 'expense', label: 'Expenses' },
      ],
      typeLabel: { asset: 'Asset', liability: 'Liability', equity: 'Equity', income: 'Income', expense: 'Expense' },
      typeTone: { asset: 'info', liability: 'warning', equity: 'neutral', income: 'success', expense: 'danger' },
    };
  },
  computed: {
    canManage() {
      return can('manage accounting');
    },
    visible() {
      const needle = this.search.trim().toLowerCase();
      return this.accounts.filter(a => (this.showInactive || a.is_active)
        && (!this.type || a.type === this.type)
        && (!needle || `${a.code} ${a.name}`.toLowerCase().includes(needle)));
    },
    subtypeOptions() {
      return SUBTYPES[this.form.type] || [];
    },
  },
  created() {
    this.load();
  },
  methods: {
    naira,
    subtypeLabel: (s) => SUBTYPE_LABELS[s] || s,
    load(force = false) {
      this.loading = true;
      return fetchAccounts(force)
        .then(a => {
          this.accounts = a;
        })
        .catch(() => {})
        .finally(() => {
          this.loading = false;
        });
    },
    openNew() {
      this.form = blank();
      this.dialog = { open: true, id: null, locked: false, system: false };
    },
    openEdit(row) {
      this.form = { code: row.code, name: row.name, type: row.type, subtype: row.subtype || '', description: row.description || '' };
      this.dialog = { open: true, id: row.id, locked: !!row.in_use, system: !!row.is_system };
    },
    save() {
      if (!this.form.code.trim() || !this.form.name.trim()) {
        this.$message({ message: 'Give the account a code and a name.', type: 'warning' });
        return;
      }
      const payload = { ...this.form, code: this.form.code.trim(), name: this.form.name.trim(), subtype: this.form.subtype || null, description: this.form.description || null };
      if (this.dialog.id) {
        // a used or system account keeps its code and type; send only what may change
        if (this.dialog.locked) {
          delete payload.code;
          delete payload.type;
        }
        if (this.dialog.system) {
          delete payload.type;
        }
      }
      this.saving = true;
      saveAccount(payload, this.dialog.id)
        .then(() => {
          this.$message({ message: 'Account saved', type: 'success' });
          this.dialog.open = false;
          return this.load(true);
        })
        .catch(() => {})
        .finally(() => {
          this.saving = false;
        });
    },
    toggle(row) {
      saveAccount({ name: row.name, is_active: !row.is_active }, row.id)
        .then(() => this.load(true))
        .catch(() => {});
    },
    remove(row) {
      ElMessageBox.confirm(`Delete ${row.code} · ${row.name}? It has no transactions, so nothing is lost.`, 'Delete account?', { type: 'warning', confirmButtonText: 'Delete', cancelButtonText: 'Keep' })
        .then(() => deleteAccount(row.id))
        .then(() => {
          this.$message({ message: 'Account deleted', type: 'success' });
          return this.load(true);
        })
        .catch(() => {});
    },
  },
};
</script>

<style lang="scss" scoped>
.accounts {
  &__row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0 16px;
  }

  &__lock {
    margin-left: 8px;
    color: var(--admin-muted);
    vertical-align: middle;
  }

  .is-neg {
    color: var(--admin-danger);
  }

  :deep(.el-select) {
    width: 100%;
  }
}

@media (max-width: 560px) {
  .accounts__row {
    grid-template-columns: 1fr;
  }
}
</style>
