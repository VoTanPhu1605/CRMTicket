<?php
require_once 'includes/auth.php';
require_once 'controllers/reportController.php';

requireLogin();

$type = $_GET['type'] ?? '';
$startDate = $_GET['start_date'] ?? null;
$endDate = $_GET['end_date'] ?? null;

$reportController = new ReportController();

if ($type === 'tickets') {
    $filters = [];
    if ($startDate) $filters['start_date'] = $startDate;
    if ($endDate) $filters['end_date'] = $endDate;

    $csv = $reportController->exportTickets($filters);

    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=tickets_' . date('Y-m-d') . '.csv');

    // Output CSV
    echo $csv;
    exit();
} else {
    // Invalid type
    header('HTTP/1.1 400 Bad Request');
    echo 'Invalid export type';
    exit();
}
?>