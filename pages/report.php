<?php
// pages/report.php
require_once __DIR__ . '/../config/init.php';

// Block non-logged-in users immediately
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'auth/login.php?error=login_required');
    exit;
}

// Generate CSRF token if one doesn't exist yet
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = $_GET['error'] ?? '';

$old = [
    'item_name'     => htmlspecialchars($_GET['item_name']     ?? ''),
    'category'      => htmlspecialchars($_GET['category']      ?? ''),
    'description'   => htmlspecialchars($_GET['description']   ?? ''),
    'location'      => htmlspecialchars($_GET['location']      ?? ''),
    'contact'       => htmlspecialchars($_GET['contact']       ?? ''),
    'status'        => htmlspecialchars($_GET['status']        ?? 'lost'),
    'date_reported' => htmlspecialchars($_GET['date_reported'] ?? ''),
];

$errorMessages = [
    'csrf'           => 'Security token mismatch. Please try again.',
    'missing_fields' => 'All fields are required. Please fill everything in.',
    'future_date'    => 'Date reported cannot be in the future.',
    'desc_too_short' => 'Description must be at least 20 characters.',
    'invalid_status' => 'Invalid status selected.',
    'invalid_cat'    => 'Invalid category selected.',
    'not_verified'   => 'Your account must be verified to report items.',
    'db_error'       => 'Something went wrong saving your report. Please try again.',
    'upload_error'   => 'File upload failed. Please try again.',
    'invalid_file'   => 'Only JPG, PNG, and GIF images are allowed.',
    'file_too_large' => 'Image must be under 2MB.',
];

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-1 w-full">
    <div class="max-w-2xl mx-auto">

        <div class="mb-6">
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Report a Lost or Found Item</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Fill in the details below to submit your report.</p>
        </div>

        <?php if ($error && isset($errorMessages[$error])): ?>
            <div class="mb-6 px-4 py-3 rounded-lg bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-700 text-sm text-red-700 dark:text-red-300">
                <?= $errorMessages[$error] ?>
            </div>
        <?php endif; ?>

        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-8 shadow-sm">

            <form action="<?= BASE_URL ?>actions/insert_item.php" method="POST" enctype="multipart/form-data">

                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

                <!-- Item Name -->
                <div class="mb-5">
                    <label for="item_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        Item Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="item_name" name="item_name"
                           maxlength="100" required
                           value="<?= $old['item_name'] ?>"
                           placeholder="e.g. Black iPhone 13, Blue Backpack"
                           class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                </div>

                <!-- Category -->
                <div class="mb-5">
                    <label for="category" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        Category <span class="text-red-500">*</span>
                    </label>
                    <select id="category" name="category" required
                            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                        <option value="">-- Select Category --</option>
                        <?php
                        $categories = ['Electronics', 'Clothing', 'Documents', 'Accessories', 'Other'];
                        foreach ($categories as $cat):
                            $selected = ($old['category'] === $cat) ? 'selected' : '';
                        ?>
                            <option value="<?= $cat ?>" <?= $selected ?>><?= $cat ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Status -->
                <div class="mb-5">
                    <label for="status" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        Status <span class="text-red-500">*</span>
                    </label>
                    <select id="status" name="status" required
                            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                        <option value="lost"  <?= ($old['status'] === 'lost')  ? 'selected' : '' ?>>Lost</option>
                        <option value="found" <?= ($old['status'] === 'found') ? 'selected' : '' ?>>Found</option>
                    </select>
                    <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">
                        Select "Lost" if you lost this item. Select "Found" if you found it.
                    </p>
                </div>

                <!-- Description -->
                <div class="mb-5">
                    <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        Description <span class="text-red-500">*</span>
                    </label>
                    <textarea id="description" name="description"
                              rows="4" required
                              placeholder="Describe the item in detail — colour, size, brand, any identifying marks. Minimum 20 characters."
                              class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition resize-none"><?= $old['description'] ?></textarea>
                    <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">Minimum 20 characters required.</p>
                </div>

                <!-- Location -->
                <div class="mb-5">
                    <label for="location" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        Location <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="location" name="location"
                           maxlength="150" required
                           value="<?= $old['location'] ?>"
                           placeholder="e.g. Library Block B, Main Canteen, Bus Stop 12"
                           class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                </div>

                <!-- Contact -->
                <div class="mb-5">
                    <label for="contact" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        Contact Info <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="contact" name="contact"
                           maxlength="100" required
                           value="<?= $old['contact'] ?>"
                           placeholder="e.g. your email or phone number"
                           class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                </div>

                <!-- Date Reported -->
                <div class="mb-5">
                    <label for="date_reported" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        Date Reported <span class="text-red-500">*</span>
                    </label>
                    <input type="date" id="date_reported" name="date_reported"
                           required
                           value="<?= $old['date_reported'] ?>"
                           max="<?= date('Y-m-d') ?>"
                           class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                    <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">Cannot be a future date.</p>
                </div>

                <!-- Image Upload -->
                <div class="mb-8">
                    <label for="image" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        Item Image <span class="text-gray-400 font-normal">(optional)</span>
                    </label>
                    <input type="file" id="image" name="image"
                           accept=".jpg,.jpeg,.png,.gif"
                           class="w-full text-sm text-gray-600 dark:text-gray-300
                                  file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0
                                  file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700
                                  dark:file:bg-blue-900/30 dark:file:text-blue-300
                                  hover:file:bg-blue-100 dark:hover:file:bg-blue-900/50 transition">
                    <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">JPG, PNG or GIF only. Maximum size: 2MB.</p>
                </div>

                <!-- Buttons -->
                <div class="flex gap-3">
                    <button type="submit"
                            class="flex-1 py-2.5 px-4 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                        Submit Report
                    </button>
                    <a href="<?= BASE_URL ?>pages/home.php"
                       class="flex-1 py-2.5 px-4 text-center border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 text-sm font-medium rounded-lg transition">
                        Cancel
                    </a>
                </div>

            </form>
        </div>
    </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>