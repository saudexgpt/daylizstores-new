<template>
  <div>
    <div v-if="cart.length > 0" class="cart">
      <h3>My Cart Content</h3>
      <div class="">
        <el-button @click="shop();">Continue Shopping</el-button>
        <el-button type="success" :disabled="hasStockIssues" @click="checkOut()">
          Check Out
        </el-button>
      </div>
      <div class="cart__scroll">
        <table class="table">
          <tbody>
            <tr v-for="(item, index) in cart" :key="index" :class="{ 'cart-row--flagged': stockIssues[item.stock_id] }">
              <td v-if="item.media.length > 0">
                <img :src="item.media[0].link" width="100" loading="lazy" @error="onImageError">
              </td>
              <td v-else>
                <img src="/images/no-image.jpeg" width="100" loading="lazy">
              </td>
              <td>
                <strong>{{ item.name }}</strong>
                <p><label>{{ item.quantity }} pieces</label></p>
                <span v-if="item.standardAmount !== item.rate">
                  <span><s>{{ '₦' + formatNumber(item.standardAmount, 2) }}</s></span>
                  <span><label style="color: brown">{{ '₦' + formatNumber(item.rate, 2) }}</label></span>
                </span>
                <span v-else><label style="color: brown">{{ '₦' + formatNumber(item.rate, 2) }}</label></span>
                <p v-if="stockIssues[item.stock_id]" class="cart-row__flag">
                  {{ stockIssues[item.stock_id].balance > 0 ? `Only ${stockIssues[item.stock_id].balance} left in stock` : 'No longer available' }}
                  — remove or adjust quantity to check out
                </p>
              </td>
              <td>
                <el-button type="danger" circle @click="removeItem(index)"><el-icon><Delete /></el-icon></el-button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <div class="cart__footer">
        <h3 class="cart__total">Total: <strong>{{ '₦' + formatNumber(cartTotal, 2) }}</strong></h3>
        <el-alert v-if="hasStockIssues" type="error" :closable="false">Some items in your cart are no longer available in the quantity requested. Remove or adjust them to check out.</el-alert>
        <div class="cart__actions">
          <el-button @click="shop();">Continue Shopping</el-button>
          <el-button type="success" :disabled="hasStockIssues" @click="checkOut()">
            Check Out
          </el-button>
        </div>
      </div>
    </div>
    <div v-else align="center">
      <h1>&nbsp;</h1>
      <img src="/images/empty-cart.png">
      <h3>Your cart is empty. <br>Kindly shop for items</h3>
      <button class="btn btn-primary" @click="shop()">Shop Now</button>
    </div>
  </div>
</template>
<script>
import { Delete } from '@element-plus/icons-vue';
import { formatNumber, onImageError, roundMoney } from '@/utils/index';
import { useOrderStore } from '@/store';
// import { addClass, removeClass } from '@/utils';

export default {
  name: 'RightPanel',
  components: {
    Delete,
  },
  props: {
    clickNotClose: {
      default: false,
      type: Boolean,
    },
    buttonTop: {
      default: 250,
      type: Number,
    },
  },
  data() {
    return {
      show: false,
    };
  },
  computed: {
    orderStore() {
      return useOrderStore();
    },
    cart() {
      return this.orderStore.cart;
    },
    // Derived from the live cart, so it can never show a stale figure after
    // a line is removed or adjusted while the panel is open.
    cartTotal() {
      return roundMoney(this.cart.reduce((sum, line) => sum + line.subTotal, 0));
    },
    stockIssues() {
      return this.orderStore.stockIssues;
    },
    hasStockIssues() {
      return Object.keys(this.stockIssues).length > 0;
    },
  },
  created() {
    this.orderStore.validateCart();
  },
  methods: {
    formatNumber,
    onImageError,
    // calculateTotalStockBal(item) {
    //   let total_stock_balance = 0;
    //   item.item_stocks.forEach(stock => {
    //     total_stock_balance += parseInt(stock.total_stock_balance - stock.sold);
    //   });
    //   return total_stock_balance;
    // },
    removeItem(index) {
      const app = this;
      const unsyc_data = app.cart;
      unsyc_data.splice(index, 1);
      app.orderStore.setCartItems(unsyc_data);
      app.orderStore.syncPendingOrder();
      // a removed line may have been the one blocking checkout
      app.orderStore.validateCart();
    },
    shop() {
      const app = this;
      app.$emit('close');
      if (app.$route.name !== 'Menu') {
        app.$router.push({ path: '/product/list' });
      }
    },
    checkOut() {
      const app = this;
      if (app.hasStockIssues) {
        app.$alert('Some items in your cart are no longer available in the quantity requested. Remove or adjust them before checking out.');
        return false;
      }
      app.orderStore.setCartItems(app.cart);
      app.orderStore.syncPendingOrder();
      app.$emit('close');
      if (app.$route.name !== 'CheckOut') {
        app.$router.push({ path: '/product/check-out' });
      }
    },
  },
};
</script>
<style lang="scss" scoped>
.cart-row--flagged {
  background: #fef0f0;
}

.cart-row__flag {
  color: #f56c6c;
  font-size: 12px;
  font-weight: 600;
  margin: 4px 0 0;
}

.cart__scroll {
  max-height: 500px;
  overflow-y: auto;
  margin-bottom: 12px;
  padding: 12px;
}

// The panel around this component scrolls, not this component itself — so
// the footer sticks to the bottom of that scrolling viewport as the item
// list scrolls past it, rather than being pushed off-screen by a long cart.
.cart__footer {
  position: sticky;
  bottom: -10px;
  margin: 0 -10px -10px;
  padding: 12px 10px calc(10px + env(safe-area-inset-bottom));
  background: #fff;
  border-top: 1px solid var(--color-border, #e5e7ec);
  text-align: right;
  z-index: 2;

  // The mobile bottom tab bar (MobileNav.vue) is fixed with a higher
  // z-index than this panel, so on small screens it would otherwise sit on
  // top of — and hide — these buttons; lift the sticky point by its height.
  @media (max-width: 900px) {
    bottom: 46px;
  }
}

.cart__total {
  margin: 0 0 8px;
}

.cart__actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;

  .btn {
    flex: 0 0 auto;
  }
}
</style>
