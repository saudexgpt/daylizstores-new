<template>
  <el-dialog :title="title" :model-value="dialogFormVisible" :before-close="closeModal">
    <div class="form-container">
      <table class="table table-bordered table-striped">
        <thead>
          <tr>
            <th>DATE</th>
            <th>INVOICE NO.</th>
            <th>WAYBILL/DELIVERY</th>
            <th>Quantity</th>
            <th>MODE</th>
            <th>CUSTOMER</th>
          </tr>
        </thead>
        <tbody>

          <tr v-for="(transaction, index) in transactions" :key="index">
            <td>{{ moment(transaction.date).format('ll') }}</td>
            <td>{{ transaction.invoice_no }}</td>
            <td>{{ transaction.waybill_no }}</td>
            <td>{{ transaction.quantity }}</td>
            <td>{{ transaction.mode }}</td>
            <td>{{ transaction.customer }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </el-dialog>
</template>
<script>
import moment from 'moment';
export default {
  props: {
    dialogFormVisible: {
      type: Boolean,
      default: () => (false),
    },
    transactions: {
      type: Array,
      default: () => ([]),
    },
    title: {
      type: String,
      default: () => ('Transactions'),
    },
  },
  data() {
    return {
    };
  },
  methods: {
    moment,
    closeModal(){
      this.$emit('close', false);
    },
  },
};
</script>
