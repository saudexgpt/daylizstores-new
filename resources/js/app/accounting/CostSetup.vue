<template>
  <div class="setup">
    <admin-page-header
      title="Product costing"
      :subtitle="live ? 'Costing is live: every delivery is costed and every sale takes its cost from the oldest stock first.' : 'Give every product size on the shelf a cost, then switch costing on. From that day, profit is real.'"
    />

    <div v-if="loading" class="setup__loading"><el-skeleton :rows="6" animated /></div>

    <!-- ============================== live: is everything still in step? ============================== -->
    <template v-else-if="live">
      <admin-card :title="`Live since ${dateText(startDate)}`" subtitle="Stock only moves through Receive stock, sales and stock adjustments, so the books and the shelf must always agree.">
        <template #actions>
          <el-button :loading="checking" @click="refresh"><el-icon><IconRefresh /></el-icon>Check now</el-button>
        </template>

        <div v-if="check" class="tiles">
          <div class="tile"><span>Stock at cost</span><strong>{{ naira(check.stock_value) }}</strong></div>
          <div class="tile"><span>Inventory in the books</span><strong>{{ naira(check.books_value) }}</strong></div>
          <div class="tile" :class="check.in_step ? 'is-good' : 'is-bad'">
            <span>Books and shelf</span>
            <strong>{{ check.in_step ? 'In step' : 'Out of step' }}</strong>
          </div>
          <div class="tile" :class="check.provisional_units ? 'is-warn' : ''">
            <span>Sold without a delivery on record</span>
            <strong>{{ check.provisional_units }} unit{{ check.provisional_units === 1 ? '' : 's' }}</strong>
          </div>
        </div>

        <el-alert
          v-if="check && check.provisional_units"
          type="warning"
          show-icon
          :closable="false"
          class="setup__alert"
          title="Some sales had no stock on record when they were dispatched"
          :description="`${check.provisional_units} unit(s) were costed at the latest known price (${naira(check.provisional_value)}). Record the missing delivery with Receive stock and they are corrected automatically.`"
        />
        <el-alert
          v-if="check && !check.in_step"
          type="error"
          show-icon
          :closable="false"
          class="setup__alert"
          title="The books and the shelf do not agree"
          :description="`Difference in value: ${naira(check.value_difference)}. ${check.unit_differences} product size(s) have a different number of units on the shelf than in the cost records. This usually means stock was changed outside Receive stock or Stock adjustments — fix each with a stock adjustment.`"
        />

        <el-table v-if="check && check.differences.length" :data="check.differences" size="small" class="setup__table">
          <el-table-column label="Product" prop="product" min-width="200" />
          <el-table-column label="Size" min-width="90"><template #default="{ row }">{{ row.size || '—' }}</template></el-table-column>
          <el-table-column label="On the shelf" prop="shelf" align="right" width="120" />
          <el-table-column label="In cost records" prop="layers" align="right" width="130" />
          <el-table-column label="Difference" align="right" width="110"><template #default="{ row }"><strong>{{ row.shelf - row.layers }}</strong></template></el-table-column>
        </el-table>
      </admin-card>

      <admin-card title="Day to day">
        <div class="links">
          <router-link to="/food-menu/receive-stock"><el-button type="primary"><el-icon><IconUpload /></el-icon>Receive stock</el-button></router-link>
          <router-link to="/food-menu/deliveries"><el-button>Deliveries</el-button></router-link>
          <router-link to="/food-menu/stock-adjustments"><el-button>Stock adjustments</el-button></router-link>
          <router-link to="/reports/index?report=gross-margin"><el-button>Gross margin report</el-button></router-link>
          <router-link to="/reports/index?report=inventory-valuation"><el-button>Inventory valuation</el-button></router-link>
        </div>
      </admin-card>
    </template>

    <!-- ============================== not live: the cut-over ============================== -->
    <template v-else>
      <admin-card title="How switching on works">
        <ol class="steps">
          <li><strong>Count the shelves</strong> and correct any differences first, on the Products page or with Stock adjustments. The system quantity is what gets valued.</li>
          <li><strong>Download the cost sheet</strong> below: one line per product size on the shelf. Fill in the cost of one unit from your latest supplier invoice (include freight and customs if they apply).</li>
          <li><strong>Upload it</strong>. It is checked but nothing changes yet.</li>
          <li><strong>Switch on.</strong> The shelf is valued, the books get an opening inventory entry, and from then on stock comes in through Receive stock.</li>
        </ol>
        <p class="setup__hint">Do this when the shop is quiet, on the day costing should start (usually the 1st of a month). Nothing you have already sold is re-costed.</p>
      </admin-card>

      <admin-card title="1 · Download the cost sheet" subtitle="Everything on the shelf right now, ready to fill in">
        <el-button type="primary" :loading="downloading" @click="downloadSheet"><el-icon><IconDownload /></el-icon>Download cost sheet (Excel)</el-button>
        <span v-if="shelfInfo" class="setup__hint setup__inline">{{ shelfInfo.rows.length.toLocaleString() }} product sizes · {{ shelfInfo.units.toLocaleString() }} units</span>
      </admin-card>

      <admin-card title="2 · Upload the filled sheet" subtitle="It is checked here; nothing is changed until you switch costing on">
        <input ref="file" type="file" accept=".xlsx,.xls,.csv" class="setup__file" @change="onFile">
        <el-button @click="$refs.file.click()"><el-icon><IconUpload /></el-icon>Choose the filled sheet</el-button>
        <span v-if="fileName" class="setup__hint setup__inline">{{ fileName }} — {{ rows.length.toLocaleString() }} rows read</span>
        <el-alert v-if="parseError" type="error" show-icon :closable="false" class="setup__alert" :title="parseError" />

        <div v-if="result" v-loading="checking" class="setup__result">
          <div class="tiles">
            <div class="tile" :class="result.ok ? 'is-good' : 'is-bad'"><span>Product sizes with a cost</span><strong>{{ result.priced_lines }} of {{ result.shelf_lines }}</strong></div>
            <div class="tile"><span>Units valued</span><strong>{{ result.units.toLocaleString() }}</strong></div>
            <div class="tile"><span>Stock at cost</span><strong>{{ naira(result.value) }}</strong></div>
          </div>

          <el-alert v-if="result.invalid_count" type="error" show-icon :closable="false" class="setup__alert" :title="`${result.invalid_count} cost(s) are not a number above zero`" />
          <el-table v-if="result.invalid.length" :data="result.invalid" size="small" class="setup__table" max-height="220">
            <el-table-column label="Sheet row" prop="row" width="100" />
            <el-table-column label="Item ID" prop="item_id" width="100" />
            <el-table-column label="Size" prop="size" width="100" />
            <el-table-column label="Problem" prop="reason" min-width="220" />
          </el-table>

          <el-alert v-if="result.missing_count" type="warning" show-icon :closable="false" class="setup__alert" :title="`${result.missing_count} product size(s) on the shelf still need a cost`" description="Fill them in on the sheet and upload it again. Sizes with no stock are not needed." />
          <el-table v-if="result.missing.length" :data="result.missing" size="small" class="setup__table" max-height="260">
            <el-table-column label="Product" prop="product" min-width="220" />
            <el-table-column label="Size" min-width="90"><template #default="{ row }">{{ row.size || '—' }}</template></el-table-column>
            <el-table-column label="Units" prop="qty" align="right" width="90" />
          </el-table>
          <p v-if="result.missing_count > result.missing.length" class="setup__hint">Showing the first {{ result.missing.length }}.</p>
          <p v-if="result.not_on_shelf_count" class="setup__hint">{{ result.not_on_shelf_count }} row(s) in the sheet are not on the shelf now and will be ignored.</p>
        </div>
      </admin-card>

      <admin-card title="3 · Switch costing on">
        <el-form label-position="top" class="setup__start" @submit.prevent>
          <el-form-item label="Costing starts on">
            <el-date-picker v-model="startDate" type="date" value-format="YYYY-MM-DD" format="D MMM YYYY" :clearable="false" :disabled-date="isFuture" />
          </el-form-item>
        </el-form>
        <p class="setup__hint">Use today's date if you are doing this right after the stock count. The opening inventory is booked on the day before.</p>
        <el-button type="primary" size="large" :disabled="!result || !result.ok" :loading="going" @click="switchOn">
          <el-icon><IconCheck /></el-icon>Switch on product costing
        </el-button>
        <span v-if="!result || !result.ok" class="setup__hint setup__inline">Available once every size on the shelf has a valid cost.</span>
      </admin-card>
    </template>
  </div>
