<template>
  <el-dialog
    :model-value="modelValue"
    :title="title"
    width="640px"
    top="6vh"
    append-to-body
    destroy-on-close
    @update:model-value="$emit('update:modelValue', $event)"
    @open="load"
  >
    <div class="txn-dialog">
    <el-radio-group v-if="!isEdit" v-model="form.type" class="txn-dialog__types" @change="onTypeChange">
      <el-radio-button value="expense">Expense</el-radio-button>
      <el-radio-button value="income">Income</el-radio-button>
      <el-radio-button value="transfer">Transfer</el-radio-button>
      <el-radio-button value="journal">Journal</el-radio-button>
    </el-radio-group>

    <p class="txn-dialog__intro">{{ intro }}</p>

    <el-form label-position="top" :model="form" @submit.prevent="submit">
      <div class="txn-dialog__row">
        <el-form-item label="Date" required>
          <el-date-picker
            v-model="form.date"
            type="date"
            value-format="YYYY-MM-DD"
            format="D MMM YYYY"
            :clearable="false"
            :disabled-date="isFuture"
          />
        </el-form-item>

        <el-form-item v-if="form.type !== 'journal'" label="Amount (₦)" required>
          <el-input-number v-model="form.amount" :min="0.01" :precision="2" :controls="false" placeholder="0.00" />
        </el-form-item>
      </div>

      <!-- expense / income -->
      <template v-if="form.type === 'expense' || form.type === 'income'">
        <div class="txn-dialog__row">
          <el-form-item :label="form.type === 'expense' ? 'Category' : 'Income category'" required>
            <el-select v-model="form.account_id" filterable placeholder="Choose a category">
              <el-option v-for="a in categoryOptions" :key="a.id" :label="`${a.code} · ${a.name}`" :value="a.id" />
            </el-select>
          </el-form-item>
          <el-form-item :label="form.type === 'expense' ? 'Paid from' : 'Received into'" required>
            <el-select v-model="form.payment_account_id" filterable placeholder="Choose an account">
              <el-option v-for="a in paymentOptions" :key="a.id" :label="`${a.code} · ${a.name}`" :value="a.id" />
            </el-select>
          </el-form-item>
        </div>
        <el-form-item :label="form.type === 'expense' ? 'Paid to' : 'Received from'">
          <el-input v-model="form.party" maxlength="150" :placeholder="form.type === 'expense' ? 'Supplier or payee' : 'Who paid you'" />
        </el-form-item>
      </template>

      <!-- transfer -->
      <div v-if="form.type === 'transfer'" class="txn-dialog__row">
        <el-form-item label="From" required>
          <el-select v-model="form.from_account_id" filterable placeholder="Money leaves">
            <el-option v-for="a in transferOptions" :key="a.id" :label="`${a.code} · ${a.name}`" :value="a.id" />
          </el-select>
        </el-form-item>
        <el-form-item label="To" required>
          <el-select v-model="form.to_account_id" filterable placeholder="Money arrives">
            <el-option v-for="a in transferOptions" :key="a.id" :label="`${a.code} · ${a.name}`" :value="a.id" />
          </el-select>
        </el-form-item>
      </div>

      <el-form-item label="Description" required>
        <el-input v-model="form.description" maxlength="255" :placeholder="placeholder" />
      </el-form-item>

      <!-- journal -->
      <div v-if="form.type === 'journal'" class="journal">
        <div class="journal__head">
          <span>Account</span><span>Debit</span><span>Credit</span><span>Memo</span><span />
        </div>
        <div v-for="(line, index) in form.lines" :key="index" class="journal__row">
          <el-select v-model="line.account_id" filterable placeholder="Account">
            <el-option v-for="a in allActive" :key="a.id" :label="`${a.code} · ${a.name}`" :value="a.id" />
          </el-select>
          <el-input-number v-model="line.debit" :min="0" :precision="2" :controls="false" placeholder="0.00" @change="line.debit && (line.credit = undefined)" />
          <el-input-number v-model="line.credit" :min="0" :precision="2" :controls="false" placeholder="0.00" @change="line.credit && (line.debit = undefined)" />
          <el-input v-model="line.memo" maxlength="255" placeholder="Optional" />
          <el-button circle size="small" :disabled="form.lines.length <= 2" aria-label="Remove line" @click="form.lines.splice(index, 1)">
            <el-icon><IconDelete /></el-icon>
          </el-button>
        </div>
        <div class="journal__foot">
          <el-button size="small" @click="form.lines.push(blankLine())">
            <el-icon><IconPlus /></el-icon>Add line
          </el-button>
          <span class="journal__total">Debits <strong>{{ naira(debitTotal) }}</strong></span>
          <span class="journal__total">Credits <strong>{{ naira(creditTotal) }}</strong></span>
          <el-tag :type="balanced ? 'success' : 'danger'" effect="light">{{ balanced ? 'Balanced' : `Out by ${naira(Math.abs(debitTotal - creditTotal))}` }}</el-tag>
        </div>
      </div>

      <el-form-item :label="form.type === 'journal' ? 'Party (optional)' : 'Receipt / reference no. (optional)'">
        <el-input v-if="form.type !== 'journal'" v-model="form.payment_reference" maxlength="100" placeholder="e.g. receipt number or transfer reference" />
        <el-input v-else v-model="form.party" maxlength="150" />
      </el-form-item>
    </el-form>
    </div>

    <template #footer>
      <el-button @click="$emit('update:modelValue', false)">Cancel</el-button>
      <el-button type="primary" :loading="saving" @click="submit">{{ isEdit ? 'Save correction' : 'Save' }}</el-button>
    </template>
  </el-dialog>
