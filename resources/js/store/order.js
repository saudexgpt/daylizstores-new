import { get, set } from 'idb-keyval';
import { defineStore } from 'pinia';
import Resource from '@/api/resource';
import { roundMoney } from '@/utils/index';
import { clampQuantity, knownAvailability } from '@/utils/cartQuantity';

// Stepping a quantity up and down quickly must not fire a stock check per click (the API is rate-limited):
// the check runs once the customer pauses. `validationRun` numbers each check so a slow answer to an
// earlier one can never overwrite a newer state.
const REVALIDATE_AFTER_MS = 600;
let validationRun = 0;
let revalidateTimer = null;

function setItemsToDb(items, db) {
  set(db, JSON.stringify(items))
    .then().catch((err) => console.log('Cannots add item to cart!', err));
}
function setPendingOrderToDb(order) {
  set('order', JSON.stringify(order))
    .then().catch((err) => console.log('Cannots set pending order!', err));
}

// Both loaders return their promise so callers can genuinely wait for the
// IndexedDB read to finish before acting on the restored state.
function fetchItemsInDb(db, store) {
  return get(db).then((value) => {
    const valid_items = [];
    if (value) {
      const unsaved_items = JSON.parse(value);
      unsaved_items.forEach(item => {
        valid_items.push(item);
      });
    }
    switch (db) {
      case 'cart':
        store.setCartItems(valid_items);
        break;
      case 'wish_list':
        store.setWishlist(valid_items);
        break;
      case 'comparison':
        store.setComparedItems(valid_items);
        break;
      default:
        break;
    }
  });
}
function fetchPendingOrder(store) {
  return get('order').then((value) => {
    if (value) {
      store.setPendingOrder(JSON.parse(value));
    }
  });
}

/**
 * Discount minimum-order-quantity tiers are per (item, size) — color is
 * purely cosmetic and never splits or affects a discount bucket. Cart lines
 * for the same item+size (e.g. two different colors) are kept as separate
 * lines for display, but their quantities are pooled here to decide which
 * tier applies, then that tier's rate is applied back to every pooled line.
 */
function recalculateDiscounts(items) {
  const groups = {};
  items.forEach(line => {
    const key = `${line.id}_${line.size}`;
    (groups[key] = groups[key] || []).push(line);
  });
  Object.values(groups).forEach(lines => {
    const totalQuantity = lines.reduce((sum, line) => sum + parseInt(line.quantity), 0);
    const discounts = [...(lines[0].discounts || [])].sort((a, b) => a.minimum_order_quantity - b.minimum_order_quantity);
    let rate = lines[0].standardAmount;
    discounts.forEach(discount => {
      if (totalQuantity >= discount.minimum_order_quantity) {
        rate = discount.amount;
      }
    });
    lines.forEach(line => {
      line.rate = rate;
      line.subTotal = roundMoney(line.quantity * rate);
    });
  });
  return items;
}

