<template>
  <div v-loading="loading">
    <div class="text-center wow fadeInUp" data-wow-delay="0.1s" style="visibility: visible; animation-delay: 0.1s; animation-name: fadeInUp;">
      <h1 class="section-title ff-secondary text-center text-primary-custom fw-normal">Track My Order</h1>
    </div>
    <el-alert v-if="paymentBanner" :type="paymentBanner.type" :title="paymentBanner.title" :description="paymentBanner.description" show-icon style="margin-bottom: 20px;" />
    <el-row
      v-if="page.option=='order_details'"
      :gutter="20"
    >
      <el-col
        :lg="24"
        :md="24"
        :sm="24"
        :xs="24"
      >
        <a class="btn btn-danger no-print" @click="page.option='list';">Go Back</a>
        <order-details :order="order" />
      </el-col>
    </el-row>
    <el-row v-else :gutter="20">
      <el-col
        v-if="userData.id === null"
        :lg="24"
        :md="24"
        :sm="24"
        :xs="24"
      >
        <el-card>
          <template #header><span>Kindly fill the form below to continue</span></template>
          <el-row :gutter="10">
            <el-col
              :lg="10"
              :md="10"
              :sm="10"
              :xs="24"
            >

              <el-input
                v-model="trackOrderForm.username"
                name="username"
                type="text"
                auto-complete="off"
                placeholder="Email OR Phone *"
              />
            </el-col>
            <el-col
              :lg="10"
              :md="10"
              :sm="10"
              :xs="24"
            >

              <el-input
                v-model="trackOrderForm.order_number"
                name="order_number"
                type="text"
                auto-complete="off"
                placeholder="Order Number *"
              />
            </el-col>
            <el-col
              :lg="4"
              :md="4"
              :sm="4"
              :xs="24"
            >
              <br>
              <el-button type="primary" size="large" round @click="submitOrder">
                Track
              </el-button>
            </el-col>

          </el-row>
        </el-card>
      </el-col>
      <el-col
        v-else
        :lg="24"
        :md="24"
        :sm="24"
        :xs="24"
      >
        <el-card>
          <el-table :data="orders">
            <el-table-column prop="order_number" label="Order Number" />
            <el-table-column label="Amount">
              <template #default="scope">
                {{ currency + formatNumber(scope.row.total, 2) }}
              </template>
            </el-table-column>
            <el-table-column label="Date">
              <template #default="scope">
                {{ moment(scope.row.created_at).format('MMMM Do YYYY, h:mm:ss a') }}
              </template>
            </el-table-column>
            <el-table-column label="Action">
              <template #default="scope">
                <a class="btn btn-primary" @click="order=scope.row; page.option='order_details'"><el-icon><IconTickets /></el-icon> View</a>
              </template>
            </el-table-column>
          </el-table>
          <el-row :gutter="20">
            <pagination
              v-show="total > 0"
              :total="total"
              v-model:page="form.page"
              v-model:limit="form.limit"
              @pagination="myOrders"
            />
          </el-row>
        </el-card>
      </el-col>
    </el-row>
  </div>
</template>
<script>
import moment from 'moment';
import Pagination from '@/components/Pagination';
import Resource from '@/api/resource';
import OrderDetails from '@/app/order/Details';
import { formatNumber } from '@/utils/index';
import { useUserStore, useOrderStore } from '@/store';
export default {
  name: 'Trackorder',
  components: {
    OrderDetails,
    Pagination,
  },
  data() {
    return {
      currency: '₦',
      orders: [],
      page: {
        option: 'list',
      },
      selectedItem: null,
      loading: false,
      trackOrderForm: {
        order_number: '',
        username: '',
      },
      form: {
        page: 1,
        limit: 10,
      },
      total: 0,
      order: null,
      paymentBanner: null,
    };
  },
  computed: {
    userStore() {
      return useUserStore();
    },
    orderStore() {
      return useOrderStore();
    },
    userData() {
      return this.userStore.userData;
    },
  },
  created() {
    this.setForm();
    this.checkPaymentRedirect();
    if (this.userData.id !== null) {
      this.myOrders();
    }
  },
  methods: {
    moment,
    formatNumber,
    setForm() {
      const app = this;
      app.trackOrderForm.username = app.userData.email;
    },
    // Paystack redirects the browser back here after checkout (see
    // OrdersController::paystackCallback) with ?payment=success|failed and,
    // on success, the order_number/email to look the order up with directly.
    checkPaymentRedirect() {
      const app = this;
      const { payment, order_number, email } = app.$route.query;
      if (!payment) {
        // Arriving from the checkout confirmation screen ("Track this
        // order"): the details are known, so look the order up straight away.
        if (order_number && email) {
          app.trackOrderForm.username = email;
          app.trackOrderForm.order_number = order_number;
          app.submitOrder();
        }
        return;
      }
      if (payment === 'success') {
        app.paymentBanner = {
          type: 'success',
          title: 'Payment Successful',
          description: 'Your order has been confirmed and will be prepared for pickup/delivery.',
        };
        // Paystack has server-confirmed this order paid by the time we're
        // redirected here — the items just bought no longer belong in the
        // cart, the same way the bank-transfer path clears it immediately
        // on a successful submitOrder() (see CheckOut.vue's showOrderDetails).
        app.orderStore.setCartItems([]);
        app.orderStore.setPendingOrder({ amount: 0, cart_items: [] });
        if (order_number && email) {
          app.trackOrderForm.username = email;
          app.trackOrderForm.order_number = order_number;
          app.submitOrder();
        }
      } else if (payment === 'failed') {
        app.paymentBanner = {
          type: 'error',
          title: 'Payment Not Confirmed',
          description: 'We could not confirm your payment. If you were charged, please contact support with your order number, or track it below once the bank/card confirms the debit.',
        };
      }
    },
    submitOrder() {
      const app = this;
      app.order = null;
      const form = app.trackOrderForm;
      if (!form.username || !form.order_number) {
        app.$alert('Kindly enter your email or phone and your order number.');
        return false;
      }
      app.loading = true;
      const storeOrder = new Resource('order/search');
      storeOrder.store(form).then(response => {
        if (response.message === 'success') {
          app.order = response.order;
          app.page.option = 'order_details';
        } else {
          app.$alert('We could not find an order matching those details. Check the order number and the email or phone you used at checkout.', 'Order not found');
        }
        app.loading = false;
      }).catch(() => {
        // HTTP errors (validation, rate limit, ...) are already shown as a
        // message by the shared axios interceptor.
        app.loading = false;
      });
    },
    myOrders() {
      const app = this;
      const { limit, page } = this.form;
      this.loading = true;

      const param = app.form;
      const myOrdersResource = new Resource('order/general/my-orders');
      myOrdersResource.list(param)
        .then(response => {
          this.orders = response.orders.data;
          this.orders.forEach((element, index) => {
            element['index'] = (page - 1) * limit + index + 1;
          });
          this.total = response.orders.total;
          //  app.in_location = 'in ' + app.locations[param.location_index].name;
          this.loading = false;
        })
        .catch(error => {
          this.loading = false;
          console.log(error.message);
        });
    },
  },
};
</script>
