<template>
  <div class="stock-item">
    <admin-page-header title="Add stock" :subtitle="item.name">
      <el-button @click="$emit('cancel')">
        <el-icon><IconArrowLeft /></el-icon>
        Back to products
      </el-button>
      <el-button type="primary" :loading="saving" @click="submit">
        <el-icon><IconUpload /></el-icon>
        Add to stock
      </el-button>
    </admin-page-header>

    <admin-card title="New stock" subtitle="Add one line per colour / size you are receiving">
      <div class="lines">
        <div class="lines__head">
          <span>Quantity</span>
          <span>Colour <em>(if it applies)</em></span>
          <span>Size <em>(if it applies)</em></span>
          <span />
        </div>
        <div v-for="(line, index) in lines" :key="index" class="lines__row">
          <el-input-number v-model="line.quantity" :min="1" :max="1000000" controls-position="right" placeholder="Qty" />
          <div class="lines__color">
            <el-select v-model="line.color" filterable clearable placeholder="Choose colour" style="width: 100%">
              <el-option value="others" label="Other…" />
              <el-option v-for="(color, name) in colors" :key="name" :value="name" :label="name">
                <span class="swatch" :style="{ background: name }" />
                {{ name }}
              </el-option>
            </el-select>
            <el-input v-if="line.color === 'others'" v-model="line.other_color" placeholder="Type the colour" maxlength="60" />
          </div>
          <el-input v-model="line.size" placeholder="e.g. M, 42, Age 10" maxlength="60" />
          <el-tooltip content="Remove this line" placement="top">
            <el-button circle size="small" type="danger" plain :disabled="lines.length === 1" aria-label="Remove line" @click="removeLine(index)">
              <el-icon><IconDelete /></el-icon>
            </el-button>
          </el-tooltip>
        </div>
      </div>
      <el-button class="lines__add" @click="addLine">
        <el-icon><IconPlus /></el-icon>
        Add another line
      </el-button>
    </admin-card>

    <admin-card v-if="distinctSizes.length" title="Price by size" subtitle="Optional. A size's price is shared by every colour of that size; leave it blank to use the product's normal price.">
      <div class="sizes">
        <div v-for="size in distinctSizes" :key="size" class="sizes__row">
          <span class="sizes__label">{{ size }}</span>
          <el-input v-model="sizePrices[size]" placeholder="Uses the product price" inputmode="decimal">
            <template #prepend>₦</template>
          </el-input>
        </div>
      </div>
    </admin-card>

    <!-- after the form: it is reference material, and can be long -->
    <admin-card v-if="currentStock.length" title="Current stock" subtitle="What is on the shelf right now">
      <el-table :data="currentStock" size="small" max-height="340">
        <el-table-column label="Colour" prop="color" min-width="120">
          <template #default="{ row }">{{ row.color || '—' }}</template>
        </el-table-column>
        <el-table-column label="Size" prop="size" min-width="100">
          <template #default="{ row }">{{ row.size || '—' }}</template>
        </el-table-column>
        <el-table-column label="Stocked" prop="quantity_stocked" align="right" min-width="90" />
        <el-table-column label="Reserved" prop="reserved" align="right" min-width="90" />
        <el-table-column label="Sold" prop="sold" align="right" min-width="90" />
        <el-table-column label="Available" align="right" min-width="100">
          <template #default="{ row }">
            <strong>{{ row.quantity_stocked - row.reserved - row.sold }}</strong>
          </template>
        </el-table-column>
      </el-table>
    </admin-card>
  </div>
</template>

<script>
import { useAppStore } from '@/store';
import Resource from '@/api/resource';

const stockUpResource = new Resource('stock/general-items/stockup');
const blankLine = () => ({ quantity: undefined, color: '', other_color: '', size: '' });

export default {
  name: 'StockProduct',
  props: {
    item: { type: Object, required: true },
  },
  emits: ['saved', 'cancel'],
  data() {
    return {
      saving: false,
      lines: [blankLine()],
      sizePrices: {},
    };
  },
  computed: {
    // null until the params request finishes, and only rich for signed-in staff
    colors() {
      return (useAppStore().params && useAppStore().params.colors) || {};
    },
    currentStock() {
      return this.item.item_stocks || [];
    },
    distinctSizes() {
      const sizes = this.lines.map(line => (line.size || '').trim()).filter(Boolean);
      return [...new Set(sizes)];
    },
  },
  created() {
    // start the price-by-size inputs from the prices already saved
    (this.item.size_prices || []).forEach(sizePrice => {
      this.sizePrices[sizePrice.size] = sizePrice.amount;
    });
  },
  methods: {
    addLine() {
      if (this.lines.some(line => !line.quantity)) {
        this.$message({ message: 'Enter a quantity on the current line first', type: 'warning' });
        return;
      }
      this.lines.push(blankLine());
    },
    removeLine(index) {
      if (this.lines.length > 1) {
        this.lines.splice(index, 1);
      }
    },
    submit() {
      if (this.lines.some(line => !line.quantity)) {
        this.$message({ message: 'Every line needs a quantity', type: 'warning' });
        return;
      }
      if (this.lines.some(line => line.color === 'others' && !line.other_color.trim())) {
        this.$message({ message: 'Type the colour for the "Other" lines', type: 'warning' });
        return;
      }
      const payload = {
        sub_batches: this.lines.map(line => ({
          quantity: line.quantity,
          color: line.color || null,
          other_color: line.other_color,
          size: (line.size || '').trim() || null,
        })),
        size_prices: this.distinctSizes
          .filter(size => this.sizePrices[size] !== '' && this.sizePrices[size] !== undefined && this.sizePrices[size] !== null)
          .map(size => ({ size, amount: this.sizePrices[size] })),
      };
      this.saving = true;
      stockUpResource.update(this.item.id, payload)
        .then(() => {
          this.$message({ message: 'Stock added', type: 'success' });
          this.$emit('saved');
        })
        .catch(() => {
          // the shared axios interceptor has already shown the reason
        })
        .finally(() => {
          this.saving = false;
        });
    },
  },
};
</script>

<style lang="scss" scoped>
.lines {
  &__head,
  &__row {
    display: grid;
    grid-template-columns: 170px minmax(0, 1.2fr) minmax(0, 1fr) 36px;
    gap: 12px;
    align-items: start;
  }

  &__head {
    margin-bottom: 8px;
    font-size: 12px;
    font-weight: 600;
    color: var(--admin-muted);

    em { font-style: normal; font-weight: 400; }
  }

  &__row {
    margin-bottom: 12px;

    :deep(.el-input-number) { width: 100%; }
  }

  &__color {
    display: flex;
    flex-direction: column;
    gap: 8px;
  }

  &__add { margin-top: 4px; }
}

.swatch {
  display: inline-block;
  width: 14px;
  height: 14px;
  margin-right: 8px;
  border-radius: 4px;
  border: 1px solid var(--admin-border);
  vertical-align: -2px;
}

.sizes {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 14px;

  &__row {
    display: flex;
    align-items: center;
    gap: 12px;
  }

  &__label {
    flex: none;
    min-width: 54px;
    font-weight: 650;
  }
}

@media (max-width: 760px) {
  .lines__head { display: none; }
  .lines__row {
    grid-template-columns: minmax(0, 1fr) 36px;
    padding: 14px;
    border: 1px solid var(--admin-border);
    border-radius: 12px;

    > :nth-child(1),
    > :nth-child(2),
    > :nth-child(3) { grid-column: 1; }
    > :nth-child(4) { grid-column: 2; grid-row: 1; }
  }
}
</style>
