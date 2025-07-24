<?php
require_once 'vendor/autoload.php';

use Smalot\PdfParser\Parser;

function extract_attendance_data($pdf_path) {
    $parser = new Parser();
    $pdf = $parser->parseFile($pdf_path);
    $text = $pdf->getText();

    $lines = explode("\n", $text);
    $employees = [];
    $currentEmpId = null;

    foreach ($lines as $line) {
        $line = trim($line);

        // Skip headers or empty lines
        if (empty($line) || preg_match('/^(No|#)?\\s*Date/i', $line)) {
            continue;
        }

        // Match employee header
        if (preg_match('/^(\d{6})\s*:\s*(.+)$/', $line, $matches)) {
            $currentEmpId = $matches[1];
            $employees[$currentEmpId] = [
                'id' => $currentEmpId,
                'name' => trim($matches[2]),
                'records' => []
            ];
            continue;
        }

        // Match attendance record like: 1 2025-05-02 08:35:00 16:45:00
        if (preg_match('/^\d+\s+(\d{4}-\d{2}-\d{2})\s+([\d:]{5,8})\s+([\d:]{5,8}|-)$/', $line, $matches)) {
            if ($currentEmpId) {
                $employees[$currentEmpId]['records'][] = [
                    'date' => $matches[1],
                    'in_time' => $matches[2],
                    'out_time' => $matches[3]
                ];
            }
        }
    }

    return $employees;
}
