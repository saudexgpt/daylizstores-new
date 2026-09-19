<template>
  <div v-loading="load">
    <div v-if="items.length > 0 && load === false">
      <div class="product-search__grid">
        <ProductCard
          v-for="item in items"
          :key="item.slug"
          :item="item"
          @wishlist="addItemToWishlist"
        />
      </div>
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
import Resource from '@/api/resource';
import { useOrderStore } from '@/store';
export default {
  name: 'ProductSearch',
  components: {
    Pagination,
    Error404,
    ProductCard,
  },
  data() {
    return {
      items: [],
      load: false,
      query: {
        page: 1,
        limit: 20,
      },
      total: 0,
    };
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
      const slug = app.$route.params.slug;
      const param = app.query;
      param.slug = slug;
      app.load = true;

      const itemCategory = new Resource('search-product');
      itemCategory.list(param)
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
.product-search {
  &__grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 32px 24px;
    margin-bottom: 32px;

    @media (max-width: 900px) {
      grid-template-columns: repeat(2, 1fr);
      gap: 24px 16px;
    }
  }
}
</style>
