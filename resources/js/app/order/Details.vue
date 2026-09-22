<template>
  <div v-if="order && order.customer" class="order-details">
    <!-- summary -->
    <section class="od-card od-summary">
      <div class="od-summary__main">
        <img src="/images/logo.png" alt="DayLiz Stores" class="od-logo">
        <p class="od-eyebrow">Order</p>
        <h2 class="od-number">{{ order.order_number }}</h2>
        <p class="od-muted">Placed {{ moment(order.created_at).format('dddd, D MMMM YYYY [at] h:mm a') }}</p>
      </div>
      <div class="od-summary__tags">
        <admin-status-tag :status="orderStatus" kind="order" />
        <admin-status-tag :status="paymentStatus" kind="payment" :label="paymentLabel" />
      </div>
      <div class="od-summary__total">
        <span>Total</span>
        <strong>{{ currency }}{{ formatNumber(grandTotal, 2) }}</strong>
      </div>
    </section>

    <!-- progress -->
    <el-alert
      v-if="orderStatus === 'Cancelled'"
      type="error"
      :closable="false"
      show-icon
      title="This order was cancelled"
      class="od-alert"
    />
    <el-steps v-else :active="stepsDone" finish-status="success" align-center class="od-steps no-print">
      <el-step title="Order placed" :description="moment(order.created_at).format('D MMM YYYY')" />
      <el-step title="Payment confirmed" :description="paymentStatus === 'paid' ? 'Received' : 'Awaiting payment'" />
      <el-step title="Delivered" :description="orderStatus === 'Delivered' ? moment(order.updated_at).format('D MMM YYYY') : 'Not yet'" />
    </el-steps>

    <div class="od-grid">
      <section class="od-card">
        <h3 class="od-card__title">Customer</h3>
        <dl class="od-list">
          <div><dt>Name</dt><dd>{{ order.customer.name }}</dd></div>
          <div><dt>Email</dt><dd>{{ order.customer.email }}</dd></div>
          <div><dt>Phone</dt><dd>{{ order.customer.phone }}</dd></div>
          <div v-if="order.nearest_bustop"><dt>Nearest bus stop</dt><dd>{{ order.nearest_bustop }}</dd></div>
          <div v-if="order.address"><dt>Address</dt><dd>{{ order.address }}</dd></div>
        </dl>
      </section>

      <section class="od-card">
        <h3 class="od-card__title">Delivery &amp; payment</h3>
        <dl class="od-list">
          <div><dt>Pickup / delivery area</dt><dd>{{ order.location || '—' }}</dd></div>
          <div><dt>Payment method</dt><dd>{{ paymentMethodLabel }}</dd></div>
          <div><dt>Extra note</dt><dd>{{ order.notes || '—' }}</dd></div>
        </dl>
      </section>

      <section class="od-card od-receipt no-print">
        <h3 class="od-card__title">Payment receipt</h3>
        <el-image
          v-if="order.receipt_image"
          :src="order.receipt_image"
          :preview-src-list="[order.receipt_image]"
          :preview-teleported="true"
          fit="cover"
          class="od-receipt__image"
        >
          <template #error>
            <div class="od-receipt__empty">Receipt could not be loaded</div>
          </template>
        </el-image>
        <div v-else class="od-receipt__empty">
          <el-icon><IconPicture /></el-icon>
          No receipt uploaded
        </div>
        <p v-if="order.receipt_image" class="od-muted od-receipt__hint">Click the image to enlarge</p>
      </section>
    </div>

    <!-- items -->
    <section class="od-card od-items">
      <h3 class="od-card__title">Items ({{ orderItems.length }})</h3>
      <div class="od-table-wrap">
        <table class="od-table">
          <thead>
            <tr>
              <th>Product</th>
              <th class="num">Qty</th>
              <th class="num">Rate</th>
              <th class="num">Subtotal</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(item, index) in orderItems" :key="item.id || index">
              <td class="od-table__product">{{ item.product_name }}</td>
              <td class="num">{{ item.quantity }}</td>
              <td class="num">{{ currency }}{{ formatNumber(item.price, 2) }}</td>
              <td class="num">{{ currency }}{{ formatNumber(item.total, 2) }}</td>
            </tr>
          </tbody>
          <tfoot>
            <tr class="od-table__grand">
              <td colspan="3">Grand total</td>
              <td class="num">{{ currency }}{{ formatNumber(grandTotal, 2) }}</td>
            </tr>
            <tr v-if="totalInWords">
              <td colspan="4" class="od-table__words">In words: {{ totalInWords }}</td>
            </tr>
          </tfoot>
        </table>
      </div>
    </section>

    <!-- staff actions -->
    <section v-if="canUpdate && (canMarkPaid || canDeliver || canCancel)" class="od-card od-actions no-print">
      <h3 class="od-card__title">Manage this order</h3>
      <div class="od-actions__buttons">
        <el-button v-if="canMarkPaid" type="success" :loading="busy === 'paid'" :disabled="!!busy" @click="markPaid">
          <el-icon><IconMoney /></el-icon>
          Mark as paid
        </el-button>
        <el-button v-if="canDeliver" type="primary" :loading="busy === 'delivered'" :disabled="!!busy" @click="markDelivered">
          <el-icon><IconVan /></el-icon>
          Mark as delivered
        </el-button>
        <el-button v-if="canCancel" type="danger" plain :loading="busy === 'cancelled'" :disabled="!!busy" @click="cancelOrder">
          <el-icon><IconCircleClose /></el-icon>
          Cancel order
        </el-button>
      </div>
      <p class="od-hint">
        <template v-if="canMarkPaid">Only mark an order as paid once the customer's payment has been received in full. </template>
        <template v-if="canDeliver">Delivering releases the reserved stock as sold. </template>
        <template v-if="canCancel">Cancelling returns the reserved stock to the shelf.</template>
      </p>
    </section>

    <!-- customer: rate delivered products -->
    <section v-if="!canUpdate && orderStatus === 'Delivered'" class="od-card od-reviews no-print">
      <h3 class="od-card__title">Rate your purchase</h3>
      <p class="od-muted">Tell other customers what you thought.</p>
      <div v-for="item in reviewable" :key="item.item_id" class="od-review">
        <div class="od-review__name">{{ item.product_name }}</div>
        <el-rate v-model="reviews[item.item_id].star" />
        <el-input
          v-model="reviews[item.item_id].comment"
          type="textarea"
          :rows="2"
          maxlength="1000"
          placeholder="Write a short review (optional)"
        />
        <el-button
          size="small"
          type="primary"
          :loading="reviews[item.item_id].saving"
          :disabled="!reviews[item.item_id].star && !reviews[item.item_id].comment"
          @click="saveReview(item)"
        >
          {{ reviews[item.item_id].saved ? 'Saved' : 'Submit review' }}
        </el-button>
      </div>
    </section>
  </div>
