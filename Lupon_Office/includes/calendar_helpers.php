<?php
/**
 * Recomputes `cases.schedule_date` as the soonest upcoming,
 * still-Scheduled hearing/mediation/conciliation event for that
 * case. Call this after any insert/update/delete that could
 * change a case's next hearing date.
 *
 * This keeps cases.php, view_case.php, and summons.php showing
 * an accurate "Next Hearing" without duplicating scheduling
 * logic in each of those files — the calendar becomes the single
 * source of truth, cases.schedule_date is just a cached pointer
 * into it.
 */
function calendar_sync_case_schedule($conn, $case_id) {
    $case_id = (int) $case_id;
    if ($case_id <= 0) {
        return;
    }

    $sql = "SELECT e.event_date
            FROM calendar_events e
            INNER JOIN event_types et ON et.id = e.event_type_id
            WHERE e.case_id = ?
              AND e.status = 'Scheduled'
              AND et.name IN ('Hearing', 'Mediation', 'Conciliation')
              AND e.event_date >= CURDATE()
            ORDER BY e.event_date ASC, e.start_time ASC
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $case_id);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $next_date = $row['event_date'] ?? null; // mysqli sends this through as NULL correctly

    $update = $conn->prepare("UPDATE cases SET schedule_date = ? WHERE id = ?");
    $update->bind_param("si", $next_date, $case_id);
    $update->execute();
    $update->close();
}

/**
 * Keeps a calendar_events entry in sync with the "Schedule" date
 * on the case form (edit_case.php, and the case-creation form
 * behind save_case.php). Case Management is the quick-entry point;
 * this makes sure whatever gets typed there actually shows up on
 * the Calendar too, instead of being a date only cases.php can see.
 *
 * - A schedule date with no matching calendar event yet creates one
 *   (defaulting to type "Hearing", 9:00 AM, at the barangay hall).
 * - A schedule date with a matching event already on file moves
 *   that event instead of creating a duplicate.
 * - Clearing the schedule date marks the matching event Cancelled
 *   rather than deleting it, so the record isn't lost.
 * - Either way, finishes by calling calendar_sync_case_schedule()
 *   so cases.schedule_date ends up reflecting the true earliest
 *   upcoming event afterward — which may differ from what was just
 *   typed, if an even earlier hearing was already on the calendar.
 *
 * "The matching event" = the most recently created still-Scheduled
 * Hearing/Mediation/Conciliation event for this case. If a case has
 * several such events booked directly through the Calendar, this
 * field only ever tracks the newest one — see the note in the
 * accompanying response for that tradeoff.
 */
function calendar_apply_case_schedule_change($conn, $case_id, $case_no, $schedule_date, $official_id) {
    $case_id = (int) $case_id;
    if ($case_id <= 0) {
        return;
    }

    $find_stmt = $conn->prepare(
        "SELECT e.id
         FROM calendar_events e
         INNER JOIN event_types et ON et.id = e.event_type_id
         WHERE e.case_id = ?
           AND e.status = 'Scheduled'
           AND et.name IN ('Hearing', 'Mediation', 'Conciliation')
         ORDER BY e.id DESC
         LIMIT 1"
    );
    $find_stmt->bind_param("i", $case_id);
    $find_stmt->execute();
    $existing = $find_stmt->get_result()->fetch_assoc();
    $find_stmt->close();

    $schedule_date = trim((string) $schedule_date);

    if ($schedule_date === '' || $schedule_date === '0000-00-00') {
        // Schedule was cleared — cancel the linked event rather than delete it.
        if ($existing) {
            $cancel = $conn->prepare("UPDATE calendar_events SET status = 'Cancelled' WHERE id = ?");
            $cancel->bind_param("i", $existing['id']);
            $cancel->execute();
            $cancel->close();
        }
    } elseif ($existing) {
        // Move the existing event to the new date instead of creating a duplicate.
        $move = $conn->prepare("UPDATE calendar_events SET event_date = ? WHERE id = ?");
        $move->bind_param("si", $schedule_date, $existing['id']);
        $move->execute();
        $move->close();
    } else {
        // No event yet — create one so it actually shows up on the Calendar.
        $type_row = $conn->query("SELECT id FROM event_types WHERE name = 'Hearing' LIMIT 1")->fetch_assoc();
        if ($type_row) {
            $type_id = (int) $type_row['id'];
            $title = "Hearing - {$case_no}";
            $start_time = '09:00:00';
            $location = 'Barangay Hall - Lupon Office';

            $insert = $conn->prepare(
                "INSERT INTO calendar_events
                    (title, event_type_id, case_id, event_date, start_time, location, status, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, 'Scheduled', ?)"
            );
            $insert->bind_param("siisssi", $title, $type_id, $case_id, $schedule_date, $start_time, $location, $official_id);
            $insert->execute();
            $insert->close();
        }
    }

    // Recompute cases.schedule_date from the calendar so it always
    // reflects the true earliest upcoming hearing, not just what
    // was typed into this one form.
    calendar_sync_case_schedule($conn, $case_id);
}

/**
 * "Jan 5, 2026 at 2:30 PM" — used to make audit trail entries for
 * rescheduled events human-readable instead of raw "2026-01-05" /
 * "14:30:00" values.
 */
function formatDisplayDateTime($event_date, $start_time) {
    $dt = DateTime::createFromFormat('Y-m-d H:i:s', $event_date . ' ' . $start_time)
        ?: DateTime::createFromFormat('Y-m-d H:i', $event_date . ' ' . $start_time);

    if (!$dt) {
        return trim($event_date . ' ' . $start_time);
    }

    return $dt->format('M j, Y \a\t g:i A');
}
