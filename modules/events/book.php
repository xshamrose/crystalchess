<?php

/**
 * Event Booking Page - WITH PARTICIPANT SELECTION
 * File: modules/events/book.php
 */

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Auth.php';

// Start session safely
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$auth = new Auth();
$auth->requireLogin();

$db = Database::getInstance();
$userId = $_SESSION['user_id'];

// Get Event ID
$eventId = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;
if ($eventId === 0) {
    setFlash('error', 'Invalid event ID');
    header('Location: ' . BASE_URL . '/browse-events');
    exit;
}

// Fetch Event Details
$event = $db->query("
    SELECT 
        e.*, 
        u.full_name AS organizer_name,
        (e.max_capacity - e.current_bookings) AS available_slots
    FROM events e
    LEFT JOIN users u ON e.organizer_id = u.user_id
    WHERE e.event_id = :event_id
")->bind(':event_id', $eventId)->fetch();

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

// Get event categories
$eventDates = json_decode($event['event_dates'], true);
$categories = $db->query("
    SELECT ec.* 
    FROM event_categories ec
    JOIN event_category_mapping ecm ON ec.category_id = ecm.category_id
    WHERE ecm.event_id = :event_id
")->bind(':event_id', $eventId)->fetchAll();

// Get eligible participants for this event
$allParticipants = $db->query("
    SELECT * FROM participants 
    WHERE user_id = :user_id 
    ORDER BY full_name ASC
")->bind(':user_id', $userId)->fetchAll();

// Filter eligible participants based on categories
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

        // Check age eligibility
        $ageEligible = false;
        if ($categoryCode === 'OPEN') {
            $ageEligible = true;
        } elseif (preg_match('/U(\d+)/', $categoryCode, $matches)) {
            $maxAge = intval($matches[1]);
            $ageEligible = ($age < $maxAge);
        }

        // Check gender eligibility
        // RULE: Girls can play in Boys/Open, Boys cannot play in Girls
        $genderEligible = false;
        if (stripos($categoryCode, 'GIRLS') !== false || stripos($categoryCode, 'WOMEN') !== false) {
            $genderEligible = ($gender === 'female');
        } else {
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

$errors = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedParticipants = isset($_POST['participants']) ? $_POST['participants'] : [];

    if (empty($selectedParticipants)) {
        $errors[] = 'Please select at least one participant';
    }

    if (!isset($_POST['agree_terms'])) {
        $errors[] = 'You must agree to the terms and conditions';
    }

    if (empty($errors)) {
        $bookingReference = 'CC-' . strtoupper(uniqid());
        $amount = $event['entry_fee'] * count($selectedParticipants);

        try {
            $db->beginTransaction();

            // Create booking
            $bookingId = $db->insert('bookings', [
                'event_id' => $eventId,
                'user_id' => $userId,
                'booking_reference' => $bookingReference,
                'booking_status' => 'pending',
                'payment_status' => 'pending',
                'amount_paid' => $amount
            ]);

            if (!$bookingId) {
                throw new Exception('Failed to create booking');
            }

            // Add participants to booking_participants table
            foreach ($selectedParticipants as $participantId) {
                $participantId = intval($participantId);
                $db->insert('booking_participants', [
                    'booking_id' => $bookingId,
                    'participant_id' => $participantId,
                    'event_id' => $eventId
                ]);
            }

            // Update event current_bookings
            $db->query("
                UPDATE events 
                SET current_bookings = current_bookings + :count
                WHERE event_id = :event_id
            ")->bind(':count', count($selectedParticipants))
                ->bind(':event_id', $eventId)
                ->execute();

            $db->commit();

            $_SESSION['pending_booking_id'] = $bookingId;
            setFlash('success', 'Booking created successfully!');

            header('Location: ' . BASE_URL . '/checkout?booking_id=' . $bookingId);
            exit;
        } catch (Exception $e) {
            $db->rollback();
            error_log("Booking Error: " . $e->getMessage());
            $errors[] = 'Failed to create booking. Please try again.';
        }
    }
}

$pageTitle = 'Book Event - ' . htmlspecialchars($event['event_name']);
require_once __DIR__ . '/../../includes/header.php';
?>

<style>
    .participant-card {
        transition: all 0.2s ease;
        cursor: pointer;
    }

    .participant-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
    }

    .participant-card.selected {
        border-color: #3B82F6;
        background: #EFF6FF;
    }

    .participant-avatar {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        font-weight: bold;
        color: white;
    }
</style>

<div class="container mx-auto px-4 py-8">
    <div class="max-w-5xl mx-auto">

        <!-- Event Summary -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
            <h1 class="text-2xl font-bold text-gray-900 mb-2">Complete Your Booking</h1>
            <p class="text-gray-600">Event: <span class="font-semibold"><?= htmlspecialchars($event['event_name']) ?></span></p>
            <div class="mt-4 flex flex-wrap gap-4 text-sm text-gray-600">
                <span><i class="fas fa-calendar mr-2"></i><?= implode(', ', array_map(fn($d) => date('M d', strtotime($d)), $eventDates)) ?></span>
                <span><i class="fas fa-clock mr-2"></i><?= date('h:i A', strtotime($event['event_start_time'])) ?></span>
                <span><i class="fas fa-dollar-sign mr-2"></i>$<?= number_format($event['entry_fee'], 2) ?> per participant</span>
            </div>

            <!-- Categories -->
            <?php if (!empty($categories)): ?>
                <div class="mt-4">
                    <p class="text-sm font-semibold text-gray-700 mb-2">Event Categories:</p>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach ($categories as $cat): ?>
                            <span class="bg-blue-100 text-blue-800 text-xs px-3 py-1 rounded-full font-medium">
                                <?= htmlspecialchars($cat['category_name']) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Error Messages -->
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

        <form method="POST" id="bookingForm">

            <!-- Select Participants -->
            <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
                <h2 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
                    <i class="fas fa-users text-blue-600 mr-2"></i>
                    Select Participants
                </h2>

                <?php if (empty($eligibleParticipants)): ?>
                    <div class="text-center py-12 bg-gray-50 rounded-lg">
                        <div class="text-5xl mb-4">👤</div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">No Eligible Participants</h3>
                        <p class="text-gray-600 mb-4">You don't have any participants eligible for this event's categories.</p>
                        <a href="<?= BASE_URL ?>/manage-participants"
                            class="inline-block bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-semibold transition">
                            <i class="fas fa-plus mr-2"></i>Add Participant
                        </a>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php foreach ($eligibleParticipants as $participant): ?>
                            <label class="participant-card border-2 border-gray-200 rounded-lg p-4">
                                <div class="flex items-center">
                                    <input type="checkbox" name="participants[]" value="<?= $participant['participant_id'] ?>"
                                        class="mr-4 w-5 h-5 text-blue-600 participant-checkbox">

                                    <div class="participant-avatar mr-4"
                                        style="background: linear-gradient(135deg, <?= $participant['gender'] === 'male' ? '#3B82F6, #1D4ED8' : ($participant['gender'] === 'female' ? '#EC4899, #BE185D' : '#8B5CF6, #6D28D9') ?>)">
                                        <?= strtoupper(substr($participant['full_name'], 0, 1)) ?>
                                    </div>

                                    <div class="flex-1">
                                        <h3 class="font-bold text-gray-900"><?= htmlspecialchars($participant['full_name']) ?></h3>
                                        <p class="text-sm text-gray-600">
                                            <?= $participant['age'] ?> years •
                                            <?= $participant['gender'] === 'male' ? '♂️ Male' : ($participant['gender'] === 'female' ? '♀️ Female' : '⚧ Other') ?>
                                        </p>
                                        <p class="text-xs text-blue-600 mt-1">
                                            Eligible: <?= implode(', ', $participant['eligible_categories']) ?>
                                        </p>
                                    </div>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <!-- Selection Summary -->
                    <div id="selectionSummary" class="mt-6 p-4 bg-blue-50 rounded-lg hidden">
                        <div class="flex justify-between items-center">
                            <div>
                                <span class="font-semibold text-gray-900">Selected:</span>
                                <span id="selectedCount" class="text-blue-600 font-bold ml-2">0</span>
                                <span class="text-gray-600 ml-1">participant(s)</span>
                            </div>
                            <div class="text-right">
                                <span class="text-sm text-gray-600">Total Amount:</span>
                                <div id="totalAmount" class="text-2xl font-bold text-blue-600">$0.00</div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($eligibleParticipants)): ?>
                <!-- Terms & Conditions -->
                <div class="bg-gray-50 border border-gray-200 p-6 rounded-lg mb-6">
                    <label class="flex items-start cursor-pointer">
                        <input type="checkbox" name="agree_terms" value="1" class="mt-1 mr-3 w-4 h-4" required>
                        <span class="text-sm text-gray-700">
                            I agree to the <a href="<?= BASE_URL ?>/pages/terms.php" target="_blank" class="text-blue-600 hover:underline">Terms and Conditions</a>
                            and <a href="<?= BASE_URL ?>/pages/refund.php" target="_blank" class="text-blue-600 hover:underline">Cancellation Policy</a>.
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
                        class="flex-1 px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition shadow-lg disabled:bg-gray-400 disabled:cursor-not-allowed">
                        <i class="fas fa-arrow-right mr-2"></i>Proceed to Payment
                    </button>
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<script>
    const entryFee = <?= $event['entry_fee'] ?>;
    const checkboxes = document.querySelectorAll('.participant-checkbox');
    const selectionSummary = document.getElementById('selectionSummary');
    const selectedCountEl = document.getElementById('selectedCount');
    const totalAmountEl = document.getElementById('totalAmount');
    const submitBtn = document.getElementById('submitBtn');

    function updateSelection() {
        const selectedCount = document.querySelectorAll('.participant-checkbox:checked').length;
        const totalAmount = selectedCount * entryFee;

        selectedCountEl.textContent = selectedCount;
        totalAmountEl.textContent = '$' + totalAmount.toFixed(2);

        if (selectedCount > 0) {
            selectionSummary.classList.remove('hidden');
            submitBtn.disabled = false;
        } else {
            selectionSummary.classList.add('hidden');
            submitBtn.disabled = true;
        }

        // Update card styles
        document.querySelectorAll('.participant-card').forEach(card => {
            const checkbox = card.querySelector('.participant-checkbox');
            if (checkbox.checked) {
                card.classList.add('selected');
            } else {
                card.classList.remove('selected');
            }
        });
    }

    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateSelection);
    });

    // Initial state
    submitBtn.disabled = true;

    document.getElementById('bookingForm')?.addEventListener('submit', function(e) {
        const selectedCount = document.querySelectorAll('.participant-checkbox:checked').length;
        if (selectedCount === 0) {
            e.preventDefault();
            alert('Please select at least one participant');
            return;
        }

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Processing...';
    });
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>