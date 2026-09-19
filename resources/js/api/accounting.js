import request from '@/utils/request';

const get = (url, params) => request({ url: `/accounting/${url}`, method: 'get', params });
const send = (method, url, data) => request({ url: `/accounting/${url}`, method, data });

export const fetchOverview = (params) => get('overview', params);

// ---- transactions (income, expense, transfer, journal)
export const listTransactions = (params) => get('transactions', params);
export const showTransaction = (id) => get(`transactions/${id}`);
export const saveTransaction = (payload, id) => (id ? send('put', `transactions/${id}`, payload) : send('post', 'transactions', payload));
export const voidTransaction = (id, reason) => send('post', `transactions/${id}/void`, { reason });

// ---- chart of accounts
let accountCache = null;

/** the accounts, remembered for the session of the page: every form needs the same list */
export function fetchAccounts(force = false) {
  if (!accountCache || force) {
    accountCache = get('accounts', { with_balance: 1 }).then(r => r.accounts).catch(e => {
      accountCache = null;
      throw e;
    });
  }
  return accountCache;
}

export function saveAccount(payload, id) {
  accountCache = null;
  return id ? send('put', `accounts/${id}`, payload) : send('post', 'accounts', payload);
}

export function deleteAccount(id) {
  accountCache = null;
  return send('delete', `accounts/${id}`);
}

// ---- settings
export const fetchSettings = () => get('settings');
export const saveSettings = (payload) => send('put', 'settings', payload);
export const syncSales = () => send('post', 'sync-sales');
