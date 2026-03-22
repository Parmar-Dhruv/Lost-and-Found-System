<?php
require_once __DIR__ . '/../config/init.php';

$db = getDB();

// --- COLLECT FILTER INPUTS ---
$keyword  = trim($_GET['keyword']   ?? '');
$category = trim($_GET['category']  ?? '');
$status   = trim($_GET['status']    ?? '');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo   = trim($_GET['date_to']   ?? '');

// --- COLLECT SORT INPUTS ---
$sortCol = trim($_GET['sort_col'] ?? 'created_at');
$sortDir = trim($_GET['sort_dir'] ?? 'desc');

$allowedSortCols = [
    'item_name'     => 'i.item_name',
    'category'      => 'i.category',
    'status'        => 'i.status',
    'location'      => 'i.location',
    'reporter_name' => 'u.full_name',
    'date_reported' => 'i.date_reported',
    'created_at'    => 'i.created_at',
];

$allowedSortDirs = ['asc', 'desc'];

if (!array_key_exists($sortCol, $allowedSortCols)) $sortCol = 'created_at';
if (!in_array($sortDir, $allowedSortDirs))         $sortDir = 'desc';

$orderByCol = $allowedSortCols[$sortCol];

// --- WHITELIST FILTER VALIDATION ---
$allowedCategories = ['Electronics', 'Clothing', 'Documents', 'Accessories', 'Other'];
$allowedStatuses   = ['lost', 'found', 'claimed', 'expired'];

if (!in_array($category, $allowedCategories)) $category = '';
if (!in_array($status,   $allowedStatuses))   $status   = '';

// --- BUILD DYNAMIC WHERE CLAUSE ---
$conditions = ["i.is_deleted = 0"];
$params     = [];

if ($keyword !== '') {
    $conditions[] = "(i.item_name LIKE ? OR i.description LIKE ?)";
    $params[]     = '%' . $keyword . '%';
    $params[]     = '%' . $keyword . '%';
}
if ($category !== '') {
    $conditions[] = "i.category = ?";
    $params[]     = $category;
}
if ($status !== '') {
    $conditions[] = "i.status = ?";
    $params[]     = $status;
}
if ($dateFrom !== '' && strtotime($dateFrom) !== false) {
    $conditions[] = "i.date_reported >= ?";
    $params[]     = $dateFrom;
}
if ($dateTo !== '' && strtotime($dateTo) !== false) {
    $conditions[] = "i.date_reported <= ?";
    $params[]     = $dateTo;
}

$whereClause = implode(' AND ', $conditions);

// --- PAGINATION SETUP ---
$perPage = 10;
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

$searchSubmitted = array_key_exists('keyword', $_GET);

$totalCount = 0;
$totalPages = 1;

