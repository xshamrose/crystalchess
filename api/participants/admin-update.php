<?php
// ===== api/participants/admin-update.php =====
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Validator.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$auth = new Auth();
if (!$auth->check() || $_SESSION['user_type'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$db = Database::getInstance();
$participantId = intval($_POST['participant_id'] ?? 0);

if (!$participantId) {
    echo json_encode(['success' => false, 'message' => 'Participant ID required']);
    exit;
}

$participant = $db->query("SELECT * FROM participants WHERE participant_id = :id")
    ->bind(':id', $participantId)->fetch();

if (!$participant) {
    echo json_encode(['success' => false, 'message' => 'Participant not found']);
    exit;
}

// File uploads
$uploadDir = __DIR__ . '/../../uploads/participants/';
$passportPhoto = $participant['passport_photo'];
$birthCertificate = $participant['birth_certificate'];
$aadharCard = $participant['aadhar_card'];

if (isset($_FILES['passport_photo']) && $_FILES['passport_photo']['error'] === UPLOAD_ERR_OK) {
    if ($passportPhoto && file_exists($uploadDir . $passportPhoto)) unlink($uploadDir . $passportPhoto);
    $ext = pathinfo($_FILES['passport_photo']['name'], PATHINFO_EXTENSION);
    $passportPhoto = 'passport_' . uniqid() . '.' . $ext;
    move_uploaded_file($_FILES['passport_photo']['tmp_name'], $uploadDir . $passportPhoto);
}

if (isset($_FILES['birth_certificate']) && $_FILES['birth_certificate']['error'] === UPLOAD_ERR_OK) {
    if ($birthCertificate && file_exists($uploadDir . $birthCertificate)) unlink($uploadDir . $birthCertificate);
    $ext = pathinfo($_FILES['birth_certificate']['name'], PATHINFO_EXTENSION);
    $birthCertificate = 'birth_' . uniqid() . '.' . $ext;
    move_uploaded_file($_FILES['birth_certificate']['tmp_name'], $uploadDir . $birthCertificate);
}

if (isset($_FILES['aadhar_card']) && $_FILES['aadhar_card']['error'] === UPLOAD_ERR_OK) {
    if ($aadharCard && file_exists($uploadDir . $aadharCard)) unlink($uploadDir . $aadharCard);
    $ext = pathinfo($_FILES['aadhar_card']['name'], PATHINFO_EXTENSION);
    $aadharCard = 'aadhar_' . uniqid() . '.' . $ext;
    move_uploaded_file($_FILES['aadhar_card']['tmp_name'], $uploadDir . $aadharCard);
}

try {
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
    ], ['participant_id' => $participantId]);

    echo json_encode(['success' => true, 'message' => 'Participant updated!']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error occurred']);
}
