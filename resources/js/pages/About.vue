<template>
  <div class="about">
    <section class="about__hero">
      <div class="about__hero-copy">
        <p class="about__eyebrow">Our Story</p>
        <h1 class="about__title">Your happiness, our priority</h1>
        <p class="about__text">
          DayLiz Stores brings household items, souvenirs, kiddies wear, fashion and gadgets
          together in one place, at prices built for everyday life.
        </p>
        <p class="about__text">
          We're open for orders online &mdash; browse the shop, place your order, and collect
          in-store or have it delivered to you.
        </p>
        <BaseButton variant="accent" to="/product/list">Shop Now</BaseButton>
      </div>
      <div class="about__hero-media">
        <img src="/images/about6.jpg" alt="Customer shopping at DayLiz Stores" loading="lazy">
      </div>
    </section>

    <section class="about__values">
      <div class="about__value">
        <span class="about__value-icon"><el-icon :size="24"><Medal /></el-icon></span>
        <h3>Quality First</h3>
        <p>Every item is hand-checked before pickup or delivery.</p>
      </div>
      <div class="about__value">
        <span class="about__value-icon"><el-icon :size="24"><PriceTag /></el-icon></span>
        <h3>Affordable Prices</h3>
        <p>Quality products at prices built for everyday life.</p>
      </div>
      <div class="about__value">
        <span class="about__value-icon"><el-icon :size="24"><Van /></el-icon></span>
        <h3>Pickup &amp; Delivery</h3>
        <p>Collect in-store or have your order delivered to you.</p>
      </div>
      <div class="about__value">
        <span class="about__value-icon"><el-icon :size="24"><ChatDotRound /></el-icon></span>
        <h3>Customer Focused</h3>
        <p>Reach us directly on WhatsApp &mdash; your happiness is our priority.</p>
      </div>
    </section>

    <section class="about__promise">
      <div class="about__promise-media">
        <img src="/images/header.jpg" alt="Inside a DayLiz Stores aisle" loading="lazy">
      </div>
      <div class="about__promise-copy">
        <h2 class="about__promise-title">Our Promise</h2>
        <p class="about__text">
          We're more than a store &mdash; we want every order to be quick, honest, and worry-free,
          from browsing to pickup.
        </p>
        <p class="about__thank-you">Thank you for shopping with us.</p>
      </div>
    </section>

    <section id="contact" class="about__contact">
      <SectionHeader title="Get in Touch" align="center" />
      <div class="about__contact-grid">
        <div class="about__contact-item">
          <el-icon :size="20"><Location /></el-icon>
          <p>23, Golden Bimot Plaza, Opposite Musec Filling Station,<br>Ashipa Road, Amule bus stop,<br>Ayobo-Ipaja, Lagos, Nigeria</p>
        </div>
        <div class="about__contact-item">
          <el-icon :size="20"><Phone /></el-icon>
          <p>+234 814 687 5777</p>
        </div>
        <div class="about__contact-item">
          <el-icon :size="20"><Message /></el-icon>
          <p>info@daylizstores.com</p>
        </div>
      </div>
      <div class="about__socials">
        <a href="https://web.facebook.com/daylizcollections" target="_blank" rel="noopener" aria-label="Facebook"><i class="fab fa-facebook" aria-hidden="true" /></a>
        <a href="https://web.whatsapp.com/send?phone=+2348146875777" target="_blank" rel="noopener" aria-label="WhatsApp"><i class="fab fa-whatsapp" aria-hidden="true" /></a>
        <a href="https://instagram.com/dayliz_stores" target="_blank" rel="noopener" aria-label="Instagram"><i class="fab fa-instagram" aria-hidden="true" /></a>
      </div>
    </section>
  </div>
