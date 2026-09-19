// Run with: npm run test:js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { costSheetRows, parseCostSheet, landedPreview, SHEET_HEADERS } from '../../resources/js/utils/costSheet.js';

test('the cost sheet starts from what is on the shelf, with the cost column empty', () => {
  const rows = costSheetRows([
    { item_id: 7, product: 'Runner', category: 'Footwear', size: '42', qty: 12 },
    { item_id: 9, product: 'Tote', category: null, size: '', qty: 5 },
  ]);
  assert.deepEqual(rows[0], SHEET_HEADERS);
  assert.deepEqual(rows[1], [7, 'Runner', 'Footwear', '42', 12, '']);
  assert.deepEqual(rows[2], [9, 'Tote', '', '', 5, '']);
});

test('a filled-in sheet is read back by its headings, not its column positions', () => {
  const aoa = [
    ['My stock take, 30 Sept'],   // a title line above the table
    [],
    ['Unit cost (₦)', 'Size', 'Item ID', 'Product'],   // columns re-ordered
    ['1,250.50', 42, 7, 'Runner'],
    [' ₦800 ', '', 9, 'Tote'],
    [],
    ['', '', '', ''],
  ];
  const { rows, error } = parseCostSheet(aoa);
  assert.equal(error, '');
  assert.deepEqual(rows, [
    { item_id: 7, size: '42', unit_cost: 1250.5 },
    { item_id: 9, size: '', unit_cost: 800 },
  ]);
});

test('an empty cost stays empty and a non-number is passed on for the server to flag', () => {
  const { rows } = parseCostSheet([['Item ID', 'Size', 'Unit cost'], [1, 'M', ''], [2, 'M', 'abc'], [3, 'M', '0']]);
  assert.equal(rows[0].unit_cost, '');
  assert.equal(rows[1].unit_cost, 'abc');
  assert.equal(rows[2].unit_cost, 0);
});

test('a sheet that is not the cost sheet is refused with a useful message', () => {
  assert.match(parseCostSheet([['Name', 'Price'], ['a', 1]]).error, /Item ID/);
  assert.match(parseCostSheet([['Item ID', 'Size'], [1, 'M']]).error, /Unit cost/);
  assert.match(parseCostSheet([['Item ID', 'Unit cost']]).error, /no product rows/);
});

test('landed cost spreads extra costs by value and never loses a kobo', () => {
  // two products of equal value plus 1,000 freight: 500 each
  const even = landedPreview([
    { key: 'a|M', quantity: 10, unit_cost: 1000 },
    { key: 'b|L', quantity: 5, unit_cost: 2000 },
  ], 1000);
  assert.equal(even.items_total, 20000);
  assert.equal(even.landed_total, 21000);
  assert.equal(even.by_key['a|M'].cost, 10500);
  assert.equal(even.by_key['a|M'].unit, 1050);
  assert.equal(even.by_key['b|L'].cost, 10500);
  assert.equal(even.by_key['b|L'].unit, 2100);

  // 100.01 over three equal lines cannot divide into kobo: 33.33 + 33.33 + 33.35
  const odd = landedPreview([
    { key: 'c', quantity: 1, unit_cost: 100 }, { key: 'd', quantity: 1, unit_cost: 100 }, { key: 'e', quantity: 1, unit_cost: 100 },
  ], 100.01);
  assert.deepEqual(['c', 'd', 'e'].map(k => odd.by_key[k].cost), [133.33, 133.33, 133.35]);
  assert.equal(odd.landed_total, 400.01);
  assert.equal(['c', 'd', 'e'].reduce((s, k) => s + odd.by_key[k].costK, 0), 40001, 'exact, in kobo');
});

test('colours of one size share a layer', () => {
  const p = landedPreview([
    { key: 'runner|42', quantity: 10, unit_cost: 1000 },   // black
    { key: 'runner|42', quantity: 5, unit_cost: 1000 },    // white
  ], 150);
  assert.equal(p.by_key['runner|42'].quantity, 15);
  assert.equal(p.by_key['runner|42'].cost, 15150);
  assert.equal(p.by_key['runner|42'].unit, 1010);
});

test('incomplete lines are ignored in the preview, and no extras are charged without goods', () => {
  const p = landedPreview([{ key: 'a', quantity: 0, unit_cost: 100 }, { key: 'b', quantity: 3, unit_cost: 0 }], 500);
  assert.equal(p.items_total, 0);
  assert.equal(p.landed_total, 0);
  assert.deepEqual(p.by_key, {});
});
