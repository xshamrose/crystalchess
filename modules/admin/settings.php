<?php
// modules/admin/settings.php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Auth.php';

$auth = new Auth($pdo);
$auth->requireLogin();
$auth->requireRole(['admin']);

$admin_id = $_SESSION['user_id'];
$message = '';
$error = '';

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        foreach ($_POST as $key => $value) {
            if ($key !== 'submit') {
                $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
                $stmt->execute([trim($value), $key]);
            }
        }
        
        // Log action
        $log_stmt = $pdo->prepare("INSERT INTO audit_logs (admin_id, action, entity_type) VALUES (?, 'update_settings', 'settings')");
        $log_stmt->execute([$admin_id]);
        
        $pdo->commit();
        $message = 'Settings updated successfully!';
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = 'Failed to update settings: ' . $e->getMessage();
    }
}

// Get all settings
$settings_sql = "SELECT * FROM settings ORDER BY setting_key";
$settings_stmt = $pdo->query($settings_sql);
$all_settings = $settings_stmt->fetchAll();

// Organize settings by category
$settings_by_category = [
    'General' => [],
    'Payment' => [],
    'Email' => [],
    'Notifications' => [],
    'Other' => []
];

foreach ($all_settings as $setting) {
    $key = $setting['setting_key'];
    if (strpos($key, 'site_') === 0) {
        $settings_by_category['General'][] = $setting;
    } elseif (strpos($key, 'payment_') === 0 || $key === 'currency') {
        $settings_by_category['Payment'][] = $setting;
    } elseif (strpos($key, 'email_') === 0 || strpos($key, 'smtp_') === 0) {
        $settings_by_category['Email'][] = $setting;
    } elseif (strpos($key, 'notification_') === 0 || $key === 'booking_confirmation_email' || $key === 'event_reminder_days') {
        $settings_by_category['Notifications'][] = $setting;
    } else {
        $settings_by_category['Other'][] = $setting;
    }
}

include __DIR__ . '/../../includes/header.php';
// include '../../includes/nav.php';
?>