</template>

<script>
import moment from 'moment';
import { ElMessageBox } from 'element-plus';
import { formatNumber, roundMoney } from '@/utils/index';
import { amountInWords } from '@/utils/amountInWords';
import Resource from '@/api/resource';

const changeStatusResource = new Resource('order/general/change-status');
const reviewResource = new Resource('give-product-review');

export default {
  props: {
    order: {
      type: Object,
      default: () => ({}),
    },
    page: {
      type: Object,
      default: () => ({
        option: 'order_details',
      }),
    },
    canUpdate: {
      type: Boolean,
      default: false,
    },
  },
  emits: ['updated'],
  data() {
    return {
      currency: '₦',
      // the statuses shown — kept locally so a change is reflected without mutating the prop
      orderStatus: '',
      paymentStatus: '',
      busy: '',
      reviews: {},
    };
  },
  computed: {
    orderItems() {
      return this.order.order_items || [];
    },
    // The API sends decimal columns as strings ("12500.00"), so each item
    // total is converted before adding — `total += item.total` concatenated
    // them ("012500.003400.00...") and showed only the first item's amount.
    grandTotal() {
      return roundMoney(this.orderItems.reduce((sum, item) => sum + (parseFloat(item.total) || 0), 0));
    },
    totalInWords() {
      const words = amountInWords(this.grandTotal);
      return words ? words.toUpperCase() : '';
    },
    stepsDone() {
      // placed is always done; then payment; then delivery
      return 1 + (this.paymentStatus === 'paid' ? 1 : 0) + (this.orderStatus === 'Delivered' ? 1 : 0);
    },
    paymentLabel() {
      return this.paymentStatus === 'paid' ? 'Paid' : this.paymentStatus === 'pending' ? 'Payment pending' : '';
    },
    paymentMethodLabel() {
      const method = String(this.order.payment_method || '').toLowerCase();
      if (!method) {
        return 'Bank transfer';
      }
      return method === 'paystack' ? 'Online (Paystack)' : method.charAt(0).toUpperCase() + method.slice(1);
    },
    open() {
      return this.orderStatus !== 'Cancelled' && this.orderStatus !== 'Delivered';
    },
    canMarkPaid() {
      return this.orderStatus !== 'Cancelled' && (this.paymentStatus === 'pending' || this.paymentStatus === 'carp');
    },
    canDeliver() {
      return this.paymentStatus === 'paid' && (this.orderStatus === 'Pending' || this.orderStatus === 'CARP' || this.orderStatus === 'On Transit');
    },
    canCancel() {
      return this.open;
    },
    reviewable() {
      return this.orderItems.filter(item => item.item_id);
    },
  },
  watch: {
    order: {
      immediate: true,
      handler(order) {
        this.orderStatus = order.order_status || '';
        this.paymentStatus = order.payment_status || '';
        const reviews = {};
        (order.order_items || []).forEach(item => {
          reviews[item.item_id] = { star: item.star || 0, comment: item.comment || '', saving: false, saved: false };
        });
        this.reviews = reviews;
      },
    },
  },
  methods: {
    moment,
    formatNumber,
    // one place that asks first, sends the change, then tells the parent
    async change(key, payload, confirm) {
      try {
        await ElMessageBox.confirm(confirm.message, confirm.title, {
          confirmButtonText: confirm.button,
          cancelButtonText: 'Keep as is',
          type: confirm.type || 'warning',
        });
      } catch (cancelled) {
        return;
      }
      this.busy = key;
      const body = {
        status: payload.status || this.orderStatus,
        payment_status: payload.payment_status || this.paymentStatus,
      };
      try {
        await changeStatusResource.update(this.order.id, body);
        // the server maps "On Transit" to "Delivered"
        this.orderStatus = body.status === 'On Transit' ? 'Delivered' : body.status;
        this.paymentStatus = body.payment_status;
        this.$emit('updated', { id: this.order.id, order_status: this.orderStatus, payment_status: this.paymentStatus });
        this.$message({ message: 'Order updated', type: 'success' });
      } catch (error) {
        // the shared axios interceptor has already shown the reason
      } finally {
        this.busy = '';
      }
    },
    markPaid() {
      return this.change('paid', { payment_status: 'paid' }, {
        title: 'Mark as paid?',
        message: `Confirm that payment for ${this.order.order_number} has been received in full.`,
        button: 'Yes, mark as paid',
      });
    },
    markDelivered() {
      return this.change('delivered', { status: 'Delivered' }, {
        title: 'Mark as delivered?',
        message: `${this.order.order_number} will be marked delivered and its reserved stock counted as sold.`,
        button: 'Yes, delivered',
        type: 'info',
      });
    },
    cancelOrder() {
      return this.change('cancelled', { status: 'Cancelled' }, {
        title: 'Cancel this order?',
        message: `${this.order.order_number} will be cancelled and its reserved stock returned to the shelf.`,
        button: 'Yes, cancel order',
      });
    },
    async saveReview(item) {
      const review = this.reviews[item.item_id];
      review.saving = true;
      try {
        // the API stores one field per call
        if (review.star) {
          await reviewResource.store({ user_id: this.order.user_id, item_id: item.item_id, field: 'star', value: review.star });
        }
        if (review.comment) {
          await reviewResource.store({ user_id: this.order.user_id, item_id: item.item_id, field: 'comment', value: review.comment });
        }
        review.saved = true;
        this.$message({ message: 'Thank you for your feedback', type: 'success' });
      } catch (error) {
        // the shared axios interceptor has already shown the reason
      } finally {
        review.saving = false;
      }
    },
  },
};
</script>

