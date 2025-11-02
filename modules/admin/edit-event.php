<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Auth.php';

$auth = new Auth($pdo);
$auth->requireLogin();
$auth->requireRole(['admin']);

$event_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($event_id <= 0) {
    die("Invalid event ID");
}

// Fetch event
$stmt = $pdo->prepare("SELECT * FROM events WHERE event_id = ?");
$stmt->execute([$event_id]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$event) {
    die("Event not found");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $event_name = trim($_POST['event_name']);
    $location = trim($_POST['location']);
    $event_date = $_POST['event_date'];
    $event_time = $_POST['event_time'];
    $max_capacity = (int)$_POST['max_capacity'];
    $entry_fee = (float)$_POST['entry_fee'];
    $event_status = $_POST['event_status'];
    $description = trim($_POST['description']);

    $update = $pdo->prepare("UPDATE events 
        SET event_name = ?, location = ?, event_date = ?, event_time = ?, 
            max_capacity = ?, entry_fee = ?, event_status = ?, description = ?
        WHERE event_id = ?");
    $update->execute([
        $event_name, $location, $event_date, $event_time,
        $max_capacity, $entry_fee, $event_status, $description, $event_id
    ]);

    header("Location: " . BASE_URL . "/admin-events?updated=1");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Event - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">

<div class="max-w-3xl mx-auto mt-10 bg-white shadow-lg rounded-lg p-8">
    <h1 class="text-2xl font-bold text-gray-800 mb-6">Edit Event</h1>

    <form method="POST" class="space-y-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Event Name</label>
            <input type="text" name="event_name" value="<?= htmlspecialchars($event['event_name']) ?>"
                   class="w-full border border-gray-300 rounded-lg p-2">
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Location</label>
            <input type="text" name="location" value="<?= htmlspecialchars($event['location']) ?>"
                   class="w-full border border-gray-300 rounded-lg p-2">
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                <input type="date" name="event_date" value="<?= htmlspecialchars($event['event_date']) ?>"
                       class="w-full border border-gray-300 rounded-lg p-2">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Time</label>
                <input type="time" name="event_time" value="<?= htmlspecialchars($event['event_time']) ?>"
                       class="w-full border border-gray-300 rounded-lg p-2">
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Max Capacity</label>
                <input type="number" name="max_capacity" value="<?= htmlspecialchars($event['max_capacity']) ?>"
                       class="w-full border border-gray-300 rounded-lg p-2">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Entry Fee</label>
                <input type="number" step="0.01" name="entry_fee" value="<?= htmlspecialchars($event['entry_fee']) ?>"
                       class="w-full border border-gray-300 rounded-lg p-2">
            </div>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
            <select name="event_status" class="w-full border border-gray-300 rounded-lg p-2">
                <option value="upcoming" <?= $event['event_status'] === 'upcoming' ? 'selected' : '' ?>>Upcoming</option>
                <option value="in_progress" <?= $event['event_status'] === 'in_progress' ? 'selected' : '' ?>>In Progress</option>
                <option value="completed" <?= $event['event_status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                <option value="cancelled" <?= $event['event_status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
            <textarea name="description" rows="4"
                      class="w-full border border-gray-300 rounded-lg p-2"><?= htmlspecialchars($event['description']) ?></textarea>
        </div>

        <div class="flex justify-between items-center">
            <a href="<?= BASE_URL ?>/admin-events"
               class="bg-gray-500 text-white px-4 py-2 rounded-lg hover:bg-gray-600">Cancel</a>
            <button type="submit"
                    class="bg-indigo-600 text-white px-6 py-2 rounded-lg font-semibold hover:bg-indigo-700">
                Save Changes
            </button>
        </div>
    </form>
</div>

</body>
</html>
