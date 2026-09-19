// Run with: npm run test:js   (uses Node's built-in test runner — no dependencies)
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { amountInWords, numberToWords } from '../../resources/js/utils/amountInWords.js';

// ---- known-good table -------------------------------------------------------

test('whole naira amounts', () => {
  const expected = {
    0: 'zero naira only',
    1: 'one naira only',
    // Under 100 must not get a stray leading "and" (it used to: "and twenty five").
    25: 'twenty five naira only',
    99: 'ninety nine naira only',
    100: 'one hundred naira only',
    105: 'one hundred and five naira only',
    610: 'six hundred and ten naira only',
    1000: 'one thousand naira only',
    1005: 'one thousand and five naira only',
    1050: 'one thousand and fifty naira only',
    1100: 'one thousand one hundred naira only',
    5200: 'five thousand two hundred naira only',
    12300: 'twelve thousand three hundred naira only',
    29500: 'twenty nine thousand five hundred naira only',
    100000: 'one hundred thousand naira only',
    100001: 'one hundred thousand and one naira only',
    1050000: 'one million fifty thousand naira only',
    1250000: 'one million two hundred and fifty thousand naira only',
    1000001: 'one million and one naira only',
    2000000000: 'two billion naira only',
  };
  for (const [amount, words] of Object.entries(expected)) {
    assert.equal(amountInWords(Number(amount)), words, `amount ${amount}`);
  }
});

test('kobo is spoken, and decimals no longer shift the whole number', () => {
  // These used to come out as 3,752,000 / 125,000 / 1,499,000 (the "." was
  // treated as a digit).
  assert.equal(amountInWords(3752.25), 'three thousand seven hundred and fifty two naira, twenty five kobo only');
  assert.equal(amountInWords(1250.5), 'one thousand two hundred and fifty naira, fifty kobo only');
  assert.equal(amountInWords(1499.99), 'one thousand four hundred and ninety nine naira, ninety nine kobo only');
  assert.equal(amountInWords(0.75), 'seventy five kobo only');
  assert.equal(amountInWords(1.01), 'one naira, one kobo only');
  // floating-point noise must not leak into the words
  assert.equal(amountInWords(0.1 + 0.2), 'thirty kobo only');
});

test('numeric strings, as the API sends decimals', () => {
  assert.equal(amountInWords('12500.00'), 'twelve thousand five hundred naira only');
  assert.equal(amountInWords('29,500.00'), 'twenty nine thousand five hundred naira only');
  assert.equal(amountInWords(' 3400.5 '), 'three thousand four hundred naira, fifty kobo only');
});

test('invalid amounts give an empty string rather than nonsense', () => {
  for (const bad of ['', null, undefined, 'abc', NaN, Infinity, -5, '-1', {}]) {
    assert.equal(amountInWords(bad), '', `input ${String(bad)}`);
  }
});

test('numberToWords handles the edges', () => {
  assert.equal(numberToWords(0), 'zero');
  assert.equal(numberToWords(19), 'nineteen');
  assert.equal(numberToWords(20), 'twenty');
  assert.equal(numberToWords(999), 'nine hundred and ninety nine');
  assert.equal(numberToWords(Number.MAX_SAFE_INTEGER), 'nine quadrillion seven trillion one hundred and ninety nine billion two hundred and fifty four million seven hundred and forty thousand nine hundred and ninety one');
  assert.equal(numberToWords(-1), '');
  assert.equal(numberToWords(Number.MAX_SAFE_INTEGER + 2), '');
});

// ---- independent check: parse the words back into a number ------------------
// A separate, deliberately different implementation (a tokenising parser), so
// the converter is checked against something other than itself.

const WORD_VALUES = {
  zero: 0, one: 1, two: 2, three: 3, four: 4, five: 5, six: 6, seven: 7, eight: 8, nine: 9, ten: 10,
  eleven: 11, twelve: 12, thirteen: 13, fourteen: 14, fifteen: 15, sixteen: 16, seventeen: 17, eighteen: 18, nineteen: 19,
  twenty: 20, thirty: 30, forty: 40, fifty: 50, sixty: 60, seventy: 70, eighty: 80, ninety: 90,
};
const SCALE_VALUES = { thousand: 1e3, million: 1e6, billion: 1e9, trillion: 1e12, quadrillion: 1e15 };

function wordsToNumber(words) {
  let total = 0;
  let current = 0;
  for (const token of words.split(/\s+/)) {
    if (token === 'and' || token === '') {
      continue;
    }
    if (token in WORD_VALUES) {
      current += WORD_VALUES[token];
    } else if (token === 'hundred') {
      current *= 100;
    } else if (token in SCALE_VALUES) {
      total += current * SCALE_VALUES[token];
      current = 0;
    } else {
      throw new Error(`unexpected word "${token}" in "${words}"`);
    }
  }
  return total + current;
}

// small deterministic PRNG so failures are reproducible
function makeRandom(seed) {
  let s = seed;
  return () => {
    s = (s * 1664525 + 1013904223) % 4294967296;
    return s / 4294967296;
  };
}

test('every number round-trips through words and back', () => {
  const random = makeRandom(20260919);
  const samples = [];
  for (let n = 0; n <= 2000; n++) {
    samples.push(n); // every number up to 2000
  }
  for (let i = 0; i < 5000; i++) {
    samples.push(Math.floor(random() * 10 ** Math.floor(random() * 13))); // up to ~1e12, every magnitude
  }
  for (const n of samples) {
    assert.equal(wordsToNumber(numberToWords(n)), n, `${n} -> "${numberToWords(n)}"`);
  }
});

test('money with kobo round-trips (naira and kobo parsed separately)', () => {
  const random = makeRandom(7);
  for (let i = 0; i < 3000; i++) {
    const naira = Math.floor(random() * 10 ** Math.floor(random() * 10));
    const kobo = Math.floor(random() * 100);
    const words = amountInWords(naira + kobo / 100);
    assert.match(words, / only$/);
    const [nairaPart, koboPart] = words.replace(/ only$/, '').split(/, /);
    if (naira === 0 && kobo === 0) {
      assert.equal(words, 'zero naira only');
    } else if (naira === 0) {
      assert.equal(wordsToNumber(nairaPart.replace(/ kobo$/, '')), kobo);
    } else {
      assert.equal(wordsToNumber(nairaPart.replace(/ naira$/, '')), naira, words);
      if (kobo) {
        assert.equal(wordsToNumber(koboPart.replace(/ kobo$/, '')), kobo, words);
      } else {
        assert.equal(koboPart, undefined);
      }
    }
  }
});

test('"and" only ever appears where British usage puts it', () => {
  for (const n of [1, 9, 25, 99]) {
    assert.doesNotMatch(numberToWords(n), /^and\b/, `${n}`); // never a leading "and"
  }
  for (let n = 1; n < 100000; n += 37) {
    assert.doesNotMatch(numberToWords(n), /^and\b|\band$|\band and\b/, `${n}`);
  }
});
