<template>
  <div>
    <div v-if="load" class="related-products__grid">
      <ProductCardSkeleton v-for="n in 4" :key="n" />
    </div>
    <div v-else-if="items.length > 0" class="related-products__grid">
      <ProductCard
        v-for="item in items"
        :key="item.slug"
        :item="item"
        @wishlist="addItemToWishlist"
      />
    </div>
  </div>
</template>
<script>
import ProductCard from '@/components/ui/ProductCard.vue';
import ProductCardSkeleton from '@/components/ui/ProductCardSkeleton.vue';
import Resource from '@/api/resource';
import { useOrderStore } from '@/store';
export default {
  components: {
    ProductCard,
    ProductCardSkeleton,
  },
  props: {
    categoryId: {
      type: Number,
      default: () => null,
    },
    excludeItemId: {
      type: Number,
      default: () => null,
    },
  },
  data() {
    return {
      items: [],
      load: false,
      query: {
        page: 1,
        limit: 10,
      },
      total: 0,
    };
  },
  computed: {
    orderStore() {
      return useOrderStore();
    },
  },
  watch: {
    categoryId() {
      this.fetchItems();
    },
  },
  created() {
    this.fetchItems();
  },
  methods: {
    addItemToWishlist(item) {
      item.quantity = 1;
      this.orderStore.addItemToWishlist(item);
      this.$notify({
        title: `${item.name} is added to wish list`,
      });
    },
    fetchItems() {
      const app = this;
      const { limit, page } = app.query;
      const itemResource = new Resource('get-items');
      app.load = true;
      const param = app.query;
      param.category_id = app.categoryId;
      param.exclude_item_id = app.excludeItemId;
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
.related-products {
  &__grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 32px 24px;

    @media (max-width: 900px) {
      grid-template-columns: repeat(2, 1fr);
      gap: 24px 16px;
    }
  }
}
</style>
