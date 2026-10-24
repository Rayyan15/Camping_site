// Strings come from lang/*.json through partials/js-i18n, so the server stays the single source of copy.
const strings = JSON.parse(document.getElementById('js-i18n')?.textContent || '{}');

export const locale = document.documentElement.lang === 'en' ? 'en-GB' : 'id-ID';

// A translation may hold "singular|plural"; vars.count picks the form.
const pickForm = (line, count) => (line.includes('|') ? line.split('|')[Number(count) === 1 ? 0 : 1] : line);

export const t = (text, vars = {}) =>
    Object.entries(vars).reduce((out, [key, value]) => out.replaceAll(`:${key}`, value), pickForm(strings[text] ?? text, vars.count));

const dateFormat = (options) => new Intl.DateTimeFormat(locale, options);
const longDate = dateFormat({ weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
const shortDate = dateFormat({ weekday: 'short', day: 'numeric', month: 'short' });
const monthTitle = dateFormat({ month: 'long', year: 'numeric' });
const weekdayShort = dateFormat({ weekday: 'short' });

export const formatLong = (d) => longDate.format(d);
export const formatShort = (d) => shortDate.format(d);
export const formatMonth = (year, month) => monthTitle.format(new Date(year, month, 1));

// 5 Jan 2026 was a Monday, so these seven dates give Monday-first headers in any locale.
export const weekdayHeads = Array.from({ length: 7 }, (_, i) => weekdayShort.format(new Date(2026, 0, 5 + i)));

const rupiahFormat = new Intl.NumberFormat('id-ID');
export const rupiah = (n) => `Rp ${rupiahFormat.format(n)}`;