export const useOrderStore = defineStore('order', {
  state: () => ({
    cart: [],
    wishList: [],
    comparedItems: [],
    pendingOrder: {
      amount: 0,
      cart_items: [],
    },
    // Keyed by stock_id — populated by validateCart() with any cart line
    // whose live stock balance has dropped below the requested quantity
    // since it was added, so the cart/checkout UI can flag and block it.
    stockIssues: {},
    // Keyed by stock_id — how many of each cart line can be bought right now (stocked − reserved − sold), as last
    // reported by the server. The cart's quantity controls stop here. A line missing from it is "not known yet",
    // and until it is known that line can be lowered but not raised.
    stockLevels: {},
  }),
  actions: {
    addItemToCart(item) {
      const carts = this.cart;
      const index = carts.map(function(cart) {
        return parseInt(cart.stock_id);
      }).indexOf(parseInt(item.stock_id));
      if (index > -1) {
        carts.splice(index, 1, item);
      } else {
        carts.push(item);
      }
      this.setCartItems(carts);
    },
    setCartItems(items) {
      this.cart = recalculateDiscounts(items);
      setItemsToDb(this.cart, 'cart');
    },
    addItemToWishlist(item) {
      let count = 0;
      this.wishList.forEach(wishList => {
        if (wishList.id === item.id) {
          count++;
        }
      });
      if (count < 1) {
        this.wishList.push(item);
      }
      setItemsToDb(this.wishList, 'wish_list');
    },
    setWishlist(items) {
      this.wishList = items;
      setItemsToDb(this.wishList, 'wish_list');
    },
    addItemForComparison(item) {
      let count = 0;
      this.comparedItems.forEach(comparedItem => {
        if (comparedItem.id === item.id) {
          count++;
        }
      });
      if (count < 0) {
        this.comparedItems.push(item);
      }
      setItemsToDb(this.comparedItems, 'comparison');
    },
    setComparedItems(items) {
      this.comparedItems = items;
      setItemsToDb(this.comparedItems, 'comparison');
    },
    setPendingOrder(order) {
      Object.assign(this.pendingOrder, order);
      setPendingOrderToDb(this.pendingOrder);
    },
    /**
     * Re-checks live stock for every line currently in the cart. Populates
     * stockIssues (keyed by stock_id) with anything that's sold out or
     * dropped below the cart's requested quantity, so the cart/checkout UI
     * can flag it and block the customer from ordering it — rather than
     * only finding out after Submit Order.
     */
    validateCart() {
      const run = ++validationRun;
      if (this.cart.length < 1) {
        this.stockIssues = {};
        this.stockLevels = {};
        return Promise.resolve();
      }
      const validateCartResource = new Resource('order/validate-cart');
      return validateCartResource.store({ cart_items: this.cart })
        .then(response => {
          if (run !== validationRun) {
            return; // the cart changed while this was on its way: a newer check is coming, and it will decide
          }
          const levels = {};
          Object.entries(response.stock_levels || {}).forEach(([stockId, available]) => {
            const known = knownAvailability(available);
            if (known !== null) {
              levels[stockId] = known;
            }
          });
          this.stockLevels = levels;
          const issues = {};
          (response.details || []).forEach(detail => {
            issues[detail.updated_item.stock_id] = {
              balance: detail.balance,
              product: detail.product,
            };
          });
          this.stockIssues = issues;
        })
        .catch(error => {
          console.log(error);
        });
    },
    /**
     * Sets how many of a cart line the customer wants, never above what is available (see stockLevels) and never
     * below one, then re-prices the cart (discount tiers are pooled per item and size) and saves it.
     * Returns the quantity the line ended up with, or null when the line is missing or has nothing available.
     * This only decides what the screen allows: the order is checked again, under a lock, when it is placed.
     */
    setLineQuantity(stockId, requested) {
      const line = this.cart.find(l => parseInt(l.stock_id) === parseInt(stockId));
      if (!line) {
        return null;
      }
      const quantity = clampQuantity(requested, this.stockLevels[line.stock_id], line.quantity);
      if (quantity === null) {
        return null;
      }
      if (quantity !== parseInt(line.quantity)) {
        line.quantity = quantity;
        this.setCartItems(this.cart);
        this.syncPendingOrder();
        // any check now in flight was made for the old quantity: drop its answer and ask again once things settle
        validationRun++;
        this.scheduleValidation();
      }
      // a line that was flagged as short is fine again once it is within what is available
      const issue = this.stockIssues[line.stock_id];
      const level = knownAvailability(this.stockLevels[line.stock_id]);
      if (issue && level !== null && quantity <= level) {
        const { [line.stock_id]: cleared, ...rest } = this.stockIssues;
        this.stockIssues = rest;
      }

      return quantity;
    },
    /** validateCart(), but once the customer has stopped stepping the quantities for a moment. */
    scheduleValidation() {
      clearTimeout(revalidateTimer);
      revalidateTimer = setTimeout(() => this.validateCart(), REVALIDATE_AFTER_MS);
    },
    /**
     * Rebuilds the pending order (what the checkout page shows and submits)
     * from the current cart, so the two can never drift apart.
     */
    syncPendingOrder() {
      const amount = this.cart.reduce((sum, line) => sum + line.subTotal, 0);
      this.setPendingOrder({ amount: roundMoney(amount), cart_items: this.cart });
    },
    /**
     * Applies the server's stock verdict (a "check_cart" response) to the
     * cart: a line with nothing left is removed, a line that's short is cut
     * back to what's available. Only stock_id and balance are read from the
     * server's `details` — the cart's own line data (images, discount tiers,
     * size) is kept, and discount tiers/subtotals are recomputed here.
     */
    applyStockAdjustments(details) {
      details.forEach(detail => {
        const stockId = parseInt(detail.updated_item.stock_id);
        const index = this.cart.findIndex(line => parseInt(line.stock_id) === stockId);
        if (index < 0) {
          return;
        }
        if (detail.balance <= 0) {
          this.cart.splice(index, 1);
        } else {
          this.cart[index].quantity = detail.balance;
        }
      });
      this.setCartItems(this.cart);
      this.syncPendingOrder();
    },
    loadOfflineData() {
      return Promise.all([
        fetchItemsInDb('cart', this),
        fetchItemsInDb('wish_list', this),
        fetchItemsInDb('comparison', this),
        fetchPendingOrder(this),
      ]);
    },
  },
});
