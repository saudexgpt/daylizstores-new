<template>
  <div class="login-container">
    <el-form
      ref="loginForm"
      v-loading="loading"
      :model="loginForm"
      :rules="loginRules"
      class="login-form"
      auto-complete="on"
      label-position="left"
    >
      <div v-if="loginForm.email === ''">
        <div align="center">
          <img :src="img" alt="Company Logo" width="300">
          <h4>Enter Token </h4>
        </div>
        <el-form-item>
          <el-input
            v-model="token"
            placeholder="Enter Token"
            auto-complete="off"
            name="token"
          />
        </el-form-item>
        <el-button
          :loading="loading"
          type="primary"
          style="width: 100%"
          @click.prevent="confirmToken()"
        >Confirm</el-button>
      </div>
      <div v-else>
        <div align="center">
          <img :src="img" alt="Company Logo" width="300">
          <h4>Reset Password 🔒</h4>
        </div>
        <el-form-item>
          <el-input
            v-model="loginForm.password"
            placeholder="Password"
            :type="pwdType"
            auto-complete="off"
            name="password"
          />
        </el-form-item>
        <el-form-item>
          <el-input
            v-model="loginForm.cpassword"
            placeholder="Confirm Password"
            :type="pwdType"
            auto-complete="off"
            name="cpassword"
          />
        </el-form-item>

        <el-button
          :loading="loading"
          type="primary"
          style="width: 100%"
          :disabled="loginForm.email === ''"
          @click.prevent="resetPassword"
        >Submit</el-button>
      </div>
    </el-form>
  </div>
</template>

<script>
import Resource from '@/api/resource';
export default {
  // components: {MdInput},
  name: 'ResetPassword',
  // components: { LangSelect },
  data() {
    return {
      img: '/images/logo.png',
      loginForm: {
        email: '',
        cpassword: '',
        password: '',
      },
      loading: false,
      pwdType: 'password',
      redirect: undefined,
      token: '',
    };
  },
  watch: {
    $route: {
      handler: function(route) {
        this.redirect = route.query && route.query.redirect;
      },
      immediate: true,
    },
  },
  methods: {
    confirmToken() {
      const app = this;
      const token = app.token;
      const confirmTokenResource = new Resource('auth/confirm-password-reset-token');
      app.loading = true;
      confirmTokenResource.store({ token })
        .then(response => {
          app.loginForm.email = response.email;
          app.loading = false;
        })
        .catch(error => {
          // console.log(error.response)
          app.$message.error(error.response.data.message);
          app.loading = false;
        });
    },
    showPwd() {
      if (this.pwdType === 'password') {
        this.pwdType = '';
      } else {
        this.pwdType = 'password';
      }
    },
    resetPassword() {
      const app = this;
      const confirmEmailResource = new Resource('auth/reset-password');
      if (app.loginForm.password !== app.loginForm.cpassword) {
        app.$alert('Password does not match');
        return false;
      }
      app.loading = true;
      confirmEmailResource.store({ email: app.loginForm.email, password: app.loginForm.password })
        .then(response => {
          app.$message({
            message: response.message,
            type: 'success',
          });
          app.loginForm.email = '';
          app.$router.push({ path: '/login' });
          app.loading = false;
        })
        .catch(error => {
          app.$message({
            message: error.response.data.message,
            type: 'error',
          });
          app.loading = false;
        });
    },
  },
};
</script>

<style rel="stylesheet/scss" lang="scss">
$primary: #116809;
$secondary: #666;
$dark_gray: #889aa4;
$light_gray: #eee;
.login-container {
  position: fixed;
  height: 100%;
  width: 100%;
  background-image: url('../../assets/bg/warehouse-management-system.jpg');
  .login-form {
    position: absolute;
    left: 50;
    right: 0;
    width: 520px;
    max-width: 100%;
    height: 100%;
    padding: 35px 35px 15px 35px;
    background-color: #fff;
    opacity: 1;
  }
  .tips {
    font-size: 14px;
    color: #000000;
    margin-bottom: 10px;
    span {
      &:first-of-type {
        margin-right: 16px;
      }
    }
  }
  .svg-container {
    padding: 6px 5px 6px 15px;
    color: $primary;
    vertical-align: middle;
    width: 30px;
    display: inline-block;
  }
  .title {
    font-size: 26px;
    font-weight: 400;
    color: $primary;
    margin: 0px auto 40px auto;
    text-align: center;
    font-weight: bold;
  }
  .show-pwd {
    position: absolute;
    right: 10px;
    top: 7px;
    font-size: 16px;
    color: $primary;
    cursor: pointer;
    user-select: none;
  }
  .set-language {
    color: #fff;
    position: absolute;
    top: 40px;
    right: 35px;
  }
  .md-form label.active {
    font-size: 1.3rem;
  }
  .md-form .prefix {
    top: 0.25rem;
    font-size: 1.5rem;
  }
  .md-form.md-outline .prefix {
    position: absolute;
    top: 0.9rem;
    font-size: 1.9rem;
    -webkit-transition: color 0.2s;
    transition: color 0.2s;
  }
  .md-form.md-outline .form-control {
    padding: 1rem;
  }
}
</style>
