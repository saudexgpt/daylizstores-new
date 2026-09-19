import request from '@/utils/request';
import { excelRows, fileName } from '@/utils/reportFormat';

/** what this user may run, with each report's filters and columns */
export function fetchCatalog() {
  return request({ url: '/reports/catalog', method: 'get' });
}

/** one page of a report, plus its summary figures */
export function runReport(key, params) {
  return request({ url: `/reports/run/${key}`, method: 'get', params });
}

/** the CSV, streamed by the server — any size */
export function downloadReportCsv(key, params) {
  return request({ url: `/reports/export/${key}`, method: 'get', params: { ...params, format: 'csv' }, responseType: 'blob' })
    .then(blob => saveBlob(blob, fileName(key, params, 'csv')));
}

/** every row (up to a server ceiling) as JSON, turned into an .xlsx here in the browser */
export async function downloadReportExcel(key, params) {
  const report = await request({ url: `/reports/export/${key}`, method: 'get', params: { ...params, format: 'json' } });
  const [XLSX, files] = await Promise.all([import('xlsx'), import('file-saver')]);

  const rows = excelRows(report);
  const sheet = {};
  const range = { s: { c: 0, r: 0 }, e: { c: 0, r: 0 } };
  const widths = [];
  rows.forEach((row, r) => row.forEach((cell, c) => {
    if (!cell) {
      return;
    }
    sheet[XLSX.utils.encode_cell({ r, c })] = { v: cell.v, t: cell.t, ...(cell.z ? { z: cell.z } : {}) };
    range.e.r = Math.max(range.e.r, r);
    range.e.c = Math.max(range.e.c, c);
    // size a column to its data, not to the long title above it
    if (r >= 5) {
      widths[c] = Math.max(widths[c] || 10, Math.min(String(cell.v).length + 2, 50));
    }
  }));
  sheet['!ref'] = XLSX.utils.encode_range(range);
  sheet['!cols'] = widths.map(wch => ({ wch: wch || 10 }));

  const name = String(report.title || 'Report').replace(/[\\/?*[\]:]/g, ' ').slice(0, 31);
  const book = { SheetNames: [name], Sheets: { [name]: sheet } };
  const out = XLSX.write(book, { bookType: 'xlsx', type: 'array' });
  files.saveAs(new Blob([out], { type: 'application/octet-stream' }), fileName(key, report.filters, 'xlsx'));
}

export function saveBlob(blob, name) {
  return import('file-saver').then(files => files.saveAs(blob, name));
}
