<template>
  <div class="receive">
    <admin-page-header title="Receive stock" subtitle="Record a supplier delivery with what each size cost. This is what makes profit real.">
      <router-link v-if="canSeeCosts" to="/food-menu/deliveries">
        <el-button><el-icon><IconTickets /></el-icon>Past deliveries</el-button>
      </router-link>
    </admin-page-header>

    <div v-if="stateLoading" class="receive__loading"><el-skeleton :rows="5" animated /></div>

    <admin-card v-else-if="!live">
      <admin-empty
        title="Product costing is not switched on yet"
        description="Until it is, stock is added on the Products page as before. Costing starts after your stock count, once every size on the shelf has a cost."
        icon="Coin"
      >
        <router-link v-if="canSetup" to="/accounting/cost-setup"><el-button type="primary">Go to cost set-up</el-button></router-link>
        <router-link v-else to="/food-menu/manage-items"><el-button>Go to Products</el-button></router-link>
      </admin-empty>
    </admin-card>

    <template v-else>
      <admin-card title="Delivery" subtitle="From your supplier's invoice">
        <el-form label-position="top" :model="form" class="receive__form" @submit.prevent>
          <el-form-item label="Date received" required>
            <el-date-picker v-model="form.received_on" type="date" value-format="YYYY-MM-DD" format="D MMM YYYY" :clearable="false" :disabled-date="isFuture" />
          </el-form-item>
          <el-form-item label="Supplier" required>
            <el-input v-model="form.supplier" maxlength="150" placeholder="Who you bought from" />
          </el-form-item>
          <el-form-item label="Invoice number">
            <el-input v-model="form.invoice_number" maxlength="100" placeholder="From the supplier's invoice" />
          </el-form-item>
          <el-form-item label="Paid from">
            <el-select v-model="form.payment_account_id" clearable placeholder="Not paid yet — owed to the supplier">
              <el-option v-for="a in accounts" :key="a.id" :label="`${a.code} · ${a.name}`" :value="a.id" />
            </el-select>
          </el-form-item>
          <el-form-item label="Freight, customs and handling (₦)">
            <el-input-number v-model="form.extra_costs" :min="0" :precision="2" :controls="false" placeholder="0.00" />
          </el-form-item>
          <el-form-item label="What the extra costs were for">
            <el-input v-model="form.extra_costs_note" maxlength="255" placeholder="e.g. Sea freight and clearing" />
          </el-form-item>
        </el-form>
        <p class="receive__help">Freight and customs are added to the cost of the goods, in proportion to their value, so each size shows its true landed cost.</p>
      </admin-card>

      <admin-card title="What arrived" subtitle="Find each product, list what came in, and give each size's cost per unit">
        <el-select
          v-model="picked"
          class="receive__search"
          filterable
          remote
          reserve-keyword
          value-key="id"
          placeholder="Search a product by name to add it"
          :remote-method="search"
          :loading="searching"
          no-data-text="Type at least two letters"
          @change="addProduct"
        >
          <el-option v-for="p in results" :key="p.id" :label="p.name" :value="p" />
        </el-select>

        <admin-empty v-if="!blocks.length" title="No products yet" description="Search above and add each product that arrived on this delivery." icon="Box" />

        <section v-for="(block, b) in blocks" :key="block.product.id" class="block">
          <header class="block__head">
            <strong>{{ block.product.name }}</strong>
            <el-button link type="danger" @click="blocks.splice(b, 1)"><el-icon><IconDelete /></el-icon>Remove</el-button>
          </header>

          <div class="lines">
            <div class="lines__head"><span>Quantity</span><span>Colour <em>(if it applies)</em></span><span>Size <em>(if it applies)</em></span><span /></div>
            <div v-for="(line, i) in block.lines" :key="i" class="lines__row">
              <el-input-number v-model="line.quantity" :min="1" :max="1000000" controls-position="right" placeholder="Qty" />
              <el-select v-model="line.color" filterable clearable allow-create default-first-option placeholder="Colour">
                <el-option v-for="c in block.product.colors" :key="c" :label="c" :value="c" />
              </el-select>
              <el-select v-model="line.size" filterable clearable allow-create default-first-option placeholder="Size" @change="syncCosts(block)">
                <el-option v-for="s in block.product.sizes" :key="s" :label="s" :value="s" />
              </el-select>
              <el-button circle size="small" type="danger" plain :disabled="block.lines.length === 1" aria-label="Remove line" @click="removeLine(block, i)">
                <el-icon><IconDelete /></el-icon>
              </el-button>
            </div>
            <el-button size="small" @click="addLine(block)"><el-icon><IconPlus /></el-icon>Add another line</el-button>
          </div>

          <div class="costs">
            <h4 class="costs__title">Cost per unit, by size <span>(colours of a size share one cost)</span></h4>
            <div v-for="size in sizesOf(block)" :key="size" class="costs__row">
              <span class="costs__label">{{ size === '' ? 'No size' : 'Size ' + size }}</span>
              <el-input-number v-model="block.costs[size]" :min="0" :precision="2" :controls="false" placeholder="Unit cost ₦" />
              <span v-if="landedUnit(block, size)" class="costs__landed">
                landed {{ naira(landedUnit(block, size)) }} per unit
              </span>
              <span v-if="canSeeCosts && block.product.last_cost && block.product.last_cost[size]" class="costs__last">
                last time {{ naira(block.product.last_cost[size]) }}
              </span>
            </div>
          </div>
        </section>
      </admin-card>

      <admin-card v-if="blocks.length" title="Summary">
        <div class="sum">
          <div><span>Goods</span><strong>{{ naira(preview.items_total) }}</strong></div>
          <div><span>Freight, customs, handling</span><strong>{{ naira(preview.extra_costs) }}</strong></div>
          <div class="sum__total"><span>Landed total</span><strong>{{ naira(preview.landed_total) }}</strong></div>
          <p class="sum__note">
            {{ form.payment_account_id ? 'Paid now from the account you chose.' : 'Not paid yet: this is booked as money owed to ' + (form.supplier || 'the supplier') + '.' }}
            Stock goes up and inventory in the books goes up by the landed total.
          </p>
        </div>
        <template #footer>
          <el-button type="primary" size="large" :loading="saving" @click="submit">
            <el-icon><IconUpload /></el-icon>Record delivery
          </el-button>
        </template>
      </admin-card>
    </template>
  </div>
