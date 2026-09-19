/**
 * Pure helpers shared by the report and accounting screens: number/date formatting, which
 * columns to show, and turning a report into rows for an Excel sheet. No Vue, no DOM — so
 * the logic that decides what a reader sees (and what lands in a spreadsheet) is unit-tested.
 */

export const CURRENCY = '₦';

/** 1234.5 -> "1,234.50" (always two decimals: money is never shown rounded to a whole naira) */
export function money(value) {
  const n = Number(value);
  if (value === null || value === undefined || value === '' || Number.isNaN(n)) {
    return '';
  }
  return n.toLocaleString('en', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

/** 1234.5 -> "₦1,234.50"; negatives read "-₦1,234.50" */
export function naira(value) {
  const text = money(value);
  if (text === '') {
    return '';
  }
  return text.startsWith('-') ? '-' + CURRENCY + text.slice(1) : CURRENCY + text;
}

export function integer(value) {
  const n = Number(value);
  if (value === null || value === undefined || value === '' || Number.isNaN(n)) {
    return '';
  }
  return n.toLocaleString('en', { maximumFractionDigits: 0 });
}

export function percent(value) {
  const n = Number(value);
  if (value === null || value === undefined || value === '' || Number.isNaN(n)) {
    return '';
  }
  return n.toLocaleString('en', { minimumFractionDigits: 1, maximumFractionDigits: 1 }) + '%';
}

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

/** "2026-03-05" -> "5 Mar 2026" (read from the string, so no time-zone shift can move the day) */
export function dateText(value) {
  const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(value || ''));
  return m ? `${Number(m[3])} ${MONTHS[Number(m[2]) - 1]} ${m[1]}` : '';
}

/** "2026-03-05 12:30:00" -> "5 Mar 2026, 12:30" */
export function dateTimeText(value) {
  const m = /^(\d{4}-\d{2}-\d{2})[ T](\d{2}):(\d{2})/.exec(String(value || ''));
  return m ? `${dateText(m[1])}, ${m[2]}:${m[3]}` : dateText(value);
}

/** the text shown in a report cell, by column type */
export function formatCell(value, type) {
  if (value === null || value === undefined || value === '') {
    return '';
  }
  switch (type) {
    case 'money': return money(value);
    case 'int': return integer(value);
    case 'percent': return percent(value);
    case 'date': return dateText(value);
    case 'datetime': return dateTimeText(value);
    default: return String(value);
  }
}

/** an "optional" column is hidden when nothing in the page uses it (e.g. "Size / colour" on product-level lists) */
export function visibleColumns(columns, rows, footer) {
  const all = footer ? [...rows, footer] : rows;
  return columns.filter(c => !c.optional || all.some(r => r[c.key] !== null && r[c.key] !== undefined && r[c.key] !== ''));
}

// ---------------------------------------------------------------- periods

const pad = (n) => String(n).padStart(2, '0');
export const isoDate = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;

/** the standard reporting periods, as [from, to] ISO dates, relative to `today` (a Date) */
export function periodPresets(today = new Date()) {
  const y = today.getFullYear();
  const m = today.getMonth();
  const day = new Date(y, m, today.getDate());
  const at = (yy, mm, dd) => new Date(yy, mm, dd);
  const quarterStart = Math.floor(m / 3) * 3;
  const weekday = (day.getDay() + 6) % 7; // Monday = 0

  return [
    { key: 'today', label: 'Today', range: [day, day] },
    { key: 'yesterday', label: 'Yesterday', range: [at(y, m, day.getDate() - 1), at(y, m, day.getDate() - 1)] },
    { key: 'week', label: 'This week', range: [at(y, m, day.getDate() - weekday), day] },
    { key: 'last7', label: 'Last 7 days', range: [at(y, m, day.getDate() - 6), day] },
    { key: 'last30', label: 'Last 30 days', range: [at(y, m, day.getDate() - 29), day] },
    { key: 'month', label: 'This month', range: [at(y, m, 1), day] },
    { key: 'lastmonth', label: 'Last month', range: [at(y, m - 1, 1), at(y, m, 0)] },
    { key: 'quarter', label: 'This quarter', range: [at(y, quarterStart, 1), day] },
    { key: 'year', label: 'This year', range: [at(y, 0, 1), day] },
    { key: 'lastyear', label: 'Last year', range: [at(y - 1, 0, 1), at(y - 1, 11, 31)] },
  ].map(p => ({ key: p.key, label: p.label, from: isoDate(p.range[0]), to: isoDate(p.range[1]) }));
}

// ---------------------------------------------------------------- Excel

/** Excel number formats by column type; money keeps two decimals, ints none */
export const EXCEL_FORMATS = { money: '#,##0.00', int: '#,##0', percent: '0.0"%"' };

/**
 * Turn an export payload ({ title, filters, columns, rows, footer, summary }) into an array of
 * rows ready for a sheet: a title block, the summary figures, then the table. Cells are
 * { v, t, z } — a typed value plus an optional number format — so numbers stay numbers in Excel
 * (sortable, summable), and dates stay ISO text.
 */
export function excelRows(report, generatedAt = new Date()) {
  const cell = (v, type) => {
    if (v === null || v === undefined || v === '') {
      return null;
    }
    if (['money', 'int', 'percent'].includes(type) && !Number.isNaN(Number(v))) {
      return { v: Number(v), t: 'n', z: EXCEL_FORMATS[type] };
    }
    return { v: String(v), t: 's' };
  };
  const text = (v) => ({ v: String(v), t: 's' });

  const out = [[text(report.title)]];
  const f = report.filters || {};
  if (f.from && f.to) {
    out.push([text(`Period: ${dateText(f.from)} to ${dateText(f.to)}`)]);
  } else if (f.as_at) {
    out.push([text(`As at ${dateText(f.as_at)}`)]);
  }
  out.push([text(`Generated ${dateTimeText(isoDate(generatedAt) + ' ' + pad(generatedAt.getHours()) + ':' + pad(generatedAt.getMinutes()))}`)]);
  out.push([]);

  (report.summary || []).forEach(s => out.push([text(s.label), cell(s.value, s.type)]));
  if ((report.summary || []).length) {
    out.push([]);
  }

  const columns = visibleColumns(report.columns, report.rows, report.footer);
  out.push(columns.map(c => ({ v: c.label, t: 's', bold: true })));
  report.rows.forEach(r => out.push(columns.map(c => cell(r[c.key], c.type))));
  if (report.footer) {
    out.push(columns.map(c => {
      const x = cell(report.footer[c.key], c.type);
      return x ? { ...x, bold: true } : null;
    }));
  }

  return out;
}

/** a safe file name for an export */
export function fileName(key, filters = {}, extension = 'csv') {
  const range = filters.from && filters.to ? `_${filters.from}_to_${filters.to}` : (filters.as_at ? `_as-at-${filters.as_at}` : '');
  return `${key}${range}.${extension}`.replace(/[^A-Za-z0-9._-]+/g, '-');
}
