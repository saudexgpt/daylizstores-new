<template>
  <el-dialog :title="'Create new customer'" :model-value="dialogFormVisible" :before-close="closeModal">
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
        <el-form-item label="Team" prop="team">
          <el-select v-model="newCustomer.team" placeholder="Team" filterable class="span">
            <el-option v-for="(team, index) in params.teams" :key="index" :value="team" :label="team.toUpperCase()" />

          </el-select>
        </el-form-item>
        <el-form-item label="Type" prop="type">
          <el-select v-model="newCustomer.type" placeholder="Customer Type" filterable class="span">
            <el-option v-for="(type, index) in params.customer_types" :key="index" :value="type.name" :label="type.name.toUpperCase()" />

          </el-select>
        </el-form-item>
        <el-form-item label="Address" prop="address">
          <textarea v-model="newCustomer.address" class="form-control" />
        </el-form-item>
      </el-form>
    </div>
    <template #footer>
      <div class="dialog-footer">
        <el-button @click="closeModal">
          {{ $t('table.cancel') }}
        </el-button>
        <el-button type="primary" @click="createCustomer()">
          {{ $t('table.confirm') }}
        </el-button>
      </div>
    </template>
  </el-dialog>
</template>
<script>
import Resource from '@/api/resource';
const customerResource = new Resource('user/customer/store');
export default {
  props: {
    dialogFormVisible: {
      type: Boolean,
      default: () => (false),
    },
    params: {
      type: Object,
      default: () => ({}),
    },
  },
  data() {
    return {
      customer_types: [],
      userCreating: false,
      newCustomer: {
        name: '',
        email: null,
        phone: null,
        address: '',
        role: 'customer',
        type: '',
        team: '',
        password: '',
        confirmPassword: '',
      },
      rules: {
        team: [{ required: true, message: 'Customer Team is required', trigger: 'change' }],
        type: [{ required: true, message: 'Customer Type is required', trigger: 'change' }],
        name: [{ required: true, message: 'Name is required', trigger: 'blur' }],
        // email: [
        //   { required: true, message: 'Email is required', trigger: 'blur' },
        //   { type: 'email', message: 'Please input correct email address', trigger: ['blur', 'change'] },
        // ],
        // phone: [{ required: true, message: 'Phone is required', trigger: 'blur' }],
      },
    };
  },
  methods: {
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
              this.$emit('created', response.customer);
              // this.customers.push(response.customer);
              this.resetNewCustomer();
              this.closeModal();
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
    closeModal(){
      this.$emit('close', false);
    },
    resetNewCustomer() {
      this.newCustomer = {
        name: '',
        email: null,
        phone: null,
        address: '',
        role: 'customer',
        customer_type: '',
        password: '',
        confirmPassword: '',
      };
    },
  },
};
</script>
