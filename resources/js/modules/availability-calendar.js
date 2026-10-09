const MONTHS = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
const WEEKDAYS_SHORT = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
const WEEKDAYS_LONG = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];
const COMPACT_QUERY = '(max-width: 767px)';
const DAY_MS = 86400000;

const rupiahFormat = new Intl.NumberFormat('id-ID');
const pad = (n) => String(n).padStart(2, '0');
const toIso = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
const monthKey = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}`;
const addDays = (d, n) => new Date(d.getFullYear(), d.getMonth(), d.getDate() + n);
const firstOfMonth = (d, offset = 0) => new Date(d.getFullYear(), d.getMonth() + offset, 1);
const mondayIndex = (d) => (d.getDay() + 6) % 7;
const sameDay = (a, b) => Boolean(a && b) && toIso(a) === toIso(b);
const nightsBetween = (a, b) => Math.round((b - a) / DAY_MS);

function fromIso(value) {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value || '');
    return match ? new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3])) : null;
}

const formatLong = (d) => `${WEEKDAYS_LONG[mondayIndex(d)]}, ${d.getDate()} ${MONTHS[d.getMonth()]} ${d.getFullYear()}`;

/**
 * Range calendar for one tent type. It only displays what the server says: free units per night come
 * from the availability endpoint, and the price estimate comes from the same endpoint. The native date
 * inputs stay the source of truth for the form, so the page works the same without this script.
 */
class AvailabilityCalendar {
    constructor(root) {
        this.root = root;
        this.compact = window.matchMedia(COMPACT_QUERY);
        this.endpoint = root.dataset.endpoint;
        this.today = fromIso(root.dataset.today) ?? new Date();
        this.lastBookable = addDays(this.today, Number(root.dataset.maxAdvance) || 365);
        this.maxNights = Number(root.dataset.maxNights) || 14;

        this.checkIn = document.getElementById(root.dataset.checkInId);
        this.checkOut = document.getElementById(root.dataset.checkOutId);
        this.estimateEl = document.querySelector(root.dataset.estimate);
        this.monthsEl = root.querySelector('[data-cal-months]');
        this.prevBtn = root.querySelector('[data-cal-prev]');
        this.nextBtn = root.querySelector('[data-cal-next]');
        this.statusEl = root.querySelector('[data-cal-status]');
        this.detailEl = root.querySelector('[data-cal-detail]');
        this.loadingEl = root.querySelector('[data-cal-loading]');
        this.errorEl = root.querySelector('[data-cal-error]');
        this.retryBtn = root.querySelector('[data-cal-retry]');
        this.hint = this.detailEl.textContent;

        this.months = new Map();
        this.requests = new Map();
        this.estimateAbort = null;

        this.start = this.readInput(this.checkIn);
        this.end = this.start ? this.readInput(this.checkOut) : null;
        if (this.end && this.end <= this.start) this.end = null;
        this.cursor = this.start ?? this.today;
        this.view = firstOfMonth(this.cursor);

        this.bind();
        this.render();
        this.refreshSelection();
    }

    readInput(input) {
        const date = fromIso(input.value);
        return date && date >= this.today ? date : null;
    }

    bind() {
        this.prevBtn.addEventListener('click', () => this.shiftView(-1));
        this.nextBtn.addEventListener('click', () => this.shiftView(1));
        this.retryBtn.addEventListener('click', () => this.render());

        this.monthsEl.addEventListener('click', (e) => {
            const day = e.target.closest('.cal-day');
            if (day) this.pick(fromIso(day.dataset.date));
        });
        this.monthsEl.addEventListener('keydown', (e) => this.onGridKey(e));
        ['mouseover', 'focusin'].forEach((type) =>
            this.monthsEl.addEventListener(type, (e) => {
                const day = e.target.closest('.cal-day');
                if (day) this.detailEl.textContent = this.describe(fromIso(day.dataset.date), true);
            }),
        );

        [this.checkIn, this.checkOut].forEach((input) => input.addEventListener('change', () => this.onInputChange()));
        this.compact.addEventListener('change', () => this.render());
    }

    visibleMonths() {
        return this.compact.matches ? 1 : 2;
    }

    isOutOfRange(date) {
        return date < this.today || date > this.lastBookable;
    }

    dayData(date) {
        return this.months.get(monthKey(date))?.[toIso(date)] ?? null;
    }

    describe(date, long = false) {
        const label = formatLong(date);
        if (date < this.today) return `${label}, sudah lewat.`;
        if (date > this.lastBookable) return `${label}, di luar jangkauan pemesanan.`;
        const data = this.dayData(date);
        if (!data) return `${label}, ketersediaan belum dimuat.`;
        if (data.free === 0) return long ? `${label}, penuh. Masih bisa dipilih sebagai tanggal check-out.` : `${label}, penuh`;
        return `${label}, sisa ${data.free} dari ${data.total} unit`;
    }

    /* Data loading */

    loadMonth(key) {
        if (this.months.has(key)) return Promise.resolve(true);
        if (this.requests.has(key)) return this.requests.get(key);

        const request = fetch(`${this.endpoint}?bulan=${key}`, { headers: { Accept: 'application/json' } })
            .then((response) => (response.ok ? response.json() : Promise.reject(new Error(String(response.status)))))
            .then((payload) => {
                this.months.set(key, payload.days);
                return true;
            })
            .catch(() => false)
            .finally(() => this.requests.delete(key));

        this.requests.set(key, request);
        return request;
    }

    async ensureRange(from, to) {
        const keys = [];
        for (let cursor = firstOfMonth(from); cursor <= to; cursor = firstOfMonth(cursor, 1)) keys.push(monthKey(cursor));
        const results = await Promise.all(keys.map((key) => this.loadMonth(key)));
        return results.every(Boolean);
    }

    /* Rendering */

    render() {
        const count = this.visibleMonths();
        this.monthsEl.innerHTML = Array.from({ length: count }, (_, i) => this.monthHtml(firstOfMonth(this.view, i))).join('');
        this.prevBtn.disabled = this.view <= firstOfMonth(this.today);
        this.nextBtn.disabled = firstOfMonth(this.view, count) > this.lastBookable;
        this.paint();
        this.loadVisible();
    }

    async loadVisible() {
        const count = this.visibleMonths();
        const token = (this.loadToken = (this.loadToken ?? 0) + 1);
        this.root.setAttribute('aria-busy', 'true');
        this.loadingEl.hidden = false;
        this.errorEl.hidden = true;

        const ok = await this.ensureRange(this.view, addDays(firstOfMonth(this.view, count), -1));
        if (token !== this.loadToken) return;

        this.root.setAttribute('aria-busy', 'false');
        this.loadingEl.hidden = true;
        this.errorEl.hidden = ok;
        this.paint();
    }

    monthHtml(first) {
        const year = first.getFullYear();
        const month = first.getMonth();
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const cells = Array.from({ length: mondayIndex(first) }, () => '<div role="gridcell" class="cal-cell"></div>');

        for (let day = 1; day <= daysInMonth; day += 1) {
            const date = new Date(year, month, day);
            cells.push(
                `<div role="gridcell" class="cal-cell" aria-selected="false"><button type="button" class="cal-day" data-date="${toIso(date)}" tabindex="-1">${day}</button></div>`,
            );
        }

        const rows = [];
        for (let i = 0; i < cells.length; i += 7) rows.push(`<div role="row" class="cal-row">${cells.slice(i, i + 7).join('')}</div>`);

        const heads = WEEKDAYS_SHORT.map((d) => `<div role="columnheader" class="cal-head">${d}</div>`).join('');
        const id = `avail-month-${year}-${month}`;

        return `<div class="cal-month"><p id="${id}" class="cal-title">${MONTHS[month]} ${year}</p><div role="grid" aria-labelledby="${id}" class="cal-grid"><div role="row" class="cal-row">${heads}</div>${rows.join('')}</div></div>`;
    }

    paint() {
        this.monthsEl.querySelectorAll('.cal-day').forEach((btn) => {
            const date = fromIso(btn.dataset.date);
            const data = this.dayData(date);
            const blocked = this.isOutOfRange(date);
            const isStart = sameDay(date, this.start);
            const isEnd = sameDay(date, this.end);
            const between = Boolean(this.start && this.end) && date > this.start && date < this.end;

            btn.classList.toggle('cal-past', blocked);
            btn.classList.toggle('avail-full', !blocked && data?.free === 0);
            btn.classList.toggle('avail-limited', !blocked && Boolean(data?.limited));
            btn.classList.toggle('cal-today', sameDay(date, this.today));
            btn.classList.toggle('cal-start', isStart);
            btn.classList.toggle('cal-end', isEnd);
            btn.classList.toggle('cal-tail', isStart && Boolean(this.end));
            btn.classList.toggle('cal-mid', between);
            btn.setAttribute('aria-label', this.describe(date));
            if (blocked) btn.setAttribute('aria-disabled', 'true');
            else btn.removeAttribute('aria-disabled');
            btn.tabIndex = sameDay(date, this.cursor) ? 0 : -1;
            btn.parentElement.setAttribute('aria-selected', String(isStart || isEnd || between));
        });
    }

    shiftView(step) {
        const next = firstOfMonth(this.view, step);
        if (next < firstOfMonth(this.today) || next > this.lastBookable) return;
        this.view = next;
        this.render();
    }

    focusCursor() {
        this.monthsEl.querySelector(`.cal-day[data-date="${toIso(this.cursor)}"]`)?.focus();
    }

    say(message) {
        this.statusEl.textContent = message;
    }

    /* Selection */

    async pick(date) {
        if (this.isOutOfRange(date)) {
            this.say(date < this.today ? 'Tanggal itu sudah lewat.' : 'Tanggal itu di luar jangkauan pemesanan.');
            return;
        }
        this.cursor = date;

        if (!this.start || this.end || date <= this.start) {
            await this.ensureRange(date, date);
            if (this.dayData(date)?.free === 0) {
                this.say(`${formatLong(date)} penuh. Pilih tanggal check-in lain.`);
                this.paint();
                return;
            }
            this.start = date;
            this.end = null;
            this.writeInputs();
            this.paint();
            this.say(`Check-in ${formatLong(date)}. Pilih tanggal check-out.`);
            this.showEstimate('hint');
            return;
        }

        if (nightsBetween(this.start, date) > this.maxNights) {
            this.say(`Menginap maksimal ${this.maxNights} malam. Pilih check-out yang lebih dekat.`);
            return;
        }

        const full = await this.firstFullNight(this.start, date);
        if (full) {
            this.say(`Malam ${formatLong(full)} sudah penuh, jadi rentang ini tidak bisa dipesan. Pilih tanggal lain.`);
            return;
        }

        this.end = date;
        this.writeInputs();
        this.paint();
        this.say(`${nightsBetween(this.start, this.end)} malam, ${formatLong(this.start)} sampai ${formatLong(this.end)}.`);
        this.loadEstimate();
    }

    async firstFullNight(from, to) {
        await this.ensureRange(from, to);
        for (let night = from; night < to; night = addDays(night, 1)) {
            if (this.dayData(night)?.free === 0) return night;
        }
        return null;
    }

    writeInputs() {
        this.checkIn.value = this.start ? toIso(this.start) : '';
        this.checkOut.value = this.end ? toIso(this.end) : '';
        if (this.start) this.checkOut.min = toIso(addDays(this.start, 1));
    }

    onInputChange() {
        this.start = this.readInput(this.checkIn);
        this.end = this.start ? this.readInput(this.checkOut) : null;
        if (this.end && this.end <= this.start) this.end = null;
        if (this.start) {
            this.cursor = this.start;
            this.view = firstOfMonth(this.start);
        }
        this.render();
        this.refreshSelection();
    }

    async refreshSelection() {
        if (!this.start || !this.end) {
            this.showEstimate('hint');
            return;
        }
        const full = await this.firstFullNight(this.start, this.end);
        this.paint();
        if (full) {
            this.say(`Malam ${formatLong(full)} sudah penuh di tipe ini. Ubah tanggalnya.`);
            this.showEstimate('blocked');
            return;
        }
        this.loadEstimate();
    }

    /* Estimate: the number shown here is computed by the server, never by this script. */

    showEstimate(state, payload = {}) {
        if (!this.estimateEl) return;
        const main = this.estimateEl.querySelector('[data-est-main]');
        const note = this.estimateEl.querySelector('[data-est-note]');
        const texts = {
            hint: ['Pilih check-in dan check-out untuk melihat estimasi sewa.', ''],
            loading: ['Menghitung estimasi...', ''],
            blocked: ['Estimasi tidak ditampilkan karena ada malam yang penuh.', ''],
            error: ['Estimasi belum bisa dimuat.', 'Harga akhir tetap dihitung saat checkout.'],
            invalid: [payload.message ?? 'Tanggal belum sesuai.', ''],
            ok: [
                `${payload.nights} malam, 1 unit: Rp ${rupiahFormat.format(payload.total)}`,
                'Sebelum pajak, extra bed, dan makanan. Harga akhir dihitung saat checkout.',
            ],
        };
        [main.textContent, note.textContent] = texts[state];
        this.estimateEl.dataset.state = state;
    }

    async loadEstimate() {
        this.estimateAbort?.abort();
        this.estimateAbort = new AbortController();
        this.showEstimate('loading');

        try {
            const query = new URLSearchParams({ check_in: toIso(this.start), check_out: toIso(this.end) });
            const response = await fetch(`${this.endpoint}?${query}`, { headers: { Accept: 'application/json' }, signal: this.estimateAbort.signal });
            const payload = await response.json();

            if (response.status === 422) {
                this.showEstimate('invalid', { message: Object.values(payload.errors ?? {})[0]?.[0] });
            } else if (response.ok) {
                this.showEstimate('ok', payload.estimate);
            } else {
                this.showEstimate('error');
            }
        } catch (error) {
            if (error.name !== 'AbortError') this.showEstimate('error');
        }
    }

    /* Keyboard: arrows move by day or week, PageUp/PageDown by month, Home/End to week edges. */

    onGridKey(e) {
        const btn = e.target.closest('.cal-day');
        if (!btn) return;
        const date = fromIso(btn.dataset.date);
        const steps = { ArrowLeft: -1, ArrowRight: 1, ArrowUp: -7, ArrowDown: 7 };
        let next;

        if (e.key in steps) next = addDays(date, steps[e.key]);
        else if (e.key === 'PageUp') next = new Date(date.getFullYear(), date.getMonth() - 1, date.getDate());
        else if (e.key === 'PageDown') next = new Date(date.getFullYear(), date.getMonth() + 1, date.getDate());
        else if (e.key === 'Home') next = addDays(date, -mondayIndex(date));
        else if (e.key === 'End') next = addDays(date, 6 - mondayIndex(date));
        else return;

        e.preventDefault();
        this.moveCursor(next < this.today ? this.today : next > this.lastBookable ? this.lastBookable : next);
    }

    moveCursor(date) {
        this.cursor = date;
        const month = firstOfMonth(date);
        const last = firstOfMonth(this.view, this.visibleMonths() - 1);

        if (month < this.view) this.view = month;
        else if (month > last) this.view = firstOfMonth(date, 1 - this.visibleMonths());

        this.render();
        this.focusCursor();
    }
}

export function initAvailabilityCalendar() {
    document.querySelectorAll('[data-avail-cal]').forEach((root) => new AvailabilityCalendar(root));
}
