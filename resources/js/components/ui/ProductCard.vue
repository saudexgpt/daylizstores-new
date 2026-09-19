<template>
  <article class="product-card">
    <router-link :to="{ name: 'ProductDetails', params: { slug: item.slug } }" class="product-card__media">
      <img
        v-if="item.media && item.media.length > 0"
        :src="item.media[0].thumbnail"
        :alt="item.name"
        class="product-card__image"
        loading="lazy"
        @error="onImageError"
      >
      <img
        v-else
        src="/images/no-image.jpeg"
        :alt="item.name"
        class="product-card__image"
        loading="lazy"
      >
      <span v-if="outOfStock" class="product-card__badge product-card__badge--stock">Out of stock</span>
      <span v-else-if="isNew" class="product-card__badge product-card__badge--new">New</span>
    </router-link>

    <button
      type="button"
      class="product-card__wishlist"
      aria-label="Add to wishlist"
      @click="emit('wishlist', item)"
    >
      <i class="fas fa-heart" aria-hidden="true" />
    </button>

    <div class="product-card__body">
      <p v-if="item.category" class="product-card__category">{{ item.category.name }}</p>
      <h3 class="product-card__name">{{ item.name }}</h3>
      <p v-if="item.reviews_count > 0 && item.reviews_avg_star !== null" class="product-card__rating">
        <el-icon><StarFilled /></el-icon>
        {{ formatNumber(item.reviews_avg_star, 1) }}
        <span class="product-card__rating-count">({{ item.reviews_count }})</span>
      </p>
      <p class="product-card__price">
        <span v-if="pricing.isRange">From </span>
        <span v-if="pricing.percentOff > 0" class="product-card__price-original">₦{{ formatNumber(pricing.original, 2) }}</span>
        ₦{{ formatNumber(pricing.final, 2) }}
        <span v-if="pricing.percentOff > 0" class="product-card__price-off">{{ pricing.percentOff }}% OFF</span>
      </p>
      <BaseButton
        variant="primary"
        class="product-card__cta"
        @click="$router.push({name: 'ProductDetails', params: { slug: item.slug}})"
      >
        View Details
      </BaseButton>
    </div>
  </article>
</template>

<script setup>
import { computed } from 'vue';
import BaseButton from './BaseButton.vue';
import { StarFilled } from '@element-plus/icons-vue';
import { formatNumber, onImageError, resolveCardPricing, isNewItem } from '@/utils/index';

const props = defineProps({
  item: {
    type: Object,
    required: true,
  },
});

const emit = defineEmits(['wishlist', 'add-to-cart']);

const outOfStock = computed(() => !props.item.item_stocks || props.item.item_stocks.length < 1);
const pricing = computed(() => resolveCardPricing(props.item));
const isNew = computed(() => isNewItem(props.item));
</script>

<style lang="scss" scoped>
.product-card {
  position: relative;
  display: flex;
  flex-direction: column;

  &__media {
    position: relative;
    display: block;
    aspect-ratio: 1 / 1;
    overflow: hidden;
    background: var(--color-surface-alt);
    border-radius: var(--radius-sm);
  }

  &__image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.4s ease;
  }

  &__media:hover &__image {
    transform: scale(1.03);
  }

  &__badge {
    position: absolute;
    top: 12px;
    left: 12px;
    color: #fff;
    font-family: var(--font-sans);
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    padding: 4px 10px;
    border-radius: var(--radius-sm);

    &--new {
      background: var(--color-accent);
    }

    &--stock {
      background: var(--color-accent);
      top: auto;
      bottom: 12px;
    }
  }

  &__rating {
    display: flex;
    align-items: center;
    gap: 4px;
    font-family: var(--font-sans);
    font-size: 12px;
    font-weight: 600;
    color: var(--color-text);
    margin: 0 0 6px;

    .el-icon {
      color: #f5a623;
      font-size: 13px;
    }
  }

  &__rating-count {
    font-weight: 400;
    color: var(--color-text-muted);
  }

  &__price-original {
    color: var(--color-text-muted);
    text-decoration: line-through;
    font-weight: 400;
    margin-right: 4px;
  }

  &__price-off {
    display: inline-block;
    margin-left: 8px;
    color: var(--color-accent);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.02em;
  }

  &__wishlist {
    position: absolute;
    top: 12px;
    right: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    border-radius: 50%;
    border: none;
    background: rgba(255, 255, 255, 0.9);
    color: var(--color-navy);
    cursor: pointer;
    font-size: 14px;
    transition: color 0.2s ease;

    &:hover {
      color: var(--color-accent);
    }
  }

  &__body {
    padding-top: 14px;
  }

  &__category {
    font-family: var(--font-sans);
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--color-text-muted);
    margin: 0 0 4px;
  }

  &__name {
    font-family: var(--font-sans);
    font-size: 15px;
    font-weight: 600;
    color: var(--color-navy);
    margin: 0 0 6px;
  }

  &__price {
    font-family: var(--font-sans);
    font-size: 15px;
    font-weight: 600;
    color: var(--color-navy);
    margin: 0 0 14px;
  }

  &__cta {
    width: 100%;
  }
}
</style>
