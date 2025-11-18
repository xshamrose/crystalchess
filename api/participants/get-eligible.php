<?php

/**
 * API: Get Eligible Participants for Event
 * File: api/participants/get-eligible.php
 * Filters participants by event category (age, gender rules)
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Auth.php';

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

// Get event ID from request
$eventId = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;

if ($eventId === 0) {
    echo json_encode(['success' => false, 'message' => 'Event ID is required']);
    exit;
}

// Get event details with categories
$event = $db->query("
    SELECT e.event_id, e.event_name
    FROM events e
    WHERE e.event_id = :event_id
")->bind(':event_id', $eventId)->fetch();

if (!$event) {
    echo json_encode(['success' => false, 'message' => 'Event not found']);
    exit;
}

// Get event categories
$categories = $db->query("
    SELECT ec.* 
    FROM event_categories ec
    JOIN event_category_mapping ecm ON ec.category_id = ecm.category_id
    WHERE ecm.event_id = :event_id
")->bind(':event_id', $eventId)->fetchAll();

// Get all user's participants
$allParticipants = $db->query("
    SELECT * FROM participants 
    WHERE user_id = :user_id 
    ORDER BY full_name ASC
")->bind(':user_id', $userId)->fetchAll();

// Filter eligible participants
$eligibleParticipants = [];

foreach ($allParticipants as $participant) {
    $dob = new DateTime($participant['date_of_birth']);
    $now = new DateTime();
    $age = $now->diff($dob)->y;
    $gender = $participant['gender'];

    $isEligible = false;
    $eligibleCategories = [];

    foreach ($categories as $category) {
        $categoryCode = $category['category_code'];
        $ageLimit = $category['age_limit'];

        // Check age eligibility
        $ageEligible = false;
        if ($categoryCode === 'OPEN') {
            $ageEligible = true;
        } elseif (preg_match('/U(\d+)/', $categoryCode, $matches)) {
            // U-10, U-12, U-14, etc.
            $maxAge = intval($matches[1]);
            $ageEligible = ($age < $maxAge);
        }

        // Check gender eligibility
        // RULE: Girls can play in Boys tournaments, but Boys cannot play in Girls tournaments
        $genderEligible = false;

        if (stripos($categoryCode, 'GIRLS') !== false || stripos($categoryCode, 'WOMEN') !== false) {
            // Girls/Women category: Only females allowed
            $genderEligible = ($gender === 'female');
        } elseif (stripos($categoryCode, 'BOYS') !== false || stripos($categoryCode, 'MEN') !== false) {
            // Boys/Men category: Both males and females allowed (girls can play)
            $genderEligible = true;
        } else {
            // Open or other categories: Everyone allowed
            $genderEligible = true;
        }

        if ($ageEligible && $genderEligible) {
            $isEligible = true;
            $eligibleCategories[] = $category['category_name'];
        }
    }

    if ($isEligible) {
        $participant['age'] = $age;
        $participant['eligible_categories'] = $eligibleCategories;
        $eligibleParticipants[] = $participant;
    }
}

echo json_encode([
    'success' => true,
    'event' => $event,
    'categories' => $categories,
    'participants' => $eligibleParticipants,
    'total' => count($eligibleParticipants)
]);
