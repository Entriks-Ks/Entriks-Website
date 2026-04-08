class CustomDateTimePicker {
    constructor(inputElement, options = {}) {
        this.input = inputElement;

        const locales = {
            'en': {
                months: ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
                weekdays: ['Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa', 'Su'],
                clear: 'Clear',
                today: 'Today',
                selectTime: 'Select Time',
                titleFormat: (monthName, year) => `${monthName} ${year}`
            },
            'de': {
                months: ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'],
                weekdays: ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'],
                clear: 'Löschen',
                today: 'Heute',
                selectTime: 'Zeit wählen',
                titleFormat: (monthName, year) => `${monthName} ${year}`
            }
        };

        const userLang = (typeof window !== 'undefined' && window.entraLanguage) || inputElement.getAttribute('data-locale') || 'en';
        this.lang = locales[userLang] ? userLang : 'en';
        this.strings = locales[this.lang];

        this.options = {
            minDate: options.minDate || null,
            timeStep: options.timeStep || 30,
            format24h: true,
            ...options
        };

        this.selectedDate = null;
        this.selectedTime = null;
        this.currentMonth = new Date();
        this.isOpen = false;

        this.init();
    }

    init() {
        const originalName = this.input.getAttribute('name');
        this.originalValue = this.input.value || '';

        this.input.setAttribute('type', 'text');
        this.input.setAttribute('placeholder', this.lang === 'de' ? 'Datum und Zeit wählen' : 'Select date and time');
        this.input.setAttribute('readonly', 'readonly');
        this.input.removeAttribute('name');

        this.hiddenInput = document.createElement('input');
        this.hiddenInput.type = 'hidden';
        this.hiddenInput.name = originalName;
        this.hiddenInput.value = this.originalValue;
        this.input.parentElement.appendChild(this.hiddenInput);

        this.picker = document.createElement('div');
        this.picker.className = 'custom-datetime-picker';
        this.picker.innerHTML = this.renderPicker();

        const wrapper = this.input.closest('.datetime-wrapper');
        if (wrapper) {
            wrapper.style.position = 'relative';
            wrapper.appendChild(this.picker);
        } else {
            this.input.parentElement.style.position = 'relative';
            this.input.parentElement.appendChild(this.picker);
        }

        this.bindEvents();
        this.generateTimeSlots();
        this.renderCalendar();

        if (this.originalValue) {
            this.parseInputValue(this.originalValue);
        }
    }

    renderPicker() {
        return `
            <div class="picker-calendar">
                <div class="picker-calendar-header">
                    <button type="button" class="picker-nav-btn" data-action="prev-month">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5" />
                        </svg>
                    </button>
                    <span class="picker-calendar-title"></span>
                    <button type="button" class="picker-nav-btn" data-action="next-month">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5" />
                        </svg>
                    </button>
                </div>
                <div class="picker-weekdays">
                    ${this.strings.weekdays.map(d => `<span class="picker-weekday">${d}</span>`).join('')}
                </div>
                <div class="picker-days"></div>
                <div class="picker-footer">
                    <button type="button" class="picker-btn picker-btn-clear" data-action="clear">${this.strings.clear}</button>
                    <button type="button" class="picker-btn picker-btn-today" data-action="today">${this.strings.today}</button>
                </div>
            </div>
            <div class="picker-time">
                <div class="picker-time-header">${this.strings.selectTime}</div>
                <div class="picker-time-list"></div>
            </div>
        `;
    }

    bindEvents() {
        const wrapper = this.input.closest('.datetime-wrapper');
        const iconBtn = wrapper ? wrapper.querySelector('.datetime-icon') : null;

        const openPicker = (e) => {
            e.preventDefault();
            e.stopPropagation();
            this.toggle();
        };

        this.input.addEventListener('click', openPicker);
        this.input.addEventListener('focus', openPicker);
        if (iconBtn) {
            iconBtn.addEventListener('click', openPicker);
        }

        this.picker.querySelectorAll('.picker-nav-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const action = btn.dataset.action;
                if (action === 'prev-month') this.prevMonth();
                if (action === 'next-month') this.nextMonth();
            });
        });

        this.picker.querySelectorAll('.picker-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const action = btn.dataset.action;
                if (action === 'clear') this.clear();
                if (action === 'today') this.selectToday();
            });
        });

        document.addEventListener('click', (e) => {
            if (this.isOpen && !this.picker.contains(e.target) && e.target !== this.input) {
                this.close();
            }
        });

        this.picker.addEventListener('click', (e) => {
            e.stopPropagation();
        });
    }

    generateTimeSlots() {
        const timeList = this.picker.querySelector('.picker-time-list');
        timeList.innerHTML = '';

        for (let h = 0; h < 24; h++) {
            for (let m = 0; m < 60; m += this.options.timeStep) {
                const hour = h.toString().padStart(2, '0');
                const minute = m.toString().padStart(2, '0');
                const time24 = `${hour}:${minute}`;

                let displayTime;
                if (this.options.format24h) {
                    displayTime = time24;
                } else {
                    const period = h >= 12 ? 'PM' : 'AM';
                    const hour12 = h === 0 ? 12 : (h > 12 ? h - 12 : h);
                    displayTime = `${hour12}:${minute} ${period}`;
                }

                const slot = document.createElement('div');
                slot.className = 'picker-time-slot';
                slot.dataset.time = time24;
                slot.textContent = displayTime;

                slot.addEventListener('click', () => this.selectTime(time24));

                timeList.appendChild(slot);
            }
        }
    }

    renderCalendar() {
        const daysContainer = this.picker.querySelector('.picker-days');
        const titleEl = this.picker.querySelector('.picker-calendar-title');

        const year = this.currentMonth.getFullYear();
        const month = this.currentMonth.getMonth();

        titleEl.textContent = this.strings.titleFormat(this.strings.months[month], year);

        const firstDay = new Date(year, month, 1);
        let startDay = firstDay.getDay() - 1;
        if (startDay < 0) startDay = 6;
        
        const daysInMonth = new Date(year, month + 1, 0).getDate();
        const daysInPrevMonth = new Date(year, month, 0).getDate();

        const today = new Date();
        today.setHours(0, 0, 0, 0);

        let minDate = null;
        if (this.options.minDate) {
            minDate = new Date(this.options.minDate);
            minDate.setHours(0, 0, 0, 0);
        }

        daysContainer.innerHTML = '';

        for (let i = startDay - 1; i >= 0; i--) {
            const day = daysInPrevMonth - i;
            const btn = this.createDayButton(day, 'other-month', null);
            daysContainer.appendChild(btn);
        }

        for (let day = 1; day <= daysInMonth; day++) {
            const date = new Date(year, month, day);
            date.setHours(0, 0, 0, 0);

            let classes = [];

            if (date.getTime() === today.getTime()) {
                classes.push('today');
            }

            if (this.selectedDate && date.getTime() === this.selectedDate.getTime()) {
                classes.push('selected');
            }

            let disabled = false;
            if (minDate && date < minDate) {
                classes.push('disabled');
                disabled = true;
            }

            const btn = this.createDayButton(day, classes.join(' '), disabled ? null : date);
            daysContainer.appendChild(btn);
        }

        const totalCells = startDay + daysInMonth;
        const remaining = (7 - (totalCells % 7)) % 7;
        for (let day = 1; day <= remaining; day++) {
            const btn = this.createDayButton(day, 'other-month', null);
            daysContainer.appendChild(btn);
        }
    }

    createDayButton(day, className, date) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = `picker-day ${className}`;
        btn.textContent = day;

        if (date) {
            btn.addEventListener('click', () => this.selectDate(date));
        }

        return btn;
    }

    selectDate(date) {
        this.selectedDate = new Date(date);
        this.selectedDate.setHours(0, 0, 0, 0);
        this.renderCalendar();
        this.updateTimeAvailability();
        this.updateInput();
    }

    selectTime(time) {
        if (!this.selectedDate) {
            const now = new Date();
            this.selectedDate = new Date(now.getFullYear(), now.getMonth(), now.getDate());
            this.renderCalendar();
            this.updateTimeAvailability();
        }

        this.selectedTime = time;

        this.picker.querySelectorAll('.picker-time-slot').forEach(slot => {
            slot.classList.toggle('selected', slot.dataset.time === time);
        });

        this.updateInput();

        if (this.selectedDate && this.selectedTime) {
            setTimeout(() => this.close(), 150);
        }
    }

    updateInput() {
        if (this.selectedDate && this.selectedTime) {
            const year = this.selectedDate.getFullYear();
            const month = (this.selectedDate.getMonth() + 1).toString().padStart(2, '0');
            const day = this.selectedDate.getDate().toString().padStart(2, '0');

            const formValue = `${year}-${month}-${day}T${this.selectedTime}`;
            this.hiddenInput.value = formValue;

            if (this.lang === 'de') {
                this.input.value = `${day}.${month}.${year} ${this.selectedTime}`;
            } else {
                this.input.value = `${year}-${month}-${day} ${this.selectedTime}`;
            }

            this.input.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    parseInputValue(value) {
        if (!value) return;

        try {
            const [datePart, timePart] = value.split('T');
            if (datePart) {
                const [year, month, day] = datePart.split('-').map(Number);
                this.selectedDate = new Date(year, month - 1, day);
                this.currentMonth = new Date(year, month - 1, 1);
            }
            if (timePart) {
                this.selectedTime = timePart.substring(0, 5);
            }

            if (this.selectedDate && this.selectedTime) {
                const y = this.selectedDate.getFullYear();
                const m = (this.selectedDate.getMonth() + 1).toString().padStart(2, '0');
                const d = this.selectedDate.getDate().toString().padStart(2, '0');

                if (this.lang === 'de') {
                    this.input.value = `${d}.${m}.${y} ${this.selectedTime}`;
                } else {
                    this.input.value = `${y}-${m}-${d} ${this.selectedTime}`;
                }
                this.input.setAttribute('data-value', value);
            }

            this.renderCalendar();
            this.updateTimeSelection();
            this.updateTimeAvailability();
        } catch (e) {
            console.error('Error parsing date:', e);
        }
    }

    updateTimeSelection() {
        if (this.selectedTime) {
            this.picker.querySelectorAll('.picker-time-slot').forEach(slot => {
                const isSelected = slot.dataset.time === this.selectedTime;
                slot.classList.toggle('selected', isSelected);

                if (isSelected) {
                    const container = this.picker.querySelector('.picker-time-list');
                    if (container) {
                        container.scrollTop = slot.offsetTop - container.offsetTop - (container.clientHeight / 2) + (slot.clientHeight / 2);
                    }
                }
            });
        }
    }

    updateTimeAvailability() {
        const slots = this.picker.querySelectorAll('.picker-time-slot');
        const now = new Date();
        const targetDate = this.selectedDate ? new Date(this.selectedDate) : new Date();
        targetDate.setHours(0, 0, 0, 0);

        const today = new Date();
        today.setHours(0, 0, 0, 0);

        const isToday = targetDate.getTime() === today.getTime();
        const isPast = targetDate.getTime() < today.getTime();

        const currentHour = now.getHours();
        const currentMinute = now.getMinutes();

        slots.forEach(slot => {
            if (isPast) {
                slot.classList.add('disabled');
                return;
            }
            if (!isToday) {
                slot.classList.remove('disabled');
                return;
            }

            const [h, m] = slot.dataset.time.split(':').map(Number);
            if (h < currentHour || (h === currentHour && m < currentMinute)) {
                slot.classList.add('disabled');
            } else {
                slot.classList.remove('disabled');
            }
        });
    }

    prevMonth() {
        this.currentMonth.setMonth(this.currentMonth.getMonth() - 1);
        this.renderCalendar();
    }

    nextMonth() {
        this.currentMonth.setMonth(this.currentMonth.getMonth() + 1);
        this.renderCalendar();
    }

    selectToday() {
        const now = new Date();
        this.selectedDate = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        this.currentMonth = new Date(now.getFullYear(), now.getMonth(), 1);
        this.renderCalendar();
        this.updateTimeAvailability();
        this.updateInput();
    }

    clear() {
        this.selectedDate = null;
        this.selectedTime = null;
        this.input.value = '';
        this.renderCalendar();

        this.picker.querySelectorAll('.picker-time-slot').forEach(slot => {
            slot.classList.remove('selected');
            slot.classList.remove('disabled');
        });

        this.input.dispatchEvent(new Event('change', { bubbles: true }));
    }

    toggle() {
        if (this.isOpen) {
            this.close();
        } else {
            this.open();
        }
    }

    adjustPosition() {
        const rect = this.input.getBoundingClientRect();
        const pickerRect = this.picker.getBoundingClientRect();
        const pickerHeight = pickerRect.height || 320;
        const windowHeight = window.innerHeight;

        const spaceBelow = windowHeight - rect.bottom;

        if (spaceBelow < pickerHeight + 10 && rect.top > pickerHeight + 10) {
            this.picker.style.top = 'auto';
            this.picker.style.bottom = '100%';
            this.picker.style.marginTop = '0';
            this.picker.style.marginBottom = '8px';
            this.picker.classList.add('picker-above');
        } else {
            this.picker.style.top = '100%';
            this.picker.style.bottom = 'auto';
            this.picker.style.marginTop = '8px';
            this.picker.style.marginBottom = '0';
            this.picker.classList.remove('picker-above');
        }
    }

    open() {
        this.isOpen = true;
        this.picker.classList.add('open');

        this.adjustPosition();

        if (!this.selectedDate) {
            this.currentMonth = new Date();
            this.renderCalendar();
        }

        this.updateTimeAvailability();

        if (this.selectedTime) {
            this.updateTimeSelection();
        } else {
            const now = new Date();
            const h = now.getHours();
            const m = now.getMinutes();
            const step = this.options.timeStep || 30;
            const roundedM = Math.floor(m / step) * step;

            const time24 = `${h.toString().padStart(2, '0')}:${roundedM.toString().padStart(2, '0')}`;

            const slot = this.picker.querySelector(`.picker-time-slot[data-time="${time24}"]`);
            if (slot) {
                setTimeout(() => {
                    const container = this.picker.querySelector('.picker-time-list');
                    if (container) {
                        container.scrollTop = slot.offsetTop - container.offsetTop;
                    }
                }, 0);
            }
        }
    }

    close() {
        this.isOpen = false;
        this.picker.classList.remove('open');
    }
}

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('input[type="datetime-local"].custom-picker, input[type="datetime-local"].no-native').forEach(input => {
        const minDate = input.getAttribute('min') || null;

        new CustomDateTimePicker(input, {
            minDate: minDate,
            timeStep: 30,
            format24h: true
        });
    });
});