</template>

<script>
import moment from 'moment';
import { ElMessageBox } from 'element-plus';
import { checkCostSheet, costingState, fetchShelf, goLive, reconcile, resetCostingState } from '@/api/costing';
import { costSheetRows, parseCostSheet } from '@/utils/costSheet';
import { dateText, naira } from '@/utils/reportFormat';

export default {
  name: 'CostSetup',
  data() {
    return {
      loading: true,
      live: false,
      startDate: moment().format('YYYY-MM-DD'),
      shelfInfo: null,
      downloading: false,
      fileName: '',
      rows: [],
      parseError: '',
      result: null,
      checking: false,
      going: false,
      check: null,
    };
  },
  created() {
    costingState(true).then(s => {
      this.live = !!s.enabled;
      if (this.live) {
        this.startDate = s.start_date;
        return this.refresh();
      }
      return fetchShelf().then(r => {
        this.shelfInfo = r;
      });
    }).catch(() => {}).finally(() => {
      this.loading = false;
    });
  },
  methods: {
    dateText,
    naira,
    isFuture: (date) => date.getTime() > Date.now(),
    refresh() {
      this.checking = true;
      return reconcile().then(r => {
        this.check = r;
      }).catch(() => {}).finally(() => {
        this.checking = false;
      });
    },
    async downloadSheet() {
      this.downloading = true;
      try {
        const shelf = await fetchShelf();
        this.shelfInfo = shelf;
        const [XLSX, files] = await Promise.all([import('xlsx'), import('file-saver')]);
        const sheet = XLSX.utils.aoa_to_sheet(costSheetRows(shelf.rows));
        sheet['!cols'] = [{ wch: 9 }, { wch: 42 }, { wch: 22 }, { wch: 12 }, { wch: 14 }, { wch: 16 }];
        const book = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(book, sheet, 'Cost sheet');
        const out = XLSX.write(book, { bookType: 'xlsx', type: 'array' });
        files.saveAs(new Blob([out], { type: 'application/octet-stream' }), `cost-sheet_${moment().format('YYYY-MM-DD')}.xlsx`);
      } catch (e) {
        // a failed request has already been reported; anything else is shown here
        if (!e || !e.response) {
          this.$message({ message: 'The cost sheet could not be created.', type: 'error' });
        }
      } finally {
        this.downloading = false;
      }
    },
    async onFile(event) {
      const file = event.target.files && event.target.files[0];
      event.target.value = '';
      if (!file) {
        return;
      }
      this.fileName = file.name;
      this.parseError = '';
      this.result = null;
      try {
        const XLSX = await import('xlsx');
        const book = XLSX.read(await file.arrayBuffer(), { type: 'array' });
        const aoa = XLSX.utils.sheet_to_json(book.Sheets[book.SheetNames[0]], { header: 1, raw: true, defval: '' });
        const parsed = parseCostSheet(aoa);
        this.rows = parsed.rows;
        this.parseError = parsed.error;
        if (!parsed.error) {
          this.checking = true;
          this.result = await checkCostSheet(parsed.rows);
        }
      } catch (e) {
        if (!e || !e.response) {
          this.parseError = 'That file could not be read. Use the cost sheet you downloaded here (.xlsx).';
        }
      } finally {
        this.checking = false;
      }
    },
    switchOn() {
      ElMessageBox.confirm(
        `This values ${this.result.units.toLocaleString()} units at ${naira(this.result.value)}, books that as opening inventory, and starts costing on ${dateText(this.startDate)}. `
        + 'From then on stock can only come in through Receive stock. It cannot be undone from here.',
        'Switch on product costing?',
        { confirmButtonText: 'Switch on', cancelButtonText: 'Not yet', type: 'warning' },
      ).then(() => {
        this.going = true;
        return goLive(this.rows, this.startDate);
      }).then(r => {
        resetCostingState();
        this.$message({ message: `Costing is live. ${r.layers} product sizes valued at ${naira(r.value)}.`, type: 'success', duration: 7000 });
        this.live = true;
        this.startDate = r.start_date;
        return this.refresh();
      }).catch(() => {}).finally(() => {
        this.going = false;
      });
    },
  },
};
</script>

