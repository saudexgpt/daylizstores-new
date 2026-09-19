<template>
  <div>
    <item-menu :category-id="categoryId" :lg="6" :md="8" />
  </div>
</template>
<script>
import ItemMenu from './Menu';
export default {
  name: 'CategorizedItems',
  components: {
    ItemMenu,
  },
  data() {
    return {
      categoryId: null,
    };
  },
  created() {
    this.categoryId = this.$route.params.categoryId;
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
