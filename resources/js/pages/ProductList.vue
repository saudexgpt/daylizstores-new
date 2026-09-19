<template>
  <div>
    <SectionHeader v-if="heading" :title="heading" />
    <item-menu :category-id="categoryId" :sort="sort" :discounted="discounted" :lg="6" :md="8" />
  </div>
</template>
<script>
import ItemMenu from './Menu';
import SectionHeader from '@/components/ui/SectionHeader.vue';
export default {
  name: 'CategorizedItems',
  components: {
    ItemMenu,
    SectionHeader,
  },
  data() {
    return {
      categoryId: null,
      sort: null,
      discounted: false,
    };
  },
  computed: {
    heading() {
      if (this.discounted) {
        return 'Deals';
      }
      if (this.sort === 'newest') {
        return 'New Arrivals';
      }
      return null;
    },
  },
  created() {
    this.categoryId = this.$route.params.categoryId;
    this.sort = this.$route.query.sort || null;
    this.discounted = this.$route.query.discounted === '1' || this.$route.query.discounted === 'true';
  },
};
</script>
<style>
  .el-carousel__item h3 {
    color: #475669;
    font-size: 18px;
    opacity: 1;
    margin: 0;
    background-color: transparent;
  }

  .el-carousel__item:nth-child(2n) {
    background-color: transparent;
  }

  .el-carousel__item:nth-child(2n+1) {
    background-color: transparent;
  }
</style>
