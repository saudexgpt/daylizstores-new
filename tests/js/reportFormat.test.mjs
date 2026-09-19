// Run with: npm run test:js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
  money, naira, integer, percent, dateText, dateTimeText, formatCell, visibleColumns,
  periodPresets, excelRows, fileName,
} from '../../resources/js/utils/reportFormat.js';

test('money always shows two decimals and thousands separators', () => {
  assert.equal(money(1234.5), '1,234.50');
  assert.equal(money('19000'), '19,000.00');
  assert.equal(money(0), '0.00');
  assert.equal(money(-68600), '-68,600.00');
  assert.equal(money(0.005 + 0.1 + 0.2), '0.31'); // float noise is rounded away, not shown
});

test('missing values are blank, not "NaN" or "0"', () => {
  for (const f of [money, naira, integer, percent, dateText, dateTimeText]) {
    assert.equal(f(null), '', f.name);
    assert.equal(f(undefined), '', f.name);
    assert.equal(f(''), '', f.name);
  }
  assert.equal(money('abc'), '');
  assert.equal(formatCell(null, 'money'), '');
});

test('naira puts the sign before the currency symbol', () => {
  assert.equal(naira(1500), '₦1,500.00');
  assert.equal(naira(-250.5), '-₦250.50');
  assert.equal(naira(0), '₦0.00');
});

test('whole numbers and percentages', () => {
  assert.equal(integer(1234567), '1,234,567');
  assert.equal(integer(12.4), '12');
  assert.equal(percent(63.2), '63.2%');
  assert.equal(percent(100), '100.0%');
});

test('dates are read from the text, so no time zone can shift the day', () => {
  assert.equal(dateText('2026-03-05'), '5 Mar 2026');
  assert.equal(dateText('2026-12-31'), '31 Dec 2026');
  assert.equal(dateText('2026-01-01 00:00:00'), '1 Jan 2026');
  assert.equal(dateTimeText('2026-03-05 12:30:00'), '5 Mar 2026, 12:30');
  assert.equal(dateTimeText('2026-03-05T09:05:00.000000Z'), '5 Mar 2026, 09:05');
  assert.equal(dateText('nonsense'), '');
});

test('formatCell formats by column type', () => {
  assert.equal(formatCell(4400, 'money'), '4,400.00');
  assert.equal(formatCell(3, 'int'), '3');
  assert.equal(formatCell(66.7, 'percent'), '66.7%');
  assert.equal(formatCell('2026-03-20', 'date'), '20 Mar 2026');
  assert.equal(formatCell('Delivered', 'status'), 'Delivered');
  assert.equal(formatCell(0, 'money'), '0.00', 'a real zero still shows');
});

test('optional columns disappear when nothing uses them', () => {
  const columns = [
    { key: 'name', label: 'Product' },
    { key: 'variant', label: 'Size / colour', optional: true },
  ];
  assert.deepEqual(visibleColumns(columns, [{ name: 'A', variant: null }, { name: 'B', variant: '' }]).map(c => c.key), ['name']);
  assert.deepEqual(visibleColumns(columns, [{ name: 'A', variant: 'Black / 42' }]).map(c => c.key), ['name', 'variant']);
  assert.deepEqual(visibleColumns(columns, [], null).map(c => c.key), ['name']);
});

