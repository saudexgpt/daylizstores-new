<template>
  <div class="login-page">
    <!-- Brand panel: decorative, hidden from assistive tech and on small screens -->
    <aside class="login-page__brand" aria-hidden="true">
      <img class="login-page__photo" src="/images/about6.jpg" alt="">
      <div class="login-page__brand-inner">
        <p class="login-page__eyebrow">DayLiz Stores</p>
        <h2 class="login-page__tagline">Your happiness,<br>our priority.</h2>
        <p class="login-page__lede">
          Sign in to track your orders, keep your favourites close and check out faster.
        </p>
      </div>
    </aside>

    <main class="login-page__panel">
      <router-link :to="{ path: '/home' }" class="login-page__back">
        <el-icon><ArrowLeft /></el-icon>
        Back to store
      </router-link>

      <div class="login-card">
        <router-link :to="{ path: '/home' }" class="login-card__logo-link" aria-label="DayLiz Stores home">
          <img class="login-card__logo" src="/images/logo.png" alt="DayLiz Stores" width="249" height="135">
        </router-link>

        <header class="login-card__header">
          <h1 class="login-card__title">{{ forgotPassword ? 'Reset your password' : 'Welcome back' }}</h1>
          <p class="login-card__subtitle">
            {{ forgotPassword
              ? 'Enter the email on your account and we will send you a reset code.'
              : 'Sign in to your account to continue.' }}
          </p>
        </header>

        <el-form
          ref="loginForm"
          :model="loginForm"
          :rules="loginRules"
          class="login-form"
          label-position="top"
          hide-required-asterisk
          size="large"
          @submit.prevent="forgotPassword ? recoverPassword() : handleLogin()"
        >
          <el-form-item label="Email address" prop="email">
            <el-input
              v-model="loginForm.email"
              name="email"
              type="email"
              placeholder="you@example.com"
              autocomplete="email"
              :prefix-icon="Message"
            />
          </el-form-item>

          <el-form-item v-if="!forgotPassword" prop="password">
            <template #label>
              <span class="login-form__label-row">
                <span>Password</span>
                <button type="button" class="login-form__link" @click="forgotPassword = true">
                  Forgot password?
                </button>
              </span>
            </template>
            <el-input
              v-model="loginForm.password"
              name="password"
              type="password"
              placeholder="Enter your password"
              autocomplete="current-password"
              show-password
              :prefix-icon="Lock"
            />
          </el-form-item>

          <el-button
            class="login-form__submit"
            type="primary"
            native-type="submit"
            size="large"
            :loading="loading"
          >
            {{ forgotPassword ? 'Send reset code' : 'Sign in' }}
          </el-button>
        </el-form>

        <p v-if="forgotPassword" class="login-card__switch">
          <button type="button" class="login-form__link" @click="forgotPassword = false">
            <el-icon><ArrowLeft /></el-icon> Back to sign in
          </button>
        </p>
      </div>
    </main>
  </div>
</template>

