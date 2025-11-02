<?php
/**
 * Event Booking Page - DEBUG VERSION
 * File: modules/events/book.php
 * Shows full error details
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Validator.php';

// ENABLE ERROR DISPLAY FOR DEBUGGING
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();
$pdo = $db->getConnection();
$userId = $_SESSION['user_id'] ?? 0;

// Get Event ID
$eventId = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;
if ($eventId === 0) {
    setFlash('error', 'Invalid event ID');
    header('Location: ' . BASE_URL . '/browse-events');
    exit;
}

// Fetch Event Details
$db->query("
    SELECT 
        e.*, 
        u.full_name AS organizer_name,
        (e.max_capacity - e.current_bookings) AS available_slots
    FROM events e
    LEFT JOIN users u ON e.organizer_id = u.user_id
    WHERE e.event_id = :event_id
");
$db->bind(':event_id', $eventId);
$event = $db->fetch();

if (!$event) {
    setFlash('error', 'Event not found.');
    header('Location: ' . BASE_URL . '/browse-events');
    exit;
}

if ($event['event_status'] !== 'upcoming') {
    setFlash('error', 'This event is not available for booking.');
    header('Location: ' . BASE_URL . '/event-details?id=' . $eventId);
    exit;
}

if ($event['available_slots'] <= 0) {
    setFlash('error', 'This event is fully booked.');
    header('Location: ' . BASE_URL . '/event-details?id=' . $eventId);
    exit;
}

// Check if user already booked
$db->query("
    SELECT booking_id 
    FROM bookings 
    WHERE event_id = :event_id 
      AND user_id = :user_id 
      AND booking_status IN ('pending', 'confirmed')
");
$db->bind(':event_id', $eventId);
$db->bind(':user_id', $userId);
$alreadyBooked = $db->fetch();

if ($alreadyBooked) {
    setFlash('error', 'You have already booked this event.');
    header('Location: ' . BASE_URL . '/event-details?id=' . $eventId);
    exit;
}

// Fetch user details
$db->query("SELECT * FROM users WHERE user_id = :id");
$db->bind(':id', $userId);
$user = $db->fetch();

$errors = [];
$formData = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $validator = new Validator($_POST);

    $validator->required('participant_name', 'Participant name is required')
              ->required('participant_email', 'Email is required')
              ->email('participant_email', 'Invalid email format')
              ->required('participant_phone', 'Phone number is required')
              ->phone('participant_phone', 'Invalid phone number')
              ->required('participant_age', 'Age is required')
              ->numeric('participant_age', 'Age must be a number')
              ->minValue('participant_age', 5, 'Age must be at least 5 years')
              ->maxValue('participant_age', 120, 'Invalid age')
              ->required('player_type', 'Player type is required')
              ->required('agree_terms', 'You must agree to the terms and conditions');

    if ($validator->passes()) {
        $bookingReference = 'CC-' . strtoupper(uniqid());
        $amount = $event['entry_fee'];

        try {
            $pdo->beginTransaction();

            // Show what we're trying to insert
            echo "<pre style='background: #f0f0f0; padding: 20px; margin: 20px;'>";
            echo "<strong>DEBUG: Values being inserted:</strong>\n";
            echo "event_id: {$eventId}\n";
            echo "user_id: {$userId}\n";
            echo "booking_reference: {$bookingReference}\n";
            echo "participant_name: {$_POST['participant_name']}\n";
            echo "participant_email: {$_POST['participant_email']}\n";
            echo "participant_phone: {$_POST['participant_phone']}\n";
            echo "participant_age: {$_POST['participant_age']}\n";
            echo "player_type: {$_POST['player_type']}\n";
            echo "amount_paid: {$amount}\n";
            echo "</pre>";

            // Try the INSERT with direct PDO for better error reporting
            $stmt = $pdo->prepare("
                INSERT INTO bookings (
                    event_id, 
                    user_id, 
                    booking_reference, 
                    participant_name, 
                    participant_email, 
                    participant_phone, 
                    participant_age, 
                    player_type, 
                    booking_status, 
                    payment_status, 
                    amount_paid, 
                    booking_date, 
                    updated_at
                ) VALUES (
                    :event_id, 
                    :user_id, 
                    :booking_reference, 
                    :participant_name, 
                    :participant_email, 
                    :participant_phone, 
                    :participant_age, 
                    :player_type, 
                    'pending', 
                    'pending', 
                    :amount_paid, 
                    NOW(), 
                    NOW()
                )
            ");
            
            $executeResult = $stmt->execute([
                ':event_id' => $eventId,
                ':user_id' => $userId,
                ':booking_reference' => $bookingReference,
                ':participant_name' => $_POST['participant_name'],
                ':participant_email' => $_POST['participant_email'],
                ':participant_phone' => $_POST['participant_phone'],
                ':participant_age' => intval($_POST['participant_age']),
                ':player_type' => $_POST['player_type'],
                ':amount_paid' => floatval($amount)
            ]);

            if (!$executeResult) {
                $errorInfo = $stmt->errorInfo();
                echo "<pre style='background: #ffcccc; padding: 20px; margin: 20px;'>";
                echo "<strong>SQL ERROR DETAILS:</strong>\n";
                echo "SQLSTATE: {$errorInfo[0]}\n";
                echo "Driver Error Code: {$errorInfo[1]}\n";
                echo "Driver Error Message: {$errorInfo[2]}\n";
                echo "</pre>";
                throw new Exception("SQL Error [" . $errorInfo[0] . "]: " . $errorInfo[2]);
            }

            $bookingId = $pdo->lastInsertId();
            
            echo "<pre style='background: #ccffcc; padding: 20px; margin: 20px;'>";
            echo "<strong>SUCCESS!</strong>\n";
            echo "Booking ID: {$bookingId}\n";
            echo "</pre>";

            if (!$bookingId) {
                throw new Exception('Failed to retrieve booking ID');
            }

            // Update event current_bookings
            $stmt2 = $pdo->prepare("
                UPDATE events 
                SET current_bookings = current_bookings + 1 
                WHERE event_id = :event_id
            ");
            $stmt2->execute([':event_id' => $eventId]);

            $pdo->commit();

            // Store booking ID in session
            $_SESSION['pending_booking_id'] = $bookingId;
            setFlash('success', 'Booking created successfully! Please complete payment.');

            // Redirect to checkout
            echo "<p style='background: #ccffcc; padding: 20px; margin: 20px;'>";
            echo "Redirecting to checkout... If not redirected, <a href='" . BASE_URL . "/checkout?booking_id={$bookingId}'>click here</a>";
            echo "</p>";
            
            header('Location: ' . BASE_URL . '/checkout?booking_id=' . $bookingId);
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            
            // Show full error
            echo "<pre style='background: #ffcccc; padding: 20px; margin: 20px; border: 2px solid red;'>";
            echo "<strong>EXCEPTION CAUGHT:</strong>\n";
            echo "Message: " . $e->getMessage() . "\n";
            echo "File: " . $e->getFile() . "\n";
            echo "Line: " . $e->getLine() . "\n";
            echo "\nStack Trace:\n" . $e->getTraceAsString();
            echo "</pre>";
            
            error_log("Booking Error: " . $e->getMessage());
            $errors[] = 'Failed to create booking. Error: ' . $e->getMessage();
        }
    } else {
        $errors = $validator->getErrors();
        $formData = $_POST;
    }
}

$pageTitle = 'Book Event - ' . htmlspecialchars($event['event_name']);
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <!-- Event Summary -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <h1 class="text-2xl font-bold text-gray-900 mb-2">Complete Your Booking</h1>
            <p class="text-gray-600">Event: <span class="font-semibold"><?= htmlspecialchars($event['event_name']) ?></span></p>
            <div class="mt-4 flex items-center space-x-4 text-sm text-gray-600">
                <span><i class="fas fa-calendar mr-2"></i><?= date('M d, Y', strtotime($event['event_date'])) ?></span>
                <span><i class="fas fa-clock mr-2"></i><?= date('h:i A', strtotime($event['event_time'])) ?></span>
                <span><i class="fas fa-dollar-sign mr-2"></i>$<?= number_format($event['entry_fee'], 2) ?></span>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6 rounded">
                <div class="flex">
                    <i class="fas fa-exclamation-circle text-red-500 mr-3 mt-1"></i>
                    <div>
                        <?php foreach ($errors as $error): ?>
                            <p class="text-red-700"><?= htmlspecialchars($error) ?></p>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-6" id="bookingForm">
            <!-- Participant Details -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <h2 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
                    <i class="fas fa-user text-blue-600 mr-2"></i>
                    Participant Details
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block mb-2 text-gray-700 font-medium">Full Name *</label>
                        <input type="text" name="participant_name" 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               value="<?= htmlspecialchars($formData['participant_name'] ?? $user['full_name']) ?>" required>
                    </div>
                    <div>
                        <label class="block mb-2 text-gray-700 font-medium">Email *</label>
                        <input type="email" name="participant_email" 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               value="<?= htmlspecialchars($formData['participant_email'] ?? $user['email']) ?>" required>
                    </div>
                    <div>
                        <label class="block mb-2 text-gray-700 font-medium">Phone *</label>
                        <input type="text" name="participant_phone" 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               value="<?= htmlspecialchars($formData['participant_phone'] ?? $user['phone']) ?>" required>
                    </div>
                    <div>
                        <label class="block mb-2 text-gray-700 font-medium">Age *</label>
                        <input type="number" name="participant_age" 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               value="<?= htmlspecialchars($formData['participant_age'] ?? '') ?>" min="5" max="120" required>
                    </div>
                </div>

                <div class="mt-6">
                    <label class="block mb-2 text-gray-700 font-medium">Player Type *</label>
                    <select name="player_type" 
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" required>
                        <option value="">Select Player Type</option>
                        <option value="beginner" <?= ($formData['player_type'] ?? '') === 'beginner' ? 'selected' : '' ?>>Beginner</option>
                        <option value="intermediate" <?= ($formData['player_type'] ?? '') === 'intermediate' ? 'selected' : '' ?>>Intermediate</option>
                        <option value="advanced" <?= ($formData['player_type'] ?? '') === 'advanced' ? 'selected' : '' ?>>Advanced</option>
                        <option value="professional" <?= ($formData['player_type'] ?? '') === 'professional' ? 'selected' : '' ?>>Professional</option>
                    </select>
                </div>
            </div>

            <!-- Terms & Conditions -->
            <div class="bg-gray-50 border border-gray-200 p-6 rounded-lg">
                <label class="flex items-start cursor-pointer">
                    <input type="checkbox" name="agree_terms" value="1" class="mt-1 mr-3 w-4 h-4" required>
                    <span class="text-sm text-gray-700">
                        I agree to the <a href="<?= BASE_URL ?>/pages/terms.php" target="_blank" class="text-blue-600 hover:underline">Terms and Conditions</a> 
                        and <a href="<?= BASE_URL ?>/pages/refund.php" target="_blank" class="text-blue-600 hover:underline">Cancellation Policy</a>.
                        I confirm that all information provided is accurate.
                    </span>
                </label>
            </div>

            <!-- Action Buttons -->
            <div class="flex gap-4">
                <a href="<?= BASE_URL ?>/event-details?id=<?= $eventId ?>" 
                   class="flex-1 px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-800 text-center rounded-lg font-medium transition">
                    <i class="fas fa-arrow-left mr-2"></i>Cancel
                </a>
                <button type="submit" id="submitBtn" 
                        class="flex-1 px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition shadow-lg">
                    <i class="fas fa-arrow-right mr-2"></i>Proceed to Payment
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('bookingForm').addEventListener('submit', function(e) {
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>