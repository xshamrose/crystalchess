<?php

/**
 * API: Update Participant
 * File: api/participants/update.php
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Validator.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check authentication
$auth = new Auth();
if (!$auth->check()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$userId = $_SESSION['user_id'];
$db = Database::getInstance();

// Handle form data
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Validate participant ID
if (empty($_POST['participant_id'])) {
    echo json_encode(['success' => false, 'message' => 'Participant ID is required']);
    exit;
}

$participantId = intval($_POST['participant_id']);

// Check ownership
$participant = $db->query("SELECT * FROM participants WHERE participant_id = :id AND user_id = :uid")
    ->bind(':id', $participantId)
    ->bind(':uid', $userId)
    ->fetch();

if (!$participant) {
    echo json_encode(['success' => false, 'message' => 'Participant not found or access denied']);
    exit;
}

// Validate inputs
$validator = new Validator($_POST);
$validator->required('full_name', 'Full name is required')
    ->minLength('full_name', 2, 'Name must be at least 2 characters')
    ->maxLength('full_name', 100, 'Name too long')
    ->required('date_of_birth', 'Date of birth is required')
    ->required('gender', 'Gender is required');

// Validate gender manually
if (!in_array($_POST['gender'], ['male', 'female', 'others'])) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid gender value'
    ]);
    exit;
}

// Optional validations
if (!empty($_POST['contact_number'])) {
    $validator->phone('contact_number', 'Invalid phone number');
}

if (!empty($_POST['email'])) {
    $validator->email('email', 'Invalid email address');
}

if (!$validator->passes()) {
    echo json_encode([
        'success' => false,
        'message' => 'Validation failed',
        'errors' => $validator->getErrors()
    ]);
    exit;
}

// Check age
$dob = new DateTime($_POST['date_of_birth']);
$now = new DateTime();
$age = $now->diff($dob)->y;

if ($age < 5) {
    echo json_encode([
        'success' => false,
        'message' => 'Participant must be at least 5 years old'
    ]);
    exit;
}

// Handle file uploads
$uploadDir = __DIR__ . '/../../uploads/participants/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$passportPhoto = $participant['passport_photo'];
$birthCertificate = $participant['birth_certificate'];
$aadharCard = $participant['aadhar_card'];

// Upload new passport photo
if (isset($_FILES['passport_photo']) && $_FILES['passport_photo']['error'] === UPLOAD_ERR_OK) {
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
    $fileType = $_FILES['passport_photo']['type'];
    $fileSize = $_FILES['passport_photo']['size'];

    if (!in_array($fileType, $allowedTypes)) {
        echo json_encode(['success' => false, 'message' => 'Passport photo must be JPG or PNG']);
        exit;
    }

    if ($fileSize > 5 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'Passport photo too large (max 5MB)']);
        exit;
    }

    // Delete old file
    if ($passportPhoto && file_exists($uploadDir . $passportPhoto)) {
        unlink($uploadDir . $passportPhoto);
    }

    $ext = pathinfo($_FILES['passport_photo']['name'], PATHINFO_EXTENSION);
    $passportPhoto = 'passport_' . uniqid() . '_' . time() . '.' . $ext;
    move_uploaded_file($_FILES['passport_photo']['tmp_name'], $uploadDir . $passportPhoto);
}

// Upload new birth certificate
if (isset($_FILES['birth_certificate']) && $_FILES['birth_certificate']['error'] === UPLOAD_ERR_OK) {
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
    $fileType = $_FILES['birth_certificate']['type'];
    $fileSize = $_FILES['birth_certificate']['size'];

    if (!in_array($fileType, $allowedTypes)) {
        echo json_encode(['success' => false, 'message' => 'Birth certificate must be JPG, PNG or PDF']);
        exit;
    }

    if ($fileSize > 10 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'Birth certificate too large (max 10MB)']);
        exit;
    }

    // Delete old file
    if ($birthCertificate && file_exists($uploadDir . $birthCertificate)) {
        unlink($uploadDir . $birthCertificate);
    }

    $ext = pathinfo($_FILES['birth_certificate']['name'], PATHINFO_EXTENSION);
    $birthCertificate = 'birth_' . uniqid() . '_' . time() . '.' . $ext;
    move_uploaded_file($_FILES['birth_certificate']['tmp_name'], $uploadDir . $birthCertificate);
}

// Upload new aadhar card
if (isset($_FILES['aadhar_card']) && $_FILES['aadhar_card']['error'] === UPLOAD_ERR_OK) {
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
    $fileType = $_FILES['aadhar_card']['type'];
    $fileSize = $_FILES['aadhar_card']['size'];

    if (!in_array($fileType, $allowedTypes)) {
        echo json_encode(['success' => false, 'message' => 'Aadhar card must be JPG, PNG or PDF']);
        exit;
    }

    if ($fileSize > 10 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'Aadhar card too large (max 10MB)']);
        exit;
    }

    // Delete old file
    if ($aadharCard && file_exists($uploadDir . $aadharCard)) {
        unlink($uploadDir . $aadharCard);
    }

    $ext = pathinfo($_FILES['aadhar_card']['name'], PATHINFO_EXTENSION);
    $aadharCard = 'aadhar_' . uniqid() . '_' . time() . '.' . $ext;
    move_uploaded_file($_FILES['aadhar_card']['tmp_name'], $uploadDir . $aadharCard);
}

try {
    // Update participant
    $updated = $db->update('participants', [
        'full_name' => trim($_POST['full_name']),
        'date_of_birth' => $_POST['date_of_birth'],
        'gender' => $_POST['gender'],
        'contact_number' => !empty($_POST['contact_number']) ? trim($_POST['contact_number']) : null,
        'email' => !empty($_POST['email']) ? trim($_POST['email']) : null,
        'passport_photo' => $passportPhoto,
        'fide_id' => !empty($_POST['fide_id']) ? trim($_POST['fide_id']) : null,
        'birth_certificate' => $birthCertificate,
        'aadhar_card' => $aadharCard
    ], [
        'participant_id' => $participantId,
        'user_id' => $userId
    ]);

    if ($updated) {
        echo json_encode([
            'success' => true,
            'message' => 'Participant updated successfully!'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to update participant'
        ]);
    }
} catch (Exception $e) {
    error_log("Participant Update Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'An error occurred while updating participant'
    ]);
}
