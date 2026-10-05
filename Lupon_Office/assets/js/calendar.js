document.addEventListener('DOMContentLoaded', function () {

    /* ---------------- STATE ---------------- */
    var csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    var viewDate = new Date();
    viewDate.setDate(1); // pin to day 1 so month navigation never skips a month (28-31 day drift)

    var eventsByDate = {};
    var caseSearchTimer = null;

    /* ---------------- DOM REFS ---------------- */
    var grid = document.getElementById('calendarGrid');
    var monthLabel = document.getElementById('calendarMonthLabel');
    var legendList = document.getElementById('typeLegend');
    var upcomingList = document.getElementById('upcomingList');
    var typeSelect = document.getElementById('eventType');

    var modal = document.getElementById('eventModal');
    var modalTitle = document.getElementById('modalTitle');
    var eventForm = document.getElementById('eventForm');
    var formError = document.getElementById('formError');
    var btnDelete = document.getElementById('btnDeleteEvent');

    var caseSearchInput = document.getElementById('caseSearchInput');
    var caseSearchResults = document.getElementById('caseSearchResults');
    var linkedCaseChip = document.getElementById('linkedCaseChip');
    var linkedCaseIdField = document.getElementById('linkedCaseId');

    /* ---------------- HELPERS ---------------- */
    function pad2(n) { return n < 10 ? '0' + n : '' + n; }

    function toYMD(date) {
        return date.getFullYear() + '-' + pad2(date.getMonth() + 1) + '-' + pad2(date.getDate());
    }

    function formatDisplayDate(ymd) {
        var p = ymd.split('-');
        var d = new Date(p[0], p[1] - 1, p[2]);
        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
    }

    function formatTime12(hhmm) {
        if (!hhmm) return '';
        var p = hhmm.split(':');
        var h = parseInt(p[0], 10);
        var suffix = h >= 12 ? 'PM' : 'AM';
        var h12 = h % 12 === 0 ? 12 : h % 12;
        return h12 + ':' + p[1] + ' ' + suffix;
    }

    // All dynamic values below are inserted via textContent / property
    // assignment (never innerHTML string-building), so nothing coming
    // back from the API — including complainant/respondent names typed
    // in by the public — can inject markup into this page.
    function apiFetch(url, options) {
        options = options || {};
        options.credentials = 'same-origin';
        return fetch(url, options).then(function (res) {
            if (res.status === 401) {
                window.location.href = '/BMS/CODES/login.php?timeout=1';
                return Promise.reject(new Error('Session expired'));
            }
            return res.json().then(function (data) {
                return { ok: res.ok, status: res.status, data: data };
            });
        });
    }

    /* ---------------- EVENT TYPES (legend + dropdown, driven by the DB) ---------------- */
    function loadEventTypes() {
        return apiFetch('/BMS/Lupon_Office/calendar/calendar_event_types.php').then(function (result) {
            if (!result.data.success) return;

            typeSelect.innerHTML = '';
            legendList.innerHTML = '';

            result.data.types.forEach(function (t) {
                var opt = document.createElement('option');
                opt.value = t.id;
                opt.textContent = t.name;
                typeSelect.appendChild(opt);

                var li = document.createElement('li');
                var dot = document.createElement('span');
                dot.className = 'legend-dot';
                dot.style.background = t.color;
                li.appendChild(dot);
                li.appendChild(document.createTextNode(t.name));
                legendList.appendChild(li);
            });
        }).catch(function (err) { console.error('Could not load event types:', err); });
    }

    /* ---------------- CALENDAR GRID ---------------- */
    function getGridRange(year, month) {
        var firstOfMonth = new Date(year, month, 1);
        var gridStart = new Date(year, month, 1 - firstOfMonth.getDay());

        var lastOfMonth = new Date(year, month + 1, 0);
        var gridEnd = new Date(year, month, lastOfMonth.getDate() + (6 - lastOfMonth.getDay()));

        return { gridStart: gridStart, gridEnd: gridEnd };
    }

    function renderCalendar() {
        var year = viewDate.getFullYear();
        var month = viewDate.getMonth();

        monthLabel.textContent = viewDate.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });

        var range = getGridRange(year, month);
        var todayStr = toYMD(new Date());

        grid.innerHTML = '';

        var cursor = new Date(range.gridStart);
        while (cursor <= range.gridEnd) {
            var ymd = toYMD(cursor);
            var isOutside = cursor.getMonth() !== month;
            var isToday = ymd === todayStr;

            var cell = document.createElement('div');
            cell.className = 'cal-day' + (isOutside ? ' cal-day--outside' : '') + (isToday ? ' cal-day--today' : '');
            cell.dataset.date = ymd;

            var num = document.createElement('div');
            num.className = 'cal-day-number';
            num.textContent = cursor.getDate();
            cell.appendChild(num);

            var eventsWrap = document.createElement('div');
            eventsWrap.className = 'cal-day-events';
            cell.appendChild(eventsWrap);

            cell.addEventListener('click', function () { openCreateModal(this.dataset.date); });

            grid.appendChild(cell);
            cursor.setDate(cursor.getDate() + 1);
        }

        // Fetch only the visible range — never the whole events table.
        loadEventsForRange(toYMD(range.gridStart), toYMD(range.gridEnd));
    }

    function loadEventsForRange(start, end) {
        apiFetch('/BMS/Lupon_Office/calendar/calendar_events_fetch.php?start=' + start + '&end=' + end).then(function (result) {
            if (!result.data.success) return;

            eventsByDate = {};
            result.data.events.forEach(function (evt) {
                if (!eventsByDate[evt.date]) eventsByDate[evt.date] = [];
                eventsByDate[evt.date].push(evt);
            });

            Object.keys(eventsByDate).forEach(function (ymd) {
                var cell = grid.querySelector('.cal-day[data-date="' + ymd + '"]');
                if (cell) renderDayEvents(cell, eventsByDate[ymd]);
            });
        }).catch(function (err) { console.error('Could not load events:', err); });
    }

    function renderDayEvents(cell, events) {
        var wrap = cell.querySelector('.cal-day-events');
        wrap.innerHTML = '';

        events.forEach(function (evt, index) {
            var chip = document.createElement('div');
            chip.className = 'cal-event cal-event--' + evt.status + (index >= 3 ? ' cal-event--extra' : '');
            chip.style.setProperty('--evt-color', evt.color);
            chip.title = evt.title
                + (evt.case_no ? ' — ' + evt.case_no : '')
                + (evt.description ? '\n' + evt.description : '');

            var timeSpan = document.createElement('span');
            timeSpan.className = 'cal-event-time';
            timeSpan.textContent = formatTime12(evt.start_time);

            var titleSpan = document.createElement('span');
            titleSpan.className = 'cal-event-title';
            titleSpan.textContent = ' ' + evt.title;

            chip.appendChild(timeSpan);
            chip.appendChild(titleSpan);

            chip.addEventListener('click', function (e) {
                e.stopPropagation();
                openEditModal(evt);
            });

            wrap.appendChild(chip);
        });

        if (events.length > 3) {
            var more = document.createElement('button');
            more.type = 'button';
            more.className = 'cal-more';
            more.textContent = '+' + (events.length - 3) + ' more';
            more.addEventListener('click', function (e) {
                e.stopPropagation();
                var expanded = cell.classList.toggle('expanded');
                more.textContent = expanded ? 'Show less' : '+' + (events.length - 3) + ' more';
            });
            wrap.appendChild(more);
        }
    }

    /* ---------------- UPCOMING PANEL ---------------- */
    function loadUpcoming() {
        var today = new Date();
        var future = new Date();
        future.setDate(future.getDate() + 30);

        apiFetch('/BMS/Lupon_Office/calendar/calendar_events_fetch.php?start=' + toYMD(today) + '&end=' + toYMD(future)).then(function (result) {
            upcomingList.innerHTML = '';
            if (!result.data.success) return;

            var upcoming = result.data.events
                .filter(function (e) { return e.status === 'Scheduled'; })
                .slice(0, 8);

            if (upcoming.length === 0) {
                var empty = document.createElement('li');
                empty.className = 'empty-note';
                empty.textContent = 'No upcoming hearings in the next 30 days.';
                upcomingList.appendChild(empty);
                return;
            }

            upcoming.forEach(function (evt) {
                var li = document.createElement('li');
                li.className = 'upcoming-item';
                li.style.setProperty('--evt-color', evt.color);

                var dateDiv = document.createElement('div');
                dateDiv.className = 'upcoming-date';
                dateDiv.textContent = formatDisplayDate(evt.date) + ' \u00B7 ' + formatTime12(evt.start_time);

                var titleDiv = document.createElement('div');
                titleDiv.className = 'upcoming-title';
                titleDiv.textContent = evt.title;

                li.appendChild(dateDiv);
                li.appendChild(titleDiv);

                if (evt.case_no) {
                    var caseDiv = document.createElement('div');
                    caseDiv.className = 'upcoming-case';
                    caseDiv.textContent = evt.case_no;
                    li.appendChild(caseDiv);
                }

                if (evt.description) {
                    var notesDiv = document.createElement('div');
                    notesDiv.className = 'upcoming-notes';
                    notesDiv.textContent = evt.description;
                    li.appendChild(notesDiv);
                }

                li.addEventListener('click', function () { openEditModal(evt); });
                upcomingList.appendChild(li);
            });
        }).catch(function (err) { console.error('Could not load upcoming hearings:', err); });
    }

    /* ---------------- MODAL ---------------- */
    var modalSnapshot = '';
    var originalEventStatus = null;

    function serializeEventForm() {
        var data = new FormData(eventForm);
        var pairs = [];
        data.forEach(function (value, key) { pairs.push(key + '=' + value); });
        return pairs.join('&');
    }

    function isModalDirty() {
        return serializeEventForm() !== modalSnapshot;
    }

    function closeModalWithConfirm() {
        if (isModalDirty()) {
            showConfirmModal('Any unsaved changes or progress may be lost.', {
                title: 'Are you sure you want to exit?',
                confirmText: 'Discard & Exit',
                danger: true
            }).then(function (confirmed) {
                if (confirmed) closeModal();
            });
        } else {
            closeModal();
        }
    }

    function openCreateModal(dateStr) {
        eventForm.reset();
        document.getElementById('eventId').value = '';
        clearLinkedCase();
        formError.hidden = true;
        btnDelete.hidden = true;
        modalTitle.textContent = 'New Event';
        if (dateStr) document.getElementById('eventDate').value = dateStr;
        document.getElementById('eventStatus').value = 'Scheduled';
        originalEventStatus = null;
        modal.hidden = false;
        modalSnapshot = serializeEventForm();
    }

    function openEditModal(evt) {
        eventForm.reset();
        formError.hidden = true;
        btnDelete.hidden = false;
        modalTitle.textContent = 'Edit Event';

        document.getElementById('eventId').value = evt.id;
        document.getElementById('eventTitle').value = evt.title;
        document.getElementById('eventType').value = evt.type_id;
        document.getElementById('eventStatus').value = evt.status;
        document.getElementById('eventDate').value = evt.date;
        document.getElementById('eventStart').value = evt.start_time;
        document.getElementById('eventEnd').value = evt.end_time || '';
        document.getElementById('eventLocation').value = evt.location || '';
        document.getElementById('eventDescription').value = evt.description || '';

        if (evt.case_id) {
            setLinkedCase({ id: evt.case_id, case_no: evt.case_no, complainant: evt.complainant, respondent: evt.respondent });
        } else {
            clearLinkedCase();
        }

        originalEventStatus = evt.status;
        modal.hidden = false;
        modalSnapshot = serializeEventForm();
    }

    function closeModal() { modal.hidden = true; }

    document.getElementById('btnNewEvent').addEventListener('click', function () { openCreateModal(toYMD(new Date())); });
    document.getElementById('modalClose').addEventListener('click', closeModalWithConfirm);
    document.getElementById('btnCancelModal').addEventListener('click', closeModalWithConfirm);
    modal.addEventListener('click', function (e) { if (e.target === modal) closeModalWithConfirm(); });

    /* ---------------- CASE SEARCH / LINKING ---------------- */
    function setLinkedCase(c) {
        linkedCaseIdField.value = c.id;
        linkedCaseChip.hidden = false;
        linkedCaseChip.innerHTML = '';

        var text = document.createElement('span');
        text.textContent = c.case_no + ' — ' + c.complainant + ' vs ' + c.respondent;

        var clearBtn = document.createElement('button');
        clearBtn.type = 'button';
        clearBtn.textContent = '\u00D7';
        clearBtn.setAttribute('aria-label', 'Remove linked case');
        clearBtn.addEventListener('click', clearLinkedCase);

        linkedCaseChip.appendChild(text);
        linkedCaseChip.appendChild(clearBtn);

        caseSearchInput.value = '';
        caseSearchInput.hidden = true;
        caseSearchResults.hidden = true;
    }

    function clearLinkedCase() {
        linkedCaseIdField.value = '';
        linkedCaseChip.hidden = true;
        linkedCaseChip.innerHTML = '';
        caseSearchInput.hidden = false;
        caseSearchInput.value = '';
        caseSearchResults.hidden = true;
        caseSearchResults.innerHTML = '';
    }

    caseSearchInput.addEventListener('input', function () {
        var q = this.value.trim();
        clearTimeout(caseSearchTimer);
        if (q.length < 2) {
            caseSearchResults.hidden = true;
            return;
        }
        caseSearchTimer = setTimeout(function () {
            apiFetch('/BMS/Lupon_Office/calendar/calendar_case_search.php?q=' + encodeURIComponent(q)).then(function (result) {
                if (result.data.success) renderCaseSearchResults(result.data.cases);
            }).catch(function (err) { console.error('Case search failed:', err); });
        }, 300);
    });

    function renderCaseSearchResults(cases) {
        caseSearchResults.innerHTML = '';
        if (cases.length === 0) {
            caseSearchResults.hidden = true;
            return;
        }
        cases.forEach(function (c) {
            var li = document.createElement('li');

            var no = document.createElement('div');
            no.className = 'case-result-no';
            no.textContent = c.case_no;

            var parties = document.createElement('div');
            parties.className = 'case-result-parties';
            parties.textContent = c.complainant + ' vs ' + c.respondent;

            li.appendChild(no);
            li.appendChild(parties);
            li.addEventListener('click', function () { setLinkedCase(c); });

            caseSearchResults.appendChild(li);
        });
        caseSearchResults.hidden = false;
    }

    /* ---------------- FORM SUBMIT / DELETE ---------------- */
    var isSubmitting = false;
    var saveBtn = eventForm.querySelector('button[type="submit"]');

    function performSave(url, payload) {
        isSubmitting = true;
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.textContent = 'Saving…';
        }

        apiFetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify(payload)
        }).then(function (result) {
            if (result.data.success) {
                closeModal();
                renderCalendar();
                loadUpcoming();
            } else {
                formError.textContent = result.data.message || 'Something went wrong. Please try again.';
                formError.hidden = false;
            }
        }).catch(function (err) {
            console.error('Save failed:', err);
            formError.textContent = 'Could not reach the server. Please try again.';
            formError.hidden = false;
        }).finally(function () {
            isSubmitting = false;
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.textContent = 'Save Event';
            }
        });
    }

    eventForm.addEventListener('submit', function (e) {
        e.preventDefault();
        if (isSubmitting) return; // guards against a double-click firing two requests
        formError.hidden = true;

        var id = document.getElementById('eventId').value;
        var payload = {
            title: document.getElementById('eventTitle').value.trim(),
            event_type_id: document.getElementById('eventType').value,
            status: document.getElementById('eventStatus').value,
            event_date: document.getElementById('eventDate').value,
            start_time: document.getElementById('eventStart').value,
            end_time: document.getElementById('eventEnd').value,
            location: document.getElementById('eventLocation').value.trim(),
            description: document.getElementById('eventDescription').value.trim(),
            case_id: linkedCaseIdField.value || null
        };

        var url = id ? '/BMS/Lupon_Office/calendar/calendar_update_event.php' : '/BMS/Lupon_Office/calendar/calendar_save_event.php';
        if (id) payload.id = id;

        // Creating a brand new event is also a meaningful action (it goes
        // straight onto the shared calendar) — confirm it before saving.
        if (!id) {
            var newEventSummary = payload.title || '(untitled event)';
            if (payload.event_date) {
                newEventSummary += ' — ' + formatDisplayDate(payload.event_date);
            }
            showConfirmModal('You are about to add "' + newEventSummary + '" to the calendar.', {
                title: 'Create this event?',
                confirmText: 'Create Event'
            }).then(function (confirmed) {
                if (confirmed) performSave(url, payload);
            });
            return;
        }

        // Cancelling a hearing is a meaningful state change (it may already
        // be on someone's calendar) — confirm it, but only when it's a real,
        // new cancellation, not just re-saving an event that was already
        // Cancelled.
        if (id && payload.status === 'Cancelled' && originalEventStatus !== 'Cancelled') {
            showConfirmModal('Parties who were relying on this schedule will need to be informed separately.', {
                title: 'Mark this event as Cancelled?',
                confirmText: 'Mark Cancelled',
                danger: true
            }).then(function (confirmed) {
                if (confirmed) performSave(url, payload);
            });
            return;
        }

        performSave(url, payload);
    });

    function performDelete(id) {
        isSubmitting = true;
        btnDelete.disabled = true;
        btnDelete.textContent = 'Deleting…';

        apiFetch('/BMS/Lupon_Office/calendar/calendar_delete_event.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify({ id: id })
        }).then(function (result) {
            if (result.data.success) {
                closeModal();
                renderCalendar();
                loadUpcoming();
            } else {
                formError.textContent = result.data.message || 'Could not delete this event.';
                formError.hidden = false;
            }
        }).catch(function (err) {
            console.error('Delete failed:', err);
            formError.textContent = 'Could not reach the server. Please try again.';
            formError.hidden = false;
        }).finally(function () {
            isSubmitting = false;
            btnDelete.disabled = false;
            btnDelete.textContent = 'Delete';
        });
    }

    btnDelete.addEventListener('click', function () {
        if (isSubmitting) return;
        var id = document.getElementById('eventId').value;
        if (!id) return;

        showConfirmModal('This cannot be undone.', {
            title: 'Delete this event?',
            confirmText: 'Delete',
            danger: true
        }).then(function (confirmed) {
            if (confirmed) performDelete(id);
        });
    });

    /* ---------------- NAVIGATION ---------------- */
    document.getElementById('prevMonth').addEventListener('click', function () {
        viewDate.setMonth(viewDate.getMonth() - 1);
        renderCalendar();
    });
    document.getElementById('nextMonth').addEventListener('click', function () {
        viewDate.setMonth(viewDate.getMonth() + 1);
        renderCalendar();
    });
    document.getElementById('btnToday').addEventListener('click', function () {
        viewDate = new Date();
        viewDate.setDate(1);
        renderCalendar();
    });

    /* ---------------- INIT ---------------- */
    loadEventTypes().then(function () {
        renderCalendar();
        loadUpcoming();
        applyCasePrefillIfPresent();
    });

    /**
     * Handles the calendar.php?schedule_case=123 deep link from
     * view_case.php's "Schedule Hearing" button.
     */
    function applyCasePrefillIfPresent() {
        if (!window.PREFILL_CASE) return;

        var prefill = window.PREFILL_CASE;

        openCreateModal(toYMD(new Date()));
        setLinkedCase(prefill);

        var hearingOption = Array.prototype.slice.call(typeSelect.options)
            .find(function (opt) { return opt.textContent === 'Hearing'; });
        if (hearingOption) typeSelect.value = hearingOption.value;

        var titleField = document.getElementById('eventTitle');
        titleField.value = 'Hearing — ' + prefill.case_no;
        titleField.focus();
        titleField.select();

        // The prefill itself isn't a user change — treat it as the clean
        // starting point so closing the modal without editing further
        // doesn't trigger an unnecessary "unsaved changes" prompt.
        modalSnapshot = serializeEventForm();
    }

});