if ($searchSubmitted) {
    $countStmt = $db->prepare("
        SELECT COUNT(*)
        FROM items i
        JOIN users u ON i.user_id = u.user_id
        WHERE $whereClause
    ");
    $countStmt->execute($params);
    $totalCount = (int)$countStmt->fetchColumn();
    $totalPages = max(1, (int)ceil($totalCount / $perPage));
    $page = min($page, $totalPages);
}

$results = [];

if ($searchSubmitted) {
    $sql = "
        SELECT i.item_id, i.item_name, i.category, i.status,
               i.location, i.date_reported, u.full_name AS reporter_name
        FROM items i
        JOIN users u ON i.user_id = u.user_id
        WHERE $whereClause
        ORDER BY $orderByCol $sortDir
        LIMIT ? OFFSET ?
    ";

    $stmt = $db->prepare($sql);

    foreach ($params as $i => $val) {
        $stmt->bindValue($i + 1, $val);
    }

    $stmt->bindValue(count($params) + 1, $perPage, PDO::PARAM_INT);
    $stmt->bindValue(count($params) + 2, $offset,  PDO::PARAM_INT);

    $stmt->execute();
    $results = $stmt->fetchAll();
}

$filterParams = [
    'keyword'   => $keyword,
    'category'  => $category,
    'status'    => $status,
    'date_from' => $dateFrom,
    'date_to'   => $dateTo,
    'sort_col'  => $sortCol,
    'sort_dir'  => $sortDir,
];
$filterQuery = http_build_query($filterParams);

function sortLink(string $col, string $currentCol, string $currentDir, array $filterParams): string {
    $newDir = ($col === $currentCol && $currentDir === 'asc') ? 'desc' : 'asc';
    $filterParams['sort_col'] = $col;
    $filterParams['sort_dir'] = $newDir;
    return '?' . http_build_query($filterParams);
}

function sortArrow(string $col, string $currentCol, string $currentDir): string {
    if ($col !== $currentCol) return '';
    return $currentDir === 'asc' ? ' ↑' : ' ↓';
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 flex-1 w-full">

    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Search Items</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Filter by keyword, category, status, or date range.</p>
    </div>

    <!-- SEARCH FORM -->
    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl p-6 shadow-sm mb-6">
        <form method="get" action="<?= BASE_URL ?>pages/search.php">

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">

                <div>
                    <label for="keyword" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Keyword</label>
                    <input type="text" id="keyword" name="keyword"
                           value="<?= htmlspecialchars($keyword) ?>"
                           placeholder="e.g. wallet, phone, keys"
                           class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                </div>

                <div>
                    <label for="category" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Category</label>
                    <select id="category" name="category"
                            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                        <option value="">— All Categories —</option>
                        <?php foreach (['Electronics','Clothing','Documents','Accessories','Other'] as $cat): ?>
                            <option value="<?= $cat ?>" <?= $category === $cat ? 'selected' : '' ?>><?= $cat ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Status</label>
                    <select id="status" name="status"
                            class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                        <option value="">— All Statuses —</option>
                        <option value="lost"    <?= $status === 'lost'    ? 'selected' : '' ?>>Lost</option>
                        <option value="found"   <?= $status === 'found'   ? 'selected' : '' ?>>Found</option>
                        <option value="claimed" <?= $status === 'claimed' ? 'selected' : '' ?>>Claimed</option>
                        <option value="expired" <?= $status === 'expired' ? 'selected' : '' ?>>Expired</option>
                    </select>
                </div>

            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">

                <div>
                    <label for="date_from" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Date From</label>
                    <input type="date" id="date_from" name="date_from"
                           value="<?= htmlspecialchars($dateFrom) ?>"
                           class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                </div>

                <div>
                    <label for="date_to" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1.5">Date To</label>
                    <input type="date" id="date_to" name="date_to"
                           value="<?= htmlspecialchars($dateTo) ?>"
                           class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition">
                </div>

                <div class="flex items-end gap-3">
                    <button type="submit"
                            class="flex-1 py-2.5 px-4 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                        Search
                    </button>
                    <a href="<?= BASE_URL ?>pages/search.php"
                       class="flex-1 py-2.5 px-4 text-center border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 text-sm font-medium rounded-lg transition">
                        Clear
                    </a>
                </div>

            </div>
        </form>
    </div>

    <!-- RESULTS -->
    <?php if (!$searchSubmitted): ?>
        <div class="text-center py-16 text-sm text-gray-500 dark:text-gray-400">
            Enter your search criteria above and click Search.
        </div>

    <?php elseif (empty($results)): ?>
        <div class="text-center py-16 text-sm text-gray-500 dark:text-gray-400">
            No items found matching your criteria. Try broader filters.
        </div>

    <?php else: ?>

        <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl overflow-hidden shadow-sm">

            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <p class="text-sm text-gray-600 dark:text-gray-300">
                    Found <span class="font-semibold text-gray-900 dark:text-white"><?= $totalCount ?></span> item(s)
                    <?php if ($totalPages > 1): ?>
                        — page <span class="font-semibold"><?= $page ?></span> of <span class="font-semibold"><?= $totalPages ?></span>
                    <?php endif; ?>
                </p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm text-left">
                    <thead class="bg-gray-50 dark:bg-gray-700 text-xs uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        <tr>
                            <th class="px-6 py-3">
                                <a href="<?= sortLink('item_name', $sortCol, $sortDir, $filterParams) ?>"
                                   class="hover:text-gray-700 dark:hover:text-gray-200 transition">
                                    Item Name<?= sortArrow('item_name', $sortCol, $sortDir) ?>
                                </a>
                            </th>
                            <th class="px-6 py-3">
                                <a href="<?= sortLink('category', $sortCol, $sortDir, $filterParams) ?>"
                                   class="hover:text-gray-700 dark:hover:text-gray-200 transition">
                                    Category<?= sortArrow('category', $sortCol, $sortDir) ?>
                                </a>
                            </th>
                            <th class="px-6 py-3">
                                <a href="<?= sortLink('status', $sortCol, $sortDir, $filterParams) ?>"
                                   class="hover:text-gray-700 dark:hover:text-gray-200 transition">
                                    Status<?= sortArrow('status', $sortCol, $sortDir) ?>
                                </a>
                            </th>
                            <th class="px-6 py-3">
                                <a href="<?= sortLink('location', $sortCol, $sortDir, $filterParams) ?>"
                                   class="hover:text-gray-700 dark:hover:text-gray-200 transition">
                                    Location<?= sortArrow('location', $sortCol, $sortDir) ?>
                                </a>
                            </th>
                            <th class="px-6 py-3">
                                <a href="<?= sortLink('reporter_name', $sortCol, $sortDir, $filterParams) ?>"
                                   class="hover:text-gray-700 dark:hover:text-gray-200 transition">
                                    Reported By<?= sortArrow('reporter_name', $sortCol, $sortDir) ?>
                                </a>
                            </th>
                            <th class="px-6 py-3">
                                <a href="<?= sortLink('date_reported', $sortCol, $sortDir, $filterParams) ?>"
                                   class="hover:text-gray-700 dark:hover:text-gray-200 transition">
                                    Date<?= sortArrow('date_reported', $sortCol, $sortDir) ?>
                                </a>
                            </th>
                            <th class="px-6 py-3">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                        <?php foreach ($results as $item): ?>
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
                        <a href="?<?= $filterQuery ?>&page=<?= $page - 1 ?>"
                           class="px-4 py-2 rounded-lg border border-gray-200 dark:border-gray-600 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                            ← Previous
                        </a>
                    <?php else: ?>
                        <span class="px-4 py-2 rounded-lg border border-gray-100 dark:border-gray-700 text-gray-300 dark:text-gray-600 cursor-not-allowed">
                            ← Previous
                        </span>
                    <?php endif; ?>

                    <span class="text-gray-500 dark:text-gray-400">Page <?= $page ?> of <?= $totalPages ?></span>

                    <?php if ($page < $totalPages): ?>
                        <a href="?<?= $filterQuery ?>&page=<?= $page + 1 ?>"
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

        </div>

    <?php endif; ?>

</main>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
