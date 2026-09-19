<template>
  <div class="app-container">
    <!-- <item-details v-if="page.option== 'view_details'" :item-in-stock="returnedProduct" :page="page" /> -->
    <add-new-returns v-if="page.option== 'add_new'" :returned-products="returned_products" :params="params" :page="page" />

    <edit-returns v-if="page.option== 'edit_returns'" :returned-product="returnedProduct" :params="params" :page="page" @update="onEditUpdate" />
    <div v-if="page.option=='list'" class="box">
      <div class="box-header">
        <h4 class="box-title">List of Returned Products {{ in_warehouse }}</h4>

        <span class="pull-right">
          <a v-if="checkPermission(['manage returned products'])" class="btn btn-info" @click="page.option = 'add_new'"> Add New</a>
        </span>

      </div>
      <div class="box-body">
        <el-col :xs="24" :sm="12" :md="12">
          <label for="">Select Warehouse</label>
          <el-select v-model="form.warehouse_index" placeholder="Select Warehouse" class="span" filterable @input="fetchItemStocks">
            <el-option v-for="(warehouse, index) in warehouses" :key="index" :value="index" :label="warehouse.name" />

          </el-select>

        </el-col>
        <br><br><br><br>
        <el-table :data="returned_products">
          <el-table-column label="Confirmed By">
            <template #default="scope">
              <div :id="scope.row.id">
                <div v-if="scope.row.confirmed_by == null">
                  <a v-if="checkPermission(['audit confirm actions']) && scope.row.stocked_by !== userId" class="btn btn-success" title="Click to confirm" @click="confirmReturnedItem(scope.row.id);"><i class="fa fa-check" /> </a>
                </div>
                <div v-else>
                  {{ scope.row.confirmer.name }}
                </div>
              </div>
            </template>
          </el-table-column>
          <el-table-column prop="stocker.name" label="Stocked By" />
          <el-table-column prop="customer_name" label="Customer Name" />
          <el-table-column prop="item.name" label="Product" sortable />
          <el-table-column prop="batch_no" label="Batch No." sortable />
          <el-table-column label="QTY">
            <template #default="scope">
              <div class="alert alert-warning">
                {{ scope.row.quantity }} {{ scope.row.item.package_type }}
              </div>
            </template>
          </el-table-column>
          <el-table-column label="QTY Approved">
            <template #default="scope">
              <div class="alert alert-info">
                {{ scope.row.quantity_approved }} {{ scope.row.item.package_type }}
              </div>
            </template>
          </el-table-column>
          <el-table-column label="Expiry Date" sortable>
            <template #default="scope">
              <div :class="'alert alert-'+ expiryFlag(moment(scope.row.expiry_date).format('x'))">
                <span>
                  {{ moment(scope.row.expiry_date).calendar() }}
                </span>
              </div>
            </template>
          </el-table-column>
          <el-table-column prop="reason" label="Reason" />
          <el-table-column label="Date Returned" sortable>
            <template #default="scope">
              {{ moment(scope.row.created_at).calendar() }}
            </template>
          </el-table-column>
          <el-table-column label="Action">
            <template #default="scope">
              <span>
                <a v-if="checkPermission(['manage returned products'])" class="btn btn-primary" @click="returnedProduct=scope.row; selected_row_index=scope.$index; page.option = 'edit_returns'"><i class="fa fa-edit" /> </a>

                <a v-if="checkPermission(['approve returned products']) && parseInt(scope.row.quantity) > parseInt(scope.row.quantity_approved)" class="btn btn-default" @click="openDialog(scope.row, scope.$index)"><i class="fa fa-check" /> </a>
              </span>
            </template>
          </el-table-column>
        </el-table>

      </div>
      <el-dialog
        title="Confirm Quantity for Approval"
        v-model="dialogVisible"
        width="20%"
      >
        <el-input v-model="approvalForm.approved_quantity" type="number" placeholder="Enter quantity for approval" />
        <template #footer>
          <span class="dialog-footer">
            <el-button round @click="dialogVisible = false; approvalForm.approved_quantity = null">Cancel</el-button>
            <el-button round type="primary" @click="approveProduct(); ">Approve</el-button>
          </span>
        </template>
      </el-dialog>

    </div>

  </div>
</template>
<script>
import moment from 'moment';
import checkPermission from '@/utils/permission';
import checkRole from '@/utils/role';
import { mapState } from 'pinia';
import { useAppStore, useUserStore } from '@/store';

