<?php
/**
 * Payment Export to CSV
 * File: api/payments/export.php
 */

session_start();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Auth.php';

$auth = new Auth();
if (!$auth->isLoggedIn() || $_SESSION['user_type'] !== 'admin') {
    header('Location: /modules/user/login.php');
    exit;
}

$db = new Database();

// Get filter parameters (same as report page)
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');
$status = $_GET['status'] ?? 'all';
$gateway = $_GET['gateway'] ?? 'all';

// Build query
$whereConditions = ["DATE(p.payment_date) BETWEEN ? AND ?"];
$params = [$startDate, $endDate];

if ($status !== 'all') {
    $whereConditions[] = "p.payment_status = ?";
    $params[] = $status;
}

if ($gateway !== 'all') {
    $whereConditions[] = "p.payment_gateway = ?";
    $params[] = $gateway;
}

$whereClause = implode(' AND ', $whereConditions);

// Get payment data
$payments = $db->query(
    "SELECT 
        p.payment_id,
        p.transaction_id,
        p.payment_date,
        p.amount,
        p.currency,
        p.payment_gateway,
        p.payment_status,
        b.booking_reference,
        b.participant_name,
        e.event_name,
        e.event_date,
        u.full_name as customer_name,
        u.email as customer_email,
        p.refund_date,
        p.refund_amount
     FROM payments p
     JOIN bookings b ON p.booking_id = b.booking_id
     JOIN events e ON b.event_id = e.event_id
     JOIN users u ON b.user_id = u.user_id
     WHERE $whereClause
     ORDER BY p.payment_date DESC",
    $params
)->fetchAll();

// Set headers for CSV download
$filename = 'payment_report_' . date('Y-m-d_His') . '.csv';
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

// Open output stream
$output = fopen('php://output', 'w');

// Write CSV header
fputcsv($output, [
    'Payment ID',
    'Transaction ID',
    'Payment Date',
    'Booking Reference',
    'Customer Name',
    'Customer Email',
    'Event Name',
    'Event Date',
    'Participant',
    'Amount',
    'Currency',
    'Gateway',
    'Status',
    'Refund Date',
    'Refund Amount'
]);

// Write data rows
foreach ($payments as $payment) {
    fputcsv($output, [
        $payment['payment_id'],
        $payment['transaction_id'] ?? 'N/A',
        $payment['payment_date'],
        $payment['booking_reference'],
        $payment['customer_name'],
        $payment['customer_email'],
        $payment['event_name'],
        $payment['event_date'],
        $payment['participant_name'],
        $payment['amount'],
        $payment['currency'],
        $payment['payment_gateway'],
        $payment['payment_status'],
        $payment['refund_date'] ?? '',
        $payment['refund_amount'] ?? ''
    ]);
}

fclose($output);
exit;