</template>

<script>
import { costingLive, findProducts, paymentAccounts, saveReceipt } from '@/api/costing';
import { landedPreview } from '@/utils/costSheet';
import { naira } from '@/utils/reportFormat';
import { can } from '@/utils/permission';

const today = () => {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};
const blankLine = () => ({ quantity: undefined, color: '', size: '' });
const sizeKey = (s) => String(s || '').trim();

export default {
  name: 'ReceiveStock',
  data() {
    return {
      stateLoading: true,
      live: false,
      accounts: [],
      form: { received_on: today(), supplier: '', invoice_number: '', payment_account_id: null, extra_costs: undefined, extra_costs_note: '' },
      blocks: [],
      picked: null,
      results: [],
      searching: false,
      saving: false,
    };
  },
  computed: {
    canSeeCosts: () => can('view cost'),
    canSetup: () => can('manage accounting'),
    // every line as the server wants it, with the cost of its size
    payloadLines() {
      const out = [];
      this.blocks.forEach(b => b.lines.forEach(l => out.push({
        item_id: b.product.id, size: sizeKey(l.size), color: (l.color || '').trim() || null, quantity: l.quantity, unit_cost: b.costs[sizeKey(l.size)],
      })));
      return out;
    },
    preview() {
      return landedPreview(this.payloadLines.map(l => ({ key: `${l.item_id}|${l.size}`, quantity: l.quantity, unit_cost: l.unit_cost })), this.form.extra_costs);
    },
  },
  created() {
    costingLive().then(live => {
      this.live = live;
      this.stateLoading = false;
      if (live) {
        paymentAccounts().then(a => {
          this.accounts = a;
        }).catch(() => {});
        // arriving from a product's "add stock" button
        if (this.$route.query.name) {
          this.search(String(this.$route.query.name)).then(() => {
            const match = this.results.find(p => String(p.id) === String(this.$route.query.item));
            if (match) {
              this.addProduct(match);
            }
          });
        }
      }
    });
  },
  methods: {
    naira,
    isFuture: (date) => date.getTime() > Date.now(),
    search(q) {
      if (!q || q.trim().length < 2) {
        this.results = [];
        return Promise.resolve();
      }
      this.searching = true;
      return findProducts(q.trim())
        .then(r => {
          this.results = r.products;
        })
        .catch(() => {})
        .finally(() => {
          this.searching = false;
        });
    },
    addProduct(product) {
      this.picked = null;
      if (!product) {
        return;
      }
      if (this.blocks.some(b => b.product.id === product.id)) {
        this.$message({ message: `${product.name} is already on this delivery`, type: 'warning' });
        return;
      }
      const block = { product, lines: [blankLine()], costs: {} };
      this.syncCosts(block);
      this.blocks.push(block);
    },
    addLine(block) {
      block.lines.push(blankLine());
    },
    removeLine(block, index) {
      block.lines.splice(index, 1);
      this.syncCosts(block);
    },
    sizesOf(block) {
      return [...new Set(block.lines.map(l => sizeKey(l.size)))];
    },
    // a cost box for each size in use, started from what that size cost last time (when you may see it)
    syncCosts(block) {
      this.sizesOf(block).forEach(size => {
        if (block.costs[size] === undefined) {
          const last = block.product.last_cost && block.product.last_cost[size];
          block.costs[size] = last || undefined;
        }
      });
    },
    landedUnit(block, size) {
      const entry = this.preview.by_key[`${block.product.id}|${size}`];
      return entry ? entry.unit : 0;
    },
    problem() {
      if (!this.form.received_on) return 'Choose the date received.';
      if (!this.form.supplier.trim()) return 'Enter the supplier.';
      if (!this.blocks.length) return 'Add at least one product.';
      for (const b of this.blocks) {
        if (b.lines.some(l => !l.quantity || l.quantity < 1)) return `Every line of ${b.product.name} needs a quantity.`;
        const missing = this.sizesOf(b).find(s => !(b.costs[s] > 0));
        if (missing !== undefined) return `Enter what each ${missing === '' ? b.product.name : b.product.name + ' size ' + missing} cost.`;
      }
      return '';
    },
    submit() {
      const problem = this.problem();
      if (problem) {
        this.$message({ message: problem, type: 'warning' });
        return;
      }
      this.saving = true;
      saveReceipt({
        ...this.form,
        invoice_number: this.form.invoice_number || null,
        extra_costs: this.form.extra_costs || 0,
        extra_costs_note: this.form.extra_costs_note || null,
        lines: this.payloadLines,
      })
        .then(r => {
          this.$message({ message: `${r.receipt.reference} recorded — ${naira(r.receipt.landed_total)} of stock received`, type: 'success', duration: 6000 });
          this.blocks = [];
          this.form = { received_on: today(), supplier: '', invoice_number: '', payment_account_id: null, extra_costs: undefined, extra_costs_note: '' };
        })
        .catch(() => {
          // the request helper has already shown the reason; nothing was saved
        })
        .finally(() => {
          this.saving = false;
        });
    },
  },
};
</script>

