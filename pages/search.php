<?php
require_once __DIR__ . '/../config/init.php';

$db = getDB();

// --- COLLECT FILTER INPUTS ---
// All come from GET (form uses method="get" so filters appear in URL)
// We trim whitespace and default to empty string if not set
// This lets users bookmark or share search URLs

$keyword  = trim($_GET['keyword']  ?? '');
$category = trim($_GET['category'] ?? '');
$status   = trim($_GET['status']   ?? '');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo   = trim($_GET['date_to']   ?? '');

// --- WHITELIST VALIDATION ---
// We never trust user input directly in queries
// Category and status must be one of the allowed values or we ignore them
// This prevents someone from injecting garbage into our WHERE clause

$allowedCategories = ['Electronics', 'Clothing', 'Documents', 'Accessories', 'Other'];
$allowedStatuses   = ['lost', 'found', 'claimed', 'expired'];

if (!in_array($category, $allowedCategories)) {
    $category = '';
}
if (!in_array($status, $allowedStatuses)) {
    $status = '';
}

// --- BUILD DYNAMIC QUERY ---
// We start with a base query that always runs
// Then we add WHERE conditions only for filters the user actually provided
// We use an array to collect conditions and another for bound values
// At the end we join conditions with AND

$conditions = ["i.is_deleted = 0"];
$params     = [];

if ($keyword !== '') {
    // LIKE search on item_name and description
    // The % wildcards mean "anything before or after the keyword"
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

if ($dateFrom !== '') {
    // Validate it looks like a date before using it
    if (strtotime($dateFrom) !== false) {
        $conditions[] = "i.date_reported >= ?";
        $params[]     = $dateFrom;
    }
}

if ($dateTo !== '') {
    if (strtotime($dateTo) !== false) {
        $conditions[] = "i.date_reported <= ?";
        $params[]     = $dateTo;
    }
}

// Glue all conditions together into one WHERE clause
$whereClause = implode(' AND ', $conditions);

$sql = "
    SELECT i.item_id, i.item_name, i.category, i.status,
           i.location, i.date_reported, u.full_name AS reporter_name
    FROM items i
    JOIN users u ON i.user_id = u.user_id
    WHERE $whereClause
    ORDER BY i.created_at DESC
";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$results = $stmt->fetchAll();

// Flag: did the user actually submit the form?
// We check if ANY filter key exists in the URL
$searchSubmitted = isset($_GET['keyword']) || isset($_GET['category']) ||
                   isset($_GET['status'])  || isset($_GET['date_from']);

require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="container">

    <h2>Search Items</h2>
    <p>Filter by keyword, category, status, or date range.</p>

    <!-- SEARCH FORM -->
    <!-- method="get" keeps filters visible in the URL — good for sharing/bookmarking -->
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

        <p>Found <strong><?= count($results) ?></strong> item(s).</p>

        <table class="items-table">
            <thead>
                <tr>
                    <th>Item Name</th>
                    <th>Category</th>
                    <th>Status</th>
                    <th>Location</th>
                    <th>Reported By</th>
                    <th>Date</th>
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

    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
