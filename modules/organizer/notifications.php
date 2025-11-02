<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Mailer.php';

$auth = new Auth($pdo);
$auth->requireLogin();
$auth->requireRole(['organizer', 'admin']);

$user = $_SESSION['user'];
$organizer_id = $user['user_id'];

$success = false;
$errors = [];

// Fetch organizer's events for dropdown
$events_sql = "
    SELECT e.event_id, e.event_name, e.event_date, 
           COUNT(b.booking_id) as participant_count
    FROM events e
    LEFT JOIN bookings b ON e.event_id = b.event_id 
        AND b.booking_status IN ('confirmed', 'pending')
    WHERE e.organizer_id = ? AND e.event_status IN ('upcoming', 'in_progress')
    GROUP BY e.event_id
    ORDER BY e.event_date ASC
";
$stmt = $pdo->prepare($events_sql);
$stmt->execute([$organizer_id]);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $event_id = $_POST['event_id'] ?? null;
    $recipient_type = $_POST['recipient_type'] ?? 'all';
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    // Validation
    if (empty($event_id)) {
        $errors[] = "Please select an event";
    }
    if (empty($subject)) {
        $errors[] = "Subject is required";
    }
    if (empty($message)) {
        $errors[] = "Message is required";
    }
    
    if (empty($errors)) {
        // Verify event ownership
        $check_sql = "SELECT event_id, event_name FROM events WHERE event_id = ? AND organizer_id = ?";
        $stmt = $pdo->prepare($check_sql);
        $stmt->execute([$event_id, $organizer_id]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$event) {
            $errors[] = "Invalid event selected";
        } else {
            // Get recipients based on type
            $recipients_sql = "
                SELECT DISTINCT 
                    u.email, 
                    u.full_name,
                    b.booking_reference,
                    b.participant_name
                FROM bookings b
                JOIN users u ON b.user_id = u.user_id
                WHERE b.event_id = ?
            ";
            
            $params = [$event_id];
            
            if ($recipient_type === 'confirmed') {
                $recipients_sql .= " AND b.booking_status = 'confirmed'";
            } elseif ($recipient_type === 'pending') {
                $recipients_sql .= " AND b.booking_status = 'pending'";
            } elseif ($recipient_type === 'all') {
                $recipients_sql .= " AND b.booking_status IN ('confirmed', 'pending')";
            }
            
            $stmt = $pdo->prepare($recipients_sql);
            $stmt->execute($params);
            $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($recipients)) {
                $errors[] = "No recipients found for the selected criteria";
            } else {
                $mailer = new Mailer($pdo);
                $sent_count = 0;
                $failed_count = 0;
                
                foreach ($recipients as $recipient) {
                    // Personalize message
                    $personalized_message = str_replace([
                        '{{name}}',
                        '{{participant_name}}',
                        '{{booking_reference}}',
                        '{{event_name}}'
                    ], [
                        $recipient['full_name'],
                        $recipient['participant_name'],
                        $recipient['booking_reference'],
                        $event['event_name']
                    ], $message);
                    
                    try {
                        $email_sent = $mailer->send(
                            $recipient['email'],
                            $subject,
                            $personalized_message
                        );
                        
                        if ($email_sent) {
                            $sent_count++;
                            
                            // Log notification
                            $log_sql = "INSERT INTO notifications (user_id, event_id, notification_type, subject, message, notification_status, sent_at) 
                                       VALUES ((SELECT user_id FROM users WHERE email = ?), ?, 'email', ?, ?, 'sent', NOW())";
                            $stmt = $pdo->prepare($log_sql);
                            $stmt->execute([$recipient['email'], $event_id, $subject, $personalized_message]);
                        } else {
                            $failed_count++;
                        }
                    } catch (Exception $e) {
                        $failed_count++;
                        error_log("Email failed to {$recipient['email']}: " . $e->getMessage());
                    }
                }
                
                if ($sent_count > 0) {
                    $_SESSION['success_message'] = "Successfully sent {$sent_count} notification(s)";
                    if ($failed_count > 0) {
                        $_SESSION['success_message'] .= " ({$failed_count} failed)";
                    }
                    $success = true;
                } else {
                    $errors[] = "Failed to send notifications. Please try again.";
                }
            }
        }
    }
}

// Fetch recent notifications
$recent_notifications_sql = "
    SELECT 
        n.*,
        e.event_name,
        COUNT(n.notification_id) OVER (PARTITION BY n.event_id, n.subject) as recipient_count
    FROM notifications n
    JOIN events e ON n.event_id = e.event_id
    WHERE e.organizer_id = ?
    GROUP BY n.notification_id
    ORDER BY n.created_at DESC
    LIMIT 10
