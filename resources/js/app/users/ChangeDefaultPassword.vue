<template>
  <div class="default-password">
    <admin-page-header title="Choose a new password" subtitle="Your account is using a temporary password. Set your own to continue." />

    <admin-card class="default-password__card">
      <el-form ref="form" :model="form" :rules="rules" label-position="top" @submit.prevent="updatePassword">
        <el-form-item label="Temporary password" prop="password">
          <el-input v-model="form.password" type="password" show-password autocomplete="current-password" placeholder="The password you were given" />
        </el-form-item>
        <el-form-item label="New password" prop="new_password">
          <el-input v-model="form.new_password" type="password" show-password autocomplete="new-password" />
          <span class="default-password__hint">At least 8 characters.</span>
        </el-form-item>
        <el-form-item label="Confirm new password" prop="c_password">
          <el-input v-model="form.c_password" type="password" show-password autocomplete="new-password" />
        </el-form-item>
        <el-button type="primary" :loading="saving" @click="updatePassword">Update password</el-button>
      </el-form>
    </admin-card>
  </div>
</template>

<script>
import Resource from '@/api/resource';
import { useUserStore } from '@/store';

// POST /users/update-password. (This screen used to call PUT /users/update-password/{id},
// a route that doesn't exist, so a temporary password could never actually be replaced.)
const userPasswordResource = new Resource('users/update-password');

export default {
  name: 'ChangeDefaultPassword',
  data() {
    const sameAsNew = (rule, value, callback) => {
      callback(value !== this.form.new_password ? new Error('The passwords do not match') : undefined);
    };
    return {
      user: {},
      saving: false,
      form: { password: '', new_password: '', c_password: '' },
      rules: {
        password: [{ required: true, message: 'Enter the temporary password', trigger: 'blur' }],
        new_password: [
          { required: true, message: 'Enter a new password', trigger: 'blur' },
          { min: 8, message: 'Use at least 8 characters', trigger: 'blur' },
        ],
        c_password: [{ validator: sameAsNew, trigger: 'blur' }],
      },
    };
  },
  created() {
    useUserStore().getInfo().then(data => {
      this.user = data;
    });
  },
  methods: {
    updatePassword() {
      this.$refs.form.validate((valid) => {
        if (!valid) {
          return;
        }
        this.saving = true;
        userPasswordResource
          .store({ user_id: this.user.id, email: this.user.email, ...this.form })
          .then(async() => {
            useUserStore().resetPasswordStatus({ p_status: 'custom' });
            this.$message({ message: 'Password updated. Please sign in again.', type: 'success' });
            await useUserStore().logout();
            window.location = '/login';
          })
          .catch(() => {
            // a wrong temporary password is reported by the interceptor
          })
          .finally(() => {
            this.saving = false;
          });
      });
    },
  },
};
</script>

<style lang="scss" scoped>
.default-password {
  &__card {
    max-width: 480px;
  }

  &__hint {
    display: block;
    width: 100%;
    margin-top: 4px;
    font-size: 12px;
    color: var(--admin-muted);
  }
}
</style>