test('period presets are correct, including month and year boundaries', () => {
  const at = (iso) => Object.fromEntries(periodPresets(new Date(iso + 'T12:00:00')).map(p => [p.key, [p.from, p.to]]));

  const mid = at('2026-09-19'); // a Saturday
  assert.deepEqual(mid.today, ['2026-09-19', '2026-09-19']);
  assert.deepEqual(mid.yesterday, ['2026-09-18', '2026-09-18']);
  assert.deepEqual(mid.week, ['2026-09-14', '2026-09-19'], 'weeks start on Monday');
  assert.deepEqual(mid.last7, ['2026-09-13', '2026-09-19']);
  assert.deepEqual(mid.last30, ['2026-08-21', '2026-09-19']);
  assert.deepEqual(mid.month, ['2026-09-01', '2026-09-19']);
  assert.deepEqual(mid.lastmonth, ['2026-08-01', '2026-08-31']);
  assert.deepEqual(mid.quarter, ['2026-07-01', '2026-09-19']);
  assert.deepEqual(mid.year, ['2026-01-01', '2026-09-19']);
  assert.deepEqual(mid.lastyear, ['2025-01-01', '2025-12-31']);

  const newYear = at('2026-01-01'); // a Thursday
  assert.deepEqual(newYear.yesterday, ['2025-12-31', '2025-12-31'], 'yesterday crosses the year');
  assert.deepEqual(newYear.lastmonth, ['2025-12-01', '2025-12-31'], 'last month crosses the year');
  assert.deepEqual(newYear.week, ['2025-12-29', '2026-01-01']);

  const leap = at('2028-03-01');
  assert.deepEqual(leap.lastmonth, ['2028-02-01', '2028-02-29'], 'leap February');
});

test('excel rows keep numbers numeric, with formats, under a title block', () => {
  const rows = excelRows({
    title: 'Sales summary',
    filters: { from: '2026-03-01', to: '2026-03-31' },
    summary: [{ label: 'Net sales', value: 19000, type: 'money' }],
    columns: [
      { key: 'period', label: 'Date', type: 'text' },
      { key: 'orders', label: 'Orders', type: 'int' },
      { key: 'net', label: 'Net sales', type: 'money' },
      { key: 'note', label: 'Note', type: 'text', optional: true },
    ],
    rows: [{ period: '2026-03-05', orders: 2, net: 15000, note: null }, { period: '2026-03-20', orders: 1, net: 4000, note: null }],
    footer: { period: 'Total', orders: 3, net: 19000, note: null },
  }, new Date(2026, 8, 19, 14, 5));

  assert.equal(rows[0][0].v, 'Sales summary');
  assert.equal(rows[1][0].v, 'Period: 1 Mar 2026 to 31 Mar 2026');
  assert.equal(rows[2][0].v, 'Generated 19 Sep 2026, 14:05');
  assert.deepEqual(rows[3], []);
  assert.deepEqual(rows[4].map(c => c && c.v), ['Net sales', 19000]);
  assert.equal(rows[4][1].z, '#,##0.00');

  const header = rows[6];
  assert.deepEqual(header.map(c => c.v), ['Date', 'Orders', 'Net sales'], 'the empty optional column is left out');
  assert.equal(rows[7][1].t, 'n');
  assert.equal(rows[7][1].v, 2);
  assert.equal(rows[7][2].z, '#,##0.00');
  assert.equal(rows[7][0].t, 's');
  assert.equal(rows[9][0].v, 'Total');
  assert.equal(rows[9][0].bold, true);
  assert.equal(rows[9][2].v, 19000);
});

test('excel rows never turn text that looks like a formula into a formula', () => {
  const rows = excelRows({
    title: 'x', filters: {}, summary: [],
    columns: [{ key: 'name', label: 'Name', type: 'text' }],
    rows: [{ name: '=HYPERLINK("http://evil.example")' }],
  });
  const cell = rows.flat().find(c => c && String(c.v).startsWith('=HYPERLINK'));
  assert.equal(cell.t, 's', 'a string cell, so a spreadsheet will not evaluate it');
});

test('file names carry the period and stay filesystem-safe', () => {
  assert.equal(fileName('sales-summary', { from: '2026-03-01', to: '2026-03-31' }, 'xlsx'), 'sales-summary_2026-03-01_to_2026-03-31.xlsx');
  assert.equal(fileName('balance-sheet', { as_at: '2026-03-31' }), 'balance-sheet_as-at-2026-03-31.csv');
  assert.equal(fileName('a b/c', {}), 'a-b-c.csv');
});
