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

// Whitelist sort column — NEVER interpolate raw user input into ORDER BY
// Only these exact strings are allowed. Anything else falls back to created_at.
$allowedSortCols = [
    'item_name'     => 'i.item_name',
    'category'      => 'i.category',
    'status'        => 'i.status',
    'location'      => 'i.location',
    'reporter_name' => 'u.full_name',
    'date_reported' => 'i.date_reported',
    'created_at'    => 'i.created_at',
];

// Whitelist sort direction — only asc or desc, nothing else
$allowedSortDirs = ['asc', 'desc'];

if (!array_key_exists($sortCol, $allowedSortCols)) $sortCol = 'created_at';
if (!in_array($sortDir, $allowedSortDirs))         $sortDir = 'desc';

// Resolve to the actual SQL column expression (e.g. 'i.item_name')
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

// Flag: did the user actually submit the form?
// array_key_exists on 'keyword' is enough — it's always present when form is submitted
$searchSubmitted = array_key_exists('keyword', $_GET);

// Count total matching results — needed to calculate total pages
// Same WHERE clause, same params — just COUNT instead of SELECT
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

    // Clamp current page to valid range in case URL is manually edited
    $page = min($page, $totalPages);
}

// --- FETCH PAGINATED RESULTS ---
$results = [];

if ($searchSubmitted) {
    // ORDER BY uses whitelisted $orderByCol and $sortDir — never raw user input
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

    // Bind the filter params first (they are strings — PDO default type is fine)
    foreach ($params as $i => $val) {
        $stmt->bindValue($i + 1, $val);
    }

    // Bind LIMIT and OFFSET as integers — must be explicit or MySQL may reject them
    $stmt->bindValue(count($params) + 1, $perPage, PDO::PARAM_INT);
    $stmt->bindValue(count($params) + 2, $offset,  PDO::PARAM_INT);

    $stmt->execute();
    $results = $stmt->fetchAll();
}

// --- BUILD FILTER QUERY STRING FOR PAGINATION LINKS ---
// Pagination links must carry all current filters AND sort state in the URL.
// Otherwise clicking "Next" would lose the user's search and sort preference.
// keyword must always be present so $searchSubmitted stays true on page 2+.
$filterParams = [
    'keyword'   => $keyword,
    'category'  => $category,
    'status'    => $status,
    'date_from' => $dateFrom,
    'date_to'   => $dateTo,
    'sort_col'  => $sortCol,
    'sort_dir'  => $sortDir,
];
// http_build_query turns the array into keyword=wallet&category=Electronics&sort_col=... etc.
$filterQuery = http_build_query($filterParams);

// --- SORT LINK HELPER ---
// For each column header, build the URL that clicking it should go to.
// If the user clicks the column that is already active, flip the direction.
// If they click a different column, default to ascending.
// $filterParams already contains the current filters — we just override sort keys.
function sortLink(string $col, string $currentCol, string $currentDir, array $filterParams): string {
    $newDir = ($col === $currentCol && $currentDir === 'asc') ? 'desc' : 'asc';
    $filterParams['sort_col'] = $col;
    $filterParams['sort_dir'] = $newDir;
    return '?' . http_build_query($filterParams);
}