</template>

<script>
import { saveTransaction } from '@/api/accounting';
import { costingLive } from '@/api/costing';
import { naira } from '@/utils/reportFormat';

const today = () => {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};
const blankLine = () => ({ account_id: null, debit: undefined, credit: undefined, memo: '' });
const CASH_LIKE = ['cash', 'bank', 'clearing'];

export default {
  name: 'TransactionDialog',
  props: {
    modelValue: { type: Boolean, default: false },
    // every account (from fetchAccounts) — the dialog picks the right ones for each field
    accounts: { type: Array, default: () => [] },
    // which form to open on when adding
    type: { type: String, default: 'expense' },
    // a transaction (as returned by the API, with lines) to correct; null when adding
    transaction: { type: Object, default: null },
  },
  emits: ['update:modelValue', 'saved'],
  data() {
    return { form: this.blank('expense'), saving: false, costingIsLive: false };
  },
  computed: {
    isEdit() {
      return !!this.transaction;
    },
    title() {
      const names = { expense: 'expense', income: 'income', transfer: 'transfer', journal: 'journal entry', opening: 'opening balances' };
      return this.isEdit ? `Correct ${names[this.form.type] || 'transaction'} ${this.transaction.reference}` : 'Record a transaction';
    },
    intro() {
      if (this.isEdit) {
        return 'The original is voided (kept on record, with its reversal) and this corrected version is posted in its place.';
      }
      return {
        expense: 'Money the business spent — stock purchases, rent, transport, salaries, ads.',
        income: 'Money received that is not a website order — interest, refunds from suppliers, other income. Website sales are booked automatically.',
        transfer: 'Money moved between your own accounts, or put in / taken out by the owner or a lender. It is not income or an expense.',
        journal: 'For an accountant: enter the debit and credit lines yourself. They must balance.',
      }[this.form.type] || '';
    },
    placeholder() {
      return { expense: 'What was it for?', income: 'What was it for?', transfer: 'e.g. Owner put in capital', journal: 'What does this entry record?' }[this.form.type];
    },
    allActive() {
      return this.accounts.filter(a => a.is_active);
    },
    categoryOptions() {
      // the automatic sales accounts are fed by orders — booking to them by hand would double-count
      // sales accounts are fed by orders, and once costing is live so is Cost of Goods Sold (from sales and deliveries) —
      // booking to either by hand would double-count
      return this.allActive.filter(a => a.type === this.form.type
        && !(this.form.type === 'income' && a.is_system)
        && !(this.form.type === 'expense' && a.code === '5000' && this.costingIsLive));
    },
    paymentOptions() {
      return this.allActive.filter(a => (a.type === 'asset' && (CASH_LIKE.includes(a.subtype) || (this.form.type === 'income' && a.subtype === 'receivable')))
        || (a.type === 'liability' && a.subtype === 'payable' && this.form.type === 'expense'));
    },
    transferOptions() {
      return this.allActive.filter(a => ['asset', 'liability', 'equity'].includes(a.type));
    },
    debitTotal() {
      return this.form.lines.reduce((s, l) => s + (Number(l.debit) || 0), 0);
    },
    creditTotal() {
      return this.form.lines.reduce((s, l) => s + (Number(l.credit) || 0), 0);
    },
    balanced() {
      return Math.round(this.debitTotal * 100) === Math.round(this.creditTotal * 100) && this.debitTotal > 0;
    },
  },
  created() {
    costingLive().then(live => {
      this.costingIsLive = live;
    });
  },
  methods: {
    naira,
    blankLine,
    isFuture: (date) => date.getTime() > Date.now(),
    blank(type) {
      return {
        type, date: today(), amount: undefined, description: '', party: '', payment_reference: '',
        account_id: null, payment_account_id: null, from_account_id: null, to_account_id: null,
        lines: [blankLine(), blankLine()],
      };
    },
    defaultPayment() {
      // the main bank account if there is one, otherwise the first that fits
      const main = this.paymentOptions.find(a => a.code === '1010');
      return (main || this.paymentOptions[0] || {}).id || null;
    },
    load() {
      const t = this.transaction;
      this.form = this.blank(t ? t.type : this.type);
      if (t) {
        Object.assign(this.form, { date: t.date, description: t.description, party: t.party || '', payment_reference: t.payment_reference || '', amount: t.amount });
        if (t.type === 'income' || t.type === 'expense') {
          this.form.account_id = t.category ? t.category.id : null;
          this.form.payment_account_id = t.account ? t.account.id : null;
        } else if (t.type === 'transfer') {
          this.form.from_account_id = t.from ? t.from.id : null;
          this.form.to_account_id = t.to ? t.to.id : null;
        } else {
          this.form.lines = t.lines.map(l => ({ account_id: l.account_id, debit: l.debit || undefined, credit: l.credit || undefined, memo: l.memo || '' }));
        }
      } else {
        this.form.payment_account_id = this.defaultPayment();
      }
    },
    onTypeChange() {
      // keep what was typed that still applies; reset the account choices, which differ per type
      Object.assign(this.form, { account_id: null, payment_account_id: null, from_account_id: null, to_account_id: null });
      this.form.payment_account_id = this.defaultPayment();
    },
    payload() {
      const f = this.form;
      const common = { type: f.type, date: f.date, description: f.description.trim(), party: f.party || null, payment_reference: f.payment_reference || null };
      if (f.type === 'transfer') {
        return { ...common, amount: f.amount, from_account_id: f.from_account_id, to_account_id: f.to_account_id };
      }
      if (f.type === 'journal' || f.type === 'opening') {
        return { ...common, lines: f.lines.filter(l => l.account_id).map(l => ({ account_id: l.account_id, debit: l.debit || 0, credit: l.credit || 0, memo: l.memo || null })) };
      }
      return { ...common, amount: f.amount, account_id: f.account_id, payment_account_id: f.payment_account_id };
    },
    problem() {
      const f = this.form;
      if (!f.date) return 'Choose the date.';
      if (!f.description.trim()) return 'Add a short description.';
      if (f.type === 'journal') {
        if (f.lines.filter(l => l.account_id).length < 2) return 'A journal needs at least two lines.';
        if (!this.balanced) return 'Debits and credits must be equal before you can save.';
        return '';
      }
      if (!f.amount || f.amount <= 0) return 'Enter an amount above zero.';
      if (f.type === 'transfer') {
        if (!f.from_account_id || !f.to_account_id) return 'Choose both accounts.';
        if (f.from_account_id === f.to_account_id) return 'Choose two different accounts.';
        return '';
      }
      if (!f.account_id) return 'Choose a category.';
      if (!f.payment_account_id) return f.type === 'expense' ? 'Choose where the money was paid from.' : 'Choose where the money was received.';
      return '';
    },
    submit() {
      const problem = this.problem();
      if (problem) {
        this.$message({ message: problem, type: 'warning' });
        return;
      }
      this.saving = true;
      saveTransaction(this.payload(), this.isEdit ? this.transaction.id : null)
        .then(r => {
          this.$message({ message: this.isEdit ? 'Correction saved' : 'Saved', type: 'success' });
          this.$emit('saved', r.transaction);
          this.$emit('update:modelValue', false);
        })
        .catch(() => {
          // the request helper has already shown the reason (e.g. a closed period)
        })
        .finally(() => {
          this.saving = false;
        });
    },
  },
};
</script>

