<?php
/**
 * Event Booking Page (Singleton Database Compatible)
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Validator.php';

// session_start();

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();
$userId = $_SESSION['user_id'] ?? 0;

// ✅ Get Event ID
$eventId = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;
if ($eventId === 0) {
    header('Location: browse.php');
    exit;
}

// ✅ Fetch Event Details
$event = $db->query("
    SELECT 
        e.*, 
        u.full_name AS organizer_name,
        (e.max_capacity - e.current_bookings) AS available_slots
    FROM events e
    LEFT JOIN users u ON e.organizer_id = u.user_id
    WHERE e.event_id = :event_id
")
->bind(':event_id', $eventId)
->fetch();

if (!$event) {
    $_SESSION['error'] = 'Event not found.';
    header('Location: browse.php');
    exit;
}

if ($event['status'] !== 'upcoming') {
    $_SESSION['error'] = 'This event is not available for booking.';
    header('Location: details.php?id=' . $eventId);
    exit;
}

if ($event['available_slots'] <= 0) {
    $_SESSION['error'] = 'This event is fully booked.';
    header('Location: details.php?id=' . $eventId);
    exit;
}

// ✅ Check if user already booked
$alreadyBooked = $db->query("
    SELECT booking_id 
    FROM bookings 
    WHERE event_id = :event_id 
      AND user_id = :user_id 
      AND booking_status IN ('pending', 'confirmed')
")
->bind(':event_id', $eventId)
->bind(':user_id', $userId)
->fetch();

if ($alreadyBooked) {
    $_SESSION['error'] = 'You have already booked this event.';
    header('Location: details.php?id=' . $eventId);
    exit;
}

// ✅ Fetch user details
$user = $db->query("SELECT * FROM users WHERE user_id = :id")
           ->bind(':id', $userId)
           ->fetch();

$errors = [];
$formData = [];

// ✅ Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $validator = new Validator($_POST);

    $validator->required('participant_name', 'Participant name is required')
              ->required('participant_email', 'Email is required')
              ->email('participant_email', 'Invalid email format')
              ->required('participant_phone', 'Phone number is required')
              ->phone('participant_phone', 'Invalid phone number')
              ->required('participant_age', 'Age is required')
              ->numeric('participant_age', 'Age must be a number')
              ->min('participant_age', 5, 'Age must be at least 5 years')
              ->max('participant_age', 120, 'Invalid age')
              ->required('player_type', 'Player type is required')
              ->required('agree_terms', 'You must agree to the terms and conditions');

    // ✅ Call validate() correctly (no argument needed)
    if ($validator->validate([])) {
        $bookingReference = 'CC-' . strtoupper(uniqid());
        $amount = $event['entry_fee'];

        try {
            $pdo = $db->getConnection(); // ✅ PDO from Singleton
            $pdo->beginTransaction();

            // ✅ Create Booking
            $db->query("
                INSERT INTO bookings (
                    event_id, user_id, booking_reference, participant_name, 
                    participant_email, participant_phone, participant_age, 
                    player_type, booking_status, payment_status, amount_paid
                ) VALUES (
                    :event_id, :user_id, :booking_reference, :participant_name, 
                    :participant_email, :participant_phone, :participant_age, 
                    :player_type, 'pending', 'pending', :amount_paid
                )
            ")
            ->bind(':event_id', $eventId)
            ->bind(':user_id', $userId)
            ->bind(':booking_reference', $bookingReference)
            ->bind(':participant_name', $_POST['participant_name'])
            ->bind(':participant_email', $_POST['participant_email'])
            ->bind(':participant_phone', $_POST['participant_phone'])
            ->bind(':participant_age', $_POST['participant_age'])
            ->bind(':player_type', $_POST['player_type'])
            ->bind(':amount_paid', $amount)
            ->execute();

            $bookingId = $pdo->lastInsertId();

            // ✅ Update event bookings
            $db->query("UPDATE events SET current_bookings = current_bookings + 1 WHERE event_id = :event_id")
               ->bind(':event_id', $eventId)
               ->execute();

            $pdo->commit();

            $_SESSION['pending_booking_id'] = $bookingId;
            $_SESSION['success'] = 'Booking created successfully! Please complete payment.';

            header('Location: checkout.php?booking_id=' . $bookingId);
            exit;

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Booking Error: " . $e->getMessage());
            $errors[] = 'Failed to create booking. Please try again.';
        }
    } else {
        $errors = $validator->getErrors();
        $formData = $_POST;
    }
}

$pageTitle = 'Book Event';
require_once __DIR__ . '/../../includes/header.php';
?>

<!-- ✅ Booking Form -->
<div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <h1 class="text-2xl font-bold text-gray-900 mb-2">Complete Your Booking</h1>
            <p class="text-gray-600">Event: <span class="font-semibold"><?= htmlspecialchars($event['event_name']) ?></span></p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6">
                <?php foreach ($errors as $error): ?>
                    <p class="text-red-700"><?= htmlspecialchars($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-6" id="bookingForm">
            <!-- Participant Details -->
            <div class="bg-white rounded-lg shadow-lg p-6">
                <h2 class="text-xl font-bold text-gray-900 mb-4">Participant Details</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block mb-2 text-gray-700 font-medium">Full Name *</label>
                        <input type="text" name="participant_name" class="w-full px-4 py-2 border rounded-lg"
                            value="<?= htmlspecialchars($formData['participant_name'] ?? $user['full_name']) ?>" required>
                    </div>
                    <div>
                        <label class="block mb-2 text-gray-700 font-medium">Email *</label>
                        <input type="email" name="participant_email" class="w-full px-4 py-2 border rounded-lg"
                            value="<?= htmlspecialchars($formData['participant_email'] ?? $user['email']) ?>" required>
                    </div>
                    <div>
                        <label class="block mb-2 text-gray-700 font-medium">Phone *</label>
                        <input type="text" name="participant_phone" class="w-full px-4 py-2 border rounded-lg"
                            value="<?= htmlspecialchars($formData['participant_phone'] ?? $user['phone']) ?>" required>
                    </div>
                    <div>
                        <label class="block mb-2 text-gray-700 font-medium">Age *</label>
                        <input type="number" name="participant_age" class="w-full px-4 py-2 border rounded-lg"
                            value="<?= htmlspecialchars($formData['participant_age'] ?? '') ?>" min="5" max="120" required>
                    </div>
                </div>
            </div>

            <!-- Terms -->
            <div class="bg-gray-50 p-4 rounded-lg mb-4">
                <label class="flex items-start cursor-pointer">
                    <input type="checkbox" name="agree_terms" value="1" class="mt-1 mr-3" required>
                    <span>I agree to the <a href="#" class="text-blue-600">Terms and Conditions</a>.</span>
                </label>
            </div>

            <div class="flex gap-4">
                <a href="event-details?id=<?= $eventId ?>" class="flex-1 px-6 py-3 bg-gray-200 text-center rounded-lg">Cancel</a>
                <button type="submit" id="submitBtn" class="flex-1 px-6 py-3 bg-blue-600 text-white rounded-lg">Proceed to Payment</button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
