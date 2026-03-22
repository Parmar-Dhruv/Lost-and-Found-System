<?php
require_once __DIR__ . '/auth_check.php';

$db = getDB();
$message = '';
$messageType = 'success';

// --- Handle POST actions (delete / restore) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die('Invalid CSRF token.');
    }

    $action  = $_POST['action']  ?? '';
    $item_id = (int)($_POST['item_id'] ?? 0);

    if ($item_id <= 0) {
        $message = 'Invalid item ID.';
        $messageType = 'error';
    } elseif ($action === 'delete') {
        $stmt = $db->prepare("UPDATE items SET is_deleted = 1 WHERE item_id = ?");
        $stmt->execute([$item_id]);

        $logStmt = $db->prepare("INSERT INTO activity_log (user_id, action, entity, entity_id) VALUES (?, 'delete_item', 'item', ?)");
        $logStmt->execute([$_SESSION['user_id'], $item_id]);

        $message = 'Item soft-deleted. It can be restored.';
        $messageType = 'warning';

    } elseif ($action === 'restore') {
        $stmt = $db->prepare("UPDATE items SET is_deleted = 0 WHERE item_id = ?");
        $stmt->execute([$item_id]);

        $logStmt = $db->prepare("INSERT INTO activity_log (user_id, action, entity, entity_id) VALUES (?, 'restore_item', 'item', ?)");
        $logStmt->execute([$_SESSION['user_id'], $item_id]);

        $message = 'Item restored successfully.';
        $messageType = 'success';
    } else {
        $message = 'Unknown action.';
        $messageType = 'error';
    }
}

