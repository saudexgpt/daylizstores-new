<template>
  <div>
    <ItemDetailsSkeleton v-if="load" />
    <div v-else-if="selectedItem" class="item-details">
      <el-breadcrumb separator="|">
        <el-breadcrumb-item :to="{ path: '/' }">Home</el-breadcrumb-item>
        <el-breadcrumb-item :to="{ name: 'CategorizedItems', params: { categoryId: selectedItem.category_id }}">
          {{ selectedItem.category.name }}
        </el-breadcrumb-item>
        <el-breadcrumb-item>{{ selectedItem.name }}</el-breadcrumb-item>
      </el-breadcrumb>

      <div class="item-details__main">
        <div class="item-details__gallery">
          <div class="item-details__thumbs">
            <img
              v-for="(itemMedia, index) in selectedItem.media"
              :key="index"
              :src="itemMedia.thumbnail"
              class="item-details__thumb"
              :class="{ 'item-details__thumb--active': selectedImg === itemMedia.link }"
              loading="lazy"
              @click="selectedImg = itemMedia.link"
              @error="onImageError"
            >
          </div>
          <figure class="item-details__zoom" :style="`background-image: url(${selectedImg})`" @mousemove="zoom">
            <span v-if="isNew" class="item-details__new-badge">New</span>
            <span class="item-details__zoom-hint"><el-icon><ZoomIn /></el-icon></span>
            <img :src="selectedImg" @error="onImageError">
          </figure>
        </div>

        <div class="item-details__info">
          <small class="item-details__category">{{ selectedItem.category.name }}</small>
          <h1 class="item-details__name">{{ selectedItem.name }}</h1>

          <div v-if="(selectedItem.reviews_count > 0 && selectedItem.reviews_avg_star !== null) || soldCount > 0" class="item-details__meta">
            <span v-if="selectedItem.reviews_count > 0 && selectedItem.reviews_avg_star !== null" class="item-details__rating">
              <el-rate :model-value="parseFloat(selectedItem.reviews_avg_star) || 0" disabled />
              ({{ selectedItem.reviews_count }} reviews)
            </span>
            <span v-if="soldCount > 0" class="item-details__sold">{{ soldCount }} sold</span>
          </div>

          <p class="item-details__description">{{ selectedItem.description }}</p>

          <p class="item-details__price">
            <span v-if="detailPricing.percentOff > 0" class="item-details__price-original">₦{{ formatNumber(detailPricing.original, 2) }}</span>
            <span class="item-details__price-final">₦{{ formatNumber(detailPricing.final, 2) }}</span>
            <span v-if="detailPricing.percentOff > 0" class="item-details__price-off">{{ detailPricing.percentOff }}% OFF</span>
          </p>

          <div v-if="available_colors.length > 0" class="item-details__colors">
            <span class="item-details__label">Colors:</span>
            <button
              v-for="(color, index) in available_colors"
              :key="index"
              type="button"
              class="item-details__color-swatch"
              :class="{ 'item-details__color-swatch--selected': selectedColor === color }"
              :style="{ background: color }"
              :aria-label="`Select color ${color}`"
              @click="setItemDetailsForCart(color)"
            >
              <el-icon v-if="selectedColor === color"><IconCheck /></el-icon>
            </button>
          </div>

          <div v-if="stock_details.length > 0 && available_details.length > 0" class="item-details__sizes">
            <span class="item-details__label">Sizes:</span>
            <div class="item-details__size-grid">
              <button
                v-for="(stock, index) in stock_details"
                :key="index"
                type="button"
                class="item-details__size-box"
                :class="{
                  'item-details__size-box--selected': selectedProductStock.id === stock.id,
                  'item-details__size-box--disabled': stockBalance(stock) <= 0,
                }"
                :disabled="stockBalance(stock) <= 0"
                @click="productForCart(stock, stock.size)"
              >
                {{ stock.size }}
              </button>
            </div>
          </div>

          <div class="item-details__quantity-row">
            <span class="item-details__label">Quantity</span>
            <el-input-number v-model="quantity" :min="1" />
            <span
              v-if="selectedProductStock"
              class="item-details__stock-text"
              :class="{ 'item-details__stock-text--out': stockBalance(selectedProductStock) <= 0 }"
            >
              {{ stockBalance(selectedProductStock) > 0 ? `${stockBalance(selectedProductStock)} in stock` : 'Out of stock' }}
            </span>
          </div>
          <el-alert v-if="showQuantityOverflowError" type="error">Quantity is more than stock</el-alert>

          <div class="item-details__actions">
            <el-button :disabled="selectedItem.item_stocks.length < 1" type="primary" @click="addItemToCart(selectedItem)"><el-icon><IconShoppingCart /></el-icon> Add to Cart</el-button>
            </div>
          <button type="button" class="item-details__wishlist-link" @click="addItemToWishlist(selectedItem)">
            <i class="fas fa-heart" /> Add to Wishlist
          </button>
        </div>
      </div>

      <el-tabs type="card" class="item-details__tabs">
        <el-tab-pane>
          <template #label><span>Description</span></template>
          <p>{{ selectedItem.description || 'No description provided for this product yet.' }}</p>
        </el-tab-pane>
        <el-tab-pane>
          <template #label><span>Specifications</span></template>
          <p>No specifications have been listed for this product yet.</p>
        </el-tab-pane>
        <el-tab-pane>
          <template #label><span>Reviews {{ totalReviews > 0 ? `(${totalReviews})` : '' }}</span></template>
          <div v-loading="loadReview">
            <div v-if="totalReviews > 0" style="border: double #cccccc; border-radius: 10px; padding: 15px">
              <el-row :gutter="20">
                <el-col :xs="24" :sm="24" :md="16">
                  <div style="height: 300px;">
                    <el-row v-for="(review, review_index) in reviews" :key="review_index">
                      <el-col :xs="24" :sm="24" :md="8">
                        <img src="/images/no-image.png" width="50"><br>
                        {{ review.user.name }}
                        <el-rate
                          v-model="review.star"
                          disabled
                          text-color="#ff9900"
                        />
                      </el-col>
                      <el-col :xs="24" :sm="24" :md="16">
                        <aside>{{ review.comment }}</aside>
                      </el-col>
                    </el-row>
                  </div>
                  <pagination
                    v-show="totalReviews > 10"
                    :total="totalReviews"
                    v-model:page="query.page"
                    v-model:limit="query.limit"
                    @pagination="fetchReviews"
                  />
                </el-col>
                <el-col :xs="24" :sm="24" :md="8">
                  <h4>Based on {{ totalReviews }} reviews</h4>
                  <h2>{{ formatNumber(overallReview, 1) }}</h2>
                  <el-rate
                    v-model="overallReview"
                    disabled
                  />
                  <p>Overall</p>
                </el-col>
              </el-row>
            </div>
            <div v-else style="border: double #cccccc; border-radius: 10px; padding: 15px">
              <el-alert
                title="There are no reviews yet. Buy this product and give a review"
                type="error"
                effect="dark"
                :closable="false"
              />
            </div>
          </div>
        </el-tab-pane>
        <el-tab-pane>
          <template #label><span>Shipping &amp; Returns</span></template>
          <p>Collect in-store or have your order delivered — see checkout for pickup/delivery options.</p>
          <p>Any goods left unpicked is at owner's risk. No refunds after payment, no exchange after pickup.</p>
        </el-tab-pane>
      </el-tabs>

      <section class="item-details__related">
        <SectionHeader title="You may also like" />
        <related-products :category-id="selectedItem.category.id" :exclude-item-id="selectedItem.id" />
      </section>
    </div>
    <div v-if="!selectedItem && load === false">
      <error-404 />
    </div>
  </div>
