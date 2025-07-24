<?php
session_start();

$targetDir   = 'uploads/';
$allowedExts = ['pdf', 'dat'];
$allData     = [];                         // final combined array

// --- loop through every chosen file ---
foreach ($_FILES['fileToUpload']['name'] as $idx => $origName) {
    if ($origName === '') continue;       // skipped input slot

    $tmpName  = $_FILES['fileToUpload']['tmp_name'][$idx];
    $ext      = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

    if (!in_array($ext, $allowedExts)) continue;

    // give each file a unique name to avoid clashes
    $destPath = $targetDir . uniqid('', true) . '_' . basename($origName);
    if (!move_uploaded_file($tmpName, $destPath)) {
        // optional: collect errors and show later
        continue;
    }

    // --- parse the file ---
    if ($ext === 'pdf') {
        require_once 'parse_pdf.php';
        $fileData = parsePDF($destPath);   // wrapper added in step 3
    } else {                               // .dat
        require_once 'parse_dat.php';
        $fileData = parseDAT($destPath);
    }

    // --- merge into master array ---
    foreach ($fileData as $empId => $emp) {
        if (!isset($allData[$empId])) {
            $allData[$empId] = $emp;
        } else {
            // append new records then re‑sort by date
            $allData[$empId]['records'] = array_merge(
                $allData[$empId]['records'],
                $emp['records']
            );
        }
    }
}

/* ----- sort each employee’s records chronologically (optional) ----- */
foreach ($allData as &$emp) {
    usort($emp['records'], fn ($a, $b) => strcmp($a['date'], $b['date']));
}

$_SESSION['attendance_data'] = $allData;
header('Location: report.php');
exit;
