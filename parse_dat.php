<?php
function parseDAT($filePath) {
    $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $rawRecords = [];

    foreach ($lines as $line) {
        $parts = preg_split('/\s+/', trim($line));
        if (count($parts) < 2) continue;

        $empId = $parts[0];
        $timestamp = $parts[1] . (isset($parts[2]) ? " " . $parts[2] : "");

        // Validate timestamp format
        $ts = strtotime($timestamp);
        if (!$ts) continue;

        $date = date("Y-m-d", $ts);
        $time = date("H:i:s", $ts);

        $rawRecords[$empId][$date][] = $time;
    }

    // Final structured output
    $data = [];

    foreach ($rawRecords as $empId => $dates) {
        if (!isset($data[$empId])) {
            $data[$empId] = [
                'records' => []
            ];
        }

        foreach ($dates as $date => $times) {
            sort($times); // Order chronologically

            $data[$empId]['records'][] = [
                'emp_id' => $empId,
                'date'    => $date,
                'in_time' => $times[0] ?? '',
                'out_time'=> end($times) ?? '',
            ];
        }
    }

    return $data;
}
?>
