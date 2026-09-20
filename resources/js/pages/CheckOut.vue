<template>
  <div v-loading="loading" class="checkout">
    <div v-if="params && params.can_make_order" class="checkout__container">
      <!--
        Order confirmation. Shown in place of the form (rather than in a dialog
        followed by a redirect, which tore the dialog down before anyone could
        read it) so the customer always ends up seeing their order number.
      -->
      <div v-if="orderPlaced" class="checkout__confirmation">
        <BaseCard class="checkout__card checkout__receipt">
          <div class="checkout__receipt-banner">Thank you. We have received your order.</div>
          <h1 class="checkout__title">Order placed successfully</h1>
          <p class="checkout__hint">Keep your order number &mdash; you'll need it, with the email or phone you used here, to track this order.</p>
          <dl class="checkout__receipt-list">
            <div class="checkout__receipt-row">
              <dt>Order Number</dt>
              <dd class="checkout__receipt-number">{{ orderDetails.order_number }}</dd>
            </div>
            <div class="checkout__receipt-row">
              <dt>Order Date</dt>
              <dd>{{ moment(orderDetails.created_at).format('ll') }}</dd>
            </div>
            <div class="checkout__receipt-row">
              <dt>Total Amount</dt>
              <dd>&#8358;{{ formatNumber(orderDetails.total, 2) }}</dd>
            </div>
            <div class="checkout__receipt-row">
              <dt>Payment Method</dt>
              <dd>{{ orderDetails.payment_method }}</dd>
            </div>
          </dl>
          <p class="checkout__hint">We'll confirm your payment and start preparing your order. Please quote your Order Number as the payment reference and when contacting us.</p>
          <el-alert v-if="params.pickup_warning" type="warning" :closable="false" show-icon>{{ params.pickup_warning }}</el-alert>
          <div class="checkout__confirmation-actions">
            <BaseButton variant="primary" @click="trackPlacedOrder">Track this order</BaseButton>
            <BaseButton variant="secondary" @click="$router.push({ path: '/product/list' })">Continue shopping</BaseButton>
          </div>
        </BaseCard>
      </div>

      <div v-else-if="pendingOrder.cart_items.length > 0" class="checkout__layout">
        <div class="checkout__main">
          <h1 class="checkout__title">Checkout</h1>
          <p v-if="userData.id === null" class="checkout__login-note">
            Returning customer?
            <router-link :to="{ path: '/login?redirect=/product/check-out' }">Log in</router-link>
          </p>

          <BaseCard class="checkout__card">
            <h2 class="checkout__card-title">Contact Information</h2>
            <p v-if="userData.id !== null" class="checkout__card-note">Continue as {{ userData.name }}</p>
            <div class="checkout__fields">
              <el-input v-model="checkOutForm.name" name="name" placeholder="Full Name *" :disabled="userData.id !== null" />
              <el-input v-model="checkOutForm.email" name="email" type="email" placeholder="Email *" :disabled="userData.id !== null" />
              <el-input v-model="checkOutForm.phone" name="phone" type="number" placeholder="Phone *" :disabled="userData.id !== null" />
            </div>
          </BaseCard>

          <BaseCard class="checkout__card">
            <h2 class="checkout__card-title">Shipping Address</h2>
            <div class="checkout__fields">
              <el-input
                v-model="checkOutForm.address"
                name="address"
                type="textarea"
                :rows="2"
                placeholder="Address (House No. and street name) *"
              />
              <el-input v-model="checkOutForm.nearest_bustop" name="nearest_bustop" placeholder="Nearest Bustop" />
              <el-cascader
                v-model="checkOutForm.location"
                placeholder="Pick Delivery Location"
                :options="options"
                :props="{ expandTrigger: 'hover' }"
                filterable
                :filter-method="customFilter"
                style="width: 100%"
              />
              <p class="checkout__hint">Delivery fees are dependent on your location and will be confirmed separately.</p>
              <el-input
                v-model="checkOutForm.notes"
                name="notes"
                type="textarea"
                :rows="2"
                placeholder="Extra note for this order (optional)"
              />
            </div>
          </BaseCard>
        </div>

        <div class="checkout__aside">
          <BaseCard class="checkout__card">
            <div class="checkout__summary-head">
              <h2 class="checkout__card-title">Order Summary ({{ pendingOrder.cart_items.length }})</h2>
              <a href="#" class="checkout__edit-cart" @click.prevent="editCart">Edit Cart</a>
            </div>
            <ul class="checkout__items">
              <li v-for="(item, index) in pendingOrder.cart_items" :key="index" class="checkout__item" :class="{ 'checkout__item--flagged': stockIssues[item.stock_id] }">
                <img
                  :src="item.media.length > 0 ? item.media[0].link : '/images/no-image.jpeg'"
                  class="checkout__item-image"
                  loading="lazy"
                  :alt="item.name"
                  @error="onImageError"
                >
                <div class="checkout__item-info">
                  <p class="checkout__item-name">{{ item.name }}</p>
                  <p class="checkout__item-meta">{{ item.quantity }} pieces &commat; &#8358;{{ formatNumber(item.rate, 2) }}</p>
                  <p v-if="stockIssues[item.stock_id]" class="checkout__item-flag">
                    {{ stockIssues[item.stock_id].balance > 0 ? `Only ${stockIssues[item.stock_id].balance} left in stock` : 'No longer available' }}
                  </p>
                </div>
                <p class="checkout__item-total">&#8358;{{ formatNumber(parseFloat(item.rate * item.quantity), 2) }}</p>
              </li>
            </ul>
            <el-alert v-if="hasStockIssues" type="error" :closable="false">Some items in your order are no longer available in the quantity requested. Go back to your cart to remove or adjust them.</el-alert>
            <div class="checkout__total-row checkout__total-row--grand">
              <span>Total</span>
              <span>&#8358;{{ formatNumber(pendingOrder.amount, 2) }}</span>
            </div>
            <p class="checkout__refund-note">We operate a NO REFUND policy once payment has been confirmed.</p>
          </BaseCard>

          <BaseCard v-if="params" class="checkout__card">
            <h2 class="checkout__card-title">Payment</h2>
            <!-- Shown before submitting (its text comes from the pickup_warning setting) -->
            <el-alert v-if="params.pickup_warning" type="warning" :closable="false" show-icon class="checkout__pickup-warning">{{ params.pickup_warning }}</el-alert>
            <div v-if="params.online_payment_enabled" class="checkout__payment-toggle">
              <button
                type="button"
                class="checkout__payment-tab"
                :class="{ 'checkout__payment-tab--active': paymentMethod === 'paystack' }"
                @click="paymentMethod = 'paystack'"
              >
                Pay with Card (Paystack)
              </button>
              <button
                type="button"
                class="checkout__payment-tab"
                :class="{ 'checkout__payment-tab--active': paymentMethod === 'bank_transfer' }"
                @click="paymentMethod = 'bank_transfer'"
              >
                Bank Transfer
              </button>
            </div>

            <div v-if="paymentMethod === 'bank_transfer' || !params.online_payment_enabled">
              <p class="checkout__hint">Make your payment directly into any of our bank accounts below. Your order will not ship until payment is confirmed.</p>
              <div class="checkout__account-details" v-html="params.account_details" />

              <label class="checkout__upload-label">
                Upload Payment Receipt <span class="checkout__upload-hint">(image format only &mdash; jpg or png)</span>
              </label>
              <input type="file" class="checkout__upload-input" @change="onImageChange">

              <div v-if="imageToBeUploaded !== null" class="checkout__submit-block">
                <label class="checkout__terms">
                  <el-checkbox v-model="termsAgreed" />
                  <span>I have read and agree to the website <a class="checkout__terms-link" @click="showTermsAndConditions = true">Terms and Conditions</a></span>
                </label>
                <BaseButton variant="accent" class="checkout__submit-btn" :disabled="hasStockIssues || loading" :loading="loading" @click="submitOrder">Submit Order</BaseButton>
              </div>
            </div>

            <div v-else-if="params.online_payment_enabled" class="checkout__submit-block">
              <p class="checkout__hint">You'll be taken to Paystack's secure payment page to complete your card or bank payment. Your order ships as soon as payment is confirmed &mdash; no receipt upload needed.</p>
              <label class="checkout__terms">
                <el-checkbox v-model="termsAgreed" />
                <span>I have read and agree to the website <a class="checkout__terms-link" @click="showTermsAndConditions = true">Terms and Conditions</a></span>
              </label>
              <BaseButton variant="accent" class="checkout__submit-btn" :disabled="hasStockIssues || paystackLoading" :loading="paystackLoading" @click="payWithPaystack">
                Pay &#8358;{{ formatNumber(pendingOrder.amount, 2) }} with Paystack
              </BaseButton>
            </div>
          </BaseCard>
        </div>
      </div>

      <div v-else class="checkout__empty">
        <h2 class="checkout__title">You do not have any item in your cart</h2>
        <BaseButton variant="primary" @click="$router.push({ path: '/product/list' })">Shop Now</BaseButton>
      </div>

      <el-dialog v-model="showTermsAndConditions" title="Terms and Conditions">
        <div v-if="params">
          <el-input v-model="params.terms_and_conditions" type="textarea" readonly resize="vertical" :rows="20" />
        </div>
      </el-dialog>

    </div>

    <div v-else-if="params" class="checkout__closed">
      <img src="/images/lock.png" alt="">
      <h2>Sorry! We are not receiving orders for now. Please check back later.</h2>
    </div>
  </div>
