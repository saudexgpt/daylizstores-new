// Spells out money amounts for printed order totals ("Twelve thousand three
// hundred naira only"). Dependency-free on purpose, so it can be unit-tested
// with plain node (see tests/js/amountInWords.test.mjs).

const UNITS = ['', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten', 'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];
const TENS = ['', '', 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];
// Enough for every safe JavaScript integer (up to ~9 quadrillion).
const SCALES = ['', 'thousand', 'million', 'billion', 'trillion', 'quadrillion'];

// 1..999 -> "one hundred and twenty three"
function underOneThousand(n) {
  const hundreds = Math.floor(n / 100);
  const rest = n % 100;
  const words = [];
  if (hundreds) {
    words.push(UNITS[hundreds] + ' hundred');
  }
  if (rest) {
    if (hundreds) {
      words.push('and');
    }
    if (rest < 20) {
      words.push(UNITS[rest]);
    } else {
      words.push(TENS[Math.floor(rest / 10)] + (rest % 10 ? ' ' + UNITS[rest % 10] : ''));
    }
  }
  return words.join(' ');
}

/**
 * A whole, non-negative number in words (British/Nigerian style: "and" before
 * a final tens/units part, e.g. "one thousand and five").
 * Returns '' for anything that isn't a representable whole number.
 */
export function numberToWords(value) {
  let n = Math.floor(Number(value));
  if (!Number.isFinite(n) || n < 0 || n > Number.MAX_SAFE_INTEGER) {
    return '';
  }
  if (n === 0) {
    return 'zero';
  }
  // Split into groups of three digits, lowest first.
  const groups = [];
  while (n > 0) {
    groups.push(n % 1000);
    n = Math.floor(n / 1000);
  }
  const parts = [];
  for (let i = groups.length - 1; i >= 0; i--) {
    if (!groups[i]) {
      continue;
    }
    let words = underOneThousand(groups[i]);
    if (SCALES[i]) {
      words += ' ' + SCALES[i];
    }
    // "one thousand AND five", "one million AND fifty": the last group is
    // under a hundred and there's something bigger before it. (A single
    // group, e.g. 25, never gets one.)
    if (i === 0 && groups[0] < 100 && groups.length > 1) {
      words = 'and ' + words;
    }
    parts.push(words);
  }
  return parts.join(' ');
}

/**
 * A money amount in words, including kobo:
 *   29500    -> "twenty nine thousand five hundred naira only"
 *   1250.5   -> "one thousand two hundred and fifty naira, fifty kobo only"
 *   0.75     -> "seventy five kobo only"
 * Accepts numbers or numeric strings (the API sends decimals as strings such
 * as "12500.00"). Returns '' when the amount isn't a valid non-negative number.
 */
export function amountInWords(amount, major = 'naira', minor = 'kobo') {
  const cleaned = typeof amount === 'string' ? amount.replace(/[,\s]/g, '') : amount;
  if (cleaned === '' || cleaned === null || cleaned === undefined) {
    return '';
  }
  const value = Number(cleaned);
  if (!Number.isFinite(value) || value < 0) {
    return '';
  }
  const totalMinor = Math.round(value * 100);
  const majorPart = Math.floor(totalMinor / 100);
  const minorPart = totalMinor % 100;

  const majorWords = numberToWords(majorPart);
  if (!majorWords) {
    return '';
  }
  if (minorPart === 0) {
    return `${majorWords} ${major} only`;
  }
  const minorWords = numberToWords(minorPart);
  if (majorPart === 0) {
    return `${minorWords} ${minor} only`;
  }
  return `${majorWords} ${major}, ${minorWords} ${minor} only`;
}
