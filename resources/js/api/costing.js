import request from '@/utils/request';

const get = (url, params) => request({ url: `/costing/${url}`, method: 'get', params });
const post = (url, data) => request({ url: `/costing/${url}`, method: 'post', data });

// ---- is product costing switched on? (asked by several screens, so remembered until it changes)
let stateCache = null;

export function costingState(force = false) {
  if (!stateCache || force) {
    stateCache = get('state').catch(e => {
      stateCache = null;
      throw e;
    });
  }
  return stateCache;
}

export const costingLive = () => costingState().then(s => !!s.enabled).catch(() => false);

export function resetCostingState() {
  stateCache = null;
}

// ---- receiving stock
export const findProducts = (q) => get('receipts/products', { q });
export const paymentAccounts = () => get('payment-accounts').then(r => r.accounts);
export const saveReceipt = (payload) => post('receipts', payload);
export const listReceipts = (params) => get('receipts', params);
export const showReceipt = (id) => get(`receipts/${id}`);
export const voidReceipt = (id, reason) => post(`receipts/${id}/void`, { reason });

// ---- adjustments
export const listAdjustments = (params) => get('adjustments', params);
export const saveAdjustment = (payload) => post('adjustments', payload);

// ---- cut-over and the books-match-the-shelf check
export const fetchShelf = () => get('shelf');
export const checkCostSheet = (rows) => post('check', { rows });
export function goLive(rows, startDate) {
  resetCostingState();
  return post('go-live', { rows, start_date: startDate });
}
export const reconcile = () => get('reconcile');
