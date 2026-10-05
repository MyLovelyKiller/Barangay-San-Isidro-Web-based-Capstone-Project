<?php
/**
 * Shared helpers for case_history — field-level change tracking
 * for the `cases` table. Included by edit_case.php, which now
 * handles viewing, saving, and cancelling a case edit in one file
 * (it logs edits made through the form, and the automatic
 * Pending -> Ongoing transition and its reversal on Cancel).
 */

/**
 * Friendly display labels for case columns. Anything not listed
 * here falls back to a Title Cased version of the column name,
 * so this never needs to be kept perfectly in sync.
 */
function case_history_field_label($field_name) {
    $labels = [
        'case_type'            => 'Complaint Type',
        'status'                => 'Status',
        'date_filed'            => 'Date Filed',
        'schedule_date'         => 'Schedule',
        'complainant_name'      => 'Complainant Name',
        'complainant_contact'   => 'Complainant Contact',
        'complainant_address'   => 'Complainant Address',
        'respondent_name'       => 'Respondent Name',
        'respondent_contact'    => 'Respondent Contact',
        'respondent_address'    => 'Respondent Address',
        'complaint_details'     => 'Complaint Details',
        'summary_discussions'   => 'Summary of Discussions',
    ];

    return $labels[$field_name] ?? ucwords(str_replace('_', ' ', $field_name));
}

/**
 * Logs a single field change. Call this after the actual UPDATE
 * has already succeeded, never before — we don't want history
 * entries for changes that didn't actually persist.
 */
function case_history_log_field($conn, $case_id, $field_name, $old_value, $new_value, $changed_by) {
    $stmt = $conn->prepare(
        "INSERT INTO case_history (case_id, field_name, old_value, new_value, changed_by) VALUES (?, ?, ?, ?, ?)"
    );
    $stmt->bind_param("issss", $case_id, $field_name, $old_value, $new_value, $changed_by);
    $stmt->execute();
    $stmt->close();
}

/**
 * Compares an old row (from a SELECT * before the update) against
 * an array of new values [column => value] and logs one row per
 * field that actually changed. Returns how many fields changed.
 *
 * Values are trimmed and NULL/empty-string are treated as
 * equivalent before comparing, so e.g. saving the form without
 * touching an already-blank field doesn't create a noise entry.
 */
function case_history_log_diff($conn, $case_id, $old_row, $new_values, $changed_by) {
    $changed_count = 0;

    foreach ($new_values as $field => $new_value) {
        $old_value = $old_row[$field] ?? null;

        $old_norm = $old_value === null ? '' : trim((string) $old_value);
        $new_norm = $new_value === null ? '' : trim((string) $new_value);

        if ($old_norm !== $new_norm) {
            case_history_log_field($conn, $case_id, $field, $old_value, $new_value, $changed_by);
            $changed_count++;
        }
    }

    return $changed_count;
}

/**
 * Formats a new note as a dated, attributed journal entry and
 * appends it below whatever is already in the field. Used for
 * cumulative fields (Complaint Details, Summary of Discussions)
 * that are meant to grow over the life of a case rather than be
 * overwritten each time they're edited.
 *
 * Returns the existing text unchanged if there's nothing new to add.
 */
function case_append_entry($existing_text, $new_text, $changed_by) {
    $new_text = trim((string) $new_text);
    $existing_text = trim((string) $existing_text);

    if ($new_text === '') {
        return $existing_text;
    }

    $timestamp = date('M j, Y g:i A');
    $entry = "[{$timestamp} — {$changed_by}]\n{$new_text}";

    return $existing_text === '' ? $entry : $existing_text . "\n\n" . $entry;
}
