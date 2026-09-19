<template>
  <div class="item-menu">
    <div v-if="load" class="item-menu__grid">
      <ProductCardSkeleton v-for="n in skeletonCount" :key="n" />
    </div>
    <div v-else-if="items.length > 0" class="item-menu__grid">
      <ProductCard
        v-for="item in items"
        :key="item.slug"
        :item="item"
        @wishlist="addItemToWishlist"
        @add-to-cart="addItemToCart"
      />
    </div>
    <div v-if="!load">
      <pagination
        v-show="total > 0"
        :total="total"
        v-model:page="query.page"
        v-model:limit="query.limit"
        @pagination="fetchItems"
      />
    </div>
    <div v-if="items.length < 1 && load === false">
      <error-404 />
    </div>
  </div>
</template>
<script>
import Pagination from '@/components/Pagination';
import Error404 from '@/views/error-page/404';
import ProductCard from '@/components/ui/ProductCard.vue';
import ProductCardSkeleton from '@/components/ui/ProductCardSkeleton.vue';
import { formatNumber } from '@/utils/index';
import Resource from '@/api/resource';
import { useOrderStore } from '@/store';
export default {
  components: {
    Pagination,
    Error404,
    ProductCard,
    ProductCardSkeleton,
  },
  props: {
    categoryId: {
      type: String,
      default: () => null,
    },
    lg: {
      type: Number,
      default: 6,
    },
    md: {
      type: Number,
      default: 8,
    },
    sort: {
      type: String,
      default: () => null,
    },
    discounted: {
      type: Boolean,
      default: false,
    },
  },
  data() {
    return {
      items: [],
      selectedItem: null,
      dialogVisible: false,
      load: false,
      query: {
        page: 1,
        limit: 20,
      },
      total: 0,
      skeletonCount: 8,
    };
  },
  watch: {
    categoryId() {
      this.fetchItems();
    },
    sort() {
      this.fetchItems();
    },
    discounted() {
      this.fetchItems();
    },
  },
  created() {
    this.fetchItems();
  },
  computed: {
    orderStore() {
      return useOrderStore();
    },
  },
  methods: {
    formatNumber,
    addItemToCart(item) {
      const stock = item.item_stocks.find(s => (s.quantity_stocked - s.reserved - s.sold) > 0) || item.item_stocks[0];
      const sizePrice = (item.size_prices || []).find(sp => sp.size === stock.size);
      const standardAmount = sizePrice ? parseFloat(sizePrice.amount) : parseFloat(item.price.amount);
      let rate = standardAmount;
      if (item.discounts && item.discounts.length > 0) {
        item.discounts.forEach(discount => {
          if (1 >= discount.minimum_order_quantity) {
            rate = discount.amount;
          }
        });
      }
      this.orderStore.addItemToCart({
        id: item.id,
        stock_id: stock.id,
        size: stock.size,
        discounts: item.discounts,
        quantity: 1,
        media: item.media,
        rate,
        subTotal: rate,
        standardAmount,
        name: item.name,
      });
      this.$notify({
        title: `${item.name} is added to cart`,
      });
    },
    addItemToWishlist(item) {
      item.quantity = 1;
      this.orderStore.addItemToWishlist(item);
      this.$notify({
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
    itemDetails(item){
      const app = this;
      const slug = item.slug;
      app.$router.push({ name: 'ProductDetails', params: { slug }});
    },
    fetchItems() {
      const app = this;
      const { limit, page } = app.query;
      const itemResource = new Resource('get-items');
      app.load = true;
      const param = app.query;
      param.category_id = app.categoryId;
      param.sort = app.sort;
      param.discounted = app.discounted;
      itemResource.list(param)
        .then(response => {
          this.items = response.items.data;
          this.items.forEach((element, index) => {
            element['index'] = (page - 1) * limit + index + 1;
          });
          this.total = response.items.total;
          app.load = false;
        })
        .catch(error => {
          app.load = false;
          console.log(error);
        });
    },

  },
};
</script>
<style lang="scss" scoped>
.item-menu {
  &__grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 32px 24px;
    margin-bottom: 48px;

    @media (max-width: 900px) {
      grid-template-columns: repeat(2, 1fr);
      gap: 24px 16px;
    }
  }
}
</style>
