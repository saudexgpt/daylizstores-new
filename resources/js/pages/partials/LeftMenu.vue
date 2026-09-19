<template>
  <div class="left-menu hide-mobile">
    <div class="left-menu__categories">
      <el-collapse v-model="activeName" accordion>
        <el-collapse-item name="1">
          <template #title><label>All Categories</label></template>
          <div
            v-for="(category, index) in categories"
            :key="index"
            class="left-menu__category-item"
            @click="loadPage('CategorizedItems', { categoryId: category.id})"
          >
            {{ category.name }}
          </div>
        </el-collapse-item>
      </el-collapse>
    </div>
    <div v-if="showLatestProduct" class="left-menu__latest">
      <h4 class="left-menu__heading">Latest Products</h4>
      <div
        v-for="(stock, stock_index) in latestProducts"
        :key="stock_index"
        class="left-menu__product-item"
        @click="loadPage('ProductDetails', { slug: stock.item.slug })"
      >
        <img
          v-if="stock.item.media.length > 0"
          :src="stock.item.media[0].thumbnail"
          class="left-menu__product-image"
          loading="lazy"
          :alt="stock.item.name"
          @error="onImageError"
        >
        <img v-else src="/images/no-image.jpeg" class="left-menu__product-image" loading="lazy" :alt="stock.item.name">
        <div class="left-menu__product-info">
          <span class="left-menu__product-name">{{ stock.item.name }}</span>
          <span class="left-menu__product-price">{{ '₦' + formatNumber(stock.item.price.amount, 2) }}</span>
        </div>
      </div>
    </div>
  </div>
</template>
<script>
import { formatNumber, onImageError } from '@/utils/index';
import { useItemsStore } from '@/store';
export default {
  props: {
    showLatestProduct: {
      type: Boolean,
      default: true,
    },
  },
  data() {
    return {
      activeName: '1',
    };
  },
  computed: {
    itemsStore() {
      return useItemsStore();
    },
    categories() {
      return this.itemsStore.categories;
    },
    latestProducts() {
      return this.itemsStore.latestProducts;
    },
  },
  methods: {
    formatNumber,
    onImageError,
    loadPage(name, param) {
      this.$router.push({ name, params: param });
    },
  },
};
</script>
<style lang="scss" scoped>
.left-menu {
  &__category-item {
    padding: 10px 12px;
    border-bottom: 1px solid var(--color-border);
    font-family: var(--font-sans);
    font-size: 14px;
    color: var(--color-text);
    cursor: pointer;
    transition: background-color 0.2s ease, color 0.2s ease, padding-left 0.2s ease;

    &:hover {
      background: var(--color-surface-alt);
      color: var(--color-navy);
      padding-left: 18px;
    }
  }

  &__heading {
    font-family: var(--font-sans);
    font-size: 14px;
    font-weight: 600;
    color: var(--color-navy);
    margin: 20px 0 12px;
  }

  &__product-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px;
    border-radius: var(--radius-sm);
    cursor: pointer;
    transition: background-color 0.2s ease, transform 0.2s ease;

    &:hover {
      background: var(--color-surface-alt);
      transform: translateX(4px);
    }
  }

  &__product-image {
    width: 56px;
    height: 56px;
    object-fit: cover;
    border-radius: var(--radius-sm);
    border: 1px solid var(--color-border);
    flex-shrink: 0;
  }

  &__product-info {
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 0;
  }

  &__product-name {
    font-family: var(--font-sans);
    font-size: 13px;
    color: var(--color-text);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
  }

  &__product-price {
    font-family: var(--font-sans);
    font-size: 13px;
    font-weight: 600;
    color: var(--color-navy);
  }
}
</style>
