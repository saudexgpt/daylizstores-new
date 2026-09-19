<template>
  <admin-card v-if="user.name" class="user-activity">
    <el-tabs v-model="activeTab">
      <el-tab-pane v-if="user.can_edit" label="Profile" name="profile">
        <el-form ref="profileForm" :model="profile" :rules="profileRules" label-position="top" @submit.prevent="onSubmit">
          <div class="user-activity__grid">
            <el-form-item label="Full name" prop="name">
              <el-input v-model="profile.name" maxlength="190" />
            </el-form-item>
            <el-form-item label="Email" prop="email">
              <el-input v-model="profile.email" type="email" />
            </el-form-item>
            <el-form-item label="Phone" prop="phone">
              <el-input v-model="profile.phone" inputmode="tel" maxlength="30" />
            </el-form-item>
            <el-form-item label="Address" prop="address">
              <el-input v-model="profile.address" maxlength="255" />
            </el-form-item>
          </div>
          <el-button type="primary" :loading="updating" @click="onSubmit">Save changes</el-button>
        </el-form>
      </el-tab-pane>

      <!-- changing a password needs the CURRENT password, which only the owner knows;
           an admin resets someone else's from the Users list instead -->
      <el-tab-pane v-if="user.can_edit && isSelf" label="Password" name="password">
        <el-form ref="passwordForm" :model="form" :rules="passwordRules" label-position="top" class="user-activity__narrow" @submit.prevent="updatePassword">
          <el-form-item label="Current password" prop="password">
            <el-input v-model="form.password" type="password" show-password autocomplete="current-password" />
          </el-form-item>
          <el-form-item label="New password" prop="new_password">
            <el-input v-model="form.new_password" type="password" show-password autocomplete="new-password" />
            <span class="user-activity__hint">At least 8 characters.</span>
          </el-form-item>
          <el-form-item label="Confirm new password" prop="c_password">
            <el-input v-model="form.c_password" type="password" show-password autocomplete="new-password" />
          </el-form-item>
          <el-button type="primary" :loading="updating" @click="updatePassword">Update password</el-button>
        </el-form>
      </el-tab-pane>
    </el-tabs>
  </admin-card>
</template>

<script>
import Resource from '@/api/resource';
import { useUserStore } from '@/store';

const userResource = new Resource('users');
const userPasswordResource = new Resource('users/update-password');

export default {
  props: {
    user: {
      type: Object,
      default: () => ({ name: '', email: '', avatar: '', roles: [] }),
    },
  },
  emits: ['updated'],
  data() {
    const sameAsNew = (rule, value, callback) => {
      callback(value !== this.form.new_password ? new Error('The passwords do not match') : undefined);
    };
    return {
      activeTab: 'profile',
      updating: false,
      // an editable COPY, so typing never changes the card next to it until it is saved
      profile: { name: '', email: '', phone: '', address: '' },
      form: { password: '', new_password: '', c_password: '' },
      profileRules: {
        name: [{ required: true, message: 'Enter a name', trigger: 'blur' }],
        email: [
          { required: true, message: 'Enter an email address', trigger: 'blur' },
          { type: 'email', message: 'Enter a valid email address', trigger: ['blur', 'change'] },
        ],
      },
      passwordRules: {
        password: [{ required: true, message: 'Enter your current password', trigger: 'blur' }],
        new_password: [
          { required: true, message: 'Enter a new password', trigger: 'blur' },
          { min: 8, message: 'Use at least 8 characters', trigger: 'blur' },
        ],
        c_password: [{ validator: sameAsNew, trigger: 'blur' }],
      },
    };
  },
  computed: {
    isSelf() {
      return Number(this.user.id) === Number(useUserStore().userId);
    },
  },
  watch: {
    user: {
      immediate: true,
      handler(user) {
        this.profile = { name: user.name || '', email: user.email || '', phone: user.phone || '', address: user.address || '' };
      },
    },
  },
  methods: {
    onSubmit() {
      this.$refs.profileForm.validate((valid) => {
        if (!valid) {
          return;
        }
        this.updating = true;
        userResource
          .update(this.user.id, this.profile)
          .then((response) => {
            this.$message({ message: 'Profile updated', type: 'success' });
            this.$emit('updated', response.data || { ...this.user, ...this.profile });
          })
          .catch(() => {
            // the shared axios interceptor has already shown the reason (e.g. the email is taken)
          })
          .finally(() => {
            this.updating = false;
          });
      });
    },
    updatePassword() {
      this.$refs.passwordForm.validate((valid) => {
        if (!valid) {
          return;
        }
        this.updating = true;
        userPasswordResource
          .store({
            user_id: this.user.id,
            email: this.user.email,
            password: this.form.password,
            new_password: this.form.new_password,
            c_password: this.form.c_password,
          })
          .then(() => {
            this.form = { password: '', new_password: '', c_password: '' };
            this.$refs.passwordForm.clearValidate();
            this.$message({ message: 'Password updated. Other devices have been signed out.', type: 'success' });
          })
          .catch(() => {
            // a wrong current password is reported by the interceptor
          })
          .finally(() => {
            this.updating = false;
          });
      });
    },
  },
};
</script>

<style lang="scss" scoped>
.user-activity {
  &__grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    column-gap: 20px;
  }

  &__narrow {
    max-width: 440px;
  }

  &__hint {
    display: block;
    width: 100%;
    margin-top: 4px;
    font-size: 12px;
    color: var(--admin-muted);
  }
}

@media (max-width: 720px) {
  .user-activity__grid { grid-template-columns: minmax(0, 1fr); }
}
</style>
