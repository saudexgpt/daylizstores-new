<template>
  <div class="search-box">
    <el-autocomplete
      v-model="searchString"
      class="search-box__input"
      :fetch-suggestions="fetchSuggestions"
      placeholder="Search for products"
      :trigger-on-focus="false"
      @select="handleSearch"
      @keyup.enter="load(`/product/search/${searchString}`)"
    >
      <template #prefix>
        <el-icon class="search-box__icon"><Search /></el-icon>
      </template>
      <template #default="{ item }">
        <div class="search-box__suggestion">
          <span class="search-box__suggestion-name">{{ item.name }}</span>
          <span class="search-box__suggestion-category">{{ item.category.name }}</span>
        </div>
      </template>
    </el-autocomplete>
  </div>
</template>

<script>
import { Search } from '@element-plus/icons-vue';
import Resource from '@/api/resource';
import { useItemsStore } from '@/store';
export default {
  name: 'SearchBox',
  components: {
    Search,
  },
  data() {
    return {
      img: '/images/logo.png',
      showCartContent: false,
      selectedCategory: null,
      categories: [],
      searchString: '',
    };
  },
  computed: {
    itemsStore() {
      return useItemsStore();
    },
    allItems() {
      return this.itemsStore.allItems;
    },
  },
  created() {
    this.fetchItemCategories();
  },
  methods: {
    handleSearch(item) {
      const app = this;
      const slug = item.slug;
      const id = item.id;
      // app.$router.push({ path: `details/${slug}/${id}` });
      app.$router.push({ name: 'ProductDetails', params: { slug, id }});
    },
    showCategorizedProducts(categoryId) {
      this.$router.push({ name: 'CategorizedItems', params: { categoryId }});
    },
    fetchSuggestions(queryString, cb) {
      var items = this.allItems;
      var results = queryString ? items.filter(this.createFilter(queryString)) : items;
      // call callback function to return suggestions
      cb(results);
    },
    createFilter(queryString) {
      return (item) => {
        return (item.name.toLowerCase().indexOf(queryString.toLowerCase()) > -1);
      };
    },
    fetchItemCategories() {
      const app = this;
      const itemCategory = new Resource('menu-category');
      itemCategory.list()
        .then(response => {
        // app.categories = response.categories

          app.categories = response.categories;
        })
        .catch(error => {
          console.log(error);
        });
    },
    load(url) {
      this.$router.push({ path: url });
    },
  },
};
</script>
<style lang="scss" scoped>
.search-box {
  &__input {
    width: 100%;

    :deep(.el-input__wrapper) {
      background: var(--color-surface);
      border-radius: var(--radius-sm);
      box-shadow: none;
      border: 1px solid var(--color-border);
      padding: 4px 16px;

      &.is-focus {
        border-color: var(--color-navy);
      }
    }

    :deep(.el-input__inner) {
      font-family: var(--font-sans);
      font-size: 15px;
      height: 40px;
    }
  }

  &__icon {
    color: var(--color-text-muted);
    font-size: 16px;
  }

  &__suggestion {
    display: flex;
    align-items: baseline;
    justify-content: space-between;
    gap: 12px;
    font-family: var(--font-sans);

    &-name {
      color: var(--color-text);
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    &-category {
      color: var(--color-text-muted);
      font-size: 12px;
      white-space: nowrap;
    }
  }
}
</style>
