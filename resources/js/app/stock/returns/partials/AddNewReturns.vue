<template>
  <div class="box">
    <div class="box-header">
      <h4 class="box-title">Add Returned Product</h4>
      <span class="pull-right">
        <a class="btn btn-danger" @click="page.option = 'list'"> Back</a>
      </span>
    </div>
    <div class="box-body">
      <aside>
        <el-form ref="form" :model="form" label-width="120px">
          <el-row :gutter="5" class="padded">
            <el-col :xs="24" :sm="12" :md="12">
              <label for="">Select Warehouse</label>
              <el-select v-model="form.warehouse_id" placeholder="Select Warehouse" filterable class="span">
                <el-option v-for="(warehouse, index) in params.warehouses" :key="index" :value="warehouse.id" :label="warehouse.name" />

              </el-select>

              <label for="">Select Product</label>
              <el-select v-model="form.item_id" placeholder="Select Product" filterable class="span">
                <el-option v-for="(item, index) in params.items" :key="index" :value="item.id" :label="item.name" />

              </el-select>
              <label for="">Customer Name</label>
              <el-input v-model="form.customer_name" placeholder="Customer Name" class="span" />
              <label for="">Quantity</label>
              <el-input v-model="form.quantity" type="text" placeholder="Quantity" class="span" />
            </el-col>
            <el-col :xs="24" :sm="12" :md="12">
              <label for="">Batch No.</label>
              <el-input v-model="form.batch_no" placeholder="Batch No." class="span" />

              <label for="">Product Expiry Date</label>
              <el-date-picker v-model="form.expiry_date" type="date" outline format="yyyy/MM/dd" value-format="yyyy-MM-dd" style="width: 100%" />

              <label for="">Date of Return</label>
              <el-date-picker v-model="form.date_returned" type="date" outline format="yyyy/MM/dd" value-format="yyyy-MM-dd" style="width: 100%" :picker-options="pickerOptions" />
              <label for="">Reason for return</label>
              <el-select v-model="form.reason" placeholder="Select Product" filterable class="span">
                <el-option v-for="(reason, index) in params.product_return_reasons" :key="index" :value="reason" :label="reason" />

              </el-select>
              <div v-if="form.reason === 'Others'">
                <label for="">Specify Other Reasons</label>
                <el-input v-model="form.other_reason" type="text" placeholder="Specify" class="span" />
              </div>

            </el-col>
          </el-row>
        </el-form>
      </aside>
      <el-row :gutter="2" class="padded">
        <el-col :xs="24" :sm="6" :md="6">
          <el-button type="success" @click="addNewReturnedProduct"><i class="el-icon-plus" />
            Add
          </el-button>
        </el-col>
      </el-row>
    </div>
  </div>
</template>

<script>
import moment from 'moment';
import Resource from '@/api/resource';
const createReturnedProduct = new Resource('stock/returns/store');

export default {
  props: {
    params: {
      type: Object,
      default: () => ({}),
    },
    returnedProducts: {
      type: Array,
      default: () => ([]),
    },

    page: {
      type: Object,
      default: () => ({
        option: 'add_new',
      }),
    },

  },
  data() {
    return {
      pickerOptions: {
        disabledDate(date) {
          var d = new Date(); // today
          d.setDate(d.getDate()); // one year from now
          return date > d;
        },
      },
      fill_fields_error: false,
      form: {
        warehouse_id: '',
        item_id: '',
        customer_name: '',
        quantity: '',
        batch_no: '',
        expiry_date: '',
        date_returned: '',
        reason: null,
        other_reason: null,

      },

    };
  },
  mounted() {
  },
  methods: {
    moment,
    addNewReturnedProduct() {
      const app = this;
      const load = createReturnedProduct.loaderShow();
      var form = app.form;
      createReturnedProduct.store(form)
        .then(response => {
          app.resetForm();
          app.$message({ message: 'Returned Products Added Successfully!!!', type: 'success' });
          app.returnedProducts.push(response.returned_product);
          app.$emit('update', response);
          load.hide();
        })
        .catch(error => {
          load.hide();
          alert(error.message);
        });
    },
    resetForm(){
      this.form = {
        warehouse_id: '',
        item_id: '',
        customer_name: '',
        quantity: '',
        batch_no: '',
        expiry_date: '',
        date_returned: '',
        reason: null,
        other_reason: null,
      };
    },

  },
};
</script>

