<template>
  <div class="box">
    <div class="box-header">
      <h4 class="box-title">Product Details</h4>
      <span class="pull-right">
        <a class="btn btn-danger" @click="page.option = 'list'"> Back</a>
      </span>
    </div>
    <div class="box-body">
      {{ itemInStock }}
    </div>
  </div>
</template>

<script>

export default {
  name: 'AddNewProduct',

  props: {

    itemInStock: {
      type: Object,
      default: () => ({}),
    },

    page: {
      type: Object,
      default: () => ({
        option: 'view_details',
      }),
    },

  },
  data() {
    return {

    };
  },

};
</script>