<style lang="scss" scoped>
.order-details {
  color: var(--admin-text, #1c2340);
  font-size: 14px;
}

.od-card {
  margin-bottom: 20px;
  padding: 22px;
  background: var(--admin-surface, #fff);
  border: 1px solid var(--admin-border, #e6e9f2);
  border-radius: var(--admin-radius, 14px);
  box-shadow: var(--admin-shadow, 0 1px 2px rgba(20, 28, 74, 0.04), 0 6px 20px rgba(20, 28, 74, 0.05));

  &__title {
    margin: 0 0 14px;
    font-size: 15px;
    font-weight: 700;
  }
}

.od-muted {
  margin: 0;
  font-size: 13px;
  color: var(--admin-muted, #6b7394);
}

.od-summary {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  justify-content: space-between;
  gap: 16px 32px;

  &__tags {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
  }

  &__total {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 2px;
    text-align: right;

    span { font-size: 12px; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase; color: var(--admin-muted, #6b7394); }
    strong { font-size: 28px; font-weight: 700; letter-spacing: -0.02em; font-variant-numeric: tabular-nums; }
  }
}

.od-logo {
  display: block;
  height: 42px;
  width: auto;
  margin-bottom: 10px;
}

.od-eyebrow {
  margin: 0 0 2px;
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.06em;
  text-transform: uppercase;
  color: var(--admin-muted, #6b7394);
}

.od-number {
  margin: 0 0 4px;
  font-size: 26px;
  font-weight: 700;
  letter-spacing: -0.01em;
}

.od-alert,
.od-steps {
  margin-bottom: 20px;
}

.od-steps {
  padding: 22px 12px 18px;
  background: var(--admin-surface, #fff);
  border: 1px solid var(--admin-border, #e6e9f2);
  border-radius: var(--admin-radius, 14px);
}

.od-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 20px;

  .od-card { margin-bottom: 20px; }
}

.od-list {
  margin: 0;

  > div {
    display: flex;
    flex-direction: column;
    gap: 2px;
    padding: 9px 0;
    border-bottom: 1px solid var(--admin-border, #e6e9f2);

    &:first-child { padding-top: 0; }
    &:last-child { padding-bottom: 0; border-bottom: 0; }
  }

  dt {
    font-size: 12px;
    font-weight: 600;
    color: var(--admin-muted, #6b7394);
  }

  dd {
    margin: 0;
    font-weight: 500;
    overflow-wrap: anywhere;
  }
}

.od-receipt {
  &__image {
    width: 100%;
    height: 170px;
    border-radius: 10px;
    border: 1px solid var(--admin-border, #e6e9f2);
    cursor: zoom-in;
  }

  &__empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 6px;
    height: 170px;
    border: 1px dashed var(--admin-border-strong, #d5daea);
    border-radius: 10px;
    color: var(--admin-muted, #6b7394);
    font-size: 13px;

    .el-icon { font-size: 26px; }
  }

  &__hint { margin-top: 8px; }
}

.od-table-wrap {
  overflow-x: auto;
}

.od-table {
  width: 100%;
  border-collapse: collapse;

  th, td {
    padding: 12px 14px;
    text-align: left;
    border-bottom: 1px solid var(--admin-border, #e6e9f2);
  }

  th {
    font-size: 12px;
    font-weight: 650;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: var(--admin-muted, #6b7394);
    background: var(--admin-surface-soft, #f8f9fd);
  }

  .num {
    text-align: right;
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
  }

  &__product { font-weight: 600; }

  tfoot td { border-bottom: 0; }

  &__grand td {
    font-size: 16px;
    font-weight: 700;
    padding-top: 16px;

    &:first-child { text-align: right; }
  }

  &__words {
    text-align: right;
    font-size: 12.5px;
    letter-spacing: 0.02em;
    color: var(--admin-muted, #6b7394);
  }
}

.od-actions {
  &__buttons {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
  }
}

.od-hint {
  margin: 14px 0 0;
  font-size: 13px;
  line-height: 1.5;
  color: var(--admin-muted, #6b7394);
}

.od-review {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 8px;
  padding: 16px 0;
  border-top: 1px solid var(--admin-border, #e6e9f2);

  &__name { font-weight: 650; }
}

@media (max-width: 991px) {
  .od-grid { grid-template-columns: minmax(0, 1fr); }
  .od-summary__total { align-items: flex-start; text-align: left; }
}

// A printed order should read as a compact, single-page slip for a normal-sized order — screen spacing,
// font sizes and the (screen-only, click-to-zoom) receipt photo all get dropped or shrunk for that. The
// items table is the one part allowed to spill onto further A4 pages, for an order with many lines: its
// header repeats on each page and nothing — a row, the grand total, the amount in words — is ever split
// across a page break.
@media print {
  @page {
    size: A4;
    margin: 12mm;
  }

  .order-details {
    font-size: 11px;
    color: #000;
  }

  .no-print {
    display: none !important;
  }

  .od-card {
    margin-bottom: 10px;
    padding: 10px 14px;
    border: 1px solid #ccc;
    box-shadow: none;
    break-inside: avoid;
    page-break-inside: avoid;

    &__title {
      margin-bottom: 6px;
      font-size: 12px;
    }
  }

  .od-logo { height: 34px; margin-bottom: 6px; }
  .od-eyebrow { font-size: 10px; }
  .od-number { font-size: 20px; }
  .od-muted { font-size: 11px; }

  .od-summary {
    gap: 8px 24px;

    &__total {
      span { font-size: 10px; }
      strong { font-size: 18px; }
    }
  }

  .od-alert { margin-bottom: 10px; }

  .od-grid {
    // the receipt photo (od-receipt) is dropped on print, so what's left reads better as two columns
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;

    .od-card { margin-bottom: 10px; }
  }

  .od-list > div {
    padding: 4px 0;
  }

  .od-table {
    th, td { padding: 5px 8px; font-size: 10.5px; }

    thead { display: table-header-group; } // repeats on every page the table spills onto
    tfoot { display: table-row-group; } // prints once, after the last item — not repeated per page

    tbody tr,
    tfoot tr {
      break-inside: avoid;
      page-break-inside: avoid;
    }
  }

  .od-table__grand td { padding-top: 8px; }
}
</style>
