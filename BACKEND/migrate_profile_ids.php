<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}

require_once __DIR__ . '/db_connect.php';

$apply = in_array('--apply', $argv, true);
$conn->begin_transaction();
$migrated = 0;

try {
    foreach ([
        ['table' => 'residents', 'id' => 'resident_id'],
        ['table' => 'officials', 'id' => 'official_id'],
    ] as $target) {
        $table = $target['table'];
        $idColumn = $target['id'];
        $result = $conn->query(
            "SELECT {$idColumn}, id_number FROM {$table} WHERE id_number IS NOT NULL AND id_number <> ''"
        );
        if (!$result) {
            throw new RuntimeException('Could not read profile ID records.');
        }

        $update = $conn->prepare("UPDATE {$table} SET id_number = ? WHERE {$idColumn} = ?");
        while ($row = $result->fetch_assoc()) {
            $storedValue = (string)$row['id_number'];
            if (strncmp($storedValue, 'v2:', 3) === 0) {
                continue;
            }

            $plainValue = bms_decrypt_profile_id($storedValue);
            if (!is_string($plainValue) || $plainValue === '') {
                throw new RuntimeException('A profile ID could not be decrypted; no changes were committed.');
            }

            $encryptedValue = bms_encrypt_profile_id($plainValue);
            if (!is_string($encryptedValue)) {
                throw new RuntimeException('Profile ID encryption failed; no changes were committed.');
            }

            $rowId = (int)$row[$idColumn];
            $update->bind_param('si', $encryptedValue, $rowId);
            if (!$update->execute()) {
                throw new RuntimeException('A profile ID could not be updated; no changes were committed.');
            }
            $migrated++;
        }
        $update->close();
        $result->free();
    }

    if ($apply) {
        $conn->commit();
        echo "Migrated {$migrated} profile ID values.\n";
    } else {
        $conn->rollback();
        echo "Dry run succeeded for {$migrated} profile ID values. Run with --apply to commit.\n";
    }
} catch (Throwable $exception) {
    $conn->rollback();
    error_log('BMS profile ID migration failed: ' . $exception->getMessage());
    fwrite(STDERR, "Migration stopped; the transaction was rolled back.\n");
    exit(1);
}