// Arrow indicator shown next to the active sort column header
// Up arrow = ascending, down arrow = descending
function sortArrow(string $col, string $currentCol, string $currentDir): string {
    if ($col !== $currentCol) return '';
    return $currentDir === 'asc' ? ' ↑' : ' ↓';
}

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="container">

    <h2>Search Items</h2>
    <p>Filter by keyword, category, status, or date range.</p>

    <!-- SEARCH FORM -->
    <form method="get" action="<?= BASE_URL ?>pages/search.php" class="search-form">

        <div class="form-row">
            <div class="form-group">
                <label for="keyword">Keyword</label>
                <input
                    type="text"
                    id="keyword"
                    name="keyword"
                    value="<?= htmlspecialchars($keyword) ?>"
                    placeholder="e.g. wallet, phone, keys"
                >
            </div>

            <div class="form-group">
                <label for="category">Category</label>
                <select id="category" name="category">
                    <option value="">— All Categories —</option>
                    <?php
                    $cats = ['Electronics', 'Clothing', 'Documents', 'Accessories', 'Other'];
                    foreach ($cats as $cat):
                    ?>
                        <option value="<?= $cat ?>" <?= $category === $cat ? 'selected' : '' ?>>
                            <?= $cat ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="">— All Statuses —</option>
                    <option value="lost"    <?= $status === 'lost'    ? 'selected' : '' ?>>Lost</option>
                    <option value="found"   <?= $status === 'found'   ? 'selected' : '' ?>>Found</option>
                    <option value="claimed" <?= $status === 'claimed' ? 'selected' : '' ?>>Claimed</option>
                    <option value="expired" <?= $status === 'expired' ? 'selected' : '' ?>>Expired</option>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="date_from">Date From</label>
                <input
                    type="date"
                    id="date_from"
                    name="date_from"
                    value="<?= htmlspecialchars($dateFrom) ?>"
                >
            </div>

            <div class="form-group">
                <label for="date_to">Date To</label>
                <input
                    type="date"
                    id="date_to"
                    name="date_to"
                    value="<?= htmlspecialchars($dateTo) ?>"
                >
            </div>

            <div class="form-group form-group-submit">
                <label>&nbsp;</label>
                <button type="submit">Search</button>
                <a href="<?= BASE_URL ?>pages/search.php" class="btn-reset">Clear</a>
            </div>
        </div>

    </form>

    <!-- RESULTS -->
    <?php if (!$searchSubmitted): ?>

        <p>Enter your search criteria above and click Search.</p>

    <?php elseif (empty($results)): ?>

        <p>No items found matching your criteria. Try broader filters.</p>

    <?php else: ?>

        <p>
            Found <strong><?= $totalCount ?></strong> item(s).
            <?php if ($totalPages > 1): ?>
                Showing page <?= $page ?> of <?= $totalPages ?>.
            <?php endif; ?>
        </p>

        <table class="items-table">
            <thead>
                <tr>
                    <th>
                        <a href="<?= sortLink('item_name', $sortCol, $sortDir, $filterParams) ?>" class="sort-link">
                            Item Name<?= sortArrow('item_name', $sortCol, $sortDir) ?>
                        </a>
                    </th>
                    <th>
                        <a href="<?= sortLink('category', $sortCol, $sortDir, $filterParams) ?>" class="sort-link">
                            Category<?= sortArrow('category', $sortCol, $sortDir) ?>
                        </a>
                    </th>
                    <th>
                        <a href="<?= sortLink('status', $sortCol, $sortDir, $filterParams) ?>" class="sort-link">
                            Status<?= sortArrow('status', $sortCol, $sortDir) ?>
                        </a>
                    </th>
                    <th>
                        <a href="<?= sortLink('location', $sortCol, $sortDir, $filterParams) ?>" class="sort-link">
                            Location<?= sortArrow('location', $sortCol, $sortDir) ?>
                        </a>
                    </th>
                    <th>
                        <a href="<?= sortLink('reporter_name', $sortCol, $sortDir, $filterParams) ?>" class="sort-link">
                            Reported By<?= sortArrow('reporter_name', $sortCol, $sortDir) ?>
                        </a>
                    </th>
                    <th>
                        <a href="<?= sortLink('date_reported', $sortCol, $sortDir, $filterParams) ?>" class="sort-link">
                            Date<?= sortArrow('date_reported', $sortCol, $sortDir) ?>
                        </a>
                    </th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($results as $item): ?>
                <tr>
                    <td><?= htmlspecialchars($item['item_name']) ?></td>
                    <td><?= htmlspecialchars($item['category']) ?></td>
                    <td><?= htmlspecialchars($item['status']) ?></td>
                    <td><?= htmlspecialchars($item['location']) ?></td>
                    <td><?= htmlspecialchars($item['reporter_name']) ?></td>
                    <td><?= htmlspecialchars($item['date_reported']) ?></td>
                    <td><a href="<?= BASE_URL ?>pages/item_detail.php?id=<?= $item['item_id'] ?>">View</a></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- PAGINATION CONTROLS -->
        <?php if ($totalPages > 1): ?>
            <div class="pagination">

                <?php if ($page > 1): ?>
                    <!-- Previous link carries all current filters + sort state + page-1 -->
                    <a href="?<?= $filterQuery ?>&page=<?= $page - 1 ?>" class="page-btn">← Previous</a>
                <?php else: ?>
                    <span class="page-btn page-btn-disabled">← Previous</span>
                <?php endif; ?>

                <span class="page-info">Page <?= $page ?> of <?= $totalPages ?></span>

                <?php if ($page < $totalPages): ?>
                    <a href="?<?= $filterQuery ?>&page=<?= $page + 1 ?>" class="page-btn">Next →</a>
                <?php else: ?>
                    <span class="page-btn page-btn-disabled">Next →</span>
                <?php endif; ?>

            </div>
        <?php endif; ?>

    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
