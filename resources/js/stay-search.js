const MONTHS = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
const MONTHS_SHORT = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
const WEEKDAYS_SHORT = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
const WEEKDAYS_LONG = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];

const MIN_GUESTS = 1;
const MAX_GUESTS = 12;
const DEFAULT_GUESTS = 2;
const COMPACT_QUERY = '(max-width: 767px)';

const pad = (n) => String(n).padStart(2, '0');
const toIso = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
const addDays = (d, n) => new Date(d.getFullYear(), d.getMonth(), d.getDate() + n);
const firstOfMonth = (d, offset = 0) => new Date(d.getFullYear(), d.getMonth() + offset, 1);
const mondayIndex = (d) => (d.getDay() + 6) % 7;
const sameDay = (a, b) => Boolean(a && b) && toIso(a) === toIso(b);

function fromIso(value) {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value || '');
    return match ? new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3])) : null;
}

const formatShort = (d) => `${WEEKDAYS_SHORT[mondayIndex(d)]}, ${d.getDate()} ${MONTHS_SHORT[d.getMonth()]}`;
const formatLong = (d) => `${WEEKDAYS_LONG[mondayIndex(d)]}, ${d.getDate()} ${MONTHS[d.getMonth()]} ${d.getFullYear()}`;

class StaySearch {
    constructor(root) {
        this.root = root;
        this.compact = window.matchMedia(COMPACT_QUERY);
        this.today = fromIso(root.dataset.today) ?? new Date();
        this.start = this.parseUpcoming(root.dataset.checkIn);
        this.end = this.start ? this.parseUpcoming(root.dataset.checkOut) : null;
        if (this.end && this.end <= this.start) this.end = null;
        this.guests = this.clampGuests(Number(root.dataset.guests) || DEFAULT_GUESTS);
        this.hover = null;
        this.openPopover = null;
        this.cursor = this.start ?? this.today;
        this.view = firstOfMonth(this.cursor);

        this.triggers = Object.fromEntries([...root.querySelectorAll('[data-ss-trigger]')].map((el) => [el.dataset.ssTrigger, el]));
        this.inputs = Object.fromEntries([...root.querySelectorAll('[data-ss-input]')].map((el) => [el.dataset.ssInput, el]));
        this.values = Object.fromEntries([...root.querySelectorAll('[data-ss-value]')].map((el) => [el.dataset.ssValue, el]));
        this.popovers = Object.fromEntries([...root.querySelectorAll('[data-ss-popover]')].map((el) => [el.dataset.ssPopover, el]));
        this.monthsEl = root.querySelector('[data-ss-months]');
        this.nightsEl = root.querySelector('[data-ss-nights]');
        this.guestCountEl = root.querySelector('[data-ss-guest-count]');
        this.prevBtn = root.querySelector('[data-ss-prev]');
        this.nextBtn = root.querySelector('[data-ss-next]');

        this.bind();
        this.sync();
    }

    parseUpcoming(value) {
        const date = fromIso(value);
        return date && date >= this.today ? date : null;
    }

    clampGuests(value) {
        return Math.min(MAX_GUESTS, Math.max(MIN_GUESTS, value));
    }