<script>
import { markRaw } from 'vue';
import { ArrowLeft, Lock, Message } from '@element-plus/icons-vue';
import Resource from '@/api/resource';
import { useUserStore } from '@/store';
export default {
  name: 'Login',
  components: { ArrowLeft },
  data() {
    const validateUsername = (rule, value, callback) => {
      if (value.length < 1) {
        callback(new Error('Please enter a valid User ID or Email'));
      } else {
        callback();
      }
    };
    const validatePass = (rule, value, callback) => {
      if (value.length < 4) {
        callback(new Error('Password cannot be less than 4 digits'));
      } else {
        callback();
      }
    };
    return {
      forgotPassword: false,
      // markRaw: icon components must not be made reactive
      Message: markRaw(Message),
      Lock: markRaw(Lock),
      loginForm: {
        email: '',
        password: '',
      },
      loginRules: {
        email: [
          { required: true, trigger: 'blur', validator: validateUsername },
        ],
        password: [
          { required: true, trigger: 'blur', validator: validatePass },
        ],
      },
      loading: false,
      redirect: undefined,
    };
  },
  watch: {
    $route: {
      handler: function(route) {
        this.redirect = route.query && route.query.redirect;
      },
      immediate: true,
    },
    // Switching between "sign in" and "reset" should not carry over an error state
    forgotPassword() {
      this.$nextTick(() => {
        if (this.$refs.loginForm) {
          this.$refs.loginForm.clearValidate();
        }
      });
    },
  },
  methods: {
    handleLogin() {
      if (this.loading) {
        return;
      }
      this.$refs.loginForm.validate((valid) => {
        if (valid) {
          this.loading = true;
          useUserStore()
            .login(this.loginForm)
            .then(response => {
              if (response.role === 'customer') {
                this.$notify({
                  title: `You have successfully signed in`,
                });
                this.$router.push({ path: this.redirect || '/home' });
                // window.location = '/home';
              } else {
                window.location = '/dashboard';
              }
              // this.$router.push({ path: this.redirect || '/' });
              this.loading = false;
            })
            .catch((response) => {
              this.loading = false;
              // console.log(response)
            });
        } else {
          console.log('Please fill the form accordingly');
          return false;
        }
      });
    },
    recoverPassword() {
      const app = this;
      if (app.loading) {
        return;
      }
      // Only the email is needed here — don't make the password field block the request.
      app.$refs.loginForm.validateField('email', (valid) => {
        if (!valid) {
          return;
        }
        const confirmEmailResource = new Resource('auth/recover-password');
        app.loading = true;
        confirmEmailResource.store({ email: app.loginForm.email })
          .then(response => {
            app.$alert(response.message);
            app.loginForm.email = '';
            app.loading = false;
            app.$router.push({ path: '/reset-password' });
          })
          .catch(() => {
            // the shared axios interceptor already shows the server's error message
            app.loading = false;
          });
      });
    },
  },
};
</script>

<style lang="scss" scoped>
$brand-width: 46%;

.login-page {
  position: fixed;
  inset: 0;
  display: flex;
  overflow-y: auto;
  background: var(--color-surface);
  font-family: var(--font-sans);
  color: var(--color-text);
}

// ---- brand panel ------------------------------------------------------------
.login-page__brand {
  position: relative;
  flex: 0 0 $brand-width;
  display: flex;
  align-items: flex-end;
  padding: 64px;
  overflow: hidden;
  background: var(--color-navy);
  color: #fff;

  // navy wash so the photo reads as part of the brand rather than stock imagery
  &::after {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(180deg, rgba(19, 31, 110, 0.55) 0%, rgba(19, 31, 110, 0.92) 100%);
  }
}

.login-page__photo {
  position: absolute;
  inset: 0;
  width: 100%;
  height: 100%;
  object-fit: cover;
  object-position: 45% center;
}

.login-page__brand-inner {
  position: relative;
  z-index: 1;
  max-width: 420px;
}

.login-page__eyebrow {
  margin: 0 0 20px;
  font-size: 13px;
  font-weight: 600;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  color: var(--color-navy-light);
}

.login-page__tagline {
  margin: 0 0 20px;
  font-family: var(--font-serif);
  font-size: 52px;
  font-weight: 500;
  line-height: 1.05;
  color: #fff;
}

.login-page__lede {
  margin: 0;
  font-size: 16px;
  line-height: 1.6;
  color: rgba(255, 255, 255, 0.82);
}

// ---- form panel ----------------------------------------------------------------
.login-page__panel {
  position: relative;
  flex: 1 1 auto;
  display: flex;
  align-items: center;
  justify-content: center;
  min-width: 0;
  padding: 88px 32px 48px;
}

.login-page__back {
  position: absolute;
  top: 28px;
  left: 32px;
  display: inline-flex;
  align-items: center;
  gap: 6px;
  font-size: 14px;
  font-weight: 500;
  color: var(--color-text-muted);
  text-decoration: none;
  transition: color 0.15s ease;

  &:hover {
    color: var(--color-navy);
  }
}

.login-card {
  width: 100%;
  max-width: 400px;
}

.login-card__logo-link {
  display: inline-block;
  margin-bottom: 28px;
  line-height: 0;
}

.login-card__logo {
  display: block;
  width: 150px;
  height: auto;
}

.login-card__header {
  margin-bottom: 28px;
}

.login-card__title {
  margin: 0 0 8px;
  font-family: var(--font-serif);
  font-size: 38px;
  font-weight: 600;
  line-height: 1.1;
  color: var(--color-navy);
}

.login-card__subtitle {
  margin: 0;
  font-size: 15px;
  line-height: 1.5;
  color: var(--color-text-muted);
}

