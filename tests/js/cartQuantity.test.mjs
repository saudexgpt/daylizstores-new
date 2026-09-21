// Run with: npm run test:js   (uses Node's built-in test runner — no dependencies)
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { clampQuantity, knownAvailability, maxQuantity } from '../../resources/js/utils/cartQuantity.js';

test('a request within stock is accepted as asked', () => {
  assert.equal(clampQuantity(3, 10), 3);
  assert.equal(clampQuantity(10, 10), 10, 'the last unit is available');
  assert.equal(clampQuantity(1, 1), 1);
});

test('a request above stock is cut back to what is available — nobody can add more than exists', () => {
  assert.equal(clampQuantity(11, 10), 10);
  assert.equal(clampQuantity(9999, 5), 5);
  assert.equal(clampQuantity(Number.MAX_SAFE_INTEGER, 2), 2);
  assert.equal(clampQuantity(Infinity, 4, 2), 2, 'not a number: the line stays as it was');
});

test('quantities are whole numbers of at least one', () => {
  assert.equal(clampQuantity(0, 10), 1);
  assert.equal(clampQuantity(-4, 10), 1);
  assert.equal(clampQuantity(2.9, 10), 2);
  assert.equal(clampQuantity('4', 10), 4, 'a typed string');
  assert.equal(clampQuantity(' 7 ', 10), 7);
});

test('rubbish leaves the line as it was', () => {
  assert.equal(clampQuantity('lots', 10, 3), 3);
  assert.equal(clampQuantity(NaN, 10, 3), 3);
  assert.equal(clampQuantity(undefined, 10, 3), 3);
  assert.equal(clampQuantity(null, 10, 3), 1, 'null is 0 to Number(): clamped up to one');
  assert.equal(clampQuantity('lots', 10, 30), 10, 'and never above what is available');
});

test('nothing available means nothing can be bought', () => {
  assert.equal(clampQuantity(1, 0), null);
  assert.equal(clampQuantity(5, 0, 5), null);
  assert.equal(clampQuantity(5, -3, 5), null, 'a negative figure is treated as none');
});

test('while availability is unknown a line can be lowered but never raised', () => {
  for (const unknown of [null, undefined, '', 'abc', NaN]) {
    assert.equal(knownAvailability(unknown), null);
    assert.equal(maxQuantity(unknown, 4), 4);
    assert.equal(clampQuantity(5, unknown, 4), 4, 'no raising on a guess');
    assert.equal(clampQuantity(2, unknown, 4), 2, 'lowering is always fine');
    assert.equal(clampQuantity(1, unknown, 4), 1);
  }
});

test('zero is a real answer, not "unknown"', () => {
  assert.equal(knownAvailability(0), 0);
  assert.equal(knownAvailability('0'), 0);
  assert.equal(maxQuantity(0, 4), 0);
});

test('availability from the server is normalised', () => {
  assert.equal(knownAvailability('12'), 12);
  assert.equal(knownAvailability(7.8), 7);
  assert.equal(knownAvailability(-2), 0);
});

test('a broken current quantity does not break the cap', () => {
  assert.equal(maxQuantity(null, 'x'), 1);
  assert.equal(maxQuantity(null, 0), 1);
  assert.equal(maxQuantity(null, -5), 1);
});
