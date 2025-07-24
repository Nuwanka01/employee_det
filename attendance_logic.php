<?php
require_once 'calendarific.php';

date_default_timezone_set('Asia/Colombo');
function apply_attendance_rules(array $records, array $publicHolidays = []): array {
    $report = [];

    if (empty($records)) return [];

    // Extract and validate the first date
    $firstDate = $records[0]['date'] ?? null;
    if (!$firstDate || substr_count($firstDate, '-') !== 2) {
        return []; // Invalid or missing date format
    }

    [$year, $month, $day] = explode('-', $firstDate);
    if (!checkdate((int)$month, (int)$day, (int)$year)) {
        return []; // Date is invalid
    }

    $indexed = [];
    foreach ($records as $r) {
        if (!empty($r['date'])) {
            $indexed[$r['date']] = $r;
        }
    }

    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, (int)$month, (int)$year);
    for ($d = 1; $d <= $daysInMonth; $d++) {
        $date = sprintf('%04d-%02d-%02d', $year, $month, $d);
        $dayName = date('l', strtotime($date));

        $inTimeStr = '-';
        $outTimeStr = '-';
        $workedMinutes = 0;
        $lateMinutes = 0;
        $remark = 'Absent';

        $inTime = $outTime = null;

        if (isset($indexed[$date])) {
            $rec = $indexed[$date];
            $inTimeStr = $rec['in_time'] ?? '-';
            $outTimeStr = $rec['out_time'] ?? '-';

            $inTime = $inTimeStr !== '-' ? strtotime($inTimeStr) : null;
            $outTime = $outTimeStr !== '-' ? strtotime($outTimeStr) : null;

            if ($inTime && $outTime) {
                $workedMinutes = round(($outTime - $inTime) / 60);
                if ($inTime > strtotime("08:30")) {
                    $lateMinutes = round(($inTime - strtotime("08:30")) / 60);
                }

                if ($inTime >= strtotime("09:00") && $inTime <= strtotime("10:00") && $outTime < strtotime("16:15")) {
                    $remark = "Short Leave";
                } elseif (
                    ($inTime < strtotime("08:15") && $outTime >= strtotime("12:00") && $outTime < strtotime("16:15")) ||
                    ($inTime >= strtotime("08:15") && $inTime <= strtotime("08:30") && $outTime < strtotime("12:30")) ||
                    ($inTime >= strtotime("12:30") && $outTime >= strtotime("16:15")) ||
                    ($inTime >= strtotime("08:30") && $inTime <= strtotime("09:00") && $outTime < strtotime("14:45")) ||
                    ($inTime > strtotime("10:00") || $outTime <= strtotime("12:00"))
                ) {
                    $remark = "Half Day";
                } else {
                    $remark = "Normal Day";
                }
            } elseif ($inTime || $outTime) {
                $remark = "Missing Data";
            }
        }

        // Override for public holidays
        if (isset($publicHolidays[$date])) {
            $remark = "Public Holiday - " . $publicHolidays[$date];
            $inTimeStr = '-';
            $outTimeStr = '-';
            $workedMinutes = 0;
            $lateMinutes = 0;
        }

        $report[] = [
            'date' => $date,
            'day' => $dayName,
            'in_time' => $inTimeStr,
            'out_time' => $outTimeStr,
            'worked_minutes' => $workedMinutes,
            'late_minutes' => $lateMinutes,
            'remark' => $remark
        ];
    }

    return $report;
}
