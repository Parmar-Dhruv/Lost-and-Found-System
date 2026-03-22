<?php
// pages/my_items.php
require_once __DIR__ . '/../config/init.php';

// ── 1. Must be logged in ──────────────────────────────────────────────────────
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . BASE_URL . 'auth/login.php?error=login_required');
    exit;
}

$db      = getDB();
$user_id = (int)$_SESSION['user_id'];

// ── 2. CSRF token ─────────────────────────────────────────────────────────────
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$success = $_GET['success'] ?? '';
$error   = $_GET['error']   ?? '';

$errorMessages = [
    'not_owner'      => 'You can only edit or delete your own items.',
    'db_error'       => 'Something went wrong. Please try again.',
    'not_found'      => 'Item not found.',
    'missing_fields' => 'All fields are required.',
    'future_date'    => 'Date reported cannot be in the future.',
    'desc_too_short' => 'Description must be at least 20 characters.',
    'invalid_cat'    => 'Invalid category selected.',
    'invalid_status' => 'Invalid status selected.',
    'csrf'           => 'Security token mismatch. Please try again.',
    'upload_error'   => 'File upload failed. Please try again.',
    'invalid_file'   => 'Only JPG, PNG, and GIF images are allowed.',
    'file_too_large' => 'Image must be under 2MB.',
];

// ── 3. Edit mode ──────────────────────────────────────────────────────────────
$edit_id   = (int)($_GET['edit'] ?? 0);
$edit_item = null;

