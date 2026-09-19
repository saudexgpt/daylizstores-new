import { pluralize } from '@/filters';
export function parseTime(time, cFormat) {
  if (arguments.length === 0) {
    return null;
  }

  const format = cFormat || '{y}-{m}-{d} {h}:{i}:{s}';
  let date;
  if (typeof time === 'object') {
    date = time;
  } else {
    if (typeof time === 'string' && /^[0-9]+$/.test(time)) {
      time = parseInt(time) * 1000;
    }
    if (typeof time === 'number' && time.toString().length === 10) {
      time = time * 1000;
    }

    date = new Date(time);
  }
  const formatObj = {
    y: date.getFullYear(),
    m: date.getMonth() + 1,
    d: date.getDate(),
    h: date.getHours(),
    i: date.getMinutes(),
    s: date.getSeconds(),
    a: date.getDay(),
  };
  const timeStr = format.replace(/{(y|m|d|h|i|s|a)+}/g, (result, key) => {
    let value = formatObj[key];
    // Note: getDay() returns 0 on Sunday
    if (key === 'a') {
      return ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][value];
    }
    if (result.length > 0 && value < 10) {
      value = '0' + value;
    }

    return value || 0;
  });

  return timeStr;
}

export function formatTime(time, option) {
  time = +time * 1000;
  const d = new Date(time);
  const now = Date.now();

  const diff = (now - d) / 1000;

  if (diff < 30) {
    return 'Just now';
  } else if (diff < 3600) {
    // less 1 hour
    return pluralize(Math.ceil(diff / 60), ' minute') + ' ago';
  } else if (diff < 3600 * 24) {
    return pluralize(Math.ceil(diff / 3600), ' hour') + ' ago';
  } else if (diff < 3600 * 24 * 2) {
    return '1 day ago';
  }
  if (option) {
    return parseTime(time, option);
  } else {
    return (
      pluralize(d.getMonth() + 1, ' month') + ' ' +
      pluralize(d.getDate(), ' day') + ' ' +
      pluralize(d.getHours(), ' day') + ' ' +
      pluralize(d.getMinutes(), ' minute')
    );
  }
}

/**
 * Get query object from URL
 * @param {string} url
 */
export function getQueryObject(url) {
  url = url == null ? window.location.href : url;
  const search = url.substring(url.lastIndexOf('?') + 1);
  const obj = {};
  const reg = /([^?&=]+)=([^?&=]*)/g;
  search.replace(reg, (rs, $1, $2) => {
    const name = decodeURIComponent($1);
    let val = decodeURIComponent($2);
    val = String(val);
    obj[name] = val;
    return rs;
  });

  return obj;
}

/**
 * @param {string} input value
 * @returns {number} output value
 */
export function byteLength(str) {
  // returns the byte length of an utf8 string
  let s = str.length;
  for (var i = str.length - 1; i >= 0; i--) {
    const code = str.charCodeAt(i);
    if (code > 0x7f && code <= 0x7ff) {
      s++;
    } else if (code > 0x7ff && code <= 0xffff) {
      s += 2;
    }
    if (code >= 0xDC00 && code <= 0xDFFF) {
      i--;
    }
  }
  return s;
}

/**
 * Remove invalid (not equal true) elements from array
 *
 * @param {Array} actual
 */
export function cleanArray(actual) {
  const newArray = [];
  for (let i = 0; i < actual.length; i++) {
    if (actual[i]) {
      newArray.push(actual[i]);
    }
  }

  return newArray;
}

/**
 * Parse params from URL and return an object
 *
 * @param {string} url
 */