<style lang="scss" scoped>
.setup {
  &__hint {
    margin: 10px 0;
    font-size: 13px;
    line-height: 1.5;
    color: var(--admin-muted);
  }

  &__inline { display: inline; margin-left: 12px; }
  &__alert { margin-top: 14px; }
  &__table { margin-top: 12px; }
  &__file { display: none; }
  &__result { margin-top: 18px; }
  &__start { max-width: 280px; }
  &__start :deep(.el-date-editor) { width: 100%; }
}

.steps {
  margin: 0;
  padding-left: 20px;
  line-height: 1.7;
  font-size: 14px;

  li + li { margin-top: 6px; }
}

.tiles {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
  gap: 14px;
}

.tile {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 14px 18px;
  border: 1px solid var(--admin-border);
  border-radius: 12px;
  background: var(--admin-surface);

  span { font-size: 12px; font-weight: 500; color: var(--admin-muted); }
  strong { font-size: 20px; font-variant-numeric: tabular-nums; }

  &.is-good { background: var(--admin-success-soft); strong { color: var(--admin-success); } }
  &.is-bad { background: var(--admin-danger-soft); strong { color: var(--admin-danger); } }
  &.is-warn { background: var(--admin-warning-soft); strong { color: var(--admin-warning); } }
}

.links {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}
</style>
