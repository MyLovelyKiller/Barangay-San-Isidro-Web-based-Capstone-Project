<?php

function scanFileWithClamAV($filePath, &$scanMessage = null, &$scanOutput = null)
{
    $clamScanPath = 'C:\Users\Gary\Downloads\clamav-1.5.4.win.x64\clamav-1.5.4.win.x64\clamscan.exe';

    // Check if ClamAV exists
    if (!is_file($clamScanPath)) {
        $scanMessage = 'ClamAV scanner was not found.';
        $scanOutput = [];
        return false;
    }

    // Check if file exists
    if (!is_file($filePath)) {
        $scanMessage = 'File to scan was not found.';
        $scanOutput = [];
        return false;
    }

    // Check if PHP can execute commands
    if (!function_exists('exec')) {
        $scanMessage = 'PHP exec() function is disabled.';
        $scanOutput = [];
        return false;
    }

    // Build ClamAV command
    $command =
        escapeshellarg($clamScanPath) .
        ' --no-summary ' .
        escapeshellarg($filePath) .
        ' 2>&1';

    $output = [];
    $exitCode = -1;

    // Run ClamAV
    exec($command, $output, $exitCode);

    // Return output to caller
    $scanOutput = $output;

    // Clean
    if ($exitCode === 0) {
        $scanMessage = 'Clean';
        return true;
    }

    // Virus detected
    if ($exitCode === 1) {
        $scanMessage = 'File was detected as infected.';
        return false;
    }

    // Scanner error
    $scanMessage = 'ClamAV scan failed. Exit code: ' . $exitCode;

    return false;
}


// ============================================================
// CREATE HARMLESS TEST FILE
// ============================================================

$testFile = __DIR__ . '/clamav-test.txt';

file_put_contents(
    $testFile,
    'This is a harmless ClamAV integration test file.'
);


// ============================================================
// SCAN FILE
// ============================================================

$message = '';
$scanOutput = [];

$result = scanFileWithClamAV(
    $testFile,
    $message,
    $scanOutput
);


// ============================================================
// DISPLAY TEST RESULTS
// ============================================================

echo '<h2>ClamAV PHP Test</h2>';

echo '<p>';
echo '<strong>Test file:</strong><br>';
echo htmlspecialchars($testFile);
echo '</p>';

echo '<hr>';


// Result
if ($result) {

    echo '<p style="color: green; font-weight: bold; font-size: 18px;">';
    echo 'RESULT: CLEAN';
    echo '</p>';

} else {

    echo '<p style="color: red; font-weight: bold; font-size: 18px;">';
    echo 'RESULT: FAILED / INFECTED';
    echo '</p>';
}


// Message
echo '<p>';
echo '<strong>Message:</strong><br>';
echo htmlspecialchars($message);
echo '</p>';


// ClamAV output
echo '<hr>';

echo '<h3>ClamAV Debug Output</h3>';

echo '<pre style="
    background: #f4f4f4;
    padding: 15px;
    border: 1px solid #ccc;
    white-space: pre-wrap;
">';

if (!empty($scanOutput)) {

    echo htmlspecialchars(
        implode(PHP_EOL, $scanOutput)
    );

} else {

    echo 'No output returned by ClamAV.';

}

echo '</pre>';


// Exit code
echo '<h3>ClamAV Exit Code</h3>';

echo '<pre>';

if ($result) {
    echo '0 - CLEAN';
} else {
    echo 'ClamAV returned an error or detection.';
}

echo '</pre>';


// ============================================================
// DELETE TEST FILE
// ============================================================

if (file_exists($testFile)) {
    unlink($testFile);
}

?>