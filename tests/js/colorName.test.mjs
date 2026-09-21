// Run with: npm run test:js   (uses Node's built-in test runner — no dependencies)
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { colorLabel, hasNamedColors } from '../../resources/js/utils/colorName.js';

test('plain names stay as they are', () => {
  for (const name of ['Black', 'Brown', 'Gold', 'Navy']) {
    assert.equal(colorLabel(name), name);
  }
});

test('joined-up CSS names get their spaces back', () => {
  assert.equal(colorLabel('DarkRed'), 'Dark Red');
  assert.equal(colorLabel('PeachPuff'), 'Peach Puff');
  assert.equal(colorLabel('DeepPink'), 'Deep Pink');
  assert.equal(colorLabel('LightGoldenRodYellow'), 'Light Golden Rod Yellow');
});

test('capitals and lower case are tidied', () => {
  assert.equal(colorLabel('CREAM'), 'Cream');
  assert.equal(colorLabel('red'), 'Red');
  assert.equal(colorLabel('  dark   green '), 'Dark green');
});

test('two-colour products read as two colours', () => {
  assert.equal(colorLabel('Black/White'), 'Black / White');
  assert.equal(colorLabel('White/Pink'), 'White / Pink');
  assert.equal(colorLabel('DarkRed/CREAM'), 'Dark Red / Cream');
});

test('stock with no colour gets a name rather than "null"', () => {
  for (const none of [null, undefined, '', '   ']) {
    assert.equal(colorLabel(none), 'Standard');
  }
  assert.equal(colorLabel('/'), 'Standard');
});

test('a colour picker is only worth showing when there is a real colour', () => {
  assert.equal(hasNamedColors([null]), false);
  assert.equal(hasNamedColors([null, '']), false);
  assert.equal(hasNamedColors([]), false);
  assert.equal(hasNamedColors(undefined), false);
  assert.equal(hasNamedColors([null, 'Black']), true);
  assert.equal(hasNamedColors(['Black']), true);
});
