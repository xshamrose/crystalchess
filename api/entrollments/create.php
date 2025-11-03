<?php

/**
 * Enrollment Creation API
 * Handles enrollment form submissions (with or without login)
 */

require_once __DIR__ . '/../../config/config.php';
require_once ROOT_PATH . '/core/Database.php';

header('Content-Type: application/json');

// Start session if not started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Validate CSRF token
if (!isset($_POST['csrf_token']) || !validateCSRF($_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
    exit;
}

// Get form data
$class_name = sanitize($_POST['class_name'] ?? '');
$student_name = sanitize($_POST['student_name'] ?? '');
$student_email = filter_var($_POST['student_email'] ?? '', FILTER_SANITIZE_EMAIL);
$student_phone = sanitize($_POST['student_phone'] ?? '');
$student_age = isset($_POST['student_age']) ? intval($_POST['student_age']) : null;
$parent_name = sanitize($_POST['parent_name'] ?? '');
$parent_phone = sanitize($_POST['parent_phone'] ?? '');
$parent_email = filter_var($_POST['parent_email'] ?? '', FILTER_SANITIZE_EMAIL);
$preferred_schedule = sanitize($_POST['preferred_schedule'] ?? '');
$preferred_days = sanitize($_POST['preferred_days'] ?? '');
$address = sanitize($_POST['address'] ?? '');
$city = sanitize($_POST['city'] ?? '');
$state = sanitize($_POST['state'] ?? '');
$pincode = sanitize($_POST['pincode'] ?? '');
$previous_experience = sanitize($_POST['previous_experience'] ?? 'none');
$message = sanitize($_POST['message'] ?? '');

// Validation
$errors = [];

if (empty($class_name)) {
    $errors[] = 'Class name is required';
}

if (empty($student_name)) {
    $errors[] = 'Student name is required';
}

if (empty($student_email) || !filter_var($student_email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Valid student email is required';
}

if (empty($student_phone)) {
    $errors[] = 'Student phone is required';
}

if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => 'Validation failed', 'errors' => $errors]);
    exit;
}

try {
    $db = Database::getInstance();

    // Prepare enrollment data
    $enrollmentData = [
        'user_id' => isLoggedIn() ? $_SESSION['user_id'] : null,
        'class_name' => $class_name,
        'student_name' => $student_name,
        'student_email' => $student_email,
        'student_phone' => $student_phone,
        'student_age' => $student_age,
        'parent_name' => $parent_name,
        'parent_phone' => $parent_phone,
        'parent_email' => $parent_email,
        'preferred_schedule' => $preferred_schedule,
        'preferred_days' => $preferred_days,
        'address' => $address,
        'city' => $city,
        'state' => $state,
        'pincode' => $pincode,
        'previous_experience' => $previous_experience,
        'message' => $message,
        'enrollment_status' => 'pending'
    ];

    // Insert enrollment
    $enrollmentId = $db->insert('enrollments', $enrollmentData);

    if ($enrollmentId) {
        // Send confirmation email to student (optional)
        $emailSubject = "Enrollment Confirmation - " . $class_name;
        $emailBody = "
            <h2>Thank you for enrolling!</h2>
            <p>Dear {$student_name},</p>
            <p>Your enrollment for <strong>{$class_name}</strong> has been received successfully.</p>
            <p><strong>Enrollment ID:</strong> #{$enrollmentId}</p>
            <p><strong>Status:</strong> Pending Review</p>
            <p>Our team will review your application and contact you within 24-48 hours.</p>
            <br>
            <p>Best regards,<br>Crystal Chess Team</p>
        ";

        // Uncomment to send email
        // sendEmail($student_email, $emailSubject, $emailBody);

        echo json_encode([
            'success' => true,
            'message' => 'Enrollment submitted successfully! We will contact you soon.',
            'enrollment_id' => $enrollmentId
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to submit enrollment. Please try again.'
        ]);
    }
} catch (Exception $e) {
    error_log("Enrollment Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred. Please try again later.'
    ]);
}