// --- Generate CSRF token if not set ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// --- Fetch ALL items including soft-deleted ---
$stmt = $db->query("
    SELECT i.*, u.full_name AS reporter_name
    FROM items i
    LEFT JOIN users u ON i.user_id = u.user_id
    ORDER BY i.created_at DESC
");
$items = $stmt->fetchAll();

// --- Duplicate Detection (EC-04) ---
$duplicateIds = [];
$dupStmt = $db->query("
    SELECT i1.item_id
    FROM items i1
    JOIN items i2
        ON i1.item_id != i2.item_id
        AND LOWER(TRIM(i1.item_name)) = LOWER(TRIM(i2.item_name))
        AND LOWER(TRIM(i1.location))  = LOWER(TRIM(i2.location))
        AND ABS(DATEDIFF(i1.date_reported, i2.date_reported)) <= 2
    WHERE i1.is_deleted = 0 AND i2.is_deleted = 0
");
foreach ($dupStmt->fetchAll() as $row) {
    $duplicateIds[$row['item_id']] = true;
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/sidebar.php';
?>

    <!-- TOP BAR -->
    <header class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700 px-8 py-4 flex items-center justify-between flex-shrink-0">
        <div>
            <h1 class="text-xl font-semibold text-gray-900 dark:text-white">Manage Items</h1>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">View, delete, and restore all reported items</p>
        </div>
        <div class="flex items-center gap-3">
            <a href="<?= BASE_URL ?>actions/export_items_pdf.php"
               class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 rounded-lg transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                Export to PDF
            </a>
            <span class="text-sm text-gray-500 dark:text-gray-400">Logged in as</span>
            <span class="text-sm font-medium text-gray-900 dark:text-white"><?= htmlspecialchars($_SESSION['full_name']) ?></span>
            <div class="w-8 h-8 rounded-full bg-blue-600 flex items-center justify-center text-white text-sm font-semibold flex-shrink-0">
                <?= strtoupper(substr($_SESSION['full_name'], 0, 1)) ?>
            </div>
        </div>
    </header>

    <!-- MAIN CONTENT -->
    <main class="flex-1 px-8 py-8 space-y-6">

        <?php if ($message): ?>
            <?php
            $alertClasses = [
                'success' => 'bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-700 text-green-700 dark:text-green-300',
                'warning' => 'bg-yellow-50 dark:bg-yellow-900/30 border border-yellow-200 dark:border-yellow-700 text-yellow-700 dark:text-yellow-300',
                'error'   => 'bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-700 text-red-700 dark:text-red-300',
            ];
            $alertClass = $alertClasses[$messageType] ?? $alertClasses['success'];
            ?>
            <div class="px-4 py-3 rounded-lg text-sm <?= $alertClass ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($duplicateIds)): ?>
            <div class="px-4 py-3 rounded-lg text-sm bg-yellow-50 dark:bg-yellow-900/30 border border-yellow-200 dark:border-yellow-700 text-yellow-700 dark:text-yellow-300 flex items-start gap-2">
                <svg class="w-4 h-4 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <span>Some items are flagged as possible duplicates (same name + location within 2 days). They are highlighted below.</span>
            </div>
        <?php endif; ?>

        <!-- ITEMS TABLE -->
        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl shadow-sm">

            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                <h2 class="text-base font-semibold text-gray-900 dark:text-white">All Items</h2>
                <span class="text-sm text-gray-500 dark:text-gray-400"><?= count($items) ?> total</span>
            </div>

            <?php if (empty($items)): ?>
                <div class="px-6 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                    No items in the system yet.
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-200 dark:border-gray-700">
                                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">ID</th>
                                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Item Name</th>
                                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Category</th>
                                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Status</th>
                                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Location</th>
                                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Reporter</th>
                                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Date</th>
                                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Deleted?</th>
                                <th class="text-left px-4 py-3 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            <?php foreach ($items as $item):
                                $isDuplicate = isset($duplicateIds[$item['item_id']]);
                                $rowClass = $isDuplicate
                                    ? 'bg-yellow-50 dark:bg-yellow-900/10'
                                    : 'hover:bg-gray-50 dark:hover:bg-gray-700/50';
                            ?>
                            <tr class="transition <?= $rowClass ?>">
                                <td class="px-4 py-3 text-gray-500 dark:text-gray-400"><?= $item['item_id'] ?></td>
                                <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">
                                    <a href="<?= BASE_URL ?>pages/item_detail.php?id=<?= $item['item_id'] ?>"
                                       class="hover:text-blue-600 dark:hover:text-blue-400 transition">
                                        <?= htmlspecialchars($item['item_name']) ?>
                                    </a>
                                    <?php if ($isDuplicate): ?>
                                        <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-xs font-medium bg-yellow-100 dark:bg-yellow-900/40 text-yellow-700 dark:text-yellow-300">
                                            duplicate
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-400"><?= htmlspecialchars($item['category']) ?></td>
                                <td class="px-4 py-3">
                                    <?php
                                    $badgeClasses = [
                                        'lost'    => 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300',
                                        'found'   => 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300',
                                        'claimed' => 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300',
                                        'expired' => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400',
                                    ];
                                    $badge = $badgeClasses[$item['status']] ?? 'bg-gray-100 text-gray-600';
                                    ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium <?= $badge ?>">
                                        <?= htmlspecialchars($item['status']) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-400 max-w-32 truncate"><?= htmlspecialchars($item['location']) ?></td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-400"><?= htmlspecialchars($item['reporter_name'] ?? 'Deleted User') ?></td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-400 whitespace-nowrap"><?= htmlspecialchars($item['date_reported']) ?></td>
                                <td class="px-4 py-3">
                                    <?php if ($item['is_deleted']): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300">
                                            Yes
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400">
                                            No
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3">
                                    <?php if ($item['is_deleted'] == 0): ?>
                                        <form method="POST"
                                              onsubmit="return confirm('Soft-delete this item? It can be restored later.');">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="item_id" value="<?= $item['item_id'] ?>">
                                            <button type="submit"
                                                    class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-lg border border-red-400 text-red-600 dark:text-red-400 dark:border-red-600 hover:bg-red-50 dark:hover:bg-red-900/30 transition">
                                                Delete
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <form method="POST">
                                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                            <input type="hidden" name="action" value="restore">
                                            <input type="hidden" name="item_id" value="<?= $item['item_id'] ?>">
                                            <button type="submit"
                                                    class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-lg border border-green-400 text-green-600 dark:text-green-400 dark:border-green-600 hover:bg-green-50 dark:hover:bg-green-900/30 transition">
                                                Restore
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>

        </div>

    </main>

    </div><!-- /content-wrapper: opened in sidebar.php -->
</div><!-- /x-data wrapper: opened in sidebar.php -->

<?php require_once __DIR__ . '/footer.php'; ?>
