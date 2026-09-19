<template>
  <div class="app-container">
    <aside>
      <el-row :gutter="5">
        <el-col :xs="24" :sm="12" :md="12">
          <el-select v-model="params.warehouse_id" @change="loadChart()">
            <el-option value="" disabled="disabled">Select Warehouse</el-option>
            <el-option v-for="(warehouse, index) in warehouses" :key="index" :value="warehouse.id" :label="warehouse.name" />
          </el-select>
        </el-col>
      </el-row>
    </aside>

    <el-row :gutter="0" class="panel-group">
      <highcharts :options="products_in_stock" />
    </el-row>
  </div>

</template>
<script>

import Resource from '@/api/resource';
const chartDataFetch = new Resource('reports/admin/products-in-stock');
export default {

  props: {
    warehouses: {
      type: Array,
      default: () => ([]),
    },
  },
  data() {
    return {
      products_in_stock: {
        chart: {
          type: 'column',
          options3d: {
            enabled: false,
            alpha: 0,
            beta: 0,
            depth: 100,
            viewDistance: 25,
          },
          scrollablePlotArea: {
            minWidth: 900,
            scrollPositionX: 1,
          },
        },
        title: {
          text: '',
        },
        subtitle: {
          text: '',
        },
        xAxis: {
          type: 'category', // categories: [],
          labels: {
            skew3d: true,
            style: {
              fontSize: '14px',
            },
          },
        },
        yAxis: {
          min: 0,
          max: undefined,
          tickInterval: 500,
          title: {
            text: 'No. of Products',
          },
          stackLabels: {
            enabled: true,
            style: {
              fontWeight: 'bold',
              color: 'gray',
            },
          },
        },
        plotOptions: {
          column: {
            stacking: 'normal',
            dataLabels: {
              enabled: true,
            },
          },
        },
        series: [

        ],

        // colors: ['#063', '#910000'],
        credits: {
          enabled: false,
        },
      },
      params: {
        warehouse_id: '', // initially set the id of first warehouse
      },
    };
  },

  created: function() {
    this.loadChart();
  },

  methods: {

    loadChart() {
      const app = this;

      if (app.params.warehouse_id === '') {
        app.params.warehouse_id = app.warehouses[0].id;
      }
      const loader = chartDataFetch.loaderShow();
      chartDataFetch.list(app.params)
        .then(response => {
          app.products_in_stock.series = response.series;
          app.products_in_stock.categories = response.categories;
          app.products_in_stock.subtitle.text = response.subtitle;
          app.products_in_stock.title.text = response.title;
          loader.hide();
        })
        .catch(error => {
          loader.hide();
          console.log(error);
        });
    },

  },

};

</script>
<style>
.form-control {
  border-radius: 4px;
  box-shadow: none;
  border-color: #4db1eb;
  width: 100%;
  min-height: 35px;
}
</style>
