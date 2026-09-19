<template>
  <div class="acc-settings">
    <admin-page-header title="Accounting settings" subtitle="When the books start, which periods are closed, and where website sales money lands." />

    <div v-loading="loading" class="acc-settings__body">
      <template v-if="settings">
        <admin-card title="Books" subtitle="Sales from your website are posted to the books automatically, one summary per day.">
          <el-form label-position="top" :model="form" class="acc-settings__form" @submit.prevent="save">
            <el-form-item label="Books start on">
              <el-date-picker v-model="form.books_start_date" type="date" value-format="YYYY-MM-DD" format="D MMM YYYY" :clearable="false" :disabled-date="isFuture" />
              <p class="acc-settings__help">
                Orders before this date are left out of the books. Moving it earlier books the older sales straight away.
                Record your opening balances (cash in the bank, stock, loans) as a journal dated the day before.
              </p>
            </el-form-item>

            <el-form-item label="Period locked through">
              <el-date-picker v-model="form.books_closed_through" type="date" value-format="YYYY-MM-DD" format="D MMM YYYY" clearable placeholder="No period is locked" :disabled-date="isFuture" />
              <p class="acc-settings__help">
                Once a month is reported or filed, lock it. Nothing dated on or before this day can be added, corrected or voided, so
                finished figures cannot change under you.
              </p>
            </el-form-item>

            <el-form-item label="Website sales are deposited into">
              <el-select v-model="form.sales_deposit_account">
                <el-option v-for="a in settings.deposit_accounts" :key="a.code" :label="`${a.code} · ${a.name}`" :value="a.code" />
              </el-select>
              <p class="acc-settings__help">Customers pay by transfer or card, so this is normally your main bank account (or a payment-clearing account until the money settles).</p>
            </el-form-item>

            <el-button type="primary" native-type="submit" :loading="saving" :disabled="!dirty">Save settings</el-button>
          </el-form>
        </admin-card>

        <admin-card title="Website sales" subtitle="Keeps the books in step with your orders">
          <p class="acc-settings__line">
            <span>Last synced</span>
            <strong>{{ settings.last_sales_sync ? fromNow(settings.last_sales_sync) : 'Not yet' }}</strong>
          </p>
          <p class="acc-settings__line">
            <span>Entries in the books</span>
            <strong>{{ settings.entries.toLocaleString() }}</strong>
          </p>
          <p class="acc-settings__help">
            This runs automatically every hour and whenever you open a report. Use it after changing an order's payment or status if you want the books to reflect it right away.
            Days in a locked period are not changed; a late correction is booked today instead.
          </p>
          <el-button :loading="syncing" @click="sync">
            <el-icon><IconRefresh /></el-icon>Sync sales now
          </el-button>
        </admin-card>
      </template>
    </div>
  </div>
</template>

<script>
import moment from 'moment';
import { fetchSettings, saveSettings, syncSales } from '@/api/accounting';
import { dateText, naira } from '@/utils/reportFormat';

export default {
  name: 'AccountingSettings',
  data() {
    return { settings: null, form: {}, loading: false, saving: false, syncing: false };
  },
  computed: {
    dirty() {
      const s = this.settings;
      return !!s && (this.form.books_start_date !== s.books_start_date
        || (this.form.books_closed_through || '') !== (s.books_closed_through || '')
        || this.form.sales_deposit_account !== s.sales_deposit_account);
    },
  },
  created() {
    this.load();
  },
  methods: {
    isFuture: (date) => date.getTime() > Date.now(),
    fromNow: (value) => moment(value).fromNow(),
    apply(settings) {
      this.settings = settings;
      this.form = {
        books_start_date: settings.books_start_date,
        books_closed_through: settings.books_closed_through || '',
        sales_deposit_account: settings.sales_deposit_account,
      };
    },
    load() {
      this.loading = true;
      return fetchSettings()
        .then(this.apply)
        .catch(() => {})
        .finally(() => {
          this.loading = false;
        });
    },
    syncNote(sync) {
      return sync && sync.entries ? ` ${sync.entries} sales ${sync.entries === 1 ? 'entry' : 'entries'} posted (net ${naira(sync.net_adjustment)}).` : '';
    },
    save() {
      this.saving = true;
      return saveSettings({ ...this.form, books_closed_through: this.form.books_closed_through || null })
        .then(r => {
          this.apply(r);
          this.$message({ message: `Settings saved.${this.syncNote(r.sync)}`, type: 'success' });
        })
        .catch(() => {})
        .finally(() => {
          this.saving = false;
        });
    },
    sync() {
      this.syncing = true;
      return syncSales()
        .then(r => {
          this.apply(r);
          this.$message({ message: r.sync.entries ? `Books updated.${this.syncNote(r.sync)}` : 'The books already match your orders.', type: 'success' });
        })
        .catch(() => {})
        .finally(() => {
          this.syncing = false;
        });
    },
    dateText,
  },
};
</script>

<style lang="scss" scoped>
.acc-settings {
  &__form {
    max-width: 560px;

    :deep(.el-select),
    :deep(.el-date-editor) {
      width: 100%;
    }
  }

  &__help {
    margin: 6px 0 0;
    font-size: 12px;
    line-height: 1.55;
    color: var(--admin-muted);
  }

  &__line {
    display: flex;
    justify-content: space-between;
    max-width: 420px;
    margin: 0 0 8px;
    font-size: 14px;
  }
}
</style>