export function param2Obj(url) {
  const search = url.split('?')[1];
  if (!search) {
    return {};
  }
  return JSON.parse(
    '{"' +
    decodeURIComponent(search)
      .replace(/"/g, '\\"')
      .replace(/&/g, '","')
      .replace(/=/g, '":"')
      .replace(/\+/g, ' ') +
    '"}',
  );
}

/**
 * @param {string} val
 */
export function html2Text(val) {
  const div = document.createElement('div');
  div.innerHTML = val;

  return div.textContent || div.innerText;
}

/**
 * Merges two  objects, giving the last one precedence
 *
 * @param {Object} target
 * @param {Object} source
 */
export function objectMerge(target, source) {
  if (typeof target !== 'object') {
    target = {};
  }
  if (Array.isArray(source)) {
    return source.slice();
  }
  Object.keys(source).forEach(property => {
    const sourceProperty = source[property];
    if (typeof sourceProperty === 'object') {
      target[property] = objectMerge(target[property], sourceProperty);
    } else {
      target[property] = sourceProperty;
    }
  });

  return target;
}

/**
 * @param {HTMLElement} element
 * @param {string} className
 */
export function toggleClass(element, className) {
  if (!element || !className) {
    return;
  }
  let classString = element.className;
  const nameIndex = classString.indexOf(className);
  if (nameIndex === -1) {
    classString += '' + className;
  } else {
    classString =
      classString.substr(0, nameIndex) +
      classString.substr(nameIndex + className.length);
  }

  element.className = classString;
}

export const pickerOptions = [
  {
    text: 'Now',
    onClick(picker) {
      const end = new Date();
      const start = new Date(new Date().toDateString());
      end.setTime(start.getTime());
      picker.$emit('pick', [start, end]);
    },
  },
  {
    text: 'Last week',
    onClick(picker) {
      const end = new Date(new Date().toDateString());
      const start = new Date();
      start.setTime(end.getTime() - 3600 * 1000 * 24 * 7);
      picker.$emit('pick', [start, end]);
    },
  },
  {
    text: 'Last month',
    onClick(picker) {
      const end = new Date(new Date().toDateString());
      const start = new Date();
      start.setTime(start.getTime() - 3600 * 1000 * 24 * 30);
      picker.$emit('pick', [start, end]);
    },
  },
  {
    text: 'Last three months',
    onClick(picker) {
      const end = new Date(new Date().toDateString());
      const start = new Date();
      start.setTime(start.getTime() - 3600 * 1000 * 24 * 90);
      picker.$emit('pick', [start, end]);
    },
  },
];

export function getTime(type) {
  if (type === 'start') {
    return new Date().getTime() - 3600 * 1000 * 24 * 90;
  } else {
    return new Date(new Date().toDateString());
  }
}

/**
 * @param {Function} func
 * @param {number} wait
 * @param {boolean} immediate
 */
export function debounce(func, wait, immediate) {
  let timeout, args, context, timestamp, result;

  const later = function() {
    // According to the last trigger interval
    const last = new Date().getTime() - timestamp;

    // The last time the wrapped function was called, the interval is last less than the set time interval wait
    if (last < wait && last > 0) {
      timeout = setTimeout(later, wait - last);
    } else {
      timeout = null;
      // If it is set to immediate===true, since the start boundary has already been called, there is no need to call it here.
      if (!immediate) {
        result = func.apply(context, args);
        if (!timeout) {
          context = args = null;
        }
      }
    }
  };

  return function(...args) {
    context = this;
    timestamp = new Date().getTime();
    const callNow = immediate && !timeout;
    // If the delay does not exist, reset the delay
    if (!timeout) {
      timeout = setTimeout(later, wait);
    }
    if (callNow) {
      result = func.apply(context, args);
      context = args = null;
    }

    return result;
  };
}

/**
 * This is just a simple version of deep copy
 * Has a lot of edge cases bug
 * If you want to use a perfect deep copy, use lodash's _.cloneDeep
 * @param {Object} source
 * @returns {Object}
 */
export function deepClone(source) {
  if (!source && typeof source !== 'object') {
    throw new Error('error arguments', 'deepClone');
  }
  const targetObj = source.constructor === Array ? [] : {};
  Object.keys(source).forEach(keys => {
    if (source[keys] && typeof source[keys] === 'object') {
      targetObj[keys] = deepClone(source[keys]);
    } else {
      targetObj[keys] = source[keys];
    }
  });

  return targetObj;
}

/**
 * @param {Object[]} arr
 * @returns {Object[]}
 */
export function uniqueArr(arr) {
  return Array.from(new Set(arr));
}

/**
 * @returns {string}
 */
export function createUniqueString() {
  const timestamp = +new Date() + '';
  const randomNum = parseInt((1 + Math.random()) * 65536) + '';
  return (+(randomNum + timestamp)).toString(32);
}

/**
 * Check if an element has a class
 *
 * @param {HTMLElement} elm
 * @param {String} cls
 */
export function hasClass(ele, cls) {
  return !!ele.className.match(new RegExp('(\\s|^)' + cls + '(\\s|$)'));
}

/**
 * Add class to element
 *
 * @param {HTMLElement} elm
 * @param {String} cls
 */
export function addClass(ele, cls) {
  if (!hasClass(ele, cls)) {
    ele.className += ' ' + cls;
  }
}

/**
 * Remove class from element
 *
 * @param {HTMLElement} elm
 * @param {String} cls
 */
export function removeClass(ele, cls) {
  if (hasClass(ele, cls)) {
    const reg = new RegExp('(\\s|^)' + cls + '(\\s|$)');
    ele.className = ele.className.replace(reg, ' ');
  }
}

/**
 * Format Number with comma and decimal place
 *
 * @param {Number} num
 * @param {Number} decimalPlace
 */
export function formatNumber(num, decimalPlace) {
  const dec_no = parseFloat(num).toFixed(decimalPlace);
  const formated_no = Number(dec_no).toLocaleString('en', { minimumFractionDigits: decimalPlace, maximumFractionDigits: decimalPlace });

  return formated_no;
}

const NO_IMAGE_PLACEHOLDER = '/images/no-image.jpeg';

/**
 * @error handler for product images: swaps a broken/404 image src for the
 * placeholder. Guards against looping if the placeholder itself fails.
 */
export function onImageError(event) {
  const img = event.target;
  if (img.src.indexOf(NO_IMAGE_PLACEHOLDER) === -1) {
    img.src = NO_IMAGE_PLACEHOLDER;
  }
}

/**
 * Resolve the price to show on a product listing card. Sizes are the only
 * pricing dimension (color never affects price): each stocked size resolves
 * to its own price override if one was set, otherwise the item's base
 * price. When an item's actually-stocked sizes resolve to different
 * amounts, show the lowest one prefixed "From" rather than a single,
 * potentially-wrong number.
 */
export function resolveDisplayPrice(item) {
  const basePrice = parseFloat(item.price?.amount ?? 0);
  const sizePriceBySize = {};
  (item.size_prices || []).forEach(sizePrice => {
    sizePriceBySize[sizePrice.size] = parseFloat(sizePrice.amount);
  });

  const stockedSizes = [...new Set((item.item_stocks || []).map(stock => stock.size).filter(size => size))];
  const amounts = stockedSizes.length > 0
    ? stockedSizes.map(size => sizePriceBySize[size] ?? basePrice)
    : [basePrice];

  const min = Math.min(...amounts);
  const max = Math.max(...amounts);
  return { amount: min, isRange: min !== max };
}

/**
 * Card/detail-page pricing: the base "from" price, plus whatever a single
 * unit already qualifies for today (the lowest minimum_order_quantity tier,
 * if any) — this is a real, currently-active discount, not a fabricated
 * "compare-at" price, so items with no qty-1 tier just show a plain price.
 */
export function resolveCardPricing(item) {
  const { amount: original, isRange } = resolveDisplayPrice(item);
  const discounts = [...(item.discounts || [])].sort((a, b) => a.minimum_order_quantity - b.minimum_order_quantity);
  let final = original;
  discounts.forEach(discount => {
    if (1 >= discount.minimum_order_quantity) {
      final = parseFloat(discount.amount);
    }
  });
  const percentOff = final < original ? Math.round((1 - final / original) * 100) : 0;
  return { original, final, percentOff, isRange };
}

const NEW_ITEM_WINDOW_DAYS = 14;

/**
 * "NEW" badge — items created within the last two weeks, a defensible
 * heuristic since there's no dedicated is_new/featured flag in the schema.
 */
export function isNewItem(item) {
  if (!item.created_at) {
    return false;
  }
  const createdAt = new Date(item.created_at);
  const ageDays = (Date.now() - createdAt.getTime()) / (1000 * 60 * 60 * 24);
  return ageDays <= NEW_ITEM_WINDOW_DAYS;
}

/**
 * Round a money amount to 2 decimal places (kobo). Used instead of
 * parseInt(), which silently truncated e.g. 3752.25 to 3752 and made the
 * cart total disagree with the server-computed order total.
 */
export function roundMoney(amount) {
  return Math.round((Number(amount) + Number.EPSILON) * 100) / 100;
}
