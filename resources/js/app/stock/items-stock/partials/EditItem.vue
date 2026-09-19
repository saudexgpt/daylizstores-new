<template>
  <div class="box">
    <div class="box-header">
      <h4 class="box-title">Edit Product in stock</h4>
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
              <el-select
                v-model="form.warehouse_id"
                placeholder="Select Warehouse"
                filterable
                class="span"
              >
                <el-option
                  v-for="(warehouse, index) in params.warehouses"
                  :key="index"
                  :value="warehouse.id"
                  :label="warehouse.name"
                  :disabled="warehouse.id === 7"
                />
              </el-select>

              <label for="">Select Product</label>
              <el-select
                v-model="form.item_id"
                placeholder="Select Product"
                filterable
                class="span"
              >
                <el-option
                  v-for="(item, index) in params.items"
                  :key="index"
                  :value="item.id"
                  :label="item.name"
                />
              </el-select>
              <label for="">Batch No.</label>
              <el-input
                v-model="form.batch_no"
                placeholder="Batch No."
                class="span"
              />
            </el-col>
            <el-col :xs="24" :sm="12" :md="12">
              <label for="">Expiry Date</label>
              <el-date-picker
                v-model="form.expiry_date"
                placeholder="Expiry Date"
                type="date"
                style="width: 100%;"
                class="span"
                format="yyyy/MM/dd"
                value-format="yyyy-MM-dd"
              />
              <!-- <label for="">Quantity</label>
              <el-input
                v-model="form.quantity"
                placeholder="Quantity"
                class="span"
                @change="checkUpdatedQuantity()"
              /> -->
            </el-col>
          </el-row>
          <el-row :gutter="2" class="padded">
            <el-col :xs="24" :sm="6" :md="6">
              <el-button
                type="success"
                @click="editProduct"
              ><svg-icon icon-class="edit" />
                Update
              </el-button>
            </el-col>
          </el-row>
        </el-form>
      </aside>
    </div>
  </div>
</template>

<script>
import moment from 'moment';
import Resource from '@/api/resource';
const updateProduct = new Resource('stock/items-in-stock/update');
export default {
  name: 'AddNewProduct',

  props: {
    params: {
      type: Object,
      default: () => ({}),
    },
    itemsInStock: {
      type: Array,
      default: () => [],
    },
    itemInStock: {
      type: Object,
      default: () => ({}),
    },

    page: {
      type: Object,
      default: () => ({
        option: 'edit_item',
      }),
    },
  },
  data() {
    return {
      form: {
        warehouse_id: '',
        item_id: '',
        quantity: '',
        expiry_date: '',
        batch_no: '',
      },
      initial_stock: 0,
    };
  },
  mounted() {
    this.initial_stock = this.itemInStock.quantity;
    this.form = this.itemInStock;
  },
  methods: {
    moment,
    editProduct() {
      const app = this;
      const itemInStock = app.itemInStock;
      if (app.form.quantity >= app.initial_stock || itemInStock.balance === app.initial_stock) {
        const load = updateProduct.loaderShow();
        var form = app.form;
        form.expiry_date = app.moment(form.expiry_date).format('LLL');
        updateProduct
          .update(form.id, form)
          .then(response => {
            app.$message({
              message: 'Product Updated Successfully!!!',
              type: 'success',
            });

            app.$emit('update', response.item_in_stock);
            app.page.option = 'list'; // return to list of items
            load.hide();
          })
          .catch(error => {
            load.hide();
            alert(error.message);
          });
      } else {
        app.$alert('The new quantity cannot be less than ' + app.initial_stock);
      }
    },
    checkUpdatedQuantity() {
      const app = this;
      if (app.form.quantity < app.initial_stock) {
        app.$alert('The new quantity cannot be less than ' + app.initial_stock);
      }
    },
  },
};
</script>
