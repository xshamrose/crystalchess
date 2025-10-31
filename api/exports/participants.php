<?php
require_once '../../config/database.php';
require_once '../../core/Auth.php';

$auth = new Auth($pdo);
$auth->requireLogin();
$auth->requireRole(['organizer', 'admin']);

$user = $_SESSION['user'];
$event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : null;

if (!$event_id) {
    http_response_code(400);
    die('Event ID required');
}

// Verify event ownership
$check_sql = "SELECT event_name FROM events WHERE event_id = ? AND organizer_id = ?";
$stmt = $pdo->prepare($check_sql);
$stmt->execute([$event_id, $user['user_id']]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$event) {
    http_response_code(403);
    die('Access denied');
}

// Fetch participants
$sql = "
    SELECT 
        b.booking_reference,
        b.participant_name,
        b.participant_email,
        b.participant_phone,
        b.participant_age,
        b.player_type,
        b.booking_status,
        b.payment_status,
        b.amount_paid,
        b.booking_date,
        u.full_name as booked_by,
        u.email as booker_email,
        u.phone as booker_phone
    FROM bookings b
    JOIN users u ON b.user_id = u.user_id
    WHERE b.event_id = ?
    ORDER BY b.booking_date DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$event_id]);
$participants = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Set headers for CSV download
$filename = 'participants_' . preg_replace('/[^a-z0-9]/i', '_', $event['event_name']) . '_' . date('Y-m-d') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for proper UTF-8 handling in Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// CSV Headers
$headers = [
    'Booking Reference',
    'Participant Name',
    'Participant Email',
    'Participant Phone',
    'Age',
    'Player Type',
    'Booking Status',
    'Payment Status',
    'Amount Paid',
    'Booking Date',
    'Booked By',
    'Booker Email',
    'Booker Phone'
];

fputcsv($output, $headers);

// Add data rows
foreach ($participants as $participant) {
    fputcsv($output, [
        $participant['booking_reference'],
        $participant['participant_name'],
        $participant['participant_email'] ?? '',
        $participant['participant_phone'] ?? '',
        $participant['participant_age'] ?? '',
        ucfirst($participant['player_type']),
        ucfirst($participant['booking_status']),
        ucfirst($participant['payment_status']),
        '$' . number_format($participant['amount_paid'], 2),
        date('Y-m-d H:i:s', strtotime($participant['booking_date'])),
        $participant['booked_by'],
        $participant['booker_email'],
        $participant['booker_phone'] ?? ''
    ]);
}

fclose($output);
exit;