<?php

/**
 * API: Get Participants by User (Admin Only)
 * File: api/participants/get-by-user.php
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$auth = new Auth();
if (!$auth->check() || $_SESSION['user_type'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

if ($userId === 0) {
    echo json_encode(['success' => false, 'message' => 'User ID required']);
    exit;
}

$db = Database::getInstance();
$participants = $db->query("
    SELECT * FROM participants 
    WHERE user_id = :user_id 
    ORDER BY created_at DESC
")->bind(':user_id', $userId)->fetchAll();

echo json_encode([
    'success' => true,
    'participants' => $participants
]);
