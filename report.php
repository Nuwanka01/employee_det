<?php
session_start();
require_once 'attendance_logic.php';
require_once 'calendarific.php';

$data = $_SESSION['attendance_data'] ?? [];
$empId = $_GET['emp'] ?? null;

if (!$data) {
    echo "<p>No attendance data found in session. <a href='index.php'>Upload again</a></p>";
    exit;
}

if (!$empId || !isset($data[$empId])) {
    $searchTerm = $_GET['search'] ?? '';

    // Filter data by search if provided
    if ($searchTerm) {
        $data = array_filter($data, function ($emp, $id) use ($searchTerm) {
            return stripos($id, $searchTerm) !== false;
        }, ARRAY_FILTER_USE_BOTH);
    }

    // Back button goes to index.php if searching, else report.php
    $backUrl = $searchTerm ? 'index.php' : 'report.php';

    echo "<!DOCTYPE html><html><head>
        <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css' rel='stylesheet'>
        <title>Select Employee</title></head>
        <body class='container py-5'>

                <div class='d-flex justify-content-between align-items-center mb-4'>
            <h2 class='mb-0'>Select Employee</h2>
            <div class='d-flex gap-2'>
                <a href='export_all_excel.php' class='btn btn-outline-success'>
                    📄 Download A Full report
                </a>
                <a href='$backUrl' class='btn btn-secondary'>← Back to List</a>
                <a href='index.php' class='btn btn-outline-primary'>🏠 Home</a>
            </div>
        </div>


        <form method='GET' class='mb-4'>
            <div class='input-group'>
                <input type='text' name='search' class='form-control' placeholder='Search by Employee ID' value='" . htmlspecialchars($searchTerm) . "'>
                <button type='submit' class='btn btn-primary'>Search</button>
            </div>
        </form>

        <ul class='list-group'>";

    if ($searchTerm && empty($data)) {
        echo "<li class='list-group-item text-danger'>No matching employees found for '<strong>" . htmlspecialchars($searchTerm) . "</strong>'</li>";
    }

    foreach ($data as $id => $emp) {
        echo "<li class='list-group-item d-flex justify-content-between align-items-center'>
                <a href='report.php?emp=$id'>Employee ID: $id</a>
                <a href='export_excel.php?emp=$id' class='btn btn-sm btn-success'>📥 Download Excel</a>
            </li>";
    }

    echo "</ul></body></html>";
    exit;
}


$employee = $data[$empId];
$records = $employee['records'] ?? [];

$firstDate = $records[0]['date'] ?? null;
$publicHolidays = [];
$holidayMap = [];

if ($firstDate && substr_count($firstDate, '-') >= 2) {
    [$year, $month] = explode('-', $firstDate);
    $holidayMap = getPublicHolidays($year);

}

if (!$records) {
    echo "<p>⚠️ No attendance records found for employee ID: $empId</p>";
    exit;
}

$report = apply_attendance_rules($records, $holidayMap);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Report - Employee ID: <?php echo $empId; ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .saturday td { background-color: rgb(161, 0, 108) !important; color: white; }
        .sunday td { background-color: rgb(0, 4, 255) !important; color: white; }
        .public-holiday td { background-color: rgb(11, 88, 11) !important; color: white; }
        .absent td, .missing-data td { background-color: rgb(165, 7, 7) !important; color: white; }

        #backToTopBtn {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 99;
            display: none;
        }
    </style>
</head>
<body class="bg-light">

<div class="container my-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Attendance Report: <span class="text-primary">Employee ID <?php echo $empId; ?></span></h2>
        <div>
            <a href="export_excel.php?emp=<?php echo $empId; ?>" class="btn btn-success me-2">
                📥 Download Excel
            </a>
            <a href="report.php" class="btn btn-secondary">← Back to List</a>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table table-bordered table-hover align-middle text-center">
            <thead class="table-dark">
                <tr>
                    <th>Date</th>
                    <th>Day</th>
                    <th>In Time</th>
                    <th>Out Time</th>
                    <th>Worked Minutes</th>
                    <th>Late Minutes</th>
                    <th>Remarks</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $totalWorked = 0;
                $totalLate = 0;
                $remarkCounts = [];

                foreach ($report as $row):
                    $classes = [];

                    if ($row['day'] === 'Saturday') $classes[] = 'saturday';
                    if ($row['day'] === 'Sunday') $classes[] = 'sunday';
                    if (array_key_exists($row['date'], $holidayMap)) $classes[] = 'public-holiday';
                    if (in_array($row['remark'], ['Absent', 'Missing Data'])) $classes[] = 'absent';

                    $totalWorked += $row['worked_minutes'];
                    $totalLate   += $row['late_minutes'];
                    $remark = $row['remark'] ?? '';
                    $remarkCounts[$remark] = ($remarkCounts[$remark] ?? 0) + 1;

                    echo "<tr class='" . implode(' ', $classes) . "'>";
                    echo "<td>{$row['date']}</td>";
                    echo "<td>{$row['day']}</td>";
                    echo "<td>{$row['in_time']}</td>";
                    echo "<td>{$row['out_time']}</td>";
                    echo "<td>{$row['worked_minutes']}</td>";
                    echo "<td>{$row['late_minutes']}</td>";
                    echo "<td>{$row['remark']}</td>";
                    echo "</tr>";
                endforeach;
                ?>
            </tbody>
            <tfoot class="fw-bold bg-light">
                <tr>
                    <td colspan="4">TOTALS</td>
                    <td><?php echo $totalWorked; ?> mins</td>
                    <td><?php echo $totalLate; ?> mins</td>
                    <td>
                        <?php foreach ($remarkCounts as $key => $val): ?>
                            <?php echo htmlspecialchars($key) . ": $val<br>"; ?>
                        <?php endforeach; ?>
                    </td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- Back to Top Button -->
<button onclick="topFunction()" id="backToTopBtn" class="btn btn-primary rounded-circle">
    ↑
</button>

<script>
    // Show/hide back-to-top button
    const backToTopBtn = document.getElementById("backToTopBtn");

    window.onscroll = function () {
        if (document.body.scrollTop > 200 || document.documentElement.scrollTop > 200) {
            backToTopBtn.style.display = "block";
        } else {
            backToTopBtn.style.display = "none";
        }
    };

    function topFunction() {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }
</script>

</body>
</html>
