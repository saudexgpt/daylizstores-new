<template>
  <div class="app-container">
    <div v-if="params" class="box">
      <div class="box-header">
        <h4 class="box-title">Create New order</h4>
        <span class="pull-right">
          <router-link v-if="checkPermission(['view order'])" :to="{name:'View'}" class="btn btn-info"> View Orders</router-link>
        </span>
      </div>
      <div class="box-body">
        <el-form ref="form" :model="form" label-width="120px">
          <el-row :gutter="5" class="padded">
            <el-col :xs="24" :sm="12" :md="12">
              <label for="">Select Warehouse</label>
              <el-select v-model="form.warehouse_id" placeholder="Select Warehouse" filterable class="span">
                <el-option v-for="(warehouse, warehouse_index) in params.warehouses" :key="warehouse_index" :value="warehouse.id" :label="warehouse.name" />

              </el-select>
              <label for="">Select Currency</label>
              <el-select v-model="form.currency_id" placeholder="Select Currency" class="span">
                <el-option v-for="(currency, currency_index) in params.currencies" :key="currency_index" :value="currency.id" :label="currency.name+' ('+currency.code+')'" />

              </el-select>

            </el-col>
            <el-col :xs="24" :sm="12" :md="12">
              <label for="">Select Customer (<a style="color: brown" @click="dialogFormVisible = true">Click to Add New Customer</a>)</label>
              <el-select v-model="form.customer_id" placeholder="Select Warehouse" filterable class="span">
                <el-option v-for="(customer, customer_index) in customers" :key="customer_index" :value="customer.id" :label="customer.user.name" />
              </el-select>
              <label for="">Order Date</label>
              <el-date-picker v-model="form.ordered_at" type="date" placeholder="Order Date" style="width: 100%;" format="yyyy/MM/dd" value-format="yyyy-MM-dd" />

            </el-col>
          </el-row>
          <el-row :gutter="2" class="padded">
            <el-col>
              <div style="overflow: auto">
                <label for="">Items</label>
                <table class="table table-bordered">
                  <thead>
                    <tr>
                      <th>Action</th>
                      <th>Choose Item</th>
                      <th>Quantity</th>
                      <th>Unit Price</th>
                      <th>Tax</th>
                      <th>Total</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="(order_item, index) in order_items" :key="index">
                      <td>
                        <span>
                          <a class="btn btn-danger btn-flat fa fa-trash" @click="removeLine(index)" />
                          <a v-if="index + 1 === order_items.length" class="btn btn-info btn-flat fa fa-plus" @click="addLine(index)" />
                        </span>
                      </td>
                      <td>
                        <el-select v-model="order_item.item_index" placeholder="Select Item" filterable class="span" @input="fetchItemDetails(index)">
                          <el-option v-for="(item, item_index) in params.items" :key="item_index" :value="item_index" :label="item.name+' | '+item.sku" />

                        </el-select>
                      </td>
                      <td>
                        <el-input v-model="order_item.quantity" type="number" outline placeholder="Quantity" min="1" @change="calculateTotal(index)" />
                      </td>
                      <td>
                        <el-input v-model="order_item.price" type="hidden" outline />
                        {{ Number(order_item.price).toLocaleString() }}
                      </td>
                      <td>
                        <el-input v-model="order_item.tax" type="hidden" outline />
                        {{ Number(order_item.tax * 100).toLocaleString() }}%
                      </td>
                      <td align="right">
                        <el-input v-model="order_item.total" type="hidden" outline />
                        {{ Number(order_item.total).toLocaleString() }}
                      </td>
                    </tr>
                    <tr v-if="fill_fields_error"><td colspan="6"><label class="label label-danger">Please fill all empty fields before adding another row</label></td></tr>
                    <tr>
                      <td colspan="5" align="right"><label>Subtotal</label></td>
                      <td align="right">{{ Number(form.subtotal).toLocaleString() }}</td>
                    </tr>
                    <tr>
                      <td colspan="5" align="right">
                        <el-dropdown class="avatar-container right-menu-item hover-effect" trigger="click">
                          <div class="avatar-wrapper" style="color: brown">
                            <label style="cursor:pointer">Add Discount</label>
                          </div>
                          <el-dropdown-menu slot="dropdown" style="padding: 10px;">
                            <el-input v-model="discount_rate" type="number" min="0" style="width: 50%;" @input="calculateTotal(null)" /> % of Subtotal
                            <el-dropdown-item divided>
                              Enter Discount percentage
                            </el-dropdown-item>
                          </el-dropdown-menu>
                        </el-dropdown>
                      </td>
                      <td align="right">{{ Number(form.discount).toLocaleString() }}</td>
                    </tr>
                    <tr>
                      <td colspan="5" align="right"><label>Tax</label></td>
                      <td align="right">{{ Number(form.tax).toLocaleString() }}</td>
                    </tr>
                    <tr>
                      <td colspan="5" align="right"><label>Grand Total</label></td>
                      <td align="right"><label style="color: green">{{ Number(form.amount).toLocaleString() }}</label></td>
                    </tr>
                    <tr>
                      <td align="right">Notes</td>
                      <td colspan="5"><textarea v-model="form.notes" class="form-control" rows="5" placeholder="Type extra note on this order here..." /></td>
                    </tr>
                  </tbody>

                </table>
              </div>
            </el-col>
          </el-row>
          <el-row :gutter="2" class="padded">
            <el-col :xs="24" :sm="6" :md="6">
              <el-button type="success" @click="addNewOrder"><i class="el-icon-plus" />
                Create Order
              </el-button>
            </el-col>
          </el-row>
        </el-form>
        <el-dialog :title="'Create new customer'" v-model="dialogFormVisible">
          <div v-loading="userCreating" class="form-container">
            <el-form ref="newCustomer" :rules="rules" :model="newCustomer" label-position="left" label-width="150px" style="max-width: 500px;">
              <el-form-item :label="$t('user.name')" prop="name">
                <el-input v-model="newCustomer.name" />
              </el-form-item>
              <el-form-item :label="$t('user.email')" prop="email">
                <el-input v-model="newCustomer.email" required />
              </el-form-item>
              <el-form-item label="Phone" prop="phone">
                <el-input v-model="newCustomer.phone" required />
              </el-form-item>
              <el-form-item label="Customer Type" prop="customer_type_id">
                <el-select v-model="newCustomer.customer_type_id" placeholder="Customer Type" filterable class="span">
                  <el-option v-for="(customer_type, index) in customer_types" :key="index" :value="customer_type.id" :label="customer_type.name.toUpperCase()" />

                </el-select>
              </el-form-item>
              <el-form-item label="Address" prop="address">
                <textarea v-model="newCustomer.address" class="form-control" />
              </el-form-item>
            </el-form>
            <div slot="footer" class="dialog-footer">
              <el-button @click="dialogFormVisible = false">
                {{ $t('table.cancel') }}
              </el-button>
              <el-button type="primary" @click="createCustomer()">
                {{ $t('table.confirm') }}
              </el-button>
            </div>
          </div>
        </el-dialog>
      </div>
    </div>
  </div>