<style lang="scss" scoped>
.txn-dialog {
  &__types {
    margin-bottom: 12px;
  }

  &__intro {
    margin: 0 0 18px;
    font-size: 13px;
    line-height: 1.5;
    color: var(--admin-muted);
  }

  &__row {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0 16px;
  }

  :deep(.el-select),
  :deep(.el-date-editor),
  :deep(.el-input-number) {
    width: 100%;
  }
}

.journal {
  margin-bottom: 18px;

  &__head,
  &__row {
    display: grid;
    grid-template-columns: minmax(0, 1.6fr) minmax(0, 1fr) minmax(0, 1fr) minmax(0, 1fr) 32px;
    gap: 8px;
    align-items: center;
  }

  &__head {
    margin-bottom: 6px;
    font-size: 12px;
    font-weight: 600;
    color: var(--admin-muted);
  }

  &__row {
    margin-bottom: 8px;
  }

  &__foot {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px 18px;
    margin-top: 10px;
  }

  &__total {
    font-size: 13px;
    color: var(--admin-muted);

    strong {
      color: var(--admin-text);
      font-variant-numeric: tabular-nums;
    }
  }
}

@media (max-width: 640px) {
  .txn-dialog__row {
    grid-template-columns: minmax(0, 1fr);
  }

  .journal__head {
    display: none;
  }

  .journal__row {
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    padding-bottom: 10px;
    border-bottom: 1px dashed var(--admin-border);
  }
}
</style>
