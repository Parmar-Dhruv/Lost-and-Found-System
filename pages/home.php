<?php
require_once __DIR__ . '/../config/init.php';

$db = getDB();

// EC-12: Auto-expire lost items older than 30 days
$db->exec("
    UPDATE items
    SET status = 'expired'
    WHERE status = 'lost'
      AND is_deleted = 0
      AND date_reported < DATE_SUB(CURDATE(), INTERVAL 30 DAY)
");

// --- STATS QUERIES ---
$stmt = $db->prepare("SELECT COUNT(*) FROM items WHERE is_deleted = 0");
$stmt->execute();
$totalItems = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM items WHERE status = 'lost' AND is_deleted = 0");
$stmt->execute();
$totalLost = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM items WHERE status = 'found' AND is_deleted = 0");
$stmt->execute();
$totalFound = $stmt->fetchColumn();

$stmt = $db->prepare("SELECT COUNT(*) FROM items WHERE status = 'claimed' AND is_deleted = 0");
$stmt->execute();
$totalClaimed = $stmt->fetchColumn();

// --- PAGINATION SETUP ---
$perPage = 10;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $perPage;

$stmt = $db->prepare("SELECT COUNT(*) FROM items WHERE is_deleted = 0");
$stmt->execute();
$totalCount = (int)$stmt->fetchColumn();

$totalPages = (int)ceil($totalCount / $perPage);

$stmt = $db->prepare("
    SELECT i.item_id, i.item_name, i.category, i.status, i.location, i.date_reported,
           u.full_name AS reporter_name
    FROM items i
    JOIN users u ON i.user_id = u.user_id
    WHERE i.is_deleted = 0
    ORDER BY i.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->bindValue(1, $perPage, PDO::PARAM_INT);
$stmt->bindValue(2, $offset,  PDO::PARAM_INT);
$stmt->execute();
$recentItems = $stmt->fetchAll();

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-1 w-full">

    <div class="mb-8">
        <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Lost &amp; Found — Dashboard</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Track lost and found items reported by the community.</p>
    </div>

    <!-- STATS CARDS -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">

        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5">
            <p class="text-sm text-gray-500 dark:text-gray-400">Total Items</p>
            <p class="text-3xl font-semibold text-gray-900 dark:text-white mt-1"><?= $totalItems ?></p>
        </div>

        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5">
            <p class="text-sm text-gray-500 dark:text-gray-400">Lost</p>
            <p class="text-3xl font-semibold text-red-500 mt-1"><?= $totalLost ?></p>
        </div>

        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5">
            <p class="text-sm text-gray-500 dark:text-gray-400">Found</p>
            <p class="text-3xl font-semibold text-green-500 mt-1"><?= $totalFound ?></p>
        </div>

        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-5">
            <p class="text-sm text-gray-500 dark:text-gray-400">Claimed</p>
            <p class="text-3xl font-semibold text-blue-500 mt-1"><?= $totalClaimed ?></p>
        </div>

    </div>

    <!-- ITEMS TABLE -->
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden">

        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
            <h2 class="text-base font-semibold text-gray-900 dark:text-white">
                All Reported Items
                <span class="ml-2 text-sm font-normal text-gray-500 dark:text-gray-400">(<?= $totalCount ?> total)</span>
            </h2>
            <a href="<?= BASE_URL ?>pages/search.php"
               class="text-sm text-blue-600 dark:text-blue-400 hover:underline">
                Search &amp; filter →
            </a>
        </div>

        <?php if (empty($recentItems)): ?>
            <div class="px-6 py-12 text-center text-sm text-gray-500 dark:text-gray-400">
                No items have been reported yet.
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-gray-50 dark:bg-gray-700 text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        <tr>
                            <th class="px-6 py-3">Item Name</th>
                            <th class="px-6 py-3">Category</th>
                            <th class="px-6 py-3">Status</th>
                            <th class="px-6 py-3">Location</th>
                            <th class="px-6 py-3">Reported By</th>
                            <th class="px-6 py-3">Date</th>
                            <th class="px-6 py-3">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        <?php foreach ($recentItems as $item): ?>
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                            <td class="px-6 py-4 font-medium text-gray-900 dark:text-white">
                                <?= htmlspecialchars($item['item_name']) ?>
                            </td>
                            <td class="px-6 py-4 text-gray-600 dark:text-gray-300">
                                <?= htmlspecialchars($item['category']) ?>
                            </td>
                            <td class="px-6 py-4">
                                <?php
                                $status = $item['status'];
                                $badgeClass = match($status) {
                                    'lost'    => 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300',
                                    'found'   => 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300',
                                    'claimed' => 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300',
                                    'expired' => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400',
                                    default   => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400',
                                };
                                ?>
                                <span class="px-2 py-1 rounded-full text-xs font-medium <?= $badgeClass ?>">
                                    <?= ucfirst($status) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 text-gray-600 dark:text-gray-300">
                                <?= htmlspecialchars($item['location']) ?>
                            </td>
                            <td class="px-6 py-4 text-gray-600 dark:text-gray-300">
                                <?= htmlspecialchars($item['reporter_name']) ?>
                            </td>
                            <td class="px-6 py-4 text-gray-600 dark:text-gray-300">
                                <?= htmlspecialchars($item['date_reported']) ?>
                            </td>
                            <td class="px-6 py-4">
                                <a href="<?= BASE_URL ?>pages/item_detail.php?id=<?= $item['item_id'] ?>"
                                   class="text-blue-600 dark:text-blue-400 hover:underline font-medium">
                                    View
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- PAGINATION -->
            <?php if ($totalPages > 1): ?>
                <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between text-sm">

                    <?php if ($page > 1): ?>
                        <a href="?page=<?= $page - 1 ?>"
                           class="px-4 py-2 rounded-lg border border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                            ← Previous
                        </a>
                    <?php else: ?>
                        <span class="px-4 py-2 rounded-lg border border-gray-100 dark:border-gray-700 text-gray-300 dark:text-gray-600 cursor-not-allowed">
                            ← Previous
                        </span>
                    <?php endif; ?>

                    <span class="text-gray-500 dark:text-gray-400">
                        Page <?= $page ?> of <?= $totalPages ?>
                    </span>

                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?= $page + 1 ?>"
                           class="px-4 py-2 rounded-lg border border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                            Next →
                        </a>
                    <?php else: ?>
                        <span class="px-4 py-2 rounded-lg border border-gray-100 dark:border-gray-700 text-gray-300 dark:text-gray-600 cursor-not-allowed">
                            Next →
                        </span>
                    <?php endif; ?>

                </div>
            <?php endif; ?>

        <?php endif; ?>
    </div>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
