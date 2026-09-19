<template>
  <div class="">
    <aside>
      <el-form ref="form" :model="form" label-width="120px">
        <el-row :gutter="5" class="padded">
          <el-col :xs="24" :sm="24" :md="24">
            <label for="">Select Warehouse</label>
            <el-select v-model="form.warehouse_id" placeholder="Select Warehouse" filterable class="span">
              <el-option v-for="(warehouse, index) in params.warehouses" :key="index" :value="warehouse.id" :label="warehouse.name" :disabled="warehouse.id === 7" />

            </el-select>

          </el-col>
          <el-col :xs="24" :sm="24" :md="24">
            <upload-excel-component :on-success="handleSuccess" :before-upload="beforeUpload" />

          </el-col>
        </el-row>
      </el-form>
    </aside>
    <legend v-if="tableData.length > 0">Preview what you just uploaded and then click on the submit button. <a class="btn btn-success" @click="addBulkProductToStock">Submit</a> </legend>
    <div style="height: 600px; overflow:auto;">
      <el-table :data="tableData" border highlight-current-row style="width: 100%;margin-top:20px;">
        <el-table-column v-for="item of tableHeader" :key="item" :prop="item" :label="item" />
      </el-table>
    </div>

  </div>

</template>

<script>
import UploadExcelComponent from '@/components/UploadExcel/index.vue';
import Resource from '@/api/resource';
const uploadBulkProduct = new Resource('stock/items-in-stock/bulk-upload');

export default {
  name: 'UploadBulk',
  components: { UploadExcelComponent },
  props: {
    params: {
      type: Object,
      default: () => ({}),
    },
    itemsInStock: {
      type: Array,
      default: () => ([]),
    },

    bulkUpload: {
      type: Boolean,
      default: () => ({
        option: true,
      }),
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
      fill_fields_error: false,
      tableData: [],
      tableHeader: [],
      form: {
        warehouse_id: '',
        bulk_data: [],
      },
      sub_batches: [],

    };
  },
  mounted() {
    // this.addLine();
  },
  methods: {
    beforeUpload(file) {
      const isLt1M = file.size / 1024 / 1024 < 1;

      if (isLt1M) {
        return true;
      }

      this.$message({
        message: 'Please do not upload files larger than 1m in size.',
        type: 'warning',
      });
      return false;
    },
    handleSuccess({ results, header }) {
      this.tableData = results;
      this.tableHeader = header;
      // console.log(results);
    },
    addBulkProductToStock() {
      const app = this;
      var form = app.form;
      if (form.warehouse_id === '') {
        alert('Please select a warehouse');
      } else {
        const load = uploadBulkProduct.loaderShow();
        form.bulk_data = app.tableData;
        uploadBulkProduct.store(form)
          .then(response => {
            console.log(response.unsaved_products);
            app.tableData = response.unsaved_products;
            // app.sub_batches = [];
            for (let count = 0; count < response.items_stocked.length; count++) {
              app.itemsInStock.push(response.items_stocked[count]);
            }
            app.$message({ message: 'Bulk Products uploaded Successfully!!!', type: 'success' });
            // app.itemsInStock.push(response.item_in_stock);
            // app.$emit('update', response);
            load.hide();
            app.page.option = 'list';
          });
        // .catch(error => {
        //   load.hide();
        //   console.log(error);
        //   // alert('An error occured while trying to upload bulk product. Kindly try again.');
        // });
      }
    },

  },
};
</script>