</template>
<script>
import { Medal, PriceTag, Van, ChatDotRound, Location, Phone, Message } from '@element-plus/icons-vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import SectionHeader from '@/components/ui/SectionHeader.vue';
export default {
  name: 'About',
  components: {
    BaseButton,
    SectionHeader,
    Medal,
    PriceTag,
    Van,
    ChatDotRound,
    Location,
    Phone,
    Message,
  },
};
</script>
<style lang="scss" scoped>
.about {
  &__hero {
    max-width: var(--content-max-wide);
    margin: 0 auto;
    padding: 56px 32px;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 56px;
    align-items: center;

    @media (max-width: 900px) {
      grid-template-columns: 1fr;
      padding: 40px 20px;
      gap: 32px;
    }
  }

  &__hero-media {
    border-radius: var(--radius-sm);
    overflow: hidden;

    @media (max-width: 900px) {
      order: -1;
    }

    img {
      width: 100%;
      height: 100%;
      max-height: 420px;
      object-fit: cover;
      display: block;
    }
  }

  &__eyebrow {
    font-family: var(--font-sans);
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.16em;
    text-transform: uppercase;
    color: var(--color-accent);
    margin: 0 0 16px;
  }

  &__title {
    font-family: var(--font-serif);
    font-weight: 600;
    font-size: clamp(32px, 4vw, 48px);
    line-height: 1.15;
    color: var(--color-navy);
    margin: 0 0 20px;
  }

  &__text {
    font-family: var(--font-sans);
    font-size: 15px;
    line-height: 1.7;
    color: var(--color-text);
    max-width: 460px;
    margin: 0 0 20px;
  }

  &__values {
    max-width: var(--content-max-wide);
    margin: 0 auto;
    padding: 48px 32px;
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 32px;
    border-top: 1px solid var(--color-border);

    @media (max-width: 900px) {
      grid-template-columns: repeat(2, 1fr);
      padding: 40px 20px;
      gap: 28px;
    }
  }

  &__value {
    text-align: center;

    h3 {
      font-family: var(--font-sans);
      font-size: 15px;
      font-weight: 600;
      color: var(--color-navy);
      margin: 16px 0 8px;
    }

    p {
      font-family: var(--font-sans);
      font-size: 13px;
      line-height: 1.6;
      color: var(--color-text-muted);
      margin: 0;
    }
  }

  &__value-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 56px;
    height: 56px;
    border-radius: 50%;
    background: var(--color-surface-alt);
    color: var(--color-navy);
  }

  &__promise {
    max-width: var(--content-max-wide);
    margin: 0 auto;
    padding: 48px 32px;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 56px;
    align-items: center;
    border-top: 1px solid var(--color-border);

    @media (max-width: 900px) {
      grid-template-columns: 1fr;
      padding: 40px 20px;
      gap: 32px;
    }
  }

  &__promise-media {
    border-radius: var(--radius-sm);
    overflow: hidden;

    img {
      width: 100%;
      max-height: 320px;
      object-fit: cover;
      display: block;
    }
  }

  &__promise-title {
    font-family: var(--font-serif);
    font-weight: 600;
    font-size: clamp(24px, 3vw, 32px);
    color: var(--color-navy);
    margin: 0 0 20px;
  }

  &__thank-you {
    font-family: var(--font-serif);
    font-style: italic;
    font-size: 20px;
    color: var(--color-accent);
    margin: 0;
  }

  &__contact {
    max-width: var(--content-max-wide);
    margin: 0 auto;
    padding: var(--space-section) 32px;
    border-top: 1px solid var(--color-border);
    scroll-margin-top: 140px;

    @media (max-width: 900px) {
      padding: 56px 20px;
    }
  }

  &__contact-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 32px;
    max-width: 900px;
    margin: 0 auto;

    @media (max-width: 900px) {
      grid-template-columns: 1fr;
      gap: 28px;
    }
  }

  &__contact-item {
    text-align: center;
    color: var(--color-navy);

    p {
      font-family: var(--font-sans);
      font-size: 14px;
      line-height: 1.6;
      color: var(--color-text);
      margin: 12px 0 0;
    }
  }

  &__socials {
    display: flex;
    justify-content: center;
    gap: 16px;
    margin-top: 40px;

    a {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: var(--color-surface-alt);
      color: var(--color-navy);
      font-size: 16px;
      transition: background-color 0.2s ease, color 0.2s ease;

      &:hover {
        background: var(--color-navy);
        color: #fff;
      }
    }
  }
}
</style>