</template>
<script>
import moment from 'moment';
import { formatNumber, onImageError, createUniqueString } from '@/utils/index';
import Resource from '@/api/resource';
import BaseCard from '@/components/ui/BaseCard.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import { useAppStore, useOrderStore, useUserStore } from '@/store';

const MAX_RECEIPT_BYTES = 5 * 1024 * 1024; // mirrors the server's 5MB limit
const RECEIPT_TYPES = ['image/jpeg', 'image/jpg', 'image/png'];
const HTML_ESCAPES = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' };

// Product names in the stock-shortage message come back from the server, so
// they're escaped before going into the (HTML-enabled) message box.
function escapeHtml(text) {
  return String(text).replace(/[&<>"']/g, ch => HTML_ESCAPES[ch]);
}

export default {
  name: 'CheckOut',
  components: {
    BaseCard,
    BaseButton,
  },
  // Provided by the storefront layout (Public.vue) so "Edit Cart" can open
  // the cart panel from here.
  inject: {
    openPanel: { default: null },
  },
  data() {
    return {
      showTermsAndConditions: false,
      orderPlaced: false,
      orderDetails: { order_number: '', created_at: '', total: '', payment_method: '' },
      placedEmail: '',
      termsAgreed: false,
      loading: false,
      // Bank transfer is the only guaranteed-available method until online
      // payments are re-enabled in settings — see params.online_payment_enabled.
      paymentMethod: 'bank_transfer',
      paystackLoading: false,
      checkOutForm: {
        order_uniq_id: '',
        name: '',
        phone: '',
        email: '',
        nearest_bustop: '',
        address: '',
        notes: '',
        location: '',
      },
      options: [{
        value: 'Local Pickup',
        label: 'Local Pickup',
      }, {
        value: 'Lagos',
        label: 'Lagos Delivery',
      }, {
        value: 'Other States',
        label: 'Other States',
        children: [], // filled from params.states once the settings have loaded
      }],
      imageToBeUploaded: null,
    };
  },
  computed: {
    appStore() {
      return useAppStore();
    },
    orderStore() {
      return useOrderStore();
    },
    userStore() {
      return useUserStore();
    },
    pendingOrder() {
      return this.orderStore.pendingOrder;
    },
    userData() {
      return this.userStore.userData;
    },
    params() {
      return this.appStore.params;
    },
    stockIssues() {
      return this.orderStore.stockIssues;
    },
    hasStockIssues() {
      return Object.keys(this.stockIssues).length > 0;
    },
  },
  watch: {
    // The state list depends on the settings, which load asynchronously —
    // populate it whenever they arrive instead of on a fixed timer (which
    // threw when the settings were slower than the timer, and never ran again).
    'params.states': {
      immediate: true,
      handler(states) {
        if (states) {
          this.setStates();
        }
      },
    },
  },
  created() {
    this.appStore.setNecessaryParams();
    // Wait for the saved cart to actually be restored before re-checking its
    // stock (loadOfflineData resolves once IndexedDB has been read).
    this.orderStore.loadOfflineData().then(() => this.orderStore.validateCart());
    this.setForm();
  },
  methods: {
    moment,
    formatNumber,
    onImageError,
    createUniqueString,
    editCart() {
      if (this.openPanel) {
        this.openPanel('cart');
      }
    },
    onImageChange(e) {
      const app = this;
      app.imageToBeUploaded = null;
      const file = e.target.files && e.target.files[0];
      if (!file) {
        return; // the file dialog was cancelled
      }
      if (!RECEIPT_TYPES.includes(file.type)) {
        e.target.value = '';
        app.$alert('Only images are accepted. Upload a JPG or PNG file.');
        return;
      }
      if (file.size > MAX_RECEIPT_BYTES) {
        e.target.value = '';
        app.$alert('That image is too large. Please upload one under 5MB.');
        return;
      }
      app.imageToBeUploaded = file;
    },
    setForm() {
      const app = this;
      app.checkOutForm.order_uniq_id = app.createUniqueString();
      app.checkOutForm.name = app.userData.name;
      app.checkOutForm.email = app.userData.email;
      app.checkOutForm.phone = app.userData.phone;
      app.checkOutForm.address = app.userData.address;
      app.checkOutForm.nearest_bustop = app.userData.nearest_bustop;
    },
    setStates() {
      const states = (this.params && this.params.states) || [];
      this.options[2].children = states
        .filter(state => state !== 'Lagos')
        .map(state => ({ value: state, label: state }));
    },
    customFilter(node, keyword) {
      return (node.text.toLowerCase().indexOf(keyword.toLowerCase()) > -1);
    },
    // Only what the server acts on: which stock row, and how many. It
    // re-derives the item, name and price itself.
    cartPayload() {
      return this.pendingOrder.cart_items.map(line => ({
        id: line.id,
        stock_id: line.stock_id,
        quantity: line.quantity,
        name: line.name,
      }));
    },
    // Checks shared by both payment paths. Returns true when it's OK to submit.
    validateCheckout() {
      const app = this;
      const param = app.checkOutForm;
      if (app.hasStockIssues) {
        app.$alert('Some items in your order are no longer available in the quantity requested. Use "Edit Cart" to remove or adjust them.');
        return false;
      }
      if (!param.name || !param.email || !param.phone || !param.address) {
        app.$alert('Kindly fill all the required fields (Name, Email, Phone, Address)');
        return false;
      }
      if (!param.location || param.location.length === 0) {
        app.$alert('Kindly specify your preferred delivery location');
        return false;
      }
      if (!app.termsAgreed) {
        app.$alert('You are required to read and agree to our Terms and Condition by clicking on the check box');
        return false;
      }
      return true;
    },
    submitOrder() {
      const app = this;
      if (app.loading) {
        return false; // already sending: a second click must not send it again
      }
      if (!app.validateCheckout()) {
        return false;
      }
      if (!app.imageToBeUploaded) {
        app.$alert('Please upload your payment receipt (a JPG or PNG image).');
        return false;
      }
      const param = app.checkOutForm;
      const formData = new FormData();
      formData.append('order_uniq_id', param.order_uniq_id);
      formData.append('name', param.name);
      formData.append('email', param.email);
      formData.append('phone', param.phone);
      formData.append('nearest_bustop', param.nearest_bustop || '');
      formData.append('address', param.address);
      formData.append('notes', param.notes || '');
      formData.append('receipt_image', app.imageToBeUploaded);
      app.cartPayload().forEach((line, index) => {
        Object.keys(line).forEach(prop => {
          formData.append(`cart_items[${index}][${prop}]`, line[prop]);
        });
      });
      param.location.forEach(loc => formData.append('location[]', loc));

      app.loading = true;
      new Resource('order/store').store(formData).then(response => {
        app.loading = false;
        app.handleOrderResponse(response);
      }).catch(error => {
        // HTTP errors (validation, rate limit, server) are already shown as
        // a message by the shared axios interceptor.
        app.loading = false;
        // A timeout does not mean the order failed — it may have been placed. The same checkout id is sent again, and
        // the server answers with the existing order instead of creating another, so pressing Submit again is safe.
        if (error && (error.code === 'ECONNABORTED' || error.code === 'ETIMEDOUT')) {
          app.$alert('This is taking longer than usual. Your order may already have been received. Please press Submit Order once more — we will not create a second order.', 'Still working');
        }
      });
    },
    payWithPaystack() {
      const app = this;
      if (app.paystackLoading) {
        return false;
      }
      if (!app.validateCheckout()) {
        return false;
      }
      const param = app.checkOutForm;
      const payload = {
        order_uniq_id: param.order_uniq_id,
        name: param.name,
        email: param.email,
        phone: param.phone,
        nearest_bustop: param.nearest_bustop || '',
        address: param.address,
        notes: param.notes || '',
        location: param.location,
        cart_items: app.cartPayload(),
      };
      app.paystackLoading = true;
      new Resource('order/paystack/initialize').store(payload).then(response => {
        if (response.authorization_url) {
          // full-page redirect to Paystack's hosted checkout — the button
          // stays in its loading state since we're navigating away anyway
          window.location.href = response.authorization_url;
          return;
        }
        app.paystackLoading = false;
        app.handleOrderResponse(response);
      }).catch(() => {
        app.paystackLoading = false;
      });
    },
    handleOrderResponse(response) {
      const app = this;
      if (response.message === 'success' || response.message === 'order_made_already') {
        app.showOrderDetails(response.order_details);
      } else if (response.message === 'check_cart') {
        app.handleStockShortage(response.details);
      }
    },
    // Some items sold out (or dropped) between the customer adding them and
    // submitting. Bring the cart and the summary in line with reality and
    // say exactly what changed — items with nothing left are removed.
    handleStockShortage(details) {
      const app = this;
      app.orderStore.applyStockAdjustments(details);
      app.orderStore.validateCart();
      const rows = details.map(detail => `
        <tr>
          <td style="padding: 4px 12px 4px 0">${escapeHtml(detail.product || 'Item')}</td>
          <td style="padding: 4px 0; text-align: right">${detail.balance > 0 ? `only ${detail.balance} left` : 'no longer available'}</td>
        </tr>`).join('');
      app.$alert(`
        <div>
          <p>Some products are fast moving and sold out just before you checked out. We've updated your cart to match what's in stock &mdash; please review it and submit again.</p>
          <table>${rows}</table>
        </div>`, 'Your cart was updated', { dangerouslyUseHTMLString: true });
    },
    showOrderDetails(orderDetails) {
      const app = this;
      app.orderStore.setCartItems([]);
      app.orderStore.setPendingOrder({ amount: 0, cart_items: [] });
      app.orderStore.validateCart(); // empty cart: clears any stock flags
      app.orderDetails = orderDetails;
      app.placedEmail = app.checkOutForm.email;
      app.orderPlaced = true;
      window.scrollTo({ top: 0, behavior: 'smooth' });
    },
    trackPlacedOrder() {
      this.$router.push({
        path: '/track/order',
        query: { order_number: this.orderDetails.order_number, email: this.placedEmail },
      });
    },
  },
};
</script>
<style lang="scss" scoped>
.checkout {
  max-width: var(--content-max-wide);
  margin: 0 auto;
  padding: 48px 32px var(--space-section);

  @media (max-width: 900px) {
    padding: 32px 20px 64px;
  }

  &__title {
    font-family: var(--font-serif);
    font-weight: 600;
    font-size: clamp(28px, 3.6vw, 40px);
    color: var(--color-navy);
    margin: 0 0 8px;
  }

  &__login-note {
    font-family: var(--font-sans);
    font-size: 14px;
    color: var(--color-text-muted);
    margin: 0 0 32px;

    a {
      color: var(--color-accent);
      font-weight: 500;
    }
  }

  &__layout {
    display: grid;
    grid-template-columns: 1.3fr 1fr;
    gap: 32px;
    align-items: start;

    @media (max-width: 900px) {
      grid-template-columns: 1fr;
    }
  }

  &__main,
  &__aside {
    display: flex;
    flex-direction: column;
    gap: 24px;
  }

  &__card {
    padding: 28px;
  }

  &__card-title {
    font-family: var(--font-sans);
    font-weight: 600;
    font-size: 16px;
    color: var(--color-navy);
    margin: 0 0 4px;
  }

  &__card-note {
    font-family: var(--font-sans);
    font-size: 13px;
    color: var(--color-text-muted);
    margin: 0 0 16px;
  }

  &__fields {
    display: flex;
    flex-direction: column;
    gap: 16px;
    margin-top: 16px;
  }

  &__hint {
    font-family: var(--font-sans);
    font-size: 12px;
    color: var(--color-text-muted);
    line-height: 1.5;
    margin: 0;
  }

  &__payment-toggle {
    display: flex;
    gap: 8px;
    margin: 12px 0 20px;
    border-bottom: 1px solid var(--color-border);
  }

  &__payment-tab {
    flex: 1;
    background: none;
    border: none;
    border-bottom: 2px solid transparent;
    padding: 10px 4px;
    font-family: var(--font-sans);
    font-size: 13px;
    font-weight: 600;
    color: var(--color-text-muted);
    cursor: pointer;
    transition: color 0.2s ease, border-color 0.2s ease;

    &--active {
      color: var(--color-navy);
      border-bottom-color: var(--color-accent);
    }
  }

  &__summary-head {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    margin-bottom: 20px;
  }

  &__edit-cart {
    font-family: var(--font-sans);
    font-size: 13px;
    font-weight: 500;
    color: var(--color-accent);
    text-decoration: none;

    &:hover {
      text-decoration: underline;
    }
  }

  &__items {
    list-style: none;
    margin: 0 0 20px;
    padding: 0;
    display: flex;
    flex-direction: column;
    gap: 16px;
  }

  &__item {
    display: grid;
    grid-template-columns: 56px 1fr auto;
    gap: 14px;
    align-items: center;
  }

  &__item-image {
    width: 56px;
    height: 56px;
    object-fit: cover;
    border-radius: var(--radius-sm);
    border: 1px solid var(--color-border);
  }

  &__item-name {
    font-family: var(--font-sans);
    font-size: 14px;
    font-weight: 500;
    color: var(--color-navy);
    margin: 0 0 4px;
  }

  &__item-meta {
    font-family: var(--font-sans);
    font-size: 12px;
    color: var(--color-text-muted);
    margin: 0;
  }

  &__item--flagged {
    opacity: 0.7;
  }

  &__item-flag {
    font-family: var(--font-sans);
    font-size: 11px;
    font-weight: 600;
    color: #f56c6c;
    margin: 4px 0 0;
  }

  &__item-total {
    font-family: var(--font-sans);
    font-size: 14px;
    font-weight: 600;
    color: var(--color-navy);
    margin: 0;
    white-space: nowrap;
  }

  &__total-row {
    display: flex;
    justify-content: space-between;
    padding-top: 16px;
    border-top: 1px solid var(--color-border);
    font-family: var(--font-sans);

    &--grand {
      font-size: 17px;
      font-weight: 600;
      color: var(--color-navy);
    }
  }

  &__refund-note {
    font-family: var(--font-sans);
    font-size: 12px;
    color: var(--color-accent);
    margin: 14px 0 0;
    text-align: center;
  }

  &__account-details {
    font-family: var(--font-sans);
    font-size: 14px;
    color: var(--color-text);
    line-height: 1.6;
    background: var(--color-surface-alt);
    border-radius: var(--radius-sm);
    padding: 16px;
    margin: 16px 0 20px;
  }

  &__upload-label {
    display: block;
    font-family: var(--font-sans);
    font-size: 13px;
    font-weight: 500;
    color: var(--color-navy);
    margin-bottom: 8px;
  }

  &__upload-hint {
    font-weight: 400;
    color: var(--color-text-muted);
  }

  &__upload-input {
    display: block;
    width: 100%;
    font-family: var(--font-sans);
    font-size: 13px;
    margin-bottom: 20px;
  }

  &__submit-block {
    display: flex;
    flex-direction: column;
    gap: 16px;
    padding-top: 4px;
    border-top: 1px solid var(--color-border);
    padding-top: 20px;
  }

  &__terms {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    font-family: var(--font-sans);
    font-size: 13px;
    color: var(--color-text);
    line-height: 1.5;
  }

  &__terms-link {
    color: var(--color-accent);
    cursor: pointer;
    font-weight: 500;
  }

  &__submit-btn {
    width: 100%;
  }

  &__empty {
    text-align: center;
    padding: 80px 20px;

    .checkout__title {
      margin-bottom: 24px;
    }
  }

  &__closed {
    text-align: center;
    padding: 80px 20px;

    img {
      max-width: 120px;
      margin-bottom: 24px;
    }

    h2 {
      font-family: var(--font-sans);
      font-size: 18px;
      color: var(--color-text-muted);
      font-weight: 500;
    }
  }

  &__receipt {
    text-align: center;
  }

  &__confirmation {
    max-width: 640px;
    margin: 0 auto;
  }

  &__confirmation-actions {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 12px;
    margin-top: 24px;
  }

  &__receipt-number {
    font-size: 18px;
    letter-spacing: 0.04em;
  }

  &__pickup-warning {
    margin: 12px 0 16px;
  }

  &__receipt-banner {
    font-family: var(--font-sans);
    font-size: 14px;
    font-weight: 500;
    color: var(--color-navy);
    background: var(--color-surface-alt);
    border-radius: var(--radius-sm);
    padding: 14px;
    margin-bottom: 20px;
  }

  &__receipt-list {
    margin: 0 0 20px;
  }

  &__receipt-row {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid var(--color-border);
    font-family: var(--font-sans);
    font-size: 14px;

    dt {
      color: var(--color-text-muted);
    }

    dd {
      margin: 0;
      font-weight: 600;
      color: var(--color-navy);
    }
  }
}
</style>