";
$stmt = $pdo->prepare($recent_notifications_sql);
$stmt->execute([$organizer_id]);
$recent_notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../../includes/header.php';
#include '../../includes/nav.php';
?>

<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">Send Notifications</h1>
            <p class="mt-2 text-gray-600">Send email notifications to your event participants</p>
        </div>

        <!-- Success Message -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="mb-6 bg-green-50 border border-green-200 rounded-lg p-4">
                <div class="flex">
                    <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/>
                    </svg>
                    <p class="ml-3 text-sm text-green-700"><?= htmlspecialchars($_SESSION['success_message']) ?></p>
                </div>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>

        <!-- Error Messages -->
        <?php if (!empty($errors)): ?>
            <div class="mb-6 bg-red-50 border border-red-200 rounded-lg p-4">
                <div class="flex">
                    <svg class="h-5 w-5 text-red-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"/>
                    </svg>
                    <div class="ml-3">
                        <ul class="text-sm text-red-700 list-disc list-inside">
                            <?php foreach ($errors as $error): ?>
                                <li><?= htmlspecialchars($error) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Notification Form -->
            <div class="lg:col-span-2">
                <form method="POST" class="bg-white rounded-lg shadow">
                    
                    <!-- Form Header -->
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900">Compose Notification</h2>
                    </div>
                    
                    <div class="px-6 py-6 space-y-6">
                        
                        <!-- Event Selection -->
                        <div>
                            <label for="event_id" class="block text-sm font-medium text-gray-700 mb-2">
                                Select Event <span class="text-red-500">*</span>
                            </label>
                            <select name="event_id" id="event_id" required
                                    onchange="updateRecipientCount()"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="">Choose an event...</option>
                                <?php foreach ($events as $event): ?>
                                    <option value="<?= $event['event_id'] ?>" 
                                            data-count="<?= $event['participant_count'] ?>"
                                            <?= (isset($_POST['event_id']) && $_POST['event_id'] == $event['event_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($event['event_name']) ?> 
                                        (<?= date('M j, Y', strtotime($event['event_date'])) ?>) 
                                        - <?= $event['participant_count'] ?> participants
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Recipient Type -->
                        <div>
                            <label for="recipient_type" class="block text-sm font-medium text-gray-700 mb-2">
                                Send To <span class="text-red-500">*</span>
                            </label>
                            <select name="recipient_type" id="recipient_type" required
                                    onchange="updateRecipientCount()"
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                                <option value="all" <?= (isset($_POST['recipient_type']) && $_POST['recipient_type'] == 'all') ? 'selected' : '' ?>>
                                    All Participants
                                </option>
                                <option value="confirmed" <?= (isset($_POST['recipient_type']) && $_POST['recipient_type'] == 'confirmed') ? 'selected' : '' ?>>
                                    Confirmed Bookings Only
                                </option>
                                <option value="pending" <?= (isset($_POST['recipient_type']) && $_POST['recipient_type'] == 'pending') ? 'selected' : '' ?>>
                                    Pending Bookings Only
                                </option>
                            </select>
                            <p class="mt-2 text-sm text-gray-500" id="recipient_info">
                                Select an event to see recipient count
                            </p>
                        </div>

                        <!-- Subject -->
                        <div>
                            <label for="subject" class="block text-sm font-medium text-gray-700 mb-2">
                                Subject <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="subject" id="subject" required
                                   value="<?= htmlspecialchars($_POST['subject'] ?? '') ?>"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="e.g., Important Update: Tournament Schedule Change">
                        </div>

                        <!-- Message -->
                        <div>
                            <label for="message" class="block text-sm font-medium text-gray-700 mb-2">
                                Message <span class="text-red-500">*</span>
                            </label>
                            <textarea name="message" id="message" rows="10" required
                                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent font-mono text-sm"
                                      placeholder="Type your message here..."><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                            
                            <!-- Variable Help -->
                            <div class="mt-3 p-4 bg-blue-50 rounded-lg">
                                <p class="text-sm font-medium text-blue-900 mb-2">Available Variables:</p>
                                <div class="grid grid-cols-2 gap-2 text-sm text-blue-800">
                                    <div><code class="bg-blue-100 px-2 py-1 rounded">{{name}}</code> - User's full name</div>
                                    <div><code class="bg-blue-100 px-2 py-1 rounded">{{participant_name}}</code> - Participant name</div>
                                    <div><code class="bg-blue-100 px-2 py-1 rounded">{{booking_reference}}</code> - Booking reference</div>
                                    <div><code class="bg-blue-100 px-2 py-1 rounded">{{event_name}}</code> - Event name</div>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Templates -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Quick Templates</label>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                <button type="button" onclick="loadTemplate('reminder')"
                                        class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">
                                    Event Reminder
                                </button>
                                <button type="button" onclick="loadTemplate('update')"
                                        class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">
                                    Schedule Update
                                </button>
                                <button type="button" onclick="loadTemplate('thank_you')"
                                        class="px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">
                                    Thank You
                                </button>
                            </div>
                        </div>

                    </div>

                    <!-- Form Actions -->
                    <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 flex justify-between items-center">
                        <p class="text-sm text-gray-500">
                            <svg class="inline-block h-4 w-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Emails will be sent immediately
                        </p>
                        <button type="submit" 
                                class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                            Send Notifications
                        </button>
                    </div>

                </form>
            </div>

            <!-- Sidebar - Tips & Recent -->
            <div class="space-y-6">
                
                <!-- Tips -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Tips for Great Notifications</h3>
                    <ul class="space-y-3 text-sm text-gray-600">
                        <li class="flex items-start">
                            <svg class="h-5 w-5 text-green-500 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/>
                            </svg>
                            <span>Use personalization variables to make emails more engaging</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="h-5 w-5 text-green-500 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/>
                            </svg>
                            <span>Keep messages clear and concise</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="h-5 w-5 text-green-500 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/>
                            </svg>
                            <span>Include important dates and times</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="h-5 w-5 text-green-500 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/>
                            </svg>
                            <span>Provide contact information for questions</span>
                        </li>
                    </ul>
                </div>

                <!-- Recent Notifications -->
                <?php if (!empty($recent_notifications)): ?>
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Recent Notifications</h3>
                    <div class="space-y-4">
                        <?php 
                        $displayed = [];
                        foreach ($recent_notifications as $notif): 
                            $key = $notif['event_id'] . '-' . $notif['subject'];
                            if (in_array($key, $displayed)) continue;
                            $displayed[] = $key;
                        ?>
                            <div class="border-l-4 border-blue-400 pl-4">
                                <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars($notif['subject']) ?></p>
                                <p class="text-xs text-gray-500 mt-1">
                                    <?= htmlspecialchars($notif['event_name']) ?>
                                </p>
                                <p class="text-xs text-gray-400 mt-1">
                                    <?= date('M j, Y g:i A', strtotime($notif['created_at'])) ?>
                                </p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

            </div>

        </div>

    </div>
</div>

<script>
// Email templates
const templates = {
    reminder: {
        subject: 'Reminder: {{event_name}} - Coming Soon!',
        message: `Dear {{name}},

This is a friendly reminder about your upcoming booking:

Event: {{event_name}}
Booking Reference: {{booking_reference}}
Participant: {{participant_name}}

We look forward to seeing you at the event!

If you have any questions, please don't hesitate to contact us.

Best regards,
Crystal Chess Tournament Team`
    },
    update: {
        subject: 'Important Update: {{event_name}}',
        message: `Dear {{name}},

We have an important update regarding {{event_name}}.

[Please describe the update here]

Your booking reference: {{booking_reference}}
Participant: {{participant_name}}

If you have any concerns or questions, please contact us immediately.

Thank you for your understanding.

Best regards,
Crystal Chess Tournament Team`
    },
    thank_you: {
        subject: 'Thank You for Participating in {{event_name}}',
        message: `Dear {{name}},

Thank you for participating in {{event_name}}!

We hope you had a great experience. Your support means a lot to us.

Participant: {{participant_name}}
Booking Reference: {{booking_reference}}

We'd love to see you at our future events!

Best regards,
Crystal Chess Tournament Team`
    }
};

function loadTemplate(type) {
    if (templates[type]) {
        document.getElementById('subject').value = templates[type].subject;
        document.getElementById('message').value = templates[type].message;
    }
}

function updateRecipientCount() {
    const eventSelect = document.getElementById('event_id');
    const recipientType = document.getElementById('recipient_type').value;
    const infoElement = document.getElementById('recipient_info');
    
    if (eventSelect.value) {
        const count = parseInt(eventSelect.options[eventSelect.selectedIndex].dataset.count);
        let message = `Will send to approximately ${count} participant(s)`;
        
        if (recipientType === 'confirmed') {
            message += ' (confirmed bookings only)';
        } else if (recipientType === 'pending') {
            message += ' (pending bookings only)';
        }
        
        infoElement.textContent = message;
        infoElement.className = 'mt-2 text-sm text-blue-600';
    } else {
        infoElement.textContent = 'Select an event to see recipient count';
        infoElement.className = 'mt-2 text-sm text-gray-500';
    }
}

// Auto-update on page load
document.addEventListener('DOMContentLoaded', function() {
    updateRecipientCount();
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>