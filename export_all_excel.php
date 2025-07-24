<?php
require 'vendor/autoload.php';
require_once 'attendance_logic.php';
require_once 'calendarific.php'; // For holidays

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;

session_start();

$data = $_SESSION['attendance_data'] ?? [];

if (empty($data)) {
    die("No attendance data available to export.");
}

$spreadsheet = new Spreadsheet();
$sheet = $spreadsheet->getActiveSheet();
$sheet->setTitle("Attendance Report");

$row = 1;

foreach ($data as $empId => $empData) {
    if (empty($empData['records'])) continue;

    // Get month/year from first record for public holidays
    $firstDate = $empData['records'][0]['date'];
    [$year, $month] = explode('-', $firstDate);
    $holidays = getPublicHolidays($year, $month);
    $holidayMap = getPublicHolidays((int)$year);  // already returns ['date' => 'name']

    // Apply rules
    $processedRecords = apply_attendance_rules($empData['records'], $holidayMap);

    // Employee header
    $sheet->setCellValue("A{$row}", "Employee ID: $empId");
    $sheet->mergeCells("A{$row}:G{$row}");
    $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
    $sheet->getStyle("A{$row}")->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setRGB('DDEEFF');
    $row++;

    // Column headers
    $headers = ["Date", "Day", "In Time", "Out Time", "Worked Minutes", "Late Minutes", "Remarks"];
    foreach ($headers as $colIndex => $header) {
        $columnLetter = chr(65 + $colIndex);
        $sheet->setCellValue($columnLetter . $row, $header);
    }
    $sheet->getStyle("A{$row}:G{$row}")->getFont()->setBold(true);
    $row++;

    // Data + color rows
    $totalWorked = 0;
    $totalLate = 0;
    $remarkCounts = [];

    foreach ($processedRecords as $record) {
        $day = $record['day'] ?? date('l', strtotime($record['date']));

        $sheet->setCellValue("A{$row}", $record['date']);
        $sheet->setCellValue("B{$row}", $day);
        $sheet->setCellValue("C{$row}", $record['in_time']);
        $sheet->setCellValue("D{$row}", $record['out_time']);
        $sheet->setCellValue("E{$row}", $record['worked_minutes']);
        $sheet->setCellValue("F{$row}", $record['late_minutes']);
        $sheet->setCellValue("G{$row}", $record['remark']);

        // Summary accumulations
        $totalWorked += $record['worked_minutes'];
        $totalLate   += $record['late_minutes'];

        // Normalize remark to group all public holidays
        $remark = $record['remark'] ?? '';
        if (stripos($remark, 'Public Holiday') === 0) {
            $remarkKey = 'Public Holiday';
        } else {
            $remarkKey = $remark;
        }

        $remarkCounts[$remarkKey] = ($remarkCounts[$remarkKey] ?? 0) + 1;

        // Color rules
        $fillColor = null;
        if ($day === 'Saturday') $fillColor = 'A1006C';
        elseif ($day === 'Sunday') $fillColor = '0004FF';
        elseif (isset($holidayMap[$record['date']])) $fillColor = '0B580B';
        elseif (in_array($remark, ['Absent', 'Missing Data'])) $fillColor = 'A50707';

        if ($fillColor) {
            $sheet->getStyle("A{$row}:G{$row}")
                ->getFill()->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB($fillColor);
            $sheet->getStyle("A{$row}:G{$row}")->getFont()->getColor()->setRGB('FFFFFF');
        }

        $row++;
    }

    // Summary
    $sheet->setCellValue("A{$row}", "TOTALS");
    $sheet->mergeCells("A{$row}:D{$row}");
    $sheet->getStyle("A{$row}")->getFont()->setBold(true);
    $sheet->setCellValue("E{$row}", $totalWorked);
    $sheet->setCellValue("F{$row}", $totalLate);
    $sheet->getStyle("E{$row}:F{$row}")->getFont()->setBold(true);

    // Write remark summary one per row under G column
    $remarkRow = $row;
    foreach ($remarkCounts as $key => $val) {
        $sheet->setCellValue("G{$remarkRow}", "$key: $val");
        $remarkRow++;
    }

    // Bold remark summary
    $sheet->getStyle("G{$row}:G" . ($remarkRow - 1))->getFont()->setBold(true);

    $row = $remarkRow + 3; // Add spacing before next employee
}

// Auto column width
foreach (range('A', 'G') as $col) {
    $sheet->getColumnDimension($col)->setAutoSize(true);
}

// Output Excel
$filename = "All_Attendance_Report_" . date('Ymd_His') . ".xlsx";
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header("Content-Disposition: attachment; filename=\"$filename\"");
header('Cache-Control: max-age=0');
ob_clean();
flush();

$writer = new Xlsx($spreadsheet);
$writer->save('php://output');
exit;