.login-card__switch {
  margin: 24px 0 0;
  text-align: center;
}

// ---- form -------------------------------------------------------------------------
.login-form {
  // Element Plus is styled through its own CSS variables/classes, so these need :deep.
  :deep(.el-form-item) {
    // styles/custom-style.scss forces `.el-form-item { margin-bottom: 0 !important }` app-wide
    margin-bottom: 30px !important;
  }

  :deep(.el-form-item__label) {
    display: block;
    width: 100%;
    padding: 0;
    margin-bottom: 8px;
    font-size: 14px;
    font-weight: 600;
    line-height: 1.2;
    color: var(--color-navy);
  }

  :deep(.el-input__wrapper) {
    min-height: 50px;
    padding: 0 14px;
    border-radius: 8px;
    background: var(--color-surface-alt);
    box-shadow: 0 0 0 1px var(--color-border) inset;
    transition: box-shadow 0.15s ease, background-color 0.15s ease;

    &:hover {
      box-shadow: 0 0 0 1px var(--color-navy-light) inset;
    }

    &.is-focus {
      background: #fff;
      box-shadow: 0 0 0 2px var(--color-navy) inset, 0 0 0 4px var(--color-navy-light);
    }
  }

  :deep(.el-input__inner) {
    height: 48px;
    font-size: 15px;
    color: var(--color-text);

    &::placeholder {
      color: #a3a8b3;
    }
  }

  :deep(.el-input__prefix),
  :deep(.el-input__suffix) {
    color: var(--color-text-muted);
    font-size: 18px;
  }

  :deep(.el-input__prefix-inner) {
    gap: 4px;
  }

  // validation state: keep the same shape, swap the ring colour
  :deep(.el-form-item.is-error .el-input__wrapper) {
    background: #fff;
    box-shadow: 0 0 0 1px var(--color-accent) inset;

    &.is-focus {
      box-shadow: 0 0 0 2px var(--color-accent) inset, 0 0 0 4px rgba(190, 23, 18, 0.15);
    }
  }

  :deep(.el-form-item__error) {
    padding-top: 6px;
    font-size: 13px;
    color: var(--color-accent);
  }
}

.login-form__label-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;
}

.login-form__link {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 0;
  border: 0;
  background: none;
  font: inherit;
  font-size: 13px;
  font-weight: 500;
  color: var(--color-accent);
  cursor: pointer;

  &:hover {
    color: var(--color-accent-hover);
    text-decoration: underline;
  }
}

.login-card__switch .login-form__link {
  font-size: 14px;
  color: var(--color-navy);

  &:hover {
    color: var(--color-navy-hover);
  }
}

// Element Plus draws the primary button from these variables, so setting them on
// the button itself restyles every state (hover, active, loading, disabled) at once.
.login-form__submit.el-button {
  --el-button-bg-color: var(--color-navy);
  --el-button-border-color: var(--color-navy);
  --el-button-hover-bg-color: var(--color-navy-hover);
  --el-button-hover-border-color: var(--color-navy-hover);
  --el-button-active-bg-color: var(--color-navy-hover);
  --el-button-active-border-color: var(--color-navy-hover);
  --el-button-disabled-bg-color: var(--color-navy);
  --el-button-disabled-border-color: var(--color-navy);

  width: 100%;
  height: 52px;
  margin-top: 6px;
  border-radius: 8px;
  font-size: 16px;
  font-weight: 600;
  letter-spacing: 0.02em;
  box-shadow: 0 6px 16px rgba(25, 46, 167, 0.22);
  transition: background-color 0.15s ease, box-shadow 0.15s ease, transform 0.1s ease;

  &:hover {
    box-shadow: 0 8px 20px rgba(25, 46, 167, 0.3);
  }

  &:active {
    transform: translateY(1px);
  }
}

// ---- responsive -----------------------------------------------------------------------
@media (max-width: 991px) {
  .login-page__brand {
    display: none;
  }
}

@media (max-width: 480px) {
  .login-page__panel {
    align-items: flex-start;
    padding: 76px 20px 32px;
  }

  .login-page__back {
    left: 20px;
  }

  .login-card__title {
    font-size: 32px;
  }
}

@media (prefers-reduced-motion: reduce) {
  .login-page *,
  .login-page *::before,
  .login-page *::after {
    transition: none !important;
  }
}
</style>
