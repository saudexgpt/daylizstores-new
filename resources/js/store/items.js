import Resource from '@/api/resource';
import { get, set } from 'idb-keyval';
import { defineStore } from 'pinia';

function setItemsToDb(items, db) {
  set(db, JSON.stringify(items))
    .then().catch((err) => console.log('Cannots add item to cart!', err));
}

function fetchItemsInDb(db, store) {
  get(db).then((value) => {
    const valid_items = [];
    if (value) {
      const unsaved_items = JSON.parse(value);
      unsaved_items.forEach(item => {
        valid_items.push(item);
      });
    }
    switch (db) {
      case 'items':
        store.commitItems(valid_items);
        break;
      case 'categories':
        store.commitCategories(valid_items);
        break;
      default:
        break;
    }
  });
}

export const useItemsStore = defineStore('items', {
  state: () => ({
    allItems: [],
    categories: [],
    latestProducts: [],
  }),
  actions: {
    commitItems(items) {
      this.allItems = items;
    },
    commitCategories(categories) {
      this.categories = categories;
    },
    fetchAllItems() {
      const itemResource = new Resource('all-items');
      return itemResource.list()
        .then(response => {
          this.commitItems(response.items);
          setItemsToDb(response.items, 'items');
        })
        .catch(error => {
          console.log(error);
        });
    },
    fetchLatestProducts() {
      const latestProductResource = new Resource('latest-products');
      return latestProductResource.list().then(response => {
        this.latestProducts = response.stocks.data;
      }).catch(() => {});
    },
    fetchCategories() {
      const categoriesResource = new Resource('menu-category');
      return categoriesResource.list()
        .then(response => {
          this.commitCategories(response.categories);
          setItemsToDb(response.categories, 'categories');
        })
        .catch(error => {
          console.log(error);
        });
    },
    loadPersistentData() {
      return new Promise((resolve) => {
        fetchItemsInDb('items', this);
        fetchItemsInDb('categories', this);
        resolve();
      });
    },
  },
});