    bind() {
        this.triggers.check_in.addEventListener('click', () => this.toggle('dates', 'check_in'));
        this.triggers.check_out.addEventListener('click', () => this.toggle('dates', 'check_out'));
        this.triggers.guests.addEventListener('click', () => this.toggle('guests'));

        this.prevBtn.addEventListener('click', () => this.shiftView(-1));
        this.nextBtn.addEventListener('click', () => this.shiftView(1));
        this.root.querySelector('[data-ss-clear]').addEventListener('click', () => this.clearDates());

        this.monthsEl.addEventListener('click', (e) => {
            const day = e.target.closest('.cal-day');
            if (day) this.pick(fromIso(day.dataset.date));
        });
        this.monthsEl.addEventListener('mouseover', (e) => {
            const day = e.target.closest('.cal-day');
            if (!day || !this.start || this.end) return;
            this.hover = fromIso(day.dataset.date);
            this.paint();
        });
        this.monthsEl.addEventListener('mouseleave', () => {
            this.hover = null;
            this.paint();
        });
        this.monthsEl.addEventListener('keydown', (e) => this.onGridKey(e));

        this.root.querySelector('[data-ss-minus]').addEventListener('click', () => this.setGuests(this.guests - 1));
        this.root.querySelector('[data-ss-plus]').addEventListener('click', () => this.setGuests(this.guests + 1));

        this.root.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.openPopover) {
                e.stopPropagation();
                this.close(true);
            }
        });
        document.addEventListener('pointerdown', (e) => {
            if (this.openPopover && !this.root.contains(e.target)) this.close(false);
        });
        this.root.addEventListener('submit', (e) => this.onSubmit(e));
        this.compact.addEventListener('change', () => this.openPopover === 'dates' && this.renderMonths());
    }

    toggle(name, field = null) {
        const alreadyOpen = this.openPopover === name && (name !== 'dates' || this.field === field);
        if (alreadyOpen) {
            this.close(true);
            return;
        }
        this.close(false);
        this.openPopover = name;
        this.field = field;
        this.popovers[name].hidden = false;
        this.syncExpanded();

        if (name === 'dates') {
            this.cursor = this.pickCursor(field);
            this.view = firstOfMonth(this.cursor);
            this.renderMonths();
        }

        this.place(this.popovers[name]);

        if (name === 'dates') {
            this.focusCursor();
        } else {
            this.root.querySelector('[data-ss-plus]').focus({ preventScroll: true });
        }
    }

    /**
     * On wide screens the popover opens above the bar. When the bar sits too close to the sticky
     * header for that, it opens below instead, so the month titles are never hidden.
     */
    place(popover) {
        popover.classList.remove('is-below');
        const header = document.querySelector('body > header, header.sticky')?.getBoundingClientRect().bottom ?? 0;
        const room = this.root.getBoundingClientRect().top - header;
        if (room < popover.offsetHeight + 16) popover.classList.add('is-below');

        // Brings a popover that opened below the fold into view without hiding it under the header.
        const rect = popover.getBoundingClientRect();
        if (rect.bottom > window.innerHeight) {
            window.scrollBy({ top: Math.min(rect.bottom - window.innerHeight + 16, rect.top - header - 16), behavior: 'auto' });
        }
    }

    pickCursor(field) {
        if (field === 'check_out' && this.start) return this.end ?? addDays(this.start, 1);
        return this.start ?? this.today;
    }

    close(returnFocus) {
        if (!this.openPopover) return;
        const was = this.openPopover;
        const field = this.field;
        Object.values(this.popovers).forEach((el) => (el.hidden = true));
        this.openPopover = null;
        this.hover = null;
        this.syncExpanded();
        if (returnFocus) (was === 'guests' ? this.triggers.guests : this.triggers[field ?? 'check_in']).focus();
    }

    syncExpanded() {
        Object.entries(this.triggers).forEach(([name, el]) => {
            const controls = name === 'guests' ? 'guests' : 'dates';
            const open = this.openPopover === controls && (controls === 'guests' || this.field === name);
            el.setAttribute('aria-expanded', String(open));
            el.classList.toggle('is-open', open);
        });
    }

    visibleMonths() {
        return this.compact.matches ? 1 : 2;
    }

    shiftView(step) {
        const next = firstOfMonth(this.view, step);
        if (next < firstOfMonth(this.today)) return;
        this.view = next;
        this.renderMonths();
    }

    renderMonths() {
        const count = this.visibleMonths();
        this.monthsEl.innerHTML = Array.from({ length: count }, (_, i) => this.monthHtml(firstOfMonth(this.view, i))).join('');
        this.prevBtn.disabled = this.view <= firstOfMonth(this.today);
        this.paint();
    }

    monthHtml(first) {
        const year = first.getFullYear();
        const month = first.getMonth();
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const cells = Array.from({ length: mondayIndex(first) }, () => '<div role="gridcell" class="cal-cell"></div>');

        for (let day = 1; day <= daysInMonth; day += 1) {
            const date = new Date(year, month, day);
            const past = date < this.today;
            cells.push(
                `<div role="gridcell" class="cal-cell" aria-selected="false"><button type="button" class="cal-day" data-date="${toIso(date)}" tabindex="-1" aria-label="${formatLong(date)}"${past ? ' aria-disabled="true"' : ''}>${day}</button></div>`,
            );
        }

        const rows = [];
        for (let i = 0; i < cells.length; i += 7) rows.push(`<div role="row" class="cal-row">${cells.slice(i, i + 7).join('')}</div>`);

        const heads = WEEKDAYS_SHORT.map((d) => `<div role="columnheader" class="cal-head">${d}</div>`).join('');
        const id = `ss-month-${year}-${month}`;

        return `<div class="cal-month"><p id="${id}" class="cal-title" aria-live="polite">${MONTHS[month]} ${year}</p><div role="grid" aria-labelledby="${id}" class="cal-grid"><div role="row" class="cal-row">${heads}</div>${rows.join('')}</div></div>`;
    }

    paint() {
        const previewEnd = !this.end && this.start && this.hover && this.hover > this.start ? this.hover : null;
        const tailEnd = this.end ?? previewEnd;

        this.monthsEl.querySelectorAll('.cal-day').forEach((btn) => {
            const date = fromIso(btn.dataset.date);
            const isStart = sameDay(date, this.start);
            const isEnd = sameDay(date, this.end);
            const between = Boolean(this.start && tailEnd) && date > this.start && date < tailEnd;
            const isPreviewEnd = !this.end && sameDay(date, previewEnd);

            btn.classList.toggle('cal-start', isStart);
            btn.classList.toggle('cal-end', isEnd);
            btn.classList.toggle('cal-tail', isStart && Boolean(tailEnd));
            btn.classList.toggle('cal-mid', between && Boolean(this.end));
            btn.classList.toggle('cal-pmid', between && !this.end);
            btn.classList.toggle('cal-pend', isPreviewEnd);
            btn.classList.toggle('cal-today', sameDay(date, this.today));
            btn.classList.toggle('cal-past', date < this.today);
            btn.tabIndex = sameDay(date, this.cursor) ? 0 : -1;
            btn.parentElement.setAttribute('aria-selected', String(isStart || isEnd || (between && Boolean(this.end))));
        });
    }

    focusCursor() {
        this.monthsEl.querySelector(`.cal-day[data-date="${toIso(this.cursor)}"]`)?.focus({ preventScroll: true });
    }

    pick(date) {
        if (date < this.today) return;
        this.cursor = date;

        if (!this.start || this.end || date <= this.start) {
            this.start = date;
            this.end = null;
            this.field = 'check_out';
            this.syncExpanded();
        } else {
            this.end = date;
            this.hover = null;
            this.sync();
            this.close(true);
            return;
        }

        this.hover = null;
        this.sync();
        this.paint();
        this.focusCursor();
    }

    clearDates() {
        this.start = null;
        this.end = null;
        this.hover = null;
        this.field = 'check_in';
        this.sync();
        this.syncExpanded();
        this.paint();
        this.focusCursor();
    }

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
        this.moveCursor(next < this.today ? this.today : next);
    }

    moveCursor(date) {
        this.cursor = date;
        const month = firstOfMonth(date);
        const last = firstOfMonth(this.view, this.visibleMonths() - 1);

        if (month < this.view) this.view = month;
        else if (month > last) this.view = firstOfMonth(date, 1 - this.visibleMonths());

        if (this.start && !this.end && date > this.start) this.hover = date;
        this.renderMonths();
        this.focusCursor();
    }

    setGuests(value) {
        this.guests = this.clampGuests(value);
        this.sync();
    }

    sync() {
        this.inputs.check_in.value = this.start ? toIso(this.start) : '';
        this.inputs.check_out.value = this.end ? toIso(this.end) : '';
        this.inputs.guests.value = this.guests;

        this.setValue('check_in', this.start ? formatShort(this.start) : null);
        this.setValue('check_out', this.end ? formatShort(this.end) : null);
        this.values.guests.textContent = `${this.guests} tamu`;
        this.guestCountEl.textContent = this.guests;

        this.root.querySelector('[data-ss-minus]').disabled = this.guests <= MIN_GUESTS;
        this.root.querySelector('[data-ss-plus]').disabled = this.guests >= MAX_GUESTS;

        const nights = this.start && this.end ? Math.round((this.end - this.start) / 86400000) : 0;
        this.nightsEl.textContent = nights ? `${nights} malam` : 'Pilih check-in, lalu check-out';
    }

    setValue(name, text) {
        this.values[name].textContent = text ?? 'Pilih tanggal';
        this.values[name].classList.toggle('is-empty', text === null);
    }

    onSubmit(e) {
        if (this.start && this.end) return;
        e.preventDefault();
        this.close(false);
        this.toggle('dates', this.start ? 'check_out' : 'check_in');
    }
}

export function initStaySearch() {
    document.querySelectorAll('[data-stay-search]').forEach((root) => new StaySearch(root));
}
