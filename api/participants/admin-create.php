<?php

/**
 * API: Admin Create Participant for Any User
 * File: api/participants/admin-create.php
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Validator.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$auth = new Auth();
if (!$auth->check() || $_SESSION['user_type'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = Database::getInstance();

if (empty($_POST['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User ID required']);
    exit;
}

$userId = intval($_POST['user_id']);

// Validate
$validator = new Validator($_POST);
$validator->required('full_name', 'Full name required')
    ->required('date_of_birth', 'Date of birth required')
    ->required('gender', 'Gender required');

if (!in_array($_POST['gender'], ['male', 'female', 'others'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid gender']);
    exit;
}

if (!$validator->passes()) {
    echo json_encode(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->getErrors()]);
    exit;
}

// File uploads
$uploadDir = __DIR__ . '/../../uploads/participants/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

$passportPhoto = null;
$birthCertificate = null;
$aadharCard = null;

if (isset($_FILES['passport_photo']) && $_FILES['passport_photo']['error'] === UPLOAD_ERR_OK) {
    $ext = pathinfo($_FILES['passport_photo']['name'], PATHINFO_EXTENSION);
    $passportPhoto = 'passport_' . uniqid() . '.' . $ext;
    move_uploaded_file($_FILES['passport_photo']['tmp_name'], $uploadDir . $passportPhoto);
}

if (isset($_FILES['birth_certificate']) && $_FILES['birth_certificate']['error'] === UPLOAD_ERR_OK) {
    $ext = pathinfo($_FILES['birth_certificate']['name'], PATHINFO_EXTENSION);
    $birthCertificate = 'birth_' . uniqid() . '.' . $ext;
    move_uploaded_file($_FILES['birth_certificate']['tmp_name'], $uploadDir . $birthCertificate);
}

if (isset($_FILES['aadhar_card']) && $_FILES['aadhar_card']['error'] === UPLOAD_ERR_OK) {
    $ext = pathinfo($_FILES['aadhar_card']['name'], PATHINFO_EXTENSION);
    $aadharCard = 'aadhar_' . uniqid() . '.' . $ext;
    move_uploaded_file($_FILES['aadhar_card']['tmp_name'], $uploadDir . $aadharCard);
}

try {
    $participantId = $db->insert('participants', [
        'user_id' => $userId,
        'full_name' => trim($_POST['full_name']),
        'date_of_birth' => $_POST['date_of_birth'],
        'gender' => $_POST['gender'],
        'contact_number' => !empty($_POST['contact_number']) ? trim($_POST['contact_number']) : null,
        'email' => !empty($_POST['email']) ? trim($_POST['email']) : null,
        'passport_photo' => $passportPhoto,
        'fide_id' => !empty($_POST['fide_id']) ? trim($_POST['fide_id']) : null,
        'birth_certificate' => $birthCertificate,
        'aadhar_card' => $aadharCard
    ]);

    if ($participantId) {
        echo json_encode(['success' => true, 'message' => 'Participant added!', 'participant_id' => $participantId]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add participant']);
    }
} catch (Exception $e) {
    error_log("Admin Create Participant Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error occurred']);
}