if ($edit_id > 0) {
    $stmt = $db->prepare("
        SELECT * FROM items
        WHERE item_id  = ?
          AND user_id  = ?
          AND is_deleted = 0
    ");
    $stmt->execute([$edit_id, $user_id]);
    $edit_item = $stmt->fetch();

    if (!$edit_item) {
        header('Location: ' . BASE_URL . 'pages/my_items.php?error=not_owner');
        exit;
    }
}

// ── 4. Fetch all items belonging to this user ─────────────────────────────────
$stmt = $db->prepare("
    SELECT * FROM items
    WHERE user_id   = ?
      AND is_deleted = 0
    ORDER BY created_at DESC
");
$stmt->execute([$user_id]);
$items = $stmt->fetchAll();

function statusBadgeClass(string $status): string {
    return match($status) {
        'lost'    => 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300',
        'found'   => 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300',
        'claimed' => 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300',
        'expired' => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400',
        default   => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400',
    };
}

include __DIR__ . '/../includes/header.php';
include __DIR__ . '/../includes/navbar.php';
?>

<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-1 w-full">

    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">My Reported Items</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Manage all the items you have reported.</p>
        </div>
        <?php if (!empty($items)): ?>
            <a href="<?= BASE_URL ?>actions/export_my_items_pdf.php"
               class="px-4 py-2 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                ⬇ Export to PDF
            </a>
        <?php endif; ?>
    </div>

    <!-- Alerts -->
    <?php if ($success === 'updated'): ?>
        <div class="mb-5 px-4 py-3 rounded-lg bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-700 text-sm text-green-700 dark:text-green-300">
            Item updated successfully.
        </div>
    <?php elseif ($success === 'deleted'): ?>
        <div class="mb-5 px-4 py-3 rounded-lg bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-700 text-sm text-green-700 dark:text-green-300">
            Item deleted successfully.
        </div>
    <?php endif; ?>

    <?php if ($error && isset($errorMessages[$error])): ?>
        <div class="mb-5 px-4 py-3 rounded-lg bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-700 text-sm text-red-700 dark:text-red-300">
            <?= $errorMessages[$error] ?>
        </div>
    <?php endif; ?>

    <!-- ── EDIT FORM ──────────────────────────────────────────────────────── -->
    <?php if ($edit_item): ?>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm p-8 mb-8">

            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-1">
                Edit Item: <?= htmlspecialchars($edit_item['item_name']) ?>
            </h2>
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">Update the details below and save your changes.</p>

            <form action="<?= BASE_URL ?>actions/update_item.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="item_id"    value="<?= $edit_item['item_id'] ?>">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-5">

                    <div>
                        <label for="item_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                            Item Name <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="item_name" name="item_name"
                               maxlength="100" required
                               value="<?= htmlspecialchars($edit_item['item_name']) ?>"
                               class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                    </div>

                    <div>
                        <label for="category" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                            Category <span class="text-red-500">*</span>
                        </label>
                        <select id="category" name="category" required
                                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                            <?php foreach (['Electronics','Clothing','Documents','Accessories','Other'] as $cat): ?>
                                <option value="<?= $cat ?>" <?= $edit_item['category'] === $cat ? 'selected' : '' ?>><?= $cat ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="status" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                            Status <span class="text-red-500">*</span>
                        </label>
                        <select id="status" name="status" required
                                class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                            <?php foreach (['lost','found'] as $s): ?>
                                <option value="<?= $s ?>" <?= $edit_item['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="date_reported" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                            Date Reported <span class="text-red-500">*</span>
                        </label>
                        <input type="date" id="date_reported" name="date_reported"
                               required max="<?= date('Y-m-d') ?>"
                               value="<?= htmlspecialchars($edit_item['date_reported']) ?>"
                               class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                    </div>

                    <div>
                        <label for="location" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                            Location <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="location" name="location"
                               maxlength="150" required
                               value="<?= htmlspecialchars($edit_item['location']) ?>"
                               class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                    </div>

                    <div>
                        <label for="contact" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                            Contact Info <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="contact" name="contact"
                               maxlength="100" required
                               value="<?= htmlspecialchars($edit_item['contact']) ?>"
                               class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                    </div>

                </div>

                <div class="mb-5">
                    <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        Description <span class="text-red-500">*</span>
                    </label>
                    <textarea id="description" name="description"
                              rows="4" required
                              class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition resize-none"><?= htmlspecialchars($edit_item['description']) ?></textarea>
                    <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">Minimum 20 characters.</p>
                </div>

                <!-- Current image -->
                <div class="mb-5">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Current Image</label>
                    <?php if (!empty($edit_item['image'])): ?>
                        <img src="<?= BASE_URL . htmlspecialchars($edit_item['image']) ?>"
                             alt="Current image"
                             class="w-32 h-32 object-cover rounded-lg border border-gray-200 dark:border-gray-700 mb-3">
                        <label class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300 cursor-pointer">
                            <input type="checkbox" name="remove_image" value="1"
                                   class="rounded border-gray-300 dark:border-gray-600">
                            Remove current image
                        </label>
                    <?php else: ?>
                        <p class="text-sm text-gray-400 dark:text-gray-500 italic">No image currently uploaded.</p>
                    <?php endif; ?>
                </div>

                <!-- Replace image -->
                <div class="mb-8">
                    <label for="edit_image" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">
                        Replace Image <span class="text-gray-400 font-normal">(optional)</span>
                    </label>
                    <input type="file" id="edit_image" name="image"
                           accept=".jpg,.jpeg,.png,.gif"
                           class="w-full text-sm text-gray-600 dark:text-gray-300
                                  file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0
                                  file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700
                                  dark:file:bg-blue-900/30 dark:file:text-blue-300
                                  hover:file:bg-blue-100 transition">
                    <p class="mt-1.5 text-xs text-gray-500 dark:text-gray-400">JPG, PNG or GIF only. Max 2MB. Leave blank to keep current image.</p>
                </div>

                <div class="flex gap-3">
                    <button type="submit"
                            class="px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                        Save Changes
                    </button>
                    <a href="<?= BASE_URL ?>pages/my_items.php"
                       class="px-6 py-2.5 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 text-sm font-medium rounded-lg transition">
                        Cancel
                    </a>
                </div>

            </form>
        </div>
    <?php endif; ?>

    <!-- ── ITEMS TABLE ─────────────────────────────────────────────────────── -->
    <?php if (empty($items)): ?>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm px-6 py-16 text-center">
            <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">You have not reported any items yet.</p>
            <a href="<?= BASE_URL ?>pages/report.php"
               class="inline-block px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition">
                Report an Item
            </a>
        </div>
    <?php else: ?>
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-gray-50 dark:bg-gray-700 text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        <tr>
                            <th class="px-6 py-3">#</th>
                            <th class="px-6 py-3">Image</th>
                            <th class="px-6 py-3">Item Name</th>
                            <th class="px-6 py-3">Category</th>
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3">Location</th>
                            <th class="px-6 py-3">Date</th>
                            <th class="px-6 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        <?php foreach ($items as $item): ?>
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                            <td class="px-6 py-4 text-gray-500 dark:text-gray-400"><?= (int)$item['item_id'] ?></td>
                            <td class="px-6 py-4">
                                <?php if (!empty($item['image'])): ?>
                                    <img src="<?= BASE_URL . htmlspecialchars($item['image']) ?>"
                                         alt="<?= htmlspecialchars($item['item_name']) ?>"
                                         class="w-12 h-12 object-cover rounded-lg border border-gray-200 dark:border-gray-700">
                                <?php else: ?>
                                    <div class="w-12 h-12 rounded-lg bg-gray-100 dark:bg-gray-700 flex items-center justify-center">
                                        <span class="text-xs text-gray-400">None</span>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 font-medium text-gray-900 dark:text-white">
                                <a href="<?= BASE_URL ?>pages/item_detail.php?id=<?= (int)$item['item_id'] ?>"
                                   class="hover:text-blue-600 dark:hover:text-blue-400 transition">
                                    <?= htmlspecialchars($item['item_name']) ?>
                                </a>
                            </td>
                            <td class="px-6 py-4 text-gray-600 dark:text-gray-300"><?= htmlspecialchars($item['category']) ?></td>
                            <td class="px-6 py-4">
                                <span class="px-2 py-1 rounded-full text-xs font-medium <?= statusBadgeClass($item['status']) ?>">
                                    <?= ucfirst(htmlspecialchars($item['status'])) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-gray-600 dark:text-gray-300"><?= htmlspecialchars($item['location']) ?></td>
                            <td class="px-6 py-4 text-gray-600 dark:text-gray-300"><?= htmlspecialchars($item['date_reported']) ?></td>
                            <td class="px-6 py-4">
                                <div class="flex gap-2">
                                    <a href="<?= BASE_URL ?>pages/my_items.php?edit=<?= (int)$item['item_id'] ?>"
                                       class="px-3 py-1.5 text-xs font-medium rounded-lg border border-yellow-400 text-yellow-700 dark:text-yellow-300 dark:border-yellow-600 hover:bg-yellow-50 dark:hover:bg-yellow-900/30 transition">
                                        Edit
                                    </a>
                                    <form action="<?= BASE_URL ?>actions/delete_item.php" method="POST"
                                          onsubmit="return confirm('Delete this item?');">
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                        <input type="hidden" name="item_id"    value="<?= (int)$item['item_id'] ?>">
                                        <button type="submit"
                                                class="px-3 py-1.5 text-xs font-medium rounded-lg border border-red-400 text-red-600 dark:text-red-400 dark:border-red-600 hover:bg-red-50 dark:hover:bg-red-900/30 transition">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
