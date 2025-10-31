<?php
/**
 * Admin Payment Reports
 * File: modules/admin/payment-reports.php
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Auth.php';

$auth = new Auth();
$auth->requireRole('admin');

// Use Singleton connection
$db = Database::getInstance()->getConnection();

// Get filter parameters
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');
$status = $_GET['status'] ?? 'all';
$gateway = $_GET['gateway'] ?? 'all';

// Build WHERE clause
$whereConditions = ["DATE(p.payment_date) BETWEEN ? AND ?"];
$params = [$startDate, $endDate];

if ($status !== 'all') {
    $whereConditions[] = "p.payment_status = ?";
    $params[] = $status;
}
if ($gateway !== 'all') {
    $whereConditions[] = "p.payment_gateway = ?";
    $params[] = $gateway;
}

$whereClause = implode(' AND ', $whereConditions);

// Get payment data
$stmt = $db->prepare("
    SELECT p.*, b.booking_reference, e.event_name, u.full_name, u.email
    FROM payments p
    JOIN bookings b ON p.booking_id = b.booking_id
    JOIN events e ON b.event_id = e.event_id
    JOIN users u ON b.user_id = u.user_id
    WHERE $whereClause
    ORDER BY p.payment_date DESC
");
$stmt->execute($params);
$payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Statistics
$stmt = $db->prepare("
    SELECT 
        COUNT(*) as total_transactions,
        SUM(CASE WHEN payment_status = 'completed' THEN amount ELSE 0 END) as total_revenue,
        SUM(CASE WHEN payment_status = 'refunded' THEN refund_amount ELSE 0 END) as total_refunds,
        SUM(CASE WHEN payment_status = 'failed' THEN 1 ELSE 0 END) as failed_count,
        AVG(CASE WHEN payment_status = 'completed' THEN amount ELSE NULL END) as avg_transaction
    FROM payments p
    WHERE $whereClause
");
$stmt->execute($params);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Gateway breakdown
$stmt = $db->prepare("
    SELECT 
        payment_gateway,
        COUNT(*) as count,
        SUM(CASE WHEN payment_status = 'completed' THEN amount ELSE 0 END) as revenue
    FROM payments p
    WHERE $whereClause
    GROUP BY payment_gateway
");
$stmt->execute($params);
$gatewayStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/../../includes/header.php';
?>

<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">💰 Payment Reports</h1>
            <p class="text-gray-600 mt-2">Comprehensive financial analytics and transaction history</p>
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Start Date</label>
                    <input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">End Date</label>
                    <input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>" 
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                        <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All Statuses</option>
                        <option value="completed" <?= $status === 'completed' ? 'selected' : '' ?>>Completed</option>
                        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="failed" <?= $status === 'failed' ? 'selected' : '' ?>>Failed</option>
                        <option value="refunded" <?= $status === 'refunded' ? 'selected' : '' ?>>Refunded</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Gateway</label>
                    <select name="gateway" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500">
                        <option value="all" <?= $gateway === 'all' ? 'selected' : '' ?>>All Gateways</option>
                        <option value="stripe" <?= $gateway === 'stripe' ? 'selected' : '' ?>>Stripe</option>
                        <option value="paypal" <?= $gateway === 'paypal' ? 'selected' : '' ?>>PayPal</option>
                    </select>
                </div>
                <div class="md:col-span-4 flex justify-between items-center">
                    <button type="submit" class="bg-purple-600 text-white px-6 py-2 rounded-lg hover:bg-purple-700 transition">
                        Apply Filters
                    </button>
                    <button type="button" onclick="exportReport()" class="bg-green-600 text-white px-6 py-2 rounded-lg hover:bg-green-700 transition">
                        📊 Export CSV
                    </button>
                </div>
            </form>
        </div>

        <!-- Statistics Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
            <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-lg shadow-lg p-6 text-white">
                <p class="text-green-100 text-sm font-medium">Total Revenue</p>
                <p class="text-3xl font-bold mt-2">$<?= number_format($stats['total_revenue'], 2) ?></p>
            </div>
            <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-lg shadow-lg p-6 text-white">
                <p class="text-blue-100 text-sm font-medium">Total Transactions</p>
                <p class="text-3xl font-bold mt-2"><?= number_format($stats['total_transactions']) ?></p>
            </div>
            <div class="bg-gradient-to-br from-orange-500 to-orange-600 rounded-lg shadow-lg p-6 text-white">
                <p class="text-orange-100 text-sm font-medium">Total Refunds</p>
                <p class="text-3xl font-bold mt-2">$<?= number_format($stats['total_refunds'], 2) ?></p>
            </div>
            <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-lg shadow-lg p-6 text-white">
                <p class="text-purple-100 text-sm font-medium">Avg Transaction</p>
                <p class="text-3xl font-bold mt-2">$<?= number_format($stats['avg_transaction'] ?? 0, 2) ?></p>
            </div>
        </div>

        <!-- Transaction Table -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-xl font-bold text-gray-900">Transaction History</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Transaction ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Event</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Gateway</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($payments)): ?>
                            <tr><td colspan="8" class="px-6 py-8 text-center text-gray-500">No transactions found</td></tr>
                        <?php else: ?>
                            <?php foreach ($payments as $payment): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 text-sm font-mono text-gray-900">
                                    <?= htmlspecialchars(substr($payment['transaction_id'] ?? 'N/A', 0, 16)) ?>...
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600">
                                    <?= date('M d, Y', strtotime($payment['payment_date'])) ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    <?= htmlspecialchars($payment['full_name']) ?><br>
                                    <span class="text-gray-500"><?= htmlspecialchars($payment['email']) ?></span>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-900">
                                    <?= htmlspecialchars($payment['event_name']) ?><br>
                                    <span class="text-gray-500"><?= htmlspecialchars($payment['booking_reference']) ?></span>
                                </td>
                                <td class="px-6 py-4 text-sm capitalize">
                                    <?= htmlspecialchars($payment['payment_gateway']) ?>
                                </td>
                                <td class="px-6 py-4 text-sm font-semibold text-gray-900">
                                    $<?= number_format($payment['amount'], 2) ?>
                                </td>
                                <td class="px-6 py-4">
                                    <?php
                                    $statusColors = [
                                        'completed' => 'bg-green-100 text-green-800',
                                        'pending' => 'bg-yellow-100 text-yellow-800',
                                        'failed' => 'bg-red-100 text-red-800',
                                        'refunded' => 'bg-orange-100 text-orange-800'
                                    ];
                                    $color = $statusColors[$payment['payment_status']] ?? 'bg-gray-100 text-gray-800';
                                    ?>
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full <?= $color ?>">
                                        <?= ucfirst($payment['payment_status']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-sm">
                                    <button onclick="viewDetails(<?= $payment['payment_id'] ?>)" class="text-purple-600 hover:text-purple-800">View</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function exportReport() {
    const params = new URLSearchParams(window.location.search);
    params.append('export', 'csv');
    window.location.href = '/api/payments/export.php?' + params.toString();
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