</template>
<script>
import Pagination from '@/components/Pagination';
import SectionHeader from '@/components/ui/SectionHeader.vue';
import ItemDetailsSkeleton from '@/components/ui/ItemDetailsSkeleton.vue';
import { formatNumber, onImageError, isNewItem, roundMoney } from '@/utils/index';
import { ZoomIn } from '@element-plus/icons-vue';
import RelatedProducts from './partials/RelatedProducts.vue';
import Error404 from '@/views/error-page/404';
import Resource from '@/api/resource';
import { useOrderStore } from '@/store';
export default {
  name: 'ProductDetails',
  components: {
    Pagination,
    SectionHeader,
    ItemDetailsSkeleton,
    RelatedProducts,
    Error404,
    ZoomIn,
  },
  data() {
    return {
      load: false,
      select: 1,
      categories: [],
      selectedItem: {
        selectedColor: '',
        selectedSize: '',
      },
      selectedImg: '',
      showQuantityOverflowError: false,
      quantity: 1,
      available_colors: [],
      available_details: [],
      stock_details: [],
      selectedColor: '',
      selectedSize: '',
      selectedDetail: '',
      selectedProductStock: '',
      reviews: [],
      totalReviews: 0,
      overallReview: 0,
      query: {
        page: 1,
        limit: 10,
      },
      loadReview: false,
    };
  },
  computed: {
    orderStore() {
      return useOrderStore();
    },
    // Sizes are the only pricing dimension — a size-specific override wins,
    // otherwise the item's single base price. Color never affects price.
    resolvedPrice() {
      const item = this.selectedItem;
      if (!item || !item.price) {
        return 0;
      }
      const sizePrices = item.size_prices || [];
      const match = sizePrices.find(sizePrice => sizePrice.size === this.selectedSize);
      return match ? parseFloat(match.amount) : parseFloat(item.price.amount);
    },
    isNew() {
      return isNewItem(this.selectedItem);
    },
    soldCount() {
      const stocks = this.selectedItem.item_stocks || [];
      return stocks.reduce((sum, stock) => sum + parseInt(stock.sold || 0, 10), 0);
    },
    // A real, currently-active qty-1 discount tier (not a fabricated
    // "compare-at" price) — same rule calculateDiscounts() applies at
    // checkout time, evaluated here at quantity 1 for display purposes.
    detailPricing() {
      const original = this.resolvedPrice;
      const discounts = [...(this.selectedItem.discounts || [])].sort((a, b) => a.minimum_order_quantity - b.minimum_order_quantity);
      let final = original;
      discounts.forEach(discount => {
        if (1 >= discount.minimum_order_quantity) {
          final = parseFloat(discount.amount);
        }
      });
      const percentOff = final < original ? Math.round((1 - final / original) * 100) : 0;
      return { original, final, percentOff };
    },
  },
  created() {
    this.fetchItem();
    this.fetchReviews();
  },
  methods: {
    formatNumber,
    onImageError,
    stockBalance(stock) {
      return parseInt(stock.quantity_stocked - stock.reserved - stock.sold, 10);
    },
    quantityOverflow(quantity){
      const app = this;
      app.showQuantityOverflowError = false;
      const stock = app.selectedProductStock;
      const stockBalance = parseInt(stock.quantity_stocked - stock.reserved - stock.sold);
      if (quantity > stockBalance) {
        app.showQuantityOverflowError = true;
        return true;
      }
      return false;
    },
    calculateDiscounts(item, quantity){
      const standardAmount = this.resolvedPrice;
      item.rate = standardAmount;
      item.standardAmount = standardAmount;
      let price = roundMoney(quantity * standardAmount);
      let discountedAmount = standardAmount;
      if (item.discounts.length > 0) {
        const sortedDiscounts = [...item.discounts].sort((a, b) => a.minimum_order_quantity - b.minimum_order_quantity);
        sortedDiscounts.forEach(discount => {
          const moq = discount.minimum_order_quantity;
          if (quantity >= moq) {
            discountedAmount = discount.amount;
            item.rate = discountedAmount;
            price = roundMoney(quantity * discountedAmount);
          }
        });
      }
      item.subTotal = price;
      return item;
    },
    addItemToCart(item) {
      const app = this;
      const quantity = (app.quantity > 0) ? app.quantity : 1;
      const stock = app.selectedProductStock;
      const stockBalance = parseInt(stock.quantity_stocked - stock.reserved - stock.sold);
      if (app.quantityOverflow(quantity)) {
        item.quantity = stockBalance;
        return false;
      }
      item = app.calculateDiscounts(item, quantity);
      const new_name = `${item.name} - ${(app.selectedColor !== null) ? app.selectedColor : ''} - ${app.selectedSize}`;
      const param = {
        id: item.id,
        stock_id: stock.id,
        size: app.selectedSize,
        discounts: item.discounts,
        quantity: quantity,
        media: item.media,
        rate: item.rate,
        subTotal: item.subTotal,
        standardAmount: item.standardAmount,
        name: new_name,
      };
      app.orderStore.addItemToCart(param);
      app.$notify({
        title: `${item.name} is added to cart`,
      });
      return true;
    },
    addItemToWishlist(item) {
      const app = this;
      item.quantity = 1;
      item.new_name = `${item.name} - ${(app.selectedColor !== null) ? app.selectedColor : ''} - ${app.selectedSize}`;
      app.orderStore.addItemToWishlist(item);
      app.$notify({
        title: `${item.name} is added to wish list`,
      });
    },
    addItemToComparedItems(item) {
      item.quantity = 1;
      this.orderStore.addItemForComparison(item);
      this.$notify({
        title: `${item.name} is added for comparison`,
      });
    },
    zoom(e){
      var zoomer = e.currentTarget;
      const offsetX = (e.offsetX) ? e.offsetX : e.touches[0].pageX;
      const offsetY = (e.offsetY) ? e.offsetY : e.touches[0].pageX;
      const x = offsetX / zoomer.offsetWidth * 100;
      const y = offsetY / zoomer.offsetHeight * 100;
      zoomer.style.backgroundPosition = x + '% ' + y + '%';
    },
    fetchItem() {
      const app = this;
      const slug = app.$route.params.slug;
      app.load = true;

      const itemCategory = new Resource('item-details');
      itemCategory.list({ slug })
        .then(response => {
          app.selectedItem = response.item;
          app.selectedItem.selectedColor = '';
          app.selectedItem.selectedSize = '';
          if (response.item) {
            app.selectedImg = (response.item.media.length > 0) ? response.item.media[0].link : '/images/no-image.jpeg';

            app.setOtherItemDescription();
          }
          app.load = false;
        })
        .catch(error => {
          app.load = false;
          console.log(error);
        });
    },
    fetchReviews() {
      const param = this.query;
      const { limit, page } = param;
      param.item_id = this.$route.params.id;
      this.loadReview = true;
      const reviewsResource = new Resource('item-reviews');
      reviewsResource
        .list(param)
        .then((response) => {
          this.overallReview = parseFloat(response.average.overall);
          this.reviews = response.reviews.data;
          this.reviews.forEach((element, index) => {
            element['index'] = (page - 1) * limit + index + 1;
          });
          this.totalReviews = response.reviews.total;
          this.loadReview = false;
        })
        .catch((error) => {
          console.log(error);
          this.load = false;
        });
    },
    setOtherItemDescription() {
      const app = this;
      const colors = [];
      const itemStocks = app.selectedItem.item_stocks;
      itemStocks.forEach(stock => {
        // if (stock.color !== null) {
        colors.push(stock.color);
        // }
      });

      const uniqueColors = [...new Set(colors)];
      app.available_colors = uniqueColors;
      if (uniqueColors.length > 0) {
        app.selectedColor = uniqueColors[0];
        app.setItemDetailsForCart(app.selectedColor);
      }
    },
    setItemDetailsForCart(color) {
      const app = this;
      app.showQuantityOverflowError = false;
      const details = [];
      const itemStocks = app.selectedItem.item_stocks;
      const stock_details = itemStocks.filter(stock => stock.color === color);
      app.stock_details = stock_details;
      app.selectedColor = color;

      stock_details.forEach(stock => {
        if (stock.size !== null) {
          details.push(stock.size);
          app.selectedSize = stock.size;
        }
      });
      app.available_details = details;
      if (details.length > 0) {
        app.selectedSize = details[0];
      }
      app.productForCart(stock_details[0], app.selectedSize);
      // app.available_sizes = sizes;
    },
    productForCart(stock, size) {
      const app = this;
      app.selectedProductStock = stock;
      app.selectedSize = (size !== null) ? size : '';
    },

  },
};
</script>
<style lang="scss" scoped>
.item-details {
  &__main {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 40px;
    margin: 24px 0 48px;

    @media (max-width: 900px) {
      grid-template-columns: 1fr;
      gap: 24px;
    }
  }

  &__gallery {
    display: flex;
    gap: 12px;

    @media (max-width: 700px) {
      flex-direction: column-reverse;
    }
  }

  &__thumbs {
    display: flex;
    flex-direction: column;
    gap: 8px;
    overflow-y: auto;
    max-height: 440px;

    @media (max-width: 700px) {
      flex-direction: row;
      max-height: none;
      overflow-x: auto;
    }
  }

  &__thumb {
    width: 64px;
    height: 64px;
    object-fit: cover;
    border-radius: var(--radius-sm);
    border: 2px solid var(--color-border);
    cursor: pointer;
    flex-shrink: 0;

    &--active {
      border-color: var(--color-navy);
    }
  }

  &__zoom {
    position: relative;
    flex: 1;
    background-position: 50% 50%;
    overflow: hidden;
    cursor: zoom-in;
    border-radius: var(--radius-sm);
    background-color: var(--color-surface-alt);
    aspect-ratio: 1 / 1;

    img {
      transition: opacity 0.5s;
      display: block;
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    &:hover img {
      opacity: 0;
    }
  }

  &__new-badge {
    position: absolute;
    top: 12px;
    right: 12px;
    z-index: 1;
    background: var(--color-accent);
    color: #fff;
    font-family: var(--font-sans);
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    padding: 4px 10px;
    border-radius: var(--radius-sm);
  }

  &__zoom-hint {
    position: absolute;
    bottom: 12px;
    right: 12px;
    z-index: 1;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.9);
    border-radius: 50%;
    color: var(--color-navy);
  }

  &__category {
    font-family: var(--font-sans);
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--color-text-muted);
  }

  &__name {
    font-family: var(--font-serif);
    font-weight: 600;
    font-size: clamp(26px, 3vw, 36px);
    color: var(--color-navy);
    margin: 6px 0 10px;
  }

  &__meta {
    display: flex;
    align-items: center;
    gap: 16px;
    font-family: var(--font-sans);
    font-size: 13px;
    color: var(--color-text-muted);
    margin-bottom: 14px;
  }

  &__rating {
    display: flex;
    align-items: center;
    gap: 6px;
  }

  &__description {
    font-family: var(--font-sans);
    font-size: 14px;
    line-height: 1.7;
    color: var(--color-text);
    margin-bottom: 16px;
  }

  &__price {
    font-family: var(--font-sans);
    margin-bottom: 20px;
  }

  &__price-original {
    color: var(--color-text-muted);
    text-decoration: line-through;
    font-size: 16px;
    margin-right: 10px;
  }

  &__price-final {
    font-size: 28px;
    font-weight: 700;
    color: var(--color-navy);
  }

  &__price-off {
    display: inline-block;
    margin-left: 12px;
    color: var(--color-accent);
    font-size: 13px;
    font-weight: 700;
  }

  &__label {
    display: inline-block;
    font-family: var(--font-sans);
    font-size: 13px;
    font-weight: 600;
    color: var(--color-navy);
    margin-right: 10px;
  }

  &__colors {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 18px;
  }

  &__color-swatch {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    border: 2px solid var(--color-border);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #fff;

    &--selected {
      border-color: var(--color-navy);
    }
  }

  &__sizes {
    margin-bottom: 20px;
  }

  &__size-grid {
    display: inline-flex;
    flex-wrap: wrap;
    gap: 8px;
    vertical-align: middle;
  }

  &__size-box {
    min-width: 44px;
    height: 40px;
    padding: 0 10px;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-sm);
    background: var(--color-surface);
    font-family: var(--font-sans);
    font-size: 14px;
    cursor: pointer;

    &--selected {
      border-color: var(--color-navy);
      color: var(--color-navy);
      font-weight: 600;
    }

    &--disabled {
      opacity: 0.4;
      cursor: not-allowed;
      text-decoration: line-through;
    }
  }

  &__quantity-row {
    display: flex;
    align-items: center;
    gap: 14px;
    margin-bottom: 8px;
  }

  &__stock-text {
    font-family: var(--font-sans);
    font-size: 13px;
    font-weight: 600;
    color: #2e7d32;

    &--out {
      color: var(--color-accent);
    }
  }

  &__actions {
    display: flex;
    gap: 12px;
    margin: 20px 0 10px;

    .el-button {
      flex: 1;
    }
  }

  &__wishlist-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: none;
    border: none;
    cursor: pointer;
    font-family: var(--font-sans);
    font-size: 13px;
    color: var(--color-text-muted);

    &:hover {
      color: var(--color-accent);
    }
  }

  &__tabs {
    margin-bottom: 48px;
  }

  &__related {
    margin-bottom: 48px;
  }
}
</style>
