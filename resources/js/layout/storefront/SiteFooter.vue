<template>
  <footer class="site-footer">
    <div class="site-footer__columns">
      <div class="site-footer__col site-footer__col--brand">
        <img src="/images/logo.png" alt="DayLiz Stores" class="site-footer__logo">
        <p class="site-footer__tagline">Timeless pieces for everyday living.</p>
        <div class="site-footer__social">
          <a href="https://web.facebook.com/daylizcollections" target="_blank" rel="noopener" aria-label="Facebook"><i class="fab fa-facebook" aria-hidden="true" /></a>
          <a href="https://web.whatsapp.com/send?phone=+2348146875777" target="_blank" rel="noopener" aria-label="WhatsApp"><i class="fab fa-whatsapp" aria-hidden="true" /></a>
          <a href="#" aria-label="Instagram"><i class="fab fa-instagram" aria-hidden="true" /></a>
        </div>
      </div>

      <div class="site-footer__col">
        <h3 class="site-footer__heading">Shop</h3>
        <router-link to="/home">Home</router-link>
        <router-link to="/product/list">All Products</router-link>
        <router-link to="/track/order">Track Order</router-link>
        <router-link to="/login">Login</router-link>
      </div>

      <div v-if="params" class="site-footer__col">
        <h3 class="site-footer__heading">Account Details</h3>
        <div class="site-footer__account" v-html="params.account_details" />
        <p class="site-footer__note">Any goods left unpicked is at owner's risk. No refunds after payment, no exchange after pickup.</p>
      </div>

      <div class="site-footer__col">
        <h3 class="site-footer__heading">Contact</h3>
        <p><i class="fa fa-map-marker-alt" aria-hidden="true" /> 23, Golden Bimot Plaza, Ashipa Road, Ayobo-Ipaja, Lagos, Nigeria</p>
        <p><i class="fa fa-phone-alt" aria-hidden="true" /> +234 814 687 5777</p>
        <p><i class="fa fa-envelope" aria-hidden="true" /> info@daylizstores.com</p>
      </div>

      <div class="site-footer__col">
        <h3 class="site-footer__heading">Opening Hours</h3>
        <p>Mondays &ndash; Fridays<br>10:00AM &ndash; 5:00PM</p>
      </div>
    </div>

    <div class="site-footer__copyright">
      <span>&copy; {{ new Date().getFullYear() }} Dayliz Stores. All rights reserved.</span>
      <span>Powered by <a href="https://3coretechnology.com" target="_blank" rel="noopener">3Core Technology Limited</a></span>
    </div>
  </footer>
</template>

<script setup>
import { useAppStore } from '@/store';
import { computed } from 'vue';

const appStore = useAppStore();
const params = computed(() => appStore.params);
</script>

<style lang="scss" scoped>
.site-footer {
  background: var(--color-dark);
  color: rgba(255, 255, 255, 0.85);
  margin-top: 0;

  &__columns {
    max-width: var(--content-max-wide);
    margin: 0 auto;
    padding: 56px 32px 40px;
    display: grid;
    grid-template-columns: 1.4fr repeat(4, 1fr);
    gap: 32px;

    @media (max-width: 900px) {
      grid-template-columns: repeat(2, 1fr);
    }

    @media (max-width: 560px) {
      grid-template-columns: 1fr;
    }

    a,
    p {
      display: block;
      font-family: var(--font-sans);
      font-size: 13px;
      color: rgba(255, 255, 255, 0.7);
      text-decoration: none;
      margin: 0 0 10px;
      line-height: 1.6;

      &:hover {
        color: #fff;
      }
    }

    i {
      margin-right: 8px;
      color: rgba(255, 255, 255, 0.5);
    }
  }

  &__col--brand {
    display: flex;
    flex-direction: column;
    align-items: flex-start;
  }

  &__logo {
    height: 40px;
    width: auto;
    margin-bottom: 12px;
    filter: brightness(0) invert(1);
  }

  &__tagline {
    font-family: var(--font-serif);
    font-style: italic;
    font-size: 15px;
    color: rgba(255, 255, 255, 0.7);
    margin: 0 0 16px;
  }

  &__social {
    display: flex;
    gap: 10px;

    a {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 32px;
      height: 32px;
      border-radius: 50%;
      border: 1px solid rgba(255, 255, 255, 0.25);
      margin: 0;

      &:hover {
        border-color: #fff;
      }
    }
  }

  &__heading {
    font-family: var(--font-sans);
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #fff;
    margin: 0 0 18px;
  }

  &__note {
    opacity: 0.55;
    font-size: 11px !important;
  }

  &__copyright {
    border-top: 1px solid rgba(255, 255, 255, 0.12);
    padding: 20px 32px;
    display: flex;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
    font-family: var(--font-sans);
    font-size: 12px;
    color: rgba(255, 255, 255, 0.55);

    a {
      color: inherit;
      text-decoration: underline;
    }
  }
}
</style>
