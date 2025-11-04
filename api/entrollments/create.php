<?php

/**
 * API Endpoint - Create Enrollment
 * File: api/enrollments/create.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../../config/config.php';

// Start session to check if user is logged in
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    // Connect to database directly using PDO
    require_once __DIR__ . '/../../config/database.php';

    // Create PDO connection
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get user_id if logged in (will be NULL for guests)
    $userId = $_SESSION['user_id'] ?? null;

    // Read POST data - works with both form-data and JSON
    $postData = $_POST;
    if (empty($postData)) {
        $input = file_get_contents('php://input');
        $postData = json_decode($input, true) ?? [];
    }

    // Validate required fields
    $requiredFields = ['class_name', 'student_name', 'student_email', 'student_phone', 'student_age', 'preferred_schedule'];
    foreach ($requiredFields as $field) {
        if (empty($postData[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    // Sanitize and validate inputs
    $className = trim($postData['class_name']);
    $studentName = trim($postData['student_name']);
    $studentEmail = filter_var(trim($postData['student_email']), FILTER_VALIDATE_EMAIL);
    $studentPhone = trim($postData['student_phone']);
    $studentAge = (int)$postData['student_age'];
    $preferredSchedule = trim($postData['preferred_schedule']);

    if (!$studentEmail) {
        throw new Exception('Invalid email address');
    }

    if ($studentAge < 3 || $studentAge > 100) {
        throw new Exception('Invalid age');
    }

    if (!in_array($preferredSchedule, ['morning', 'afternoon', 'evening', 'weekend'])) {
        throw new Exception('Invalid schedule selection');
    }

    // Optional fields with proper null handling
    $parentName = !empty($postData['parent_name']) ? trim($postData['parent_name']) : null;
    $parentPhone = !empty($postData['parent_phone']) ? trim($postData['parent_phone']) : null;
    $parentEmail = !empty($postData['parent_email']) ? filter_var(trim($postData['parent_email']), FILTER_VALIDATE_EMAIL) : null;
    $address = !empty($postData['address']) ? trim($postData['address']) : null;
    $city = !empty($postData['city']) ? trim($postData['city']) : null;
    $state = !empty($postData['state']) ? trim($postData['state']) : null;
    $pincode = !empty($postData['pincode']) ? trim($postData['pincode']) : null;
    $preferredDays = !empty($postData['preferred_days']) ? trim($postData['preferred_days']) : null;
    $previousExperience = !empty($postData['previous_experience']) ? trim($postData['previous_experience']) : 'none';
    $message = !empty($postData['message']) ? trim($postData['message']) : null;

    // Validate previous experience
    if (!in_array($previousExperience, ['none', 'beginner', 'intermediate', 'advanced'])) {
        $previousExperience = 'none';
    }

    // Prepare INSERT statement
    $sql = "INSERT INTO enrollments (
        user_id, class_name, student_name, student_email, student_phone, student_age,
        parent_name, parent_phone, parent_email, preferred_schedule, preferred_days,
        address, city, state, pincode, previous_experience, message,
        enrollment_status, enrollment_date
    ) VALUES (
        :user_id, :class_name, :student_name, :student_email, :student_phone, :student_age,
        :parent_name, :parent_phone, :parent_email, :preferred_schedule, :preferred_days,
        :address, :city, :state, :pincode, :previous_experience, :message,
        'pending', NOW()
    )";

    $stmt = $pdo->prepare($sql);

    // Bind parameters
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':class_name', $className);
    $stmt->bindValue(':student_name', $studentName);
    $stmt->bindValue(':student_email', $studentEmail);
    $stmt->bindValue(':student_phone', $studentPhone);
    $stmt->bindValue(':student_age', $studentAge, PDO::PARAM_INT);
    $stmt->bindValue(':parent_name', $parentName);
    $stmt->bindValue(':parent_phone', $parentPhone);
    $stmt->bindValue(':parent_email', $parentEmail);
    $stmt->bindValue(':preferred_schedule', $preferredSchedule);
    $stmt->bindValue(':preferred_days', $preferredDays);
    $stmt->bindValue(':address', $address);
    $stmt->bindValue(':city', $city);
    $stmt->bindValue(':state', $state);
    $stmt->bindValue(':pincode', $pincode);
    $stmt->bindValue(':previous_experience', $previousExperience);
    $stmt->bindValue(':message', $message);

    // Execute the query
    if ($stmt->execute()) {
        $enrollmentId = $pdo->lastInsertId();

        echo json_encode([
            'success' => true,
            'message' => 'Enrollment submitted successfully!',
            'enrollment_id' => $enrollmentId
        ]);
    } else {
        throw new Exception('Failed to create enrollment');
    }
} catch (PDOException $e) {
    http_response_code(500);
    error_log("Database Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred. Please try again later.'
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