<style lang="scss" scoped>
.receive {
  &__form {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
    gap: 0 20px;

    :deep(.el-select),
    :deep(.el-date-editor),
    :deep(.el-input-number) {
      width: 100%;
    }
  }

  &__help {
    margin: 0;
    font-size: 12px;
    color: var(--admin-muted);
  }

  &__search {
    width: 100%;
    max-width: 520px;
    margin-bottom: 16px;
  }
}

.block {
  margin-top: 18px;
  padding: 16px 18px;
  border: 1px solid var(--admin-border);
  border-radius: 12px;

  &__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
    font-size: 15px;
  }
}

.lines {
  &__head,
  &__row {
    display: grid;
    grid-template-columns: 150px minmax(0, 1fr) minmax(0, 1fr) 36px;
    gap: 10px;
    align-items: center;
  }

  &__head {
    margin-bottom: 6px;
    font-size: 12px;
    font-weight: 600;
    color: var(--admin-muted);

    em { font-style: normal; font-weight: 400; }
  }

  &__row {
    margin-bottom: 8px;

    :deep(.el-input-number),
    :deep(.el-select) { width: 100%; }
  }
}

.costs {
  margin-top: 16px;
  padding-top: 14px;
  border-top: 1px dashed var(--admin-border);

  &__title {
    margin: 0 0 10px;
    font-size: 13px;

    span { font-weight: 400; color: var(--admin-muted); }
  }

  &__row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px 14px;
    margin-bottom: 8px;

    :deep(.el-input-number) { width: 160px; }
  }

  &__label {
    min-width: 80px;
    font-weight: 600;
  }

  &__landed { font-size: 12px; color: var(--admin-success); }
  &__last { font-size: 12px; color: var(--admin-muted); }
}

.sum {
  display: flex;
  flex-direction: column;
  gap: 6px;
  max-width: 420px;

  div {
    display: flex;
    justify-content: space-between;
    gap: 18px;
    font-size: 14px;
  }

  strong { font-variant-numeric: tabular-nums; }

  &__total {
    padding-top: 8px;
    border-top: 1px solid var(--admin-border);
    font-size: 17px !important;
  }

  &__note {
    margin: 8px 0 0;
    font-size: 12px;
    line-height: 1.5;
    color: var(--admin-muted);
  }
}

@media (max-width: 640px) {
  .lines__head { display: none; }

  .lines__row {
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    padding-bottom: 8px;
    border-bottom: 1px dashed var(--admin-border);
  }
}
</style>