import AddNewReturns from './partials/AddNewReturns';
import EditReturns from './partials/EditReturns';
import Resource from '@/api/resource';
const returnedProducts = new Resource('stock/returns');
const approveReturnedProducts = new Resource('stock/returns/approve-products');
const confirmItemReturned = new Resource('audit/confirm/returned-products');
export default {
  components: { AddNewReturns, EditReturns },
  data() {
    return {
      dialogVisible: false,
      warehouses: [],
      returned_products: [],
      page: {
        option: 'list',
      },
      // params: {},
      form: {
        warehouse_index: '',
        warehouse_id: '',
      },
      in_warehouse: '',
      returnedProduct: {},
      selected_row_index: '',
      approvalForm: {
        approved_quantity: null,
        product_details: '',
      },
      product_expiry_date_alert_in_months: 9, // defaults to 9 months

    };
  },
  computed: {
    params() {
      return useAppStore().params;
    },
    ...mapState(useUserStore, ['userId']),
  },
  mounted() {
    // this.getWarehouse();
    this.fetchNecessaryParams();
  },
  methods: {
    moment,
    checkPermission,
    checkRole,
    fetchNecessaryParams() {
      const app = this;
      useAppStore().setNecessaryParams();
      const params = app.params;
      app.warehouses = params.warehouses;
      if (app.warehouses.length > 0) {
        app.form.warehouse_id = app.warehouses[0];
        app.form.warehouse_index = 0;
        app.fetchItemStocks();
      }
      // necessaryParams.list()
      //   .then(response => {
      //     app.params = response.params;
      //     app.warehouses = response.params.warehouses;
      //     if (app.warehouses.length > 0) {
      //       app.form.warehouse_id = app.warehouses[0];
      //       app.form.warehouse_index = 0;
      //       app.fetchItemStocks();
      //     }
      //   });
    },
    confirmReturnedItem(id) {
      // const app = this;
      const form = { id: id };
      const message = 'Click okay to confirm action';
      if (confirm(message)) {
        confirmItemReturned.update(id, form)
          .then(response => {
            if (response.confirmed === 'success'){
              document.getElementById(id).innerHTML = response.confirmed_by;
            }
          });
      }
    },
    fetchItemStocks() {
      const app = this;
      const loader = returnedProducts.loaderShow();

      const param = app.form;
      param.warehouse_id = app.warehouses[param.warehouse_index].id;
      returnedProducts.list(param)
        .then(response => {
          app.returned_products = response.returned_products;
          app.in_warehouse = 'in ' + app.warehouses[param.warehouse_index].name;
          loader.hide();
        })
        .catch(error => {
          loader.hide();
          console.log(error.message);
        });
    },

    onEditUpdate(updated_row) {
      const app = this;
      // app.returned_products.splice(app.returnedProduct.index-1, 1);
      app.returned_products[app.selected_row_index - 1] = updated_row;
    },
    expiryFlag(date){
      const product_expiry_date_alert = this.product_expiry_date_alert_in_months;
      const min_expiration = parseInt(product_expiry_date_alert * 30 * 24 * 60 * 60 * 1000); // we use 30 days for one month to calculate
      const today = parseInt(this.moment().valueOf()); // Unix Timestamp (miliseconds) 1.6.0+
      if (parseInt(date) - today <= min_expiration) {
        // console.log(parseInt(date) - today);
        return 'danger'; // flag expiry date as red
      }
      return 'success'; // flag expiry date as green
    },
    // confirmDelete(props) {
    //   // this.loader();

    //   const row = props.row;
    //   const app = this;
    //   const message = 'This delete action cannot be undone. Click OK to confirm';
    //   if (confirm(message)) {
    //     deleteItemInStock.destroy(row.id, row)
    //       .then(response => {
    //         app.returned_products.splice(row.index - 1, 1);
    //         this.$message({
    //           message: 'Item has been deleted',
    //           type: 'success',
    //         });
    //       })
    //       .catch(error => {
    //         console.log(error);
    //       });
    //   }
    // },
    openDialog(product, selected_row_index){
      const app = this;
      app.approvalForm.product_details = product;
      app.selected_row_index = selected_row_index;
      app.dialogVisible = true;
    },
    approveProduct(){
      const app = this;

      const param = app.approvalForm;
      const balance = parseInt(app.approvalForm.product_details.quantity) - parseInt(app.approvalForm.product_details.quantity_approved);
      if (parseInt(param.approved_quantity) <= balance) {
        if (parseInt(param.approved_quantity) > 0) {
          app.dialogVisible = false;
          const loader = approveReturnedProducts.loaderShow();
          approveReturnedProducts.store(param)
            .then(response => {
              app.returned_products[app.selected_row_index - 1] = response.returned_product;
              loader.hide();
            })
            .catch(error => {
              loader.hide();
              console.log(error.message);
            });
        } else {
          app.$alert('Please enter a value greater than zero');
          return;
        }
      } else {
        app.$alert('Approved Quantity MUST NOT be greater than ' + balance);
        return;
      }
    },
    formatPackageType(type){
      var formated_type = type + 's';
      if (type === 'Box') {
        formated_type = type + 'es';
      }
      return formated_type;
    },
  },
};
</script>
<style rel="stylesheet/scss" lang="scss" scoped>
.alert {
  padding: 5px;
  margin: -5px;
  text-align: right;
}
td {
  padding: 0px !important;
}

</style>