</template>

<script>
import moment from 'moment';
import checkPermission from '@/utils/permission';
import checkRole from '@/utils/role';

import Resource from '@/api/resource';
const createOrder = new Resource('order/general/store');
const necessaryParams = new Resource('fetch-necessary-params');
const getCustomers = new Resource('fetch-customers');
const customerResource = new Resource('user/customer/store');
export default {
  name: 'AddNewOrder',

  data() {
    return {
      params: {},
      customers: [],
      customer_types: [],
      dialogFormVisible: false,
      userCreating: false,
      fill_fields_error: false,
      form: {
        warehouse_id: '',
        customer_id: '',
        currency_id: '',
        order_status: 'pending',
        ordered_at: '',
        subtotal: 0,
        discount: 0,
        tax: 0,
        amount: 0,
        notes: '',
        order_items: [
          {
            item_id: '',
            quantity: '',
            price: null,
            tax: null,
            total: 0,
          },
        ],
      },
      empty_form: {
        warehouse_id: '',
        customer_id: '',
        currency_id: '',
        order_status: 'pending',
        ordered_at: '',
        subtotal: 0,
        discount: 0,
        tax: 0,
        amount: 0,
        notes: '',
        order_items: [
          {
            item_index: '',
            item_id: '',
            quantity: '',
            price: null,
            tax: null,
            total: 0,
          },
        ],
      },
      order_items: [],
      newCustomer: {
        name: '',
        email: '',
        phone: '',
        address: '',
        role: 'customer',
        customer_type_id: '',
        password: '',
        confirmPassword: '',
      },
      rules: {
        customer_type_id: [{ required: true, message: 'Customer Type is required', trigger: 'change' }],
        name: [{ required: true, message: 'Name is required', trigger: 'blur' }],
        email: [
          { required: true, message: 'Email is required', trigger: 'blur' },
          { type: 'email', message: 'Please input correct email address', trigger: ['blur', 'change'] },
        ],
        phone: [{ required: true, message: 'Phone is required', trigger: 'blur' }],
      },
      discount_rate: 0,
    };
  },
  watch: {
    order_items() {
      this.blockRemoval = this.order_items.length <= 1;
    },

  },
  mounted() {
    this.fetchNecessaryParams();
    this.fetchCustomers();
    this.addLine();
  },
  methods: {
    moment,
    checkPermission,
    checkRole,
    addLine(index) {
      this.fill_fields_error = false;

      const checkEmptyLines = this.order_items.filter(detail => detail.item_id === '' || detail.quantity === '' || detail.price === null || detail.tax === null || detail.total === 0);

      if (checkEmptyLines.length >= 1 && this.order_items.length > 0) {
        this.fill_fields_error = true;
        // this.order_items[index].seleted_category = true;
        return;
      } else {
        // if (this.order_items.length > 0)
        //     this.order_items[index].grade = '';

        this.order_items.push({
          item_index: null,
          item_id: '',
          quantity: 1,
          price: null,
          tax: null,
          total: 0,
        });
      }
    },
    removeLine(detailId) {
      this.fill_fields_error = false;
      if (!this.blockRemoval) {
        this.order_items.splice(detailId, 1);
        this.calculateTotal(null);
      }
    },
    fetchNecessaryParams() {
      const app = this;
      necessaryParams.list()
        .then(response => {
          app.params = response.params;
        });
    },
    fetchCustomers() {
      const app = this;
      getCustomers.list()
        .then(response => {
          app.customers = response.customers;
          app.customer_types = response.customer_types;
        });
    },
    addNewOrder() {
      const app = this;
      var form = app.form;
      const checkEmptyFielads = (form.warehouse_id === '' || form.customer_id === '' || form.ordered_at === '' || form.currency_id === '');
      if (!checkEmptyFielads) {
        const load = createOrder.loaderShow();
        form.order_items = app.order_items;
        form.ordered_at = app.moment(form.ordered_at).format('LLL');
        createOrder.store(form)
          .then(response => {
            app.$message({ message: 'Order Created Successfully!!!', type: 'success' });
            app.form = app.empty_form;
            app.order_items = app.form.order_items;
            load.hide();
          })
          .catch(error => {
            load.hide();
            alert(error.message);
          });
      } else {
        alert('Please fill the form fields completely');
      }
    },
    createCustomer() {
      this.$refs['newCustomer'].validate((valid) => {
        if (valid) {
          this.newCustomer.roles = [this.newCustomer.role];
          this.newCustomer.password = this.newCustomer.phone; // set password as phone
          this.newCustomer.confirmPassword = this.newCustomer.phone;
          this.userCreating = true;
          customerResource
            .store(this.newCustomer)
            .then(response => {
              this.$message({
                message: 'New user ' + this.newCustomer.name + '(' + this.newCustomer.email + ') has been created successfully.',
                type: 'success',
                duration: 5 * 1000,
              });
              this.customers.push(response.customer);
              this.resetNewCustomer();
              this.dialogFormVisible = false;
            })
            .catch(error => {
              console.log(error);
            })
            .finally(() => {
              this.userCreating = false;
            });
        } else {
          console.log('error submit!!');
          return false;
        }
      });
    },
    resetNewCustomer() {
      this.newCustomer = {
        name: '',
        email: '',
        phone: '',
        address: '',
        role: 'customer',
        customer_type_id: '',
        password: '',
        confirmPassword: '',
      };
    },
    fetchItemDetails(index){
      const app = this;
      const item_index = app.order_items[index].item_index;
      const item = app.params.items[item_index];
      app.order_items[index].price = item.price.sale_price;
      app.order_items[index].item_id = item.id;
      let tax = 0;
      for (let a = 0; a < item.taxes.length; a++) {
        tax += parseFloat(item.taxes[a].rate);
      }
      app.order_items[index].tax = tax;
      app.calculateTotal(index);
    },
    calculateTotal(index) {
      const app = this;
      // Get total amount for this item without tax
      if (index !== null) {
        const quantity = app.order_items[index].quantity;
        const unit_price = app.order_items[index].price;
        app.order_items[index].total = parseFloat((quantity * unit_price)).toFixed(2);// + parseFloat(tax);
      }

      // we now calculate the running total of items ordered for with tax //////////
      let total_tax = 0;
      let subtotal = 0;
      for (let count = 0; count < app.order_items.length; count++) {
        const tax_rate = app.order_items[count].tax;
        const quantity = app.order_items[count].quantity;
        const unit_price = app.order_items[count].price;
        total_tax += parseFloat(tax_rate * quantity * unit_price);
        subtotal += parseFloat(app.order_items[count].total);
      }
      app.form.tax = total_tax.toFixed(2);
      app.form.subtotal = subtotal.toFixed(2);
      app.form.discount = parseFloat(app.discount_rate / 100 * subtotal).toFixed(2);
      // subtract discount
      app.form.amount = parseFloat(total_tax + subtotal - app.form.discount).toFixed(2);
    },

  },
};
</script>