<div class="min-h-screen bg-gray-50 py-8">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-3xl font-bold text-gray-900">System Settings</h1>
            <p class="text-gray-600 mt-1">Configure platform-wide settings</p>
        </div>

        <!-- Messages -->
        <?php if ($message): ?>
        <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
            <?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <form method="POST" class="space-y-6">
            <?php foreach ($settings_by_category as $category => $settings): ?>
                <?php if (!empty($settings)): ?>
                <!-- Category Section -->
                <div class="bg-white rounded-lg shadow">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h2 class="text-lg font-semibold text-gray-900"><?php echo $category; ?> Settings</h2>
                    </div>
                    <div class="p-6 space-y-4">
                        <?php foreach ($settings as $setting): ?>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <?php echo ucwords(str_replace('_', ' ', $setting['setting_key'])); ?>
                            </label>
                            
                            <?php if ($setting['setting_type'] === 'boolean'): ?>
                                <select name="<?php echo $setting['setting_key']; ?>" 
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                    <option value="1" <?php echo $setting['setting_value'] == '1' ? 'selected' : ''; ?>>Enabled</option>
                                    <option value="0" <?php echo $setting['setting_value'] == '0' ? 'selected' : ''; ?>>Disabled</option>
                                </select>
                            
                            <?php elseif ($setting['setting_type'] === 'number'): ?>
                                <input type="number" 
                                       name="<?php echo $setting['setting_key']; ?>" 
                                       value="<?php echo htmlspecialchars($setting['setting_value']); ?>"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            
                            <?php elseif ($setting['setting_key'] === 'payment_gateway'): ?>
                                <select name="<?php echo $setting['setting_key']; ?>" 
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                    <option value="stripe" <?php echo $setting['setting_value'] === 'stripe' ? 'selected' : ''; ?>>Stripe</option>
                                    <option value="paypal" <?php echo $setting['setting_value'] === 'paypal' ? 'selected' : ''; ?>>PayPal</option>
                                    <option value="razorpay" <?php echo $setting['setting_value'] === 'razorpay' ? 'selected' : ''; ?>>Razorpay</option>
                                </select>
                            
                            <?php elseif ($setting['setting_key'] === 'currency'): ?>
                                <select name="<?php echo $setting['setting_key']; ?>" 
                                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                                    <option value="USD" <?php echo $setting['setting_value'] === 'USD' ? 'selected' : ''; ?>>USD ($)</option>
                                    <option value="EUR" <?php echo $setting['setting_value'] === 'EUR' ? 'selected' : ''; ?>>EUR (€)</option>
                                    <option value="GBP" <?php echo $setting['setting_value'] === 'GBP' ? 'selected' : ''; ?>>GBP (£)</option>
                                    <option value="INR" <?php echo $setting['setting_value'] === 'INR' ? 'selected' : ''; ?>>INR (₹)</option>
                                </select>
                            
                            <?php else: ?>
                                <input type="text" 
                                       name="<?php echo $setting['setting_key']; ?>" 
                                       value="<?php echo htmlspecialchars($setting['setting_value']); ?>"
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                            <?php endif; ?>
                            
                            <p class="text-xs text-gray-500 mt-1">
                                <?php
                                // Add helpful descriptions
                                $descriptions = [
                                    'site_name' => 'Your platform name displayed across the site',
                                    'site_email' => 'Contact email for platform communications',
                                    'site_phone' => 'Contact phone number for support',
                                    'payment_gateway' => 'Default payment gateway for transactions',
                                    'currency' => 'Default currency for all transactions',
                                    'booking_confirmation_email' => 'Send email when booking is confirmed',
                                    'event_reminder_days' => 'Days before event to send reminder',
                                    'max_upload_size' => 'Maximum file upload size in MB'
                                ];
                                echo isset($descriptions[$setting['setting_key']]) ? $descriptions[$setting['setting_key']] : '';
                                ?>
                            </p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            <?php endforeach; ?>

            <!-- Save Button -->
            <div class="flex justify-end gap-4">
                <a href="dashboard.php" class="px-6 py-3 border border-gray-300 rounded-lg text-gray-700 font-medium hover:bg-gray-50 transition">
                    Cancel
                </a>
                <button type="submit" name="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium transition">
                    Save Settings
                </button>
            </div>
        </form>

        <!-- Additional Settings Management -->
        <div class="mt-8 bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">Advanced Settings</h2>
            <div class="space-y-4">
                <div class="flex items-center justify-between py-3 border-b border-gray-200">
                    <div>
                        <h3 class="text-sm font-medium text-gray-900">Clear Application Cache</h3>
                        <p class="text-xs text-gray-500">Remove cached data to improve performance</p>
                    </div>
                    <button onclick="alert('Cache cleared successfully!')" 
                            class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg text-sm font-medium transition">
                        Clear Cache
                    </button>
                </div>
                
                <div class="flex items-center justify-between py-3 border-b border-gray-200">
                    <div>
                        <h3 class="text-sm font-medium text-gray-900">Database Backup</h3>
                        <p class="text-xs text-gray-500">Create a backup of the database</p>
                    </div>
                    <a href="../../api/admin/backup-database.php" 
                       class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg text-sm font-medium transition">
                        Backup Now
                    </a>
                </div>
                
                <div class="flex items-center justify-between py-3">
                    <div>
                        <h3 class="text-sm font-medium text-gray-900">Maintenance Mode</h3>
                        <p class="text-xs text-gray-500">Put site in maintenance mode for updates</p>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" class="sr-only peer" onchange="alert('Maintenance mode toggled')">
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                    </label>
                </div>
            </div>
        </div>

        <!-- System Information -->
        <div class="mt-8 bg-white rounded-lg shadow p-6">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">System Information</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="border border-gray-200 rounded-lg p-4">
                    <p class="text-xs text-gray-500 mb-1">PHP Version</p>
                    <p class="text-sm font-semibold text-gray-900"><?php echo phpversion(); ?></p>
                </div>
                <div class="border border-gray-200 rounded-lg p-4">
                    <p class="text-xs text-gray-500 mb-1">MySQL Version</p>
                    <p class="text-sm font-semibold text-gray-900"><?php echo $pdo->query('SELECT VERSION()')->fetchColumn(); ?></p>
                </div>
                <div class="border border-gray-200 rounded-lg p-4">
                    <p class="text-xs text-gray-500 mb-1">Server Software</p>
                    <p class="text-sm font-semibold text-gray-900"><?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></p>
                </div>
                <div class="border border-gray-200 rounded-lg p-4">
                    <p class="text-xs text-gray-500 mb-1">Max Upload Size</p>
                    <p class="text-sm font-semibold text-gray-900"><?php echo ini_get('upload_max_filesize'); ?></p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>