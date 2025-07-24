<?php
ob_clean();
require 'vendor/autoload.php';
require_once 'attendance_logic.php';
require_once 'calendarific.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

session_start();

$empId = $_GET['emp'] ?? null;
$data = $_SESSION['attendance_data'] ?? [];

if (!$empId || !isset($data[$empId])) {
    die("Invalid employee ID");
}

$employee = $data[$empId];
$records = $employee['records'] ?? [];

if (!$records) die("No attendance records found.");

$publicHolidays = [];
$startDate = new DateTime($records[0]['date']);
$endDate = new DateTime(end($records)['date']);
$interval = new DateInterval('P1D');
$period = new DatePeriod($startDate, $interval, $endDate->modify('+1 day'));

$seenMonths = [];
foreach ($period as $dt) {
    $year = $dt->format('Y');
    $month = $dt->format('m');
    $key = "$year-$month";
    if (!isset($seenMonths[$key])) {
        $monthHolidays = getPublicHolidays($year, $month);
        foreach ($monthHolidays as $date => $name) {
    $publicHolidays[$date] = $name;
}

        $seenMonths[$key] = true;
    }
}

$holidayMap = $publicHolidays;
$report = apply_attendance_rules($records, $holidayMap);

if (empty($report)) {
    die("No data in report after applying attendance rules.");
}

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle("Attendance Report");

$headers = ['Date', 'Day', 'In Time', 'Out Time', 'Worked Minutes', 'Late Minutes', 'Remarks'];
$sheet->fromArray($headers, null, 'A1');
$sheet->getStyle('A1:G1')->getFont()->setBold(true)->setSize(12);
$sheet->getStyle('A1:G1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

// ✅ Set fixed, neat column widths
$sheet->getColumnDimension('A')->setWidth(30); // Date
$sheet->getColumnDimension('B')->setWidth(15); // Day
$sheet->getColumnDimension('C')->setWidth(12); // In Time
$sheet->getColumnDimension('D')->setWidth(12); // Out Time
$sheet->getColumnDimension('E')->setWidth(50); // Worked Minutes
$sheet->getColumnDimension('F')->setWidth(14); // Late Minutes
$sheet->getColumnDimension('G')->setWidth(40); // Remarks

$rowNum = 2;
$totalWorked = 0;
$totalLate = 0;
$remarkCounts = [];

foreach ($report as $r) {
    $isHoliday = isset($holidayMap[$r['date']]);
    $worked = $r['worked_minutes'] ?? 0;
    $late = $r['late_minutes'] ?? 0;
    $workedDisplay = $isHoliday ? $r['remark'] : $worked;

    // Format date for better readability
    $formattedDate = DateTime::createFromFormat('Y-m-d', $r['date'])->format('d-m-Y');

    $sheet->fromArray([
        $formattedDate,
        $r['day'],
        $r['in_time'],
        $r['out_time'],
        $workedDisplay,
        $late,
        $r['remark']
    ], null, 'A' . $rowNum);

    $day = strtolower($r['day']);
    $remarkText = strtolower($r['remark']);
    $style = $sheet->getStyle("A$rowNum:G$rowNum");

    // Apply row colors based on rules
    if ($day === 'saturday') {
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('A1006C');
    } elseif ($day === 'sunday') {
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0004FF');
    } elseif ($isHoliday) {
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('0B580B');
    } elseif (strpos($remarkText, 'absent') !== false || strpos($remarkText, 'missing') !== false) {
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('A50707');
    }

    // Add borders for better visibility
    $style->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    
    // Center align cells for better visual appeal
    $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    if (!$isHoliday) {
        $totalWorked += $worked;
        $totalLate += $late;
    }

    $remarkCounts[$r['remark']] = ($remarkCounts[$r['remark']] ?? 0) + 1;
    $rowNum++;
}

// Summary
$sheet->setCellValue("A$rowNum", "Summary for Employee ID: $empId");
$sheet->mergeCells("A$rowNum:G$rowNum");
$sheet->getStyle("A$rowNum")->getFont()->setBold(true)->setSize(14);
$sheet->getStyle("A$rowNum")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
$rowNum++;

$sheet->setCellValue("A$rowNum", "Total Worked:");
$sheet->setCellValue("B$rowNum", "$totalWorked mins (" . round($totalWorked / 60, 2) . " hrs)");
$rowNum++;

$sheet->setCellValue("A$rowNum", "Total Late:");
$sheet->setCellValue("B$rowNum", "$totalLate mins");
$rowNum++;

foreach ($remarkCounts as $remark => $count) {
    $sheet->setCellValue("A$rowNum", "$remark:");
    $sheet->setCellValue("B$rowNum", $count);
    $rowNum++;
}

// Output headers and file
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="Attendance_' . $empId . '.xlsx"');
header('Cache-Control: max-age=0');

ob_end_clean();
$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
