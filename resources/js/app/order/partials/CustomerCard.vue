<template>
  <el-card v-if="customer.user.name">
    <div class="user-profile">
      <h3>Customer Details</h3>
      <div class="user-avatar box-center">
        <pan-thumb :image="customer.user.photo" :height="'100px'" :width="'100px'" :hoverable="false" />
      </div>
      <div class="box-center">
        <div class="user-name text-center">
          <legend>{{ customer.user.name.toUpperCase() }}</legend>
        </div>
        <div v-if="customer.type" class="user-role text-center">
          {{ customer.type.name.toUpperCase() }}
        </div>
        <div class="user-role text-center text-muted">
          {{ customer.user.email }}
        </div>
        <div class="user-role text-center text-muted">
          {{ customer.user.phone }}
        </div>
        <div class="user-role text-center text-muted">
          Registered {{ moment(customer.created_at).fromNow() }}
        </div>
      </div>
    </div>
  </el-card>
</template>

<script>
import PanThumb from '@/components/PanThumb';
import moment from 'moment';
export default {
  components: { PanThumb },
  props: {
    customer: {
      type: Object,
      default: () => {
        return {
          user: {
            name: '',
            email: '',
            photo: '',
          },
          type: {
            name: '',
          },
        };
      },
    },
  },
  data() {
    return {
    };
  },
  methods: {
    moment,
  },
};
</script>

<style lang="scss" scoped>
.user-profile {
  .user-name {
    font-weight: bold;
  }
  .box-center {
    padding-top: 10px;
  }
  .user-role {
    padding-top: 10px;
    font-weight: 400;
    font-size: 14px;
  }
  .box-social {
    padding-top: 30px;
    .el-table {
      border-top: 1px solid #dfe6ec;
    }
  }
  .user-follow {
    padding-top: 20px;
  }
}
</style>
