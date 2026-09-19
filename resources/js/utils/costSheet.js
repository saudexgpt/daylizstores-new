/**
 * Pure helpers for product costing screens (no Vue, no DOM — unit-tested):
 *  - building the cost sheet a person fills in, and reading it back after they have,
 *  - previewing the landed cost of a delivery exactly as the server will work it out.
 */

export const SHEET_HEADERS = ['Item ID', 'Product', 'Category', 'Size', 'Units on shelf', 'Unit cost (₦)'];

/** the rows of the sheet to download: what the system says is on the shelf, with an empty cost column to fill in */
export function costSheetRows(shelf) {
  return [SHEET_HEADERS, ...shelf.map(r => [r.item_id, r.product, r.category || '', r.size || '', r.qty, ''])];
}

const clean = (v) => (v === null || v === undefined ? '' : String(v).trim());

/**
 * Read a filled-in sheet (array of arrays, e.g. from XLSX.utils.sheet_to_json({ header: 1 })) back into
 * [{ item_id, size, unit_cost }]. Columns are found by their heading, so a re-ordered or extended sheet still
 * works. A cost is a number; anything that is not a number is passed on as text so the server can point at it.
 */
export function parseCostSheet(aoa) {
  const headerAt = aoa.findIndex(row => row.some(c => clean(c).toLowerCase() === 'item id'));
  if (headerAt < 0) {
    return { rows: [], error: 'This does not look like the cost sheet: there is no "Item ID" column. Download the sheet again and fill in its last column.' };
  }
  const head = aoa[headerAt].map(c => clean(c).toLowerCase());
  const col = {
    id: head.indexOf('item id'),
    size: head.indexOf('size'),
    cost: head.findIndex(h => h.startsWith('unit cost')),
  };
  if (col.cost < 0) {
    return { rows: [], error: 'The sheet has no "Unit cost" column.' };
  }

  const rows = [];
  aoa.slice(headerAt + 1).forEach(line => {
    const id = clean(line[col.id]);
    if (id === '') {
      return; // blank or spacer line
    }
    const rawCost = clean(line[col.cost]).replace(/[₦,\s]/g, '');
    rows.push({
      item_id: Number(id),
      size: col.size >= 0 ? clean(line[col.size]) : '',
      unit_cost: rawCost === '' ? '' : (Number.isNaN(Number(rawCost)) ? rawCost : Number(rawCost)),
    });
  });

  return { rows, error: rows.length ? '' : 'The sheet has no product rows.' };
}

const toKobo = (naira) => Math.round((Number(naira) || 0) * 100);
const fromKobo = (kobo) => kobo / 100;

/**
 * The landed cost of a delivery: extra costs (freight, customs) spread across the lines in proportion to their value,
 * in whole kobo, the last line taking the remainder — the same arithmetic the server uses, so what you see is what is booked.
 *
 * @param {Array<{key:string, quantity:number, unit_cost:number}>} lines   key = product + size (its cost layer)
 * @param {number} extraCosts
 */
export function landedPreview(lines, extraCosts) {
  const valid = lines.filter(l => Number(l.quantity) > 0 && toKobo(l.unit_cost) > 0);
  const values = valid.map(l => Math.round(Number(l.quantity)) * toKobo(l.unit_cost));
  const itemsK = values.reduce((a, b) => a + b, 0);
  const extraK = Math.max(toKobo(extraCosts), 0);

  let given = 0;
  const shares = values.map((v, i) => {
    const share = i === values.length - 1 ? extraK - given : (itemsK > 0 ? Math.floor(extraK * (v / itemsK)) : 0);
    given += share;
    return share;
  });

  const byKey = {};
  valid.forEach((l, i) => {
    const b = byKey[l.key] || (byKey[l.key] = { quantity: 0, costK: 0 });
    b.quantity += Math.round(Number(l.quantity));
    b.costK += values[i] + shares[i];
  });
  Object.values(byKey).forEach(b => {
    b.cost = fromKobo(b.costK);
    b.unit = b.quantity ? fromKobo(Math.round(b.costK / b.quantity)) : 0;
  });

  return { items_total: fromKobo(itemsK), extra_costs: fromKobo(itemsK > 0 ? extraK : 0), landed_total: fromKobo(itemsK + (itemsK > 0 ? extraK : 0)), by_key: byKey };
}
