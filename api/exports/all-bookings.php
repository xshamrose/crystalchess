<?php
// api/exports/all-bookings.php
require_once '../../config/database.php';
require_once '../../core/Auth.php';

$auth = new Auth($pdo);
$auth->requireLogin();
$auth->requireRole(['admin']);

// Optional date range filters
$start_date = isset($_GET['start']) ? $_GET['start'] : null;
$end_date = isset($_GET['end']) ? $_GET['end'] : null;

// Build query
$where_conditions = [];
$params = [];

if ($start_date) {
    $where_conditions[] = "b.booking_date >= ?";
    $params[] = $start_date;
}

if ($end_date) {
    $where_conditions[] = "b.booking_date <= ?";
    $params[] = $end_date . ' 23:59:59';
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get all bookings
$sql = "SELECT 
        b.booking_reference,
        b.participant_name,
        b.participant_email,
        b.participant_phone,
        b.participant_age,
        b.player_type,
        e.event_name,
        e.event_date,
        e.location,
        u.full_name as booked_by,
        u.email as booker_email,
        org.full_name as organizer_name,
        b.amount_paid,
        b.booking_status,
        b.payment_status,
        b.booking_date
        FROM bookings b
        JOIN events e ON b.event_id = e.event_id
        JOIN users u ON b.user_id = u.user_id
        JOIN users org ON e.organizer_id = org.user_id
        $where_clause
        ORDER BY b.booking_date DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set headers for CSV download
$filename = 'all_bookings_' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Create output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Add headers
fputcsv($output, [
    'Booking Reference',
    'Participant Name',
    'Participant Email',
    'Participant Phone',
    'Age',
    'Player Type',
    'Event Name',
    'Event Date',
    'Location',
    'Booked By',
    'Booker Email',
    'Organizer',
    'Amount Paid',
    'Booking Status',
    'Payment Status',
    'Booking Date/Time'
]);

// Add data rows
foreach ($bookings as $booking) {
    fputcsv($output, [
        $booking['booking_reference'],
        $booking['participant_name'],
        $booking['participant_email'] ?? '',
        $booking['participant_phone'] ?? '',
        $booking['participant_age'] ?? '',
        ucfirst($booking['player_type']),
        $booking['event_name'],
        date('M d, Y', strtotime($booking['event_date'])),
        $booking['location'],
        $booking['booked_by'],
        $booking['booker_email'],
        $booking['organizer_name'],
        '$' . number_format($booking['amount_paid'], 2),
        ucfirst($booking['booking_status']),
        ucfirst($booking['payment_status']),
        date('M d, Y g:i A', strtotime($booking['booking_date']))
    ]);
}

fclose($output);
exit;