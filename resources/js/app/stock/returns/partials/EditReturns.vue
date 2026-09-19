<template>
  <div class="box">
    <div class="box-header">
      <h4 class="box-title">Edit Returned Product</h4>
      <span class="pull-right">
        <a class="btn btn-danger" @click="page.option = 'list'"> Back</a>
      </span>
    </div>
    <div class="box-body">
      <aside>
        <el-form ref="form" :model="form" label-width="120px">
          <el-row :gutter="5" class="padded">
            <el-col :xs="24" :sm="12" :md="12">
              <label for="">Select Product</label>
              <el-select v-model="form.item_id" placeholder="Select Product" filterable class="span">
                <el-option v-for="(item, item_index) in params.items" :key="item_index" :value="item.id" :label="item.name" />

              </el-select>
              <label for="">Customer Name</label>
              <el-input v-model="form.customer_name" placeholder="Customer Name" class="span" />
              <label for="">Quantity</label>
              <el-input v-model="form.quantity" type="text" placeholder="Quantity" class="span" />
              <label for="">Batch No.</label>
              <el-input v-model="form.batch_no" placeholder="Batch No." class="span" />
            </el-col>
            <el-col :xs="24" :sm="12" :md="12">
              <label for="">Product Expiry Date</label>
              <el-date-picker v-model="form.expiry_date" type="date" outline format="yyyy/MM/dd" value-format="yyyy-MM-dd" style="width: 100%" />

              <label for="">Date of Return</label>
              <el-date-picker v-model="form.date_returned" type="date" outline format="yyyy/MM/dd" value-format="yyyy-MM-dd" style="width: 100%" />
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
          <el-button type="success" @click="editReturnedProduct"><i class="el-icon-edit" />
            Update
          </el-button>
        </el-col>
      </el-row>
    </div>
  </div>
</template>

<script>
import moment from 'moment';
import Resource from '@/api/resource';
const updateReturnedProduct = new Resource('stock/returns/update');
export default {
  name: 'EditReturnedProduct',

  props: {
    params: {
      type: Object,
      default: () => ({}),
    },
    returnedProducts: {
      type: Array,
      default: () => ([]),
    },
    returnedProduct: {
      type: Object,
      default: () => ({}),
    },

    page: {
      type: Object,
      default: () => ({
        option: 'edit_returns',
      }),
    },

  },
  data() {
    return {
      form: {
      },
    };
  },
  mounted() {
    this.form = this.returnedProduct;
  },
  methods: {
    moment,
    editReturnedProduct() {
      const app = this;
      const load = updateReturnedProduct.loaderShow();
      var form = app.form;
      updateReturnedProduct.update(form.id, form)
        .then(response => {
          app.$message({ message: 'Product Updated Successfully!!!', type: 'success' });

          app.$emit('update', response.returned_product);
          app.page.option = 'list';// return to list of items
          load.hide();
        })
        .catch(error => {
          load.hide();
          alert(error.message);
        });
    },
  },
};
</script